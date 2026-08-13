<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Reusable\CreateReusableCommand;
use UncannyPageBuilder\Application\Reusable\CreateReusableUseCase;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

final class ReusableFactory
{
    public const CREATE_ACTION = 'uncanny_page_builder_create_reusable';
    public const IMPORT_ACTION = 'uncanny_page_builder_import_reusable';
    public const IMPORT_NOTICE_SCREEN = 'reusable_source_import';

    private const POST_TYPE = 'upb_global_part';
    private const SOURCE_PACKAGE_FILE_FIELD = 'source_package';

    public function __construct(
        private readonly CreateReusableUseCase $createReusable,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ?ReusableSourcePackageService $sourcePackages = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function redirectPostNewForReusable(): void
    {
        $postType = isset($_GET['post_type']) && is_string($_GET['post_type'])
            ? sanitize_key(wp_unslash($_GET['post_type']))
            : '';

        if ($postType !== self::POST_TYPE) {
            return;
        }

        $hasAccess = (bool) WordPressCallbackBoundary::valueOrDie(
            'reusable.post_new.authorize',
            fn (): bool => $this->allowedCapabilities->currentUserHasAllowedCapability(),
        );
        if (!$hasAccess) {
            wp_die(
                esc_html_x("You don't have permission to create reusable parts. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        $formId = 'upb-create-reusable-form';
        $form = '<form id="' . esc_attr($formId) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">'
            . '<input type="hidden" name="_wpnonce" value="' . esc_attr((string) wp_create_nonce(self::CREATE_ACTION)) . '">'
            . '<button type="submit" class="button button-primary">'
            . esc_html_x('Add new reusable', 'Page Builder', 'uncanny-automator')
            . '</button></form>'
            . '<script>document.getElementById("' . esc_js($formId) . '").submit();</script>';

        wp_die(
            $form,
            esc_html_x('Add new reusable', 'Page Builder', 'uncanny-automator'),
            ['response' => 200],
        );
    }

    public function create(): void
    {
        $this->assertPostRequest();
        check_admin_referer(self::CREATE_ACTION);

        $hasAccess = (bool) WordPressCallbackBoundary::valueOrDie(
            'reusable.create.authorize',
            fn (): bool => $this->allowedCapabilities->currentUserHasAllowedCapability(),
        );
        if (!$hasAccess) {
            wp_die(
                esc_html_x("You don't have permission to create reusable parts. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        try {
            $reusable = ($this->createReusable)(new CreateReusableCommand());
        } catch (\Throwable $failure) {
            // admin-post.php is a public WordPress boundary. Report the
            // internal failure and return a controlled response instead of
            // exposing an uncaught exception as a fatal-error page.
            $this->reportFailure('reusable.create', $failure);

            wp_die(
                esc_html_x('Could not create the reusable. Please try again.', 'Page Builder', 'uncanny-automator'),
                esc_html_x('Reusable creation failed', 'Page Builder', 'uncanny-automator'),
                ['response' => 500],
            );
        }

        wp_safe_redirect(
            AdminCanvasEditorWindowedGlobalPartPage::editorUrl($reusable->id()),
            303,
            'Uncanny Page Builder',
        );
        exit;
    }

    public function importReusable(): void
    {
        check_admin_referer(self::IMPORT_ACTION);

        $hasAccess = (bool) WordPressCallbackBoundary::valueOrDie(
            'reusable.import.authorize',
            fn (): bool => $this->allowedCapabilities->currentUserHasAllowedCapability(),
        );
        if (!$hasAccess) {
            wp_die(
                esc_html_x("You don't have permission to import reusable parts. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        if (!$this->sourcePackages instanceof ReusableSourcePackageService) {
            wp_die(
                esc_html_x('Reusable import is not available. Please try again.', 'Page Builder', 'uncanny-automator'),
                500,
            );
        }

        try {
            $payload = SourcePackageUploadReader::readJson(self::SOURCE_PACKAGE_FILE_FIELD);
            // Reusable JavaScript executes on every page that renders this
            // part, so enforce WordPress' executable-code capability before
            // the create-only import mutates global-part state.
            $package = $this->sourcePackages->validateReusable($payload);
        } catch (SourcePackageValidationException $e) {
            $this->redirectImportNotice('error', $e->userMessage());
        } catch (\Throwable $failure) {
            // admin-post.php is a public WordPress boundary. Report the
            // internal failure and return the controlled import notice
            // instead of exposing an uncaught exception as a fatal page.
            $this->reportFailure('reusable.import.validate', $failure);
            $this->redirectImportNotice(
                'error',
                _x('Could not import the reusable. Check the file and try again.', 'Page Builder', 'uncanny-automator')
            );
        }

        try {
            $importBlockedByJavaScriptCapability = trim($package->customJavaScript()) !== ''
                && !current_user_can('unfiltered_html');
        } catch (\Throwable $failure) {
            // The capability decision must fail into the controlled import
            // notice, never into a fatal page.
            $this->reportFailure('reusable.import.capability', $failure);
            $this->redirectImportNotice(
                'error',
                _x('Could not import the reusable. Check the file and try again.', 'Page Builder', 'uncanny-automator')
            );
        }

        if ($importBlockedByJavaScriptCapability) {
            $this->redirectImportNotice(
                'error',
                _x('This reusable source contains custom JavaScript. Use an account that can publish unfiltered code to import it.', 'Page Builder', 'uncanny-automator'),
            );
        }

        try {
            $result = $this->sourcePackages->importReusable($payload);
        } catch (SourcePackageValidationException $e) {
            $this->redirectImportNotice('error', $e->userMessage());
        } catch (\Throwable $failure) {
            $this->reportFailure('reusable.import.write', $failure);
            $this->redirectImportNotice(
                'error',
                _x('Could not import the reusable. Check the file and try again.', 'Page Builder', 'uncanny-automator')
            );
        }

        try {
            AdminImportNoticeStore::remember(
                self::IMPORT_NOTICE_SCREEN,
                'success',
                _x('Reusable imported.', 'Page Builder', 'uncanny-automator'),
                AdminCanvasEditorWindowedGlobalPartPage::editorUrl((int) $result['id']),
                _x('Open imported reusable', 'Page Builder', 'uncanny-automator'),
            );
        } catch (\Throwable $failure) {
            // The reusable already exists. An optional notice failure must not
            // replace the successful import with an admin-post fatal page.
            $this->reportFailure('reusable.import.notice', $failure);
        }

        wp_safe_redirect(
            AdminImportNoticeStore::url(AdminCanvasEditorWindowedGlobalPartPage::reusablesScreenUrl(), self::IMPORT_NOTICE_SCREEN),
            303,
            'Uncanny Page Builder',
        );
        exit;
    }

    /**
     * @param 'error'|'success' $status
     */
    private function redirectImportNotice(string $status, string $message): never
    {
        AdminImportNoticeStore::remember(self::IMPORT_NOTICE_SCREEN, $status, $message);
        wp_safe_redirect(
            AdminImportNoticeStore::url(AdminCanvasEditorWindowedGlobalPartPage::reusablesScreenUrl(), self::IMPORT_NOTICE_SCREEN),
            303,
            'Uncanny Page Builder',
        );
        exit;
    }

    private function assertPostRequest(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
            return;
        }

        wp_die(
            esc_html_x('Reusable creation requires a POST request.', 'Page Builder', 'uncanny-automator'),
            esc_html_x('Invalid request', 'Page Builder', 'uncanny-automator'),
            ['response' => 405],
        );
    }

    private function reportFailure(string $code, \Throwable $failure): void
    {
        try {
            if ($this->failureReporter instanceof FailureReporterInterface) {
                $this->failureReporter->report('reusable admin', 0, $code, $failure);
                return;
            }
        } catch (\Throwable) {
            // A reporting failure cannot replace the controlled admin response.
        }

        error_log(sprintf(
            '[Uncanny Page Builder] %s failed (%s).',
            $code,
            $failure::class,
        ));
    }
}
