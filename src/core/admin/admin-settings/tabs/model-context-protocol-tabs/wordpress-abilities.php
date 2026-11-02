<?php
/**
 * MCP settings: WordPress abilities sub-tab.
 *
 * Lists WordPress abilities by provider. Each provider keeps its existing
 * permission controls and defaults. Abilities are registered on every request,
 * so the page renders directly; no load-all request is needed here.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Abilities\Run_Ability_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Availability_Settings;
use WP_Error;

/**
 * Class Admin_Settings_Model_Context_Protocol_WordPress_Abilities
 */
class Admin_Settings_Model_Context_Protocol_WordPress_Abilities {

	/**
	 * AJAX action switching one ability on or off.
	 */
	public const TOGGLE_ACTION = 'automator_mcp_wp_abilities_toggle';

	/**
	 * Nonce action protecting the toggle request.
	 */
	public const NONCE_ACTION = 'automator_mcp_wp_abilities';

	/**
	 * Catalogue of registered abilities and their switch state.
	 *
	 * @var Ability_Catalog
	 */
	private $catalog;

	/** @var Admin_Settings_Model_Context_Protocol_Tools Native provider and its existing permission controls. */
	private $tools;

	/**
	 * @param Admin_Settings_Model_Context_Protocol_Tools $tools   Native Automator provider.
	 * @param Ability_Catalog|null                       $catalog Catalogue; defaults to the live registry and stored switches.
	 */
	public function __construct( Admin_Settings_Model_Context_Protocol_Tools $tools, ?Ability_Catalog $catalog = null ) {
		$this->tools   = $tools;
		$this->catalog = $catalog ?? new Ability_Catalog();

		add_filter( 'automator_settings_model_context_protocol_tabs', array( $this, 'create_tab' ), 34, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_action( 'wp_ajax_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
	}

	/**
	 * Register the sub-tab.
	 *
	 * @param array $tabs Registered sub-tabs.
	 * @return array
	 */
	public function create_tab( $tabs ) {
		$tabs['wordpress-abilities'] = (object) array(
			'name'     => esc_html__( 'WordPress abilities', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
			'icon'     => 'puzzle-piece',
		);

		return $tabs;
	}

	/**
	 * Render the sub-tab.
	 *
	 * @return void
	 */
	public function tab_output() {
		$groups     = array();
		$summary    = array(
			'abilities' => 0,
			'on'        => 0,
		);
		$supported  = Ability_Catalog::is_supported();
		$load_error = false;
		// Other providers use run_ability; Automator's native abilities require
		// rest_request. Each provider shows the notice for its required runner.
		$dispatcher_off = ( new Tool_Availability_Settings() )->is_disabled( Run_Ability_Tool::NAME );
		$tools_url      = Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'tools' );

		if ( $supported ) {
			try {
				$groups = $this->group_by_provider( $this->catalog->get_abilities() );
			} catch ( Throwable $throwable ) {
				error_log( sprintf( '[Automator MCP] WordPress abilities could not be listed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
				$load_error = true;
			}

			// Compose the native provider directly: even an undocumented WordPress
			// filter lets plugins inject unvalidated presentation rows. Extensions
			// register abilities through the WordPress catalog instead.
			$groups = $this->tools->add_ability_provider( $groups );
			usort(
				$groups,
				static function ( array $left, array $right ): int {
					return strcasecmp( $left['name'], $right['name'] );
				}
			);
			$summary = $this->summarize( $groups );
		}

		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/wordpress-abilities.php' );
	}

	/**
	 * Rows grouped by the plugin that registered them, in display order.
	 *
	 * @param array $rows Catalogue rows, already sorted by provider then label.
	 * @return array<int,array{slug:string,name:string,abilities:array}>
	 */
	public function group_by_provider( array $rows ): array {
		$groups = array();

		foreach ( $rows as $row ) {
			$slug = $row['provider']['slug'];

			if ( ! isset( $groups[ $slug ] ) ) {
				$groups[ $slug ] = array(
					'slug'      => $slug,
					'name'      => $row['provider']['name'],
					'abilities' => array(),
				);
			}

			$groups[ $slug ]['abilities'][] = $row;
		}

		return array_values( $groups );
	}

	/**
	 * Enqueue the switch script on this sub-tab only.
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
			'automator-mcp-wp-abilities',
			plugins_url(
				'src/core/admin/admin-settings/tabs/model-context-protocol-assets/wordpress-abilities.js',
				AUTOMATOR_BASE_FILE
			),
			array( 'uap-admin' ),
			Utilities::automator_get_version(),
			true
		);

		wp_localize_script(
			'automator-mcp-wp-abilities',
			'AutomatorMcpWpAbilities',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::TOGGLE_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'userId'  => get_current_user_id(),
				'strings' => array(
					'errorHeading'   => esc_html__( 'Check the ability setting', 'uncanny-automator' ),
					'unknownError'   => esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' ),
					'unknownOutcome' => esc_html__( 'The saved setting could not be confirmed. Refresh the page to check it.', 'uncanny-automator' ),
					'saving'         => esc_html__( 'Saving…', 'uncanny-automator' ),
					/* translators: %s Name of the ability that was switched on. */
					'savedOn'        => esc_html__( '%s is on.', 'uncanny-automator' ),
					/* translators: %s Name of the ability that was switched off. */
					'savedOff'       => esc_html__( '%s is off.', 'uncanny-automator' ),
					/* translators: %d Number of abilities switched on in a group. */
					'someOn'         => esc_html_x( '%d on', 'MCP WordPress abilities group state', 'uncanny-automator' ),
					/* translators: %d Number of abilities switched on across all groups. */
					'switchedOn'     => esc_html_x( '%d on', 'MCP WordPress abilities on count', 'uncanny-automator' ),
				),
			)
		);
	}

	/**
	 * AJAX: switch one ability on or off.
	 *
	 * @return void
	 */
	public function handle_toggle() {
		nocache_headers();

		try {
			$result = $this->process_toggle_request(
				$this->get_post_string( 'nonce' ),
				$this->get_post_string( 'ability' ),
				'1' === $this->get_post_string( 'enabled' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] WordPress ability toggle failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_wp_ability_toggle_unknown' );
		}

		if ( $result instanceof WP_Error ) {
			$status = in_array( $result->get_error_code(), array( 'mcp_wp_ability_forbidden', 'mcp_wp_ability_invalid_nonce' ), true ) ? 403 : 400;
			wp_send_json_error(
				array(
					'message'         => $this->get_error_message( $result->get_error_code() ),
					'outcome_unknown' => 'mcp_wp_ability_toggle_unknown' === $result->get_error_code(),
				),
				$status
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Validate a toggle request and persist it.
	 *
	 * @param string $nonce   Submitted nonce.
	 * @param string $ability Ability name.
	 * @param bool   $enabled True to expose it.
	 * @return array{ability:string,enabled:bool}|WP_Error
	 */
	private function process_toggle_request( string $nonce, string $ability, bool $enabled ) {
		if ( ! $this->can_manage() ) {
			return new WP_Error( 'mcp_wp_ability_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'mcp_wp_ability_invalid_nonce' );
		}

		if ( '' === $ability || ! $this->catalog->can_toggle( $ability ) ) {
			return new WP_Error( 'mcp_wp_ability_unknown' );
		}

		if ( ! $this->catalog->set_enabled( $ability, $enabled ) ) {
			return new WP_Error( 'mcp_wp_ability_toggle_failed' );
		}

		return array(
			'ability' => $ability,
			'enabled' => $enabled,
		);
	}

	/**
	 * Header numbers.
	 *
	 * WordPress core is a provider group, not a plugin, so groups are not counted.
	 *
	 * @param array $groups Grouped rows.
	 * @return array{abilities:int,on:int}
	 */
	private function summarize( array $groups ): array {
		$abilities = 0;
		$on        = 0;

		foreach ( $groups as $group ) {
			foreach ( $group['abilities'] as $row ) {
				++$abilities;

				if ( $row['eligible'] && $row['enabled'] ) {
					++$on;
				}
			}
		}

		return array(
			'abilities' => $abilities,
			'on'        => $on,
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
	 * Whether the current request renders this sub-tab.
	 *
	 * @return bool
	 */
	private function is_abilities_page(): bool {
		return AUTOMATOR_POST_TYPE_RECIPE === automator_filter_input( 'post_type' )
		&& 'uncanny-automator-config' === automator_filter_input( 'page' )
		&& 'model-context-protocol' === automator_filter_input( 'tab' )
		&& 'wordpress-abilities' === automator_filter_input( 'model-context-protocol' );
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
			case 'mcp_wp_ability_forbidden':
				return esc_html__( 'You do not have permission to manage these settings. Contact a site administrator.', 'uncanny-automator' );
			case 'mcp_wp_ability_invalid_nonce':
				return esc_html__( 'The security check failed. Refresh the page and try again.', 'uncanny-automator' );
			case 'mcp_wp_ability_unknown':
				return esc_html__( 'This ability is no longer available to switch here. Refresh the page.', 'uncanny-automator' );
			case 'mcp_wp_ability_toggle_unknown':
				return esc_html__( 'The saved setting could not be confirmed. Refresh the page to check it.', 'uncanny-automator' );
		}

		return esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' );
	}
}
