<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Personalization\SitePersonalizationService;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasPage;

/**
 * Resolves the Page Builder context attached to Automator's MCP payload.
 *
 * Automator owns payload creation and encryption. Page Builder only adds its
 * trusted editing context after proving the URL/global request points at a
 * Page Builder-owned page or global part.
 */
final class McpPayloadContextResolver
{
    private const CONTEXT_VERSION = '1';
    private const SURFACE = 'canvas';

    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly GlobalPartRepositoryInterface $globalPartRepository,
        private readonly ShellModeService $shellModeService,
        private readonly SitePersonalizationService $personalizationService,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ToolSettingsAccess $toolSettingsAccess,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    public function apply($payload = null): array
    {
        $payload = is_array($payload) ? $payload : [];

        try {
            $trustedTargetId = $this->resolveTrustedTargetPostId($payload['page_url'] ?? null);
            if ($trustedTargetId !== null) {
                return $this->withPageBuilderContext($payload, $trustedTargetId);
            }
        } catch (StaleSourceGenerationException $error) {
            $this->reportContextFailure($error);
        } catch (\Throwable $error) {
            // Automator owns this shared filter. A Page Builder read failure
            // must remove trusted context instead of terminating its request.
            $this->reportContextFailure($error);
        }

        return $this->withoutPageBuilderContext($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withoutPageBuilderContext(array $payload): array
    {
        unset($payload['page_id']);
        unset($payload['global_part_id']);
        unset($payload['page_builder_context']);
        unset($payload['agent_mode']);
        $payload['shell_mode'] = ShellMode::None->value;

        return $payload;
    }

    /**
     * Resolve Page Builder's request-scoped Automator availability contract.
     *
     * Automator owns the encrypted payload and defaults to unavailable. Page
     * Builder only opts in when this plugin is loaded and the current user can
     * use Page Builder on this site.
     *
     * @param mixed $availability
     * @param mixed $requestContext
     * @return array{status:string,available:bool,enabled:bool,reason:string,canvasActive:bool}
     */
    public function availability($availability = null, $requestContext = null): array
    {
        unset($availability);
        $requestContext = is_array($requestContext) ? $requestContext : [];

        try {
            if (! $this->allowedCapabilities->currentUserHasAllowedCapability()) {
                return [
                    'status'       => 'disabled',
                    'available'    => true,
                    'enabled'      => false,
                    'reason'       => 'page_builder_user_cannot_edit',
                    'canvasActive' => false,
                ];
            }

            $trustedTargetId = $this->resolveTrustedTargetPostId($requestContext['page_url'] ?? null);
        } catch (StaleSourceGenerationException $error) {
            $this->reportContextFailure($error);

            return $this->unavailableContext();
        } catch (\Throwable $error) {
            // Availability is an external filter contract. Fail closed when
            // Page Builder cannot prove that the canvas context is valid.
            $this->reportContextFailure($error);

            return $this->unavailableContext();
        }

        return [
            'status'       => 'available',
            'available'    => true,
            'enabled'      => true,
            'reason'       => 'page_builder_registered',
            'canvasActive' => $trustedTargetId !== null,
        ];
    }

    /**
     * @return array{status:string,available:bool,enabled:bool,reason:string,canvasActive:bool}
     */
    private function unavailableContext(): array
    {
        return [
            'status'       => 'disabled',
            'available'    => true,
            'enabled'      => false,
            'reason'       => 'page_builder_context_unavailable',
            'canvasActive' => false,
        ];
    }

    private function reportContextFailure(\Throwable $error): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] MCP context unavailable (%s).',
            $error::class,
        ));
    }

    private function resolveTrustedTargetPostId(mixed $pageUrl): ?int
    {
        if (! $this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return null;
        }

        $frontendPostId = $this->resolveFrontendPostId();
        if ($frontendPostId !== null && $this->isSupportedOwnedPost($frontendPostId)) {
            return $frontendPostId;
        }

        $resolvedId = $this->resolvePostIdFromPageUrl($pageUrl);
        if ($resolvedId === null || ! $this->isPageBuilderPayloadTarget($resolvedId)) {
            return null;
        }

        return $resolvedId;
    }

    private function resolveFrontendPostId(): ?int
    {
        if (is_admin() || ! is_singular()) {
            return null;
        }

        return WordPressPostId::fromCurrentQuery(get_queried_object_id());
    }

    private function resolvePostIdFromPageUrl(mixed $pageUrl): ?int
    {
        $currentAdminCanvasId = $this->currentAdminCanvasId();
        if ($currentAdminCanvasId !== null) {
            return $currentAdminCanvasId;
        }

        $urlCanvasId = $this->trustedAdminCanvasIdFromPageUrl($pageUrl);
        if ($urlCanvasId !== null) {
            return $urlCanvasId;
        }

        if (! is_string($pageUrl) || $pageUrl === '') {
            return null;
        }

        $postId = (int) url_to_postid($pageUrl);
        return $postId > 0 ? $postId : null;
    }

    private function currentAdminCanvasId(): ?int
    {
        if (
            ! is_admin()
            || ! is_string($_GET['page'] ?? null)
            || $_GET['page'] !== AdminCanvasPage::PAGE_SLUG
        ) {
            return null;
        }

        $postId = absint($_GET['canvas_id'] ?? 0);
        return $postId > 0 ? $postId : null;
    }

    private function trustedAdminCanvasIdFromPageUrl(mixed $pageUrl): ?int
    {
        if (! is_string($pageUrl) || $pageUrl === '') {
            return null;
        }

        $query = wp_parse_url($pageUrl, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        if (! is_string($params['page'] ?? null) || $params['page'] !== AdminCanvasPage::PAGE_SLUG) {
            return null;
        }

        $host = wp_parse_url($pageUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $siteHost = wp_parse_url(admin_url('admin.php'), PHP_URL_HOST);
            if (! is_string($siteHost) || strcasecmp($host, $siteHost) !== 0) {
                return null;
            }
        }

        $postId = absint($params['canvas_id'] ?? 0);
        return $postId > 0 ? $postId : null;
    }

    private function isPageBuilderPayloadTarget(int $postId): bool
    {
        if ($this->isSupportedOwnedPost($postId)) {
            return true;
        }

        return get_post_type($postId) === 'upb_global_part';
    }

    private function isSupportedOwnedPost(int $postId): bool
    {
        $postType = get_post_type($postId);

        return is_string($postType)
            && $this->supportsPostType->isSupported($postType)
            && $this->sectionRepository->isOwnedPage($postId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withPageBuilderContext(array $payload, int $postId): array
    {
        $globalPartContext = $this->globalPartCanvasContext($postId);
        if ($globalPartContext !== null) {
            // Keep the legacy target post ID for Automator compatibility, but
            // publish reusable ownership through explicit scope fields so
            // Agent does not have to infer edit intent from a CPT post ID.
            $payload['page_id'] = $postId;
            $payload['global_part_id'] = $postId;
            $payload['shell_mode'] = ShellMode::UncannyNative->value;
            unset($payload['agent_mode']);

            $payload = $this->withTrustedCanvasContext($payload, $globalPartContext);

            return $this->withPersonalization($payload);
        }

        $ctx = $this->shellModeService->resolveForPage($postId);

        $payload['page_id']    = $postId;
        unset($payload['global_part_id']);
        $payload['shell_mode'] = $ctx->mode->value;
        unset($payload['agent_mode']);

        $payload = $this->withTrustedCanvasContext($payload, [
            'scope'          => 'page',
            'page_id'        => $postId,
            'global_part_id' => 0,
            'javascript'     => $this->javaScriptContext(),
        ]);

        return $this->withPersonalization($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $contextFields
     * @return array<string, mixed>
     */
    private function withTrustedCanvasContext(array $payload, array $contextFields = []): array
    {
        $incomingContext = is_array($payload['page_builder_context'] ?? null)
            ? $payload['page_builder_context']
            : [];
        $incomingPersonalization = is_array($incomingContext['personalization'] ?? null)
            ? $incomingContext['personalization']
            : [];

        // This is canvas context only. The user's current submit request, not
        // Page Builder context, decides whether Agent bypasses General.
        $context = [
            'context_version' => self::CONTEXT_VERSION,
            'surface'         => self::SURFACE,
        ];
        foreach ($contextFields as $key => $value) {
            $context[$key] = $value;
        }

        if ($incomingPersonalization !== []) {
            $context['personalization'] = $incomingPersonalization;
        }

        $payload['page_builder_context'] = $context;

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function globalPartCanvasContext(int $postId): ?array
    {
        if (get_post_type($postId) !== 'upb_global_part') {
            return null;
        }

        $part = $this->globalPartRepository->findById($postId);

        return [
            'scope'                => 'global_part',
            'page_id'              => 0,
            'global_part_id'       => $postId,
            'global_part_type'     => (string) ($part['type'] ?? ''),
            'global_part_title'    => (string) ($part['title'] ?? ''),
            'javascript'           => $this->javaScriptContext(),
        ];
    }

    /**
     * @return array{
     *     page_custom_javascript_enabled: bool,
     *     global_part_custom_javascript_enabled: bool,
     *     approved_libraries: array{anime: bool, swiper: bool},
     *     approved_library_slugs: list<string>
     * }
     */
    private function javaScriptContext(): array
    {
        return [
            'page_custom_javascript_enabled' => $this->toolSettingsAccess->pageCustomJavaScriptEnabled(),
            'global_part_custom_javascript_enabled' => $this->toolSettingsAccess->globalPartCustomJavaScriptEnabled(),
            'approved_libraries' => $this->toolSettingsAccess->approvedLibraries(),
            'approved_library_slugs' => $this->toolSettingsAccess->approvedLibrarySlugs(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withPersonalization(array $payload): array
    {
        $instructions = $this->personalizationService->loadCustomInstructions();
        if ($instructions->isEmpty()) {
            return $payload;
        }

        $context = is_array($payload['page_builder_context'] ?? null)
            ? $payload['page_builder_context']
            : [];
        $personalization = is_array($context['personalization'] ?? null)
            ? $context['personalization']
            : [];

        // Site personalization is Page Builder context, not tool, branding, or
        // selection-file data. Keep the payload path explicit for Agent readers.
        $personalization['custom_instructions'] = $instructions->text();
        $context['personalization'] = $personalization;
        $payload['page_builder_context'] = $context;

        return $payload;
    }
}
