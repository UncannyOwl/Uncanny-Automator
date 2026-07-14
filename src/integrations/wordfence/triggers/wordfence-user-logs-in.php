<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_User_Logs_In
 *
 * Matches the central `wordfence_security_event` hook with $eventType of
 * 'adminLogin' / 'nonAdminLogin' (and their '*NewLocation' variants for a new
 * device). Selectors narrow by admin vs non-admin and by new-device. The
 * logged-in user is resolved from the event username and bound to the run.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 *
 * @property Wordfence_Helpers $item_helpers
 */
class Wordfence_User_Logs_In extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * The user resolved from the event username in validate(), reused in
	 * hydrate_tokens() to avoid a second get_user_by() query per fire.
	 *
	 * @var \WP_User|false|null
	 */
	private $resolved_user = null;

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'WORDFENCE_USER_LOGS_IN', 'WORDFENCE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WORDFENCE_LOGIN_TYPE' )
			->hook( 'wordfence_security_event', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: User type (admin/non-admin).
				esc_html_x( 'An {{admin/non-admin:%1$s}} user logs in', 'Wordfence Security', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An {{admin/non-admin}} user logs in', 'Wordfence Security', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'User', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'relevant_tokens' => array(),
				'options'         => array(
					array(
						'value' => Wordfence_Helpers::ANY,
						'text'  => esc_html_x( 'any', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'admin',
						'text'  => esc_html_x( 'admin', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'non-admin',
						'text'  => esc_html_x( 'non-admin', 'Wordfence Security', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'     => 'WORDFENCE_LOGIN_NEW_DEVICE',
				'label'           => esc_html_x( 'New device only', 'Wordfence Security', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'relevant_tokens' => array(),
				'options'         => array(
					array(
						'value' => Wordfence_Helpers::ANY,
						'text'  => esc_html_x( 'Any', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'yes',
						'text'  => esc_html_x( 'Yes', 'Wordfence Security', 'uncanny-automator' ),
					),
					array(
						'value' => 'no',
						'text'  => esc_html_x( 'No', 'Wordfence Security', 'uncanny-automator' ),
					),
				),
			),
		);
	}

	/**
	 * Validate trigger — a login event matching the user-type / new-device selection.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';

		$login_events = array( 'adminLogin', 'adminLoginNewLocation', 'nonAdminLogin', 'nonAdminLoginNewLocation' );
		if ( ! in_array( $event_type, $login_events, true ) ) {
			return false;
		}

		$is_admin      = ( 0 === strpos( $event_type, 'admin' ) );
		$is_new_device = ( false !== strpos( $event_type, 'NewLocation' ) );

		$selected_user   = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Wordfence_Helpers::ANY );
		$selected_device = (string) ( $trigger['meta']['WORDFENCE_LOGIN_NEW_DEVICE'] ?? Wordfence_Helpers::ANY );

		if ( 'admin' === $selected_user && ! $is_admin ) {
			return false;
		}
		if ( 'non-admin' === $selected_user && $is_admin ) {
			return false;
		}
		if ( 'yes' === $selected_device && ! $is_new_device ) {
			return false;
		}
		if ( 'no' === $selected_device && $is_new_device ) {
			return false;
		}

		$data                = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();
		$this->resolved_user = isset( $data['username'] ) ? get_user_by( 'login', $data['username'] ) : false;

		if ( $this->resolved_user instanceof \WP_User ) {
			$this->set_user_id( $this->resolved_user->ID );
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => 'WF_LOGIN_IP',
					'tokenName' => esc_html_x( 'IP address', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_LOGIN_IS_ADMIN',
					'tokenName' => esc_html_x( 'Is admin', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'WF_LOGIN_NEW_DEVICE',
					'tokenName' => esc_html_x( 'New device', 'Wordfence Security', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			),
			$this->item_helpers->get_user_token_definitions()
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$event_type = isset( $hook_args[0] ) ? (string) $hook_args[0] : '';
		$data       = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();

		$is_admin      = ( 0 === strpos( $event_type, 'admin' ) );
		$is_new_device = ( false !== strpos( $event_type, 'NewLocation' ) );

		// Reuse the user resolved in validate(); fall back if hydrate runs alone.
		$user = $this->resolved_user;
		if ( null === $user ) {
			$user = isset( $data['username'] ) ? get_user_by( 'login', $data['username'] ) : false;
		}

		$tokens = array(
			'WF_LOGIN_IP'         => isset( $data['ip'] ) ? (string) $data['ip'] : '',
			// Raw, non-localized values so text-comparison conditions are locale-safe.
			'WF_LOGIN_IS_ADMIN'   => $is_admin ? 'yes' : 'no',
			'WF_LOGIN_NEW_DEVICE' => $is_new_device ? 'yes' : 'no',
		);

		return array_merge( $tokens, $this->item_helpers->hydrate_user_tokens( $user ) );
	}
}
