<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Plan\Services\License\License_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the base class file
require_once __DIR__ . '/../trait-tooltip-notification.php';

/**
 * Class Automator_Tooltip_48hr
 *
 * The tooltip beside the Automator menu that asks the owner to connect an AI:
 * 48 hours after installation on a site with no AI connected, Uncanny Agent
 * off and no recipe, away from the screens that already carry the connect
 * block. Put away, it comes back once more when the install is ten days old,
 * then never again.
 *
 * The class name is the one the loader registers; the tooltip asked for a
 * recipe before 8.0.0.
 */
class Automator_Tooltip_48hr {
	use Automator_Tooltip_Trait;

	/**
	 * How long after installation the tooltip first shows (in seconds).
	 *
	 * @var int
	 */
	const FIRST_SHOW_AFTER = 48 * HOUR_IN_SECONDS;

	/**
	 * How old the install must be for the one return visit (in seconds).
	 *
	 * @var int
	 */
	const SECOND_SHOW_AFTER = 10 * DAY_IN_SECONDS;

	/**
	 * Key, in the user's tooltip visibility meta, of when the tooltip came
	 * back after its first dismissal.
	 *
	 * @var string
	 */
	const RESHOWN_KEY = 'connect-ai-reminder-reshown';

	/**
	 * Where the plugin opened the hosted app from, for its logs.
	 *
	 * @var string
	 */
	const CONNECT_REF = 'tooltip_connect_ai';

	/**
	 * Automator_Tooltip_48hr constructor.
	 */
	public function __construct() {
		// Set the tooltip ID
		$this->tooltip_id = 'connect-ai-reminder';

		// Set the parent selector
		$this->parent_selector = '#menu-posts-uo-recipe';

		// Set the element position
		// 'beforebegin': Before the targetElement itself.
		// 'afterbegin': Just inside the targetElement, before its first child.
		// 'beforeend': Just inside the targetElement, after its first child.
		// 'afterend': After the targetElement itself.
		$this->element_position = 'beforebegin';

		// Init the tooltip
		$this->init();
	}

	/**
	 * Determine if the tooltip should be shown based on the parent logic and additional checks.
	 *
	 * @return bool True if the tooltip should be shown, false otherwise.
	 */
	protected function should_display_tooltip() {

		if ( $this->is_connect_screen() ) {
			return false;
		}

		if ( ! $this->installed_for( self::FIRST_SHOW_AFTER ) ) {
			return false;
		}

		if ( Ai_Connection_State::is_connected() ) {
			return false;
		}

		if ( $this->is_agent_enabled() ) {
			return false;
		}

		return ! $this->has_recipe();
	}

	/**
	 * The tooltip's words and link.
	 *
	 * The link opens the hosted app on a site with a linked account, in a new
	 * tab like every other Connect your AI entry, and the wizard's account
	 * screen on a site without one.
	 *
	 * @return array<string,string>
	 */
	protected function get_tooltip_content() {

		$linked = $this->is_account_linked();

		return array(
			'dashicon'    => 'megaphone',
			'title'       => esc_html__( 'Connect your AI to this site', 'uncanny-automator' ),
			'description' => esc_html__( 'Manage and automate this site with ChatGPT, Claude, Gemini or any AI. Connect in 60 seconds.', 'uncanny-automator' ),
			'cta_label'   => esc_html__( 'Connect your AI', 'uncanny-automator' ),
			'cta_url'     => $linked ? Hosted_App::connect_url( self::CONNECT_REF ) : $this->get_wizard_account_url(),
			'cta_target'  => $linked ? '_blank' : '',
		);
	}

	/**
	 * Once dismissed, the tooltip returns one time, when the install is ten
	 * days old, and stays until it is dismissed again. A second dismissal
	 * ends it.
	 *
	 * @param int   $dismissed_at        When the user last put the tooltip away.
	 * @param array $tooltips_visibility The user's tooltip visibility meta.
	 *
	 * @return bool
	 */
	protected function can_reshow( $dismissed_at, $tooltips_visibility ) {

		$reshown_at = isset( $tooltips_visibility[ self::RESHOWN_KEY ] ) ? (int) $tooltips_visibility[ self::RESHOWN_KEY ] : 0;

		if ( $reshown_at > 0 ) {
			// Still up from its return, or put away for good.
			return $dismissed_at <= $reshown_at;
		}

		return $this->installed_for( self::SECOND_SHOW_AFTER );
	}

	/**
	 * Records the return visit the first time the tooltip shows after a dismissal.
	 *
	 * @param int   $user_id             The user who sees it.
	 * @param int   $dismissed_at        When the user last put the tooltip away; 0 when never.
	 * @param array $tooltips_visibility The user's tooltip visibility meta.
	 *
	 * @return void
	 */
	protected function before_display( $user_id, $dismissed_at, $tooltips_visibility ) {

		if ( $dismissed_at <= 0 || isset( $tooltips_visibility[ self::RESHOWN_KEY ] ) ) {
			return;
		}

		$tooltips_visibility[ self::RESHOWN_KEY ] = time();
		update_user_meta( $user_id, 'automator_tooltips_visibility', $tooltips_visibility );
	}

	/**
	 * Whether the plugin was installed at least this long ago.
	 *
	 * @param int $seconds The age to reach.
	 *
	 * @return bool False while the install date is unknown.
	 */
	private function installed_for( $seconds ) {

		$automator_over_time = automator_get_option( 'automator_over_time' );
		$installation_time   = isset( $automator_over_time['installed_date'] ) ? (int) $automator_over_time['installed_date'] : 0;

		return $installation_time > 0 && ( time() - $installation_time ) > $seconds;
	}

	/**
	 * Whether the current screen already carries the connect block: the setup
	 * wizard, the Automator Dashboard or the MCP settings tab.
	 *
	 * @return bool
	 */
	private function is_connect_screen() {

		$page = (string) automator_filter_input( 'page' );

		if ( in_array( $page, array( 'uncanny-automator-setup-wizard', 'uncanny-automator-dashboard' ), true ) ) {
			return true;
		}

		return 'uncanny-automator-config' === $page && 'model-context-protocol' === (string) automator_filter_input( 'tab' );
	}

	/**
	 * Whether Uncanny Agent is switched on in its settings.
	 *
	 * @return bool
	 */
	private function is_agent_enabled() {

		if ( class_exists( Admin_Settings_Uncanny_Agent_General::class ) ) {
			return (bool) Admin_Settings_Uncanny_Agent_General::get_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY );
		}

		// The settings class is loaded with the Settings page; elsewhere, read its option. Off until a choice is saved.
		$settings = automator_get_option( 'automator_uncanny_agent_settings', array() );

		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}

	/**
	 * Whether a recipe (post type `uo-recipe`) exists, in any status.
	 *
	 * @return bool
	 */
	private function has_recipe() {

		$query = new \WP_Query(
			array(
				'post_type'      => AUTOMATOR_POST_TYPE_RECIPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return $query->have_posts();
	}

	/**
	 * Whether a free or Pro account is linked to the site.
	 *
	 * @return bool
	 */
	private function is_account_linked() {

		try {
			return '' !== ( new License_Service() )->get_license_key();
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	/**
	 * The wizard's account screen.
	 *
	 * @return string
	 */
	private function get_wizard_account_url() {

		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-setup-wizard',
			),
			admin_url( 'edit.php' )
		);
	}
}
