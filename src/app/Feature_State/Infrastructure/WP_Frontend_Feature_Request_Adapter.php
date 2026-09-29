<?php
/**
 * WordPress observations for frontend allocation refresh eligibility.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Feature_State\Ports\Frontend_Feature_Request_Port;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Settings\ListDisplayableContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\ListEnabledContentTypesUseCase;
use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageOwnershipRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressContentTypeCatalog;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageBuilderAllowedCapabilityPort;

/**
 * Translates local WordPress state into the facts required by the application.
 *
 * Read this adapter at or after `wp`, when WordPress has resolved the page.
 * Nothing here queries feature visibility, contacts the credits service, or
 * boots Page Builder. Each feature's existing access provider remains the
 * authority for its capabilities.
 */
final class WP_Frontend_Feature_Request_Adapter implements Frontend_Feature_Request_Port {

	/**
	 * Exclude request types which have no frontend page controls.
	 *
	 * @return bool
	 */
	public function is_frontend_request(): bool {
		return ! is_admin()
			&& ! wp_doing_ajax()
			&& ! wp_doing_cron()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			&& ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& ! is_feed();
	}

	/**
	 * Read authentication separately from feature permissions.
	 *
	 * @return bool
	 */
	public function is_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Reuse Agent's capability without its admin-only presentation condition.
	 *
	 * @return bool
	 */
	public function can_use_agent(): bool {
		$context = new Client_Context_Service();

		// can_access_client() also requires is_admin(), so it cannot answer this
		// frontend permission question. The capability provider is still correct.
		return $context->user_has_capability( $context->get_client_access_capability() );
	}

	/**
	 * Preserve the existing opt-in for integrations that present Agent in front.
	 *
	 * @return bool
	 */
	public function agent_control_available(): bool {
		// An integration can call render_launcher() directly. The setting for the
		// default admin launcher does not control that path, nor does the admin bar.
		return (bool) apply_filters( 'automator_mcp_show_on_frontend', false );
	}

	/**
	 * Ask Page Builder's capability provider only after host compatibility passed.
	 *
	 * @return bool
	 */
	public function can_use_page_builder(): bool {
		if ( ! $this->page_builder_runtime_available() ) {
			return false;
		}

		return ( new GetPageBuilderAllowedCapabilities( new WordPressPageBuilderAllowedCapabilityPort() ) )
			->currentUserHasAllowedCapability();
	}

	/**
	 * Check local toolbar conditions without resolving allocation-based visibility.
	 *
	 * @return bool
	 */
	public function page_builder_control_available(): bool {
		if ( ! $this->page_builder_runtime_available() || ! $this->admin_bar_showing() ) {
			return false;
		}

		// New page creation can need fresh facts even when the current page is
		// unrelated to Page Builder. Calling allowsNewPages() here would resolve
		// credit-based visibility too early and could trap an empty cache.
		// Read the setting the same way allowsNewPages() does, so a stale option
		// cache cannot give this check a different answer than the toolbar.
		if ( ( new Page_Builder_Settings() )->is_enabled( true ) ) {
			return true;
		}

		// Turning off creation still leaves the toolbar's edit link on existing
		// owned content. An unrelated page does not qualify through this path.
		if ( ! is_singular() ) {
			return false;
		}

		$post_id = get_queried_object_id();
		if ( ! is_int( $post_id ) || $post_id <= 0 ) {
			return false;
		}

		if ( ! ( new WpPageOwnershipRepository() )->isOwned( $post_id ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) ) {
			return false;
		}

		// Reuse the same content-type decision as AdminBarButton. Reading only an
		// ownership marker would incorrectly include a type disabled in settings
		// or a type whose editor support was removed by another plugin.
		$settings       = new WpSettingsRepository();
		$display_policy = new PageBuilderDisplayPolicy();
		$supports_type  = new SupportsPostTypeUseCase(
			new ListDisplayableContentTypesUseCase( $settings, new WordPressContentTypeCatalog(), $display_policy ),
			new ListEnabledContentTypesUseCase( $settings, $display_policy )
		);

		return $supports_type->isSupported( $post_type );
	}

	/**
	 * Ask whether the admin bar shows without fixing the answer early.
	 *
	 * is_admin_bar_showing() stores its filtered result in a global and applies
	 * the show_admin_bar filter to that stored value on every later call. This
	 * check runs on `wp`, before core first asks on template_redirect, so leave
	 * the global unset for core to evaluate at its usual time.
	 *
	 * @return bool
	 */
	private function admin_bar_showing(): bool {
		$was_set = isset( $GLOBALS['show_admin_bar'] );
		// The show_admin_bar filter can return any type; this file is strict.
		$showing = (bool) is_admin_bar_showing();
		if ( ! $was_set ) {
			unset( $GLOBALS['show_admin_bar'] );
		}

		return $showing;
	}

	/**
	 * Keep PHP 8.1 Page Builder classes behind the existing host boundary.
	 *
	 * @return bool
	 */
	private function page_builder_runtime_available(): bool {
		// This adapter must parse on Automator's PHP 7.4 floor. Namespace imports
		// do not load classes; the checks below run before any Page Builder `new`.
		// Do not replace these checks with Plugin::boot() just to answer eligibility.
		return PHP_VERSION_ID >= 80100
			&& ! ( defined( 'AUTOMATOR_PAGE_BUILDER_DISABLED' ) && AUTOMATOR_PAGE_BUILDER_DISABLED )
			&& defined( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME' )
			&& AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME;
	}
}
