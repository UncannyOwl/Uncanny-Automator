<?php

namespace Uncanny_Automator\Integrations\Wp_Activity_Log;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Wp_Activity_Log_Event_Severity
 *
 * Fires when WP Activity Log records an event of the selected severity.
 *
 * @package Uncanny_Automator\Integrations\Wp_Activity_Log
 *
 * @property Wp_Activity_Log_Helpers $item_helpers
 */
class Wp_Activity_Log_Event_Severity extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_ACTIVITY_LOG_EVENT_SEVERITY', 'WP_ACTIVITY_LOG' )
			->trigger_meta( 'WPAL_SEVERITY' )
			->trigger_type( 'anonymous' )
			->hook( 'wsal_logged_alert', 10, 5 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Severity.
				esc_html_x( 'An event with {{a severity:%1$s}} is logged', 'WP Activity Log', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An event with {{a severity}} is logged', 'WP Activity Log', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Severity', 'WP Activity Log', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'severities' ),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_event_tokens() );
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$data = $hook_args[2] ?? array();

		if ( ! is_array( $data ) || ! isset( $data['Severity'] ) ) {
			return false;
		}

		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? Wp_Activity_Log_Helpers::ANY;

		if ( Wp_Activity_Log_Helpers::ANY !== (string) $selected && intval( $selected ) !== (int) $data['Severity'] ) {
			return false;
		}

		$this->set_user_id( isset( $data['CurrentUserID'] ) ? (int) $data['CurrentUserID'] : 0 );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( , $type, $data, $date, $site_id ) = array_pad( $hook_args, 5, null );

		return $this->item_helpers->hydrate_event_tokens( $type, $data, $date, $site_id );
	}
}
