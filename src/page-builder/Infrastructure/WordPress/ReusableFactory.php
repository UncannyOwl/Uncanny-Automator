<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
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
    ) {}

    public static function creationUrl(): string
    {
        return admin_url(
            'admin-post.php?action=' . self::CREATE_ACTION
            . '&_wpnonce=' . rawurlencode((string) wp_create_nonce(self::CREATE_ACTION))
        );
    }

    public function redirectPostNewForReusable(): void
    {
        $postType = isset($_GET['post_type']) && is_string($_GET['post_type'])
            ? sanitize_key(wp_unslash($_GET['post_type']))
            : '';

        if ($postType !== self::POST_TYPE) {
            return;
        }

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            wp_die(
                esc_html_x("You don't have permission to create reusable parts. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        wp_safe_redirect(self::creationUrl(), 303, 'Uncanny Page Builder');
        exit;
    }

    public function create(): void
    {
        check_admin_referer(self::CREATE_ACTION);

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            wp_die(
                esc_html_x("You don't have permission to create reusable parts. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        $reusable = ($this->createReusable)(new CreateReusableCommand());

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

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
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
            $this->redirectImportNotice('error', $e->getMessage());
        }

        if (trim($package->customJavaScript()) !== '' && !current_user_can('unfiltered_html')) {
            $this->redirectImportNotice(
                'error',
                _x('This reusable source contains custom JavaScript. Use an account that can publish unfiltered code to import it.', 'Page Builder', 'uncanny-automator'),
            );
        }

        try {
            $result = $this->sourcePackages->importReusable($payload);
        } catch (SourcePackageValidationException $e) {
            $this->redirectImportNotice('error', $e->getMessage());
        } catch (\Throwable) {
            $this->redirectImportNotice(
                'error',
                _x('Could not import the reusable. Check the file and try again.', 'Page Builder', 'uncanny-automator')
            );
        }

        AdminImportNoticeStore::remember(
            self::IMPORT_NOTICE_SCREEN,
            'success',
            _x('Reusable imported.', 'Page Builder', 'uncanny-automator'),
            AdminCanvasEditorWindowedGlobalPartPage::editorUrl((int) $result['id']),
            _x('Open imported reusable', 'Page Builder', 'uncanny-automator'),
        );

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
}
