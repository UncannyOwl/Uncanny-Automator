<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SourcePackage\PageSourcePackageService;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveService;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

final class PageFactory
{
    public const CREATE_ACTION = 'uncanny_page_builder_create_page';
    public const IMPORT_ACTION = 'uncanny_page_builder_import_page';
    public const IMPORT_NOTICE_SCREEN = 'page_source_import';

    private const SOURCE_PACKAGE_FILE_FIELD = 'source_package';

    private SectionRepositoryInterface $repository;
    private ShellModeService $shellModeService;

    public function __construct(
        SectionRepositoryInterface $repository,
        ShellModeService $shellModeService,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly PageDetailsPortInterface $pageDetails,
        private readonly PageBuilderAvailabilityInterface $availability,
        private readonly ?PageSourcePackageService $sourcePackages = null,
        private readonly ?PageSourceArchiveService $sourceArchives = null,
    ) {
        $this->repository = $repository;
        $this->shellModeService = $shellModeService;
    }

    public function create(): void
    {
        $this->assertPostRequest();
        check_admin_referer(self::CREATE_ACTION);

        $hasAccess = (bool) WordPressCallbackBoundary::valueOrDie(
            'page.create.authorize',
            fn (): bool => $this->allowedCapabilities->currentUserHasAllowedCapability(),
        );
        if (!$hasAccess) {
            wp_die(
                esc_html_x("You don't have permission to create Page Builder pages. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403
            );
        }

        $this->assertNewPagesAvailable();

        $pageId = 0;
        try {
            $pageId = $this->createDraftPage(
                _x('Untitled page', 'Page Builder', 'uncanny-automator'),
                /* translators: %d: the page ID used for the auto-generated title. */
                _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
            );
            $this->repository->markAsOwned($pageId);
            $this->shellModeService->setForPage($pageId, ShellMode::None);
            $this->pageDetails->initialize($pageId, max(0, (int) get_current_user_id()));
        } catch (\Throwable $failure) {
            // admin-post.php is a public WordPress boundary. Any unexpected
            // failure must end in the same controlled error response the
            // known failures use, never a raw fatal page.
            error_log(sprintf(
                '[Uncanny Page Builder] Page creation failed (%s).',
                $failure::class,
            ));
            $this->deleteCreatedPage($pageId);
            wp_die(
                esc_html_x('Could not create the page. Please try again.', 'Page Builder', 'uncanny-automator'),
                500
            );
        }

        $this->notifyObservers('uncanny_page_builder_page_created', $pageId);

        wp_safe_redirect(AdminCanvasEditorWindowedPage::editorUrl($pageId), 303, 'Uncanny Page Builder');
        exit;
    }

    public function importPage(): void
    {
        check_admin_referer(self::IMPORT_ACTION);

        $hasAccess = (bool) WordPressCallbackBoundary::valueOrDie(
            'page.import.authorize',
            fn (): bool => $this->allowedCapabilities->currentUserHasAllowedCapability(),
        );
        if (!$hasAccess) {
            wp_die(
                esc_html_x("You don't have permission to import Page Builder pages. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403
            );
        }

        $this->assertNewPagesAvailable();
        $returnPageId = $this->validatedImportReturnPageId();

        if (!$this->sourcePackages instanceof PageSourcePackageService) {
            wp_die(
                esc_html_x('Page import is not available. Please try again.', 'Page Builder', 'uncanny-automator'),
                500
            );
        }

        $requiresUnfilteredHtml = false;
        try {
            $upload = SourcePackageUploadReader::readPageSource(self::SOURCE_PACKAGE_FILE_FIELD);
            $payload = $upload->payload();
            // Validate before creating the draft so a bad file never leaves an
            // orphan Page Builder page in the Pages list.
            $package = $this->sourcePackages->validatePage($payload);
            $requiresUnfilteredHtml = trim($package->customJavaScript()) !== '';
        } catch (SourcePackageValidationException $e) {
            $this->redirectImportNotice('error', $e->userMessage(), $returnPageId);
        } catch (\Throwable $failure) {
            // Terminal containment for the upload read: an unexpected failure
            // must produce the standard import notice, not a raw fatal page.
            error_log(sprintf(
                '[Uncanny Page Builder] Page import failed while reading the upload (%s).',
                $failure::class,
            ));
            $this->redirectImportNotice(
                'error',
                _x('The page source could not be imported. Export the page again and try again. Ask your site administrator for help if the problem continues.', 'Page Builder', 'uncanny-automator'),
                $returnPageId,
            );
        }

        if ($requiresUnfilteredHtml && !current_user_can('unfiltered_html')) {
            $this->redirectImportNotice(
                'error',
                _x('This page source contains custom JavaScript. Use an account that can publish unfiltered code to import it.', 'Page Builder', 'uncanny-automator'),
                $returnPageId,
            );
        }

        $attachmentIds = [];
        $importWarnings = [];
        $importedImageCount = 0;
        $pageId = 0;
        $phase = 'create_page';
        try {
            $pageId = $this->createDraftPage(
                _x('Imported page', 'Page Builder', 'uncanny-automator'),
                /* translators: %d: the page ID used for the auto-generated title. */
                _x('Imported page #%d', 'Page Builder', 'uncanny-automator'),
                redirectOnFailure: true,
                returnPageId: $returnPageId,
            );
            $phase = 'initialize_page';
            $this->repository->markAsOwned($pageId);
            $this->shellModeService->setForPage($pageId, ShellMode::None);
            // Source restoration refreshes the working canvas. Initialize the
            // publication row first so that refresh can lock the new page's
            // state instead of failing after section source was already saved.
            $this->pageDetails->initialize($pageId, max(0, (int) get_current_user_id()));
            $phase = 'import_images';
            if ($this->sourceArchives instanceof PageSourceArchiveService) {
                $prepared = $this->sourceArchives->prepareImport($pageId, $upload);
                $preparedPayload = $prepared->payload();
                $attachmentIds = $prepared->attachmentIds();
                $importWarnings = $prepared->warnings();
                $importedImageCount = $prepared->importedImageCount();
            } elseif ($upload->images() === []) {
                // Backward-compatible seam for legacy JSON-only composition
                // roots. Production wiring always provides the archive service.
                $preparedPayload = $upload->payload();
                $importWarnings = $upload->warnings();
            } else {
                throw new \RuntimeException('Page image import is not available.');
            }
            $phase = 'restore_page_source';
            $result = $this->sourcePackages->importIntoNewPage(
                $pageId,
                $preparedPayload,
                get_current_user_id(),
            );
            $importWarnings = array_values(array_unique([
                ...$importWarnings,
                ...(is_array($result['warnings'] ?? null) ? $result['warnings'] : []),
            ]));
        } catch (\Throwable $e) {
            if ($attachmentIds !== []) {
                try {
                    $this->sourceArchives->cleanupImportedImages($attachmentIds);
                } catch (\Throwable $cleanupError) {
                    $this->reportImportFailure($pageId, 'cleanup_images', $cleanupError);
                }
            }
            $this->deleteCreatedPage($pageId);
            $this->reportImportFailure($pageId, $phase, $e);
            $this->redirectImportNotice(
                'error',
                _x('The page source could not be imported. Export the page again and try again. Ask your site administrator for help if the problem continues.', 'Page Builder', 'uncanny-automator'),
                $returnPageId,
            );
        }

        $this->notifyObservers('uncanny_page_builder_page_imported', $pageId);

        try {
            $message = $importedImageCount > 0
                ? sprintf(
                    /* translators: %d: number of imported images. */
                    _nx('Page imported with %d image.', 'Page imported with %d images.', $importedImageCount, 'Page Builder', 'uncanny-automator'),
                    $importedImageCount,
                )
                : _x('Page imported.', 'Page Builder', 'uncanny-automator');
            if ($importWarnings !== []) {
                $message .= ' ' . sprintf(
                    /* translators: %d: number of import warnings. */
                    _nx('%d compatibility warning was recorded.', '%d compatibility warnings were recorded.', count($importWarnings), 'Page Builder', 'uncanny-automator'),
                    count($importWarnings),
                );
                $message .= ' ' . implode(' ', array_slice($importWarnings, 0, 3));
            }

            AdminImportNoticeStore::remember(
                self::IMPORT_NOTICE_SCREEN,
                'success',
                $message,
                AdminCanvasEditorWindowedPage::editorUrl($pageId),
                _x('Open imported page', 'Page Builder', 'uncanny-automator'),
            );
        } catch (\Throwable $failure) {
            // The import already succeeded. A notice failure must not replace
            // the success redirect with an error page.
            error_log(sprintf(
                '[Uncanny Page Builder] Page import success notice failed (%s).',
                $failure::class,
            ));
        }

        if ($returnPageId > 0) {
            wp_safe_redirect(
                AdminImportNoticeStore::url(
                    AdminCanvasEditorWindowedPage::editorUrl($pageId),
                    self::IMPORT_NOTICE_SCREEN,
                ),
                303,
                'Uncanny Page Builder',
            );
            exit;
        }

        wp_safe_redirect(
            AdminImportNoticeStore::url(AdminCanvasEditorWindowedPage::pagesScreenUrl(), self::IMPORT_NOTICE_SCREEN),
            303,
            'Uncanny Page Builder'
        );
        exit;
    }

    private function createDraftPage(
        string $baseTitle,
        string $numberedTitleFormat,
        bool $redirectOnFailure = false,
        int $returnPageId = 0,
    ): int {
        // Create first so WordPress gives us the durable post ID that becomes
        // the default title contract across the admin, Agent, and editor.
        $pageId = wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => $baseTitle,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($pageId)) {
            if ($redirectOnFailure) {
                $this->redirectImportNotice(
                    'error',
                    _x('Could not create the imported page. Please try again.', 'Page Builder', 'uncanny-automator'),
                    $returnPageId,
                );
            }

            wp_die(
                esc_html_x('Could not create the page. Please try again.', 'Page Builder', 'uncanny-automator'),
                500
            );
        }

        $updatedId = wp_update_post([
            'ID' => $pageId,
            'post_title' => sprintf($numberedTitleFormat, $pageId),
        ], true);

        if (is_wp_error($updatedId) || (int) $updatedId <= 0) {
            $this->deleteCreatedPage((int) $pageId);

            if ($redirectOnFailure) {
                $this->redirectImportNotice(
                    'error',
                    _x('Could not create the imported page. Please try again.', 'Page Builder', 'uncanny-automator'),
                    $returnPageId,
                );
            }

            wp_die(
                esc_html_x('Could not create the page. Please try again.', 'Page Builder', 'uncanny-automator'),
                500
            );
        }

        return (int) $pageId;
    }

    /**
     * Deletes only the draft this import request created after validation. This
     * prevents a failed restore from leaving an empty imported page behind.
     */
    private function deleteCreatedPage(int $pageId): void
    {
        if ($pageId <= 0 || !function_exists('\\wp_delete_post')) {
            return;
        }

        try {
            $deleted = \wp_delete_post($pageId, true);
        } catch (\Throwable $failure) {
            error_log(sprintf(
                '[Uncanny Page Builder] Page cleanup failed for page %d (%s).',
                $pageId,
                $failure::class,
            ));
            return;
        }
        if ($deleted === false || $deleted === null) {
            error_log(sprintf(
                '[Uncanny Page Builder] Failed to delete compensated page %d after import failure.',
                $pageId,
            ));
        }
    }

    /**
     * @param 'error'|'success' $status
     */
    private function redirectImportNotice(string $status, string $message, int $returnPageId = 0): never
    {
        AdminImportNoticeStore::remember(self::IMPORT_NOTICE_SCREEN, $status, $message);
        if ($returnPageId > 0) {
            wp_safe_redirect(
                AdminImportNoticeStore::url(
                    AdminCanvasEditorWindowedPage::editorUrl($returnPageId),
                    self::IMPORT_NOTICE_SCREEN,
                ),
                303,
                'Uncanny Page Builder',
            );
            exit;
        }

        wp_safe_redirect(
            AdminImportNoticeStore::url(AdminCanvasEditorWindowedPage::pagesScreenUrl(), self::IMPORT_NOTICE_SCREEN),
            303,
            'Uncanny Page Builder'
        );
        exit;
    }

    /**
     * The editor return page is accepted only when it is still an owned page
     * the current user may edit. Otherwise the Pages list remains the safe
     * fallback for malformed or stale form submissions.
     */
    private function validatedImportReturnPageId(): int
    {
        if (sanitize_key((string) ($_POST['return_context'] ?? '')) !== 'editor') {
            return 0;
        }

        $pageId = absint($_POST['return_page_id'] ?? 0);
        if (
            $pageId <= 0
            || !current_user_can('edit_post', $pageId)
            || !WordPressCallbackBoundary::valueOrDie(
                'page.import_return.authorize',
                fn (): bool => $this->repository->isOwnedPage($pageId),
            )
        ) {
            return 0;
        }

        return $pageId;
    }

    private function reportImportFailure(int $pageId, string $phase, \Throwable $error): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Page import failed during %s for page %d (%s).',
            sanitize_key($phase),
            $pageId,
            $error::class,
        ));
    }

    private function notifyObservers(string $hook, mixed ...$args): void
    {
        try {
            do_action($hook, ...$args);
        } catch (\Throwable $error) {
            // These observers run after the page write. Their failure cannot
            // reverse that write and must not replace the success response.
            error_log(sprintf(
                '[Uncanny Page Builder] Observer %s failed (%s).',
                $hook,
                $error::class,
            ));
        }
    }

    private function assertNewPagesAvailable(): void
    {
        $isAvailable = (bool) WordPressCallbackBoundary::valueOrDie(
            'page.availability',
            fn (): bool => $this->availability->allowsNewPages(),
        );
        if ($isAvailable) {
            return;
        }

        wp_die(
            esc_html_x(
                'Uncanny Page Builder is disabled. Enable it in Automator settings to create a new Page Builder page.',
                'Page Builder',
                'uncanny-automator',
            ),
            esc_html_x('Uncanny Page Builder is disabled', 'Page Builder', 'uncanny-automator'),
            ['response' => 403],
        );
    }

    private function assertPostRequest(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
            return;
        }

        wp_die(
            esc_html_x('Page creation requires a POST request.', 'Page Builder', 'uncanny-automator'),
            esc_html_x('Invalid request', 'Page Builder', 'uncanny-automator'),
            ['response' => 405],
        );
    }
}
