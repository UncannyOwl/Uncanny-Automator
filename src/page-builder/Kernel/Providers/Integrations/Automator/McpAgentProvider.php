<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\Integrations\Automator;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Personalization\SitePersonalizationService;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseGlobalPartRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Infrastructure\Automator\McpPayloadContextResolver;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasPage;

final class McpAgentProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
        $sectionRepo      = $container->typed(DatabaseSectionRepository::class);
        $globalPartRepo   = $container->typed(DatabaseGlobalPartRepository::class);
        $shellModeService = $container->typed(ShellModeService::class);
        $personalizationService = $container->typed(SitePersonalizationService::class);
        $allowedCapabilities = $container->typed(GetPageBuilderAllowedCapabilities::class);
        $toolSettingsAccess = $container->typed(ToolSettingsAccess::class);

        /*
         * Agent rendering is intentionally absent from this provider's
         * frontend lifecycle. AdminCanvasPage owns the one launcher mount;
         * previews and public pages must stay clean even for logged-in editors.
         */
        $payloadContextResolver = new McpPayloadContextResolver(
            $sectionRepo,
            $globalPartRepo,
            $shellModeService,
            $personalizationService,
            $allowedCapabilities,
            $toolSettingsAccess,
            $container->typed(SupportsPostTypeUseCase::class),
        );
        add_filter('automator_mcp_payload_data', [$payloadContextResolver, 'apply']);
        add_filter('automator_mcp_page_builder_availability', [$payloadContextResolver, 'availability'], 10, 2);

        // Page Builder owns the conversation starters on its own canvas;
        // Automator owns the filter, validation, and chat SDK contract.
        $conversationStarters = new \UncannyPageBuilder\Infrastructure\Automator\McpConversationStarters($sectionRepo);
        add_filter('automator_mcp_conversation_starters', [$conversationStarters, 'filter'], 10, 2);

        add_filter('automator_mcp_in_allowed_pages', static function ($allowed = null) use ($allowedCapabilities): bool {
            if ($allowed === true) {
                return true;
            }
            if (! $allowedCapabilities->currentUserHasAllowedCapability()) {
                return false;
            }
            if (!function_exists('get_current_screen')) {
                return false;
            }
            $screen = get_current_screen();
            if (!$screen instanceof \WP_Screen) {
                return false;
            }
            if ('admin_page_' . AdminCanvasPage::PAGE_SLUG === $screen->id) {
                return true;
            }
            return false;
        });
    }

    /**
     * Mount Automator's MCP client inside the Page Builder canvas.
     *
     * Automator owns the encrypted payload and SDK markup. Page Builder only
     * chooses the canvas mount point and opts into the shared agent contract.
     */
    public static function renderAutomatorLauncher(): void
    {
        $clientClass = '\Uncanny_Automator\App\Application\Mcp\Mcp_Client';
        if (!class_exists($clientClass) || !is_callable([$clientClass, 'get_instance'])) {
            return;
        }

        try {
            $client = $clientClass::get_instance();
        } catch (\Throwable) {
            // Older/incompatible Automator releases must not break the canvas.
            return;
        }

        if (
            !is_object($client)
            || !is_callable([$client, 'load_chat_sdk'])
            || !is_callable([$client, 'render_launcher'])
        ) {
            return;
        }

        $client->load_chat_sdk();
        $client->render_launcher(null);
    }
}
