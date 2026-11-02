<?php
/**
 * MCP settings: Automator abilities sub-tab.
 *
 * Lists the actions a third-party AI app can use on this site, grouped by
 * integration, with switches at both levels. The page itself is a shell: a
 * plain admin request registers only the integrations published recipes use,
 * so a connected app can look absent. The catalogue is therefore built inside
 * one admin-ajax request whose automator_ prefix makes the recipe manifest load
 * everything, and the page script renders it. Only integrations that can run
 * here get switches, and action rows are drawn when an integration is opened.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Application\Mcp\Abilities\Abilities_Catalog;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings_Model_Context_Protocol_Automator_Abilities
 */
class Admin_Settings_Model_Context_Protocol_Automator_Abilities {

	/**
	 * AJAX action returning action rows for integrations whose actions were not cached.
	 *
	 * The automator_ prefix makes the recipe manifest load every recipe part for
	 * this request, so discovery sees the complete action set and caches it.
	 */
	public const CATALOG_ACTION = 'automator_mcp_abilities_catalog';

	/**
	 * AJAX action switching one integration or one action on or off.
	 */
	public const TOGGLE_ACTION = 'automator_mcp_abilities_toggle';

	/**
	 * Nonce action shared by both requests.
	 */
	public const NONCE_ACTION = 'automator_mcp_abilities';

	/**
	 * Catalogue of integrations and actions with their switch state.
	 *
	 * @var Abilities_Catalog
	 */
	private $catalog;

	/**
	 * @param Abilities_Catalog|null $catalog Catalogue; defaults to the live registries and stored switches.
	 */
	public function __construct( ?Abilities_Catalog $catalog = null ) {
		$this->catalog = $catalog ?? new Abilities_Catalog();

		add_filter( 'automator_settings_model_context_protocol_tabs', array( $this, 'create_tab' ), 32, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_action( 'wp_ajax_' . self::CATALOG_ACTION, array( $this, 'handle_catalog' ) );
		add_action( 'wp_ajax_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
	}

	/**
	 * Register the sub-tab.
	 *
	 * @param array $tabs Registered sub-tabs.
	 * @return array
	 */
	public function create_tab( $tabs ) {
		$tabs['automator-abilities'] = (object) array(
			'name'     => esc_html__( 'Automator actions', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
			'icon'     => 'bolt',
		);

		return $tabs;
	}

	/**
	 * Render the sub-tab.
	 *
	 * @return void
	 */
	public function tab_output() {
		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/automator-abilities.php' );
	}

	/**
	 * Enqueue the page script on the abilities sub-tab only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! $this->is_abilities_page() ) {
			return;
		}

		wp_enqueue_script(
			'automator-mcp-abilities',
			plugins_url(
				'src/core/admin/admin-settings/tabs/model-context-protocol-assets/automator-abilities.js',
				AUTOMATOR_BASE_FILE
			),
			array( 'uap-admin', 'wp-i18n' ),
			Utilities::automator_get_version(),
			true
		);
		// Lets wp.i18n._n() apply the site language's plural rules to counts.
		wp_set_script_translations( 'automator-mcp-abilities', 'uncanny-automator' );

		wp_localize_script(
			'automator-mcp-abilities',
			'AutomatorMcpAbilities',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
				'userId'        => get_current_user_id(),
				'catalogAction' => self::CATALOG_ACTION,
				'toggleAction'  => self::TOGGLE_ACTION,
				'strings'       => array(
					'errorHeading'       => esc_html__( 'The change was not saved', 'uncanny-automator' ),
					'catalogError'       => esc_html__( 'The action list could not be loaded. Refresh the page to try again.', 'uncanny-automator' ),
					'unknownError'       => esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' ),
					'unknownOutcome'     => esc_html__( 'The saved state could not be confirmed. Refresh before making another change.', 'uncanny-automator' ),
					'saving'             => esc_html__( 'Saving…', 'uncanny-automator' ),
					/* translators: %s Name of the action that was switched on. */
					'actionOn'           => esc_html__( 'Saved. %s is on.', 'uncanny-automator' ),
					/* translators: %s Name of the action that was switched off. */
					'actionOff'          => esc_html__( 'Saved. %s is off.', 'uncanny-automator' ),
					/* translators: %s Integration name. */
					'integrationOn'      => esc_html__( 'Saved. %s is on. AI apps with the required access can use its selected actions.', 'uncanny-automator' ),
					/* translators: %s Integration name. */
					'integrationOff'     => esc_html__( 'Saved. %s is off for direct action requests from connected AI apps.', 'uncanny-automator' ),
					/* translators: %s Integration name. */
					'parentOffHelp'      => esc_html__( 'Turn on %s to allow its selected actions.', 'uncanny-automator' ),
					/* translators: %s Integration or action name. */
					'allowItem'          => esc_html__( 'Allow %s', 'uncanny-automator' ),
					'statusLabels'       => esc_html_x( 'On,Off', 'MCP abilities switch states, separated by a comma', 'uncanny-automator' ),
					'restricted'         => esc_html_x( 'Restricted', 'MCP abilities banned action', 'uncanny-automator' ),
					'noActions'          => esc_html__( 'No actions are available for this integration.', 'uncanny-automator' ),
					'noIntegrations'     => esc_html__( 'No integration actions are available on this site yet.', 'uncanny-automator' ),
					'noIntegrationsHelp' => esc_html__( 'Activate a supported plugin or connect an app in Automator, then refresh this page.', 'uncanny-automator' ),
					/* translators: %s The search text. */
					'noResults'          => esc_html__( 'No integrations or actions match “%s”.', 'uncanny-automator' ),
					/* translators: %s The search text. */
					'noAvailableResults' => esc_html__( 'No available integrations or actions match “%s”. Matching integrations are listed below under Integrations that need setup.', 'uncanny-automator' ),
					'noResultsHelp'      => esc_html__( 'Try a different name or clear the search.', 'uncanny-automator' ),
					/* translators: %d Number of integrations that need more setup. */
					'unavailableHeading' => esc_html__( 'Integrations that need setup (%d)', 'uncanny-automator' ),
					'notInstalled'       => esc_html_x( 'Not installed', 'MCP abilities unavailable status', 'uncanny-automator' ),
					'inactive'           => esc_html_x( 'Inactive', 'MCP abilities unavailable status', 'uncanny-automator' ),
					'setupRequired'      => esc_html_x( 'Setup required', 'MCP abilities unavailable status', 'uncanny-automator' ),
					'notConnected'       => esc_html_x( 'Not connected', 'MCP abilities unavailable status', 'uncanny-automator' ),
					/* translators: %s Required Automator plan, such as Pro, Plus or Elite. */
					'requiresPlan'       => esc_html_x( 'Requires %s', 'MCP abilities unavailable status', 'uncanny-automator' ),
					'proPlan'            => esc_html_x( 'Pro', 'MCP abilities required plan fallback', 'uncanny-automator' ),
				),
			)
		);
	}

	/**
	 * AJAX: return action rows for the requested integrations.
	 *
	 * @return void
	 */
	public function handle_catalog() {
		nocache_headers();

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => $this->get_error_message( 'mcp_abilities_forbidden' ) ), 403 );
		}

		if ( ! wp_verify_nonce( $this->get_post_string( 'nonce' ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => $this->get_error_message( 'mcp_abilities_invalid_nonce' ) ), 403 );
		}

		try {
			$payload = $this->build_catalog_payload();
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Abilities catalogue build failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			wp_send_json_error( array( 'message' => $this->get_error_message( 'mcp_abilities_catalog_failed' ) ), 500 );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * The whole catalogue the page renders.
	 *
	 * Meant to run inside a load-all request: every integration is registered,
	 * so availability is right for connected apps, and discovery caches
	 * complete action sets. Integrations with no actions are left out.
	 *
	 * @return array{available:array<int,array<string,mixed>>,unavailable:array<int,array<string,mixed>>,summary:array<string,int>}
	 */
	public function build_catalog_payload(): array {
		$groups    = $this->catalog->get_integrations();
		$available = array();

		foreach ( $groups['available'] as $row ) {
			$row['entry'] = $this->catalog->get_entry( $row['code'], true );

			if ( null === $row['entry'] || empty( $row['entry']['actions'] ) ) {
				continue;
			}

			$available[] = $row;
		}

		$summary = $this->summarize( $available, $groups['unavailable'] );

		return array(
			'available'   => $available,
			'unavailable' => $groups['unavailable'],
			'summary'     => array(
				'available'   => $summary['available'],
				'unavailable' => $summary['unavailable'],
				'actions'     => (int) $summary['actions'],
				'off'         => $summary['off'],
			),
		);
	}

	/**
	 * AJAX: switch one integration or one action on or off.
	 *
	 * @return void
	 */
	public function handle_toggle() {
		nocache_headers();

		try {
			$result = $this->process_toggle_request(
				$this->get_post_string( 'nonce' ),
				$this->get_post_string( 'kind' ),
				$this->get_post_string( 'code' ),
				$this->get_post_string( 'integration' ),
				'1' === $this->get_post_string( 'enabled' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Abilities toggle failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_abilities_toggle_unknown' );
		}

		if ( $result instanceof WP_Error ) {
			$status = in_array( $result->get_error_code(), array( 'mcp_abilities_forbidden', 'mcp_abilities_invalid_nonce' ), true ) ? 403 : 400;
			wp_send_json_error(
				array(
					'message'         => $this->get_error_message( $result->get_error_code() ),
					'outcome_unknown' => 'mcp_abilities_toggle_unknown' === $result->get_error_code(),
				),
				$status
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Validate a toggle request and persist it.
	 *
	 * @param string $nonce       Submitted nonce.
	 * @param string $kind        'integration' or 'action'.
	 * @param string $code        Integration or action code.
	 * @param string $integration Integration code when toggling an action.
	 * @param bool   $enabled     True to allow, false to block.
	 * @return array{kind:string,code:string,enabled:bool}|WP_Error
	 */
	private function process_toggle_request( string $nonce, string $kind, string $code, string $integration, bool $enabled ) {
		if ( ! $this->can_manage() ) {
			return new WP_Error( 'mcp_abilities_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'mcp_abilities_invalid_nonce' );
		}

		if ( 'integration' === $kind ) {
			if ( ! $this->catalog->can_toggle_integration( $code ) ) {
				return new WP_Error( 'mcp_abilities_unknown' );
			}

			if ( ! $this->catalog->set_integration_enabled( $code, $enabled ) ) {
				return new WP_Error( 'mcp_abilities_toggle_failed' );
			}
		} elseif ( 'action' === $kind ) {
			if ( ! $this->catalog->can_toggle_action( $code, $integration ) ) {
				return new WP_Error( 'mcp_abilities_unknown' );
			}

			if ( ! $this->catalog->set_action_enabled( $code, $enabled ) ) {
				return new WP_Error( 'mcp_abilities_toggle_failed' );
			}
		} else {
			return new WP_Error( 'mcp_abilities_unknown' );
		}

		return array(
			'kind'    => $kind,
			'code'    => $code,
			'enabled' => $enabled,
		);
	}

	/**
	 * Level 0 numbers for the page header.
	 *
	 * The action total is only known when every available integration has
	 * cached actions; otherwise it is null and the page script fills it in.
	 *
	 * @param array $available   Available integration rows.
	 * @param array $unavailable Unavailable integration rows.
	 * @return array{available:int,unavailable:int,actions:int|null,off:int}
	 */
	private function summarize( array $available, array $unavailable ): array {
		$actions = 0;
		$known   = true;
		$off     = 0;

		foreach ( $available as $row ) {
			if ( ! $row['enabled'] ) {
				++$off;
			}

			if ( null === $row['entry'] ) {
				$known = false;
				continue;
			}

			$actions += count( $row['entry']['actions'] );

			foreach ( $row['entry']['actions'] as $action ) {
				if ( $row['enabled'] && ! $action['enabled'] && ! $action['locked'] ) {
					++$off;
				}
			}
		}

		return array(
			'available'   => count( $available ),
			'unavailable' => count( $unavailable ),
			'actions'     => $known ? $actions : null,
			'off'         => $off,
		);
	}

	/**
	 * Whether the current user may change MCP abilities.
	 *
	 * @return bool
	 */
	private function can_manage(): bool {
		return current_user_can( automator_get_admin_capability() ) // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Automator settings capability is configurable.
		&& current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current request renders the abilities sub-tab.
	 *
	 * @return bool
	 */
	private function is_abilities_page(): bool {
		return AUTOMATOR_POST_TYPE_RECIPE === automator_filter_input( 'post_type' )
		&& 'uncanny-automator-config' === automator_filter_input( 'page' )
		&& 'model-context-protocol' === automator_filter_input( 'tab' )
		&& 'automator-abilities' === automator_filter_input( 'model-context-protocol' );
	}

	/**
	 * Read a sanitized string from the POST body.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function get_post_string( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce after reading it through this helper.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce before mutation.
	}

	/**
	 * Map an error code to a message safe to show an administrator.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	private function get_error_message( string $code ): string {
		switch ( $code ) {
			case 'mcp_abilities_forbidden':
				return esc_html__( 'You do not have permission to manage these settings. Contact a site administrator.', 'uncanny-automator' );
			case 'mcp_abilities_invalid_nonce':
				return esc_html__( 'The security check failed. Refresh the page and try again.', 'uncanny-automator' );
			case 'mcp_abilities_unknown':
				return esc_html__( 'That item cannot be switched here. Refresh the page.', 'uncanny-automator' );
			case 'mcp_abilities_catalog_failed':
				return esc_html__( 'The action list could not be loaded. Refresh the page to try again.', 'uncanny-automator' );
			case 'mcp_abilities_toggle_unknown':
				return esc_html__( 'The saved state could not be confirmed. Refresh before making another change.', 'uncanny-automator' );
		}

		return esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' );
	}
}

new Admin_Settings_Model_Context_Protocol_Automator_Abilities();
