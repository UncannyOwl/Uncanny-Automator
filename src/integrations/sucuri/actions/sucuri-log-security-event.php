<?php

namespace Uncanny_Automator\Integrations\Sucuri;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Sucuri_Log_Security_Event
 *
 * Writes a message to Sucuri's audit log at one of its four severities, via
 * `SucuriScanEvent::report{Info,Warning,Error,Critical}Event( $message )`
 * (src/event.lib.php:535-568). Sucuri stamps the current user and remote IP
 * onto the entry, queues it locally, and flushes the queue to the Sucuri API on
 * the next cron run when an API key is registered.
 *
 * @package Uncanny_Automator\Integrations\Sucuri
 *
 * @property Sucuri_Helpers $item_helpers
 */
class Sucuri_Log_Security_Event extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SUCURI' );
		$this->set_requires_user( false );
		$this->set_action_code( 'SUCURI_LOG_SECURITY_EVENT' );
		$this->set_action_meta( 'SUCURI_EVENT_SEVERITY' );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Severity */
				esc_html_x( 'Log a security event with {{a severity:%1$s}}', 'Sucuri Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Log a security event with {{a severity}}', 'Sucuri Security', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Severity', 'Sucuri Security', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'severities_strict' ),
			),
			array(
				'option_code'      => 'SUCURI_EVENT_MESSAGE',
				'label'            => esc_html_x( 'Message', 'Sucuri Security', 'uncanny-automator' ),
				'input_type'       => 'textarea',
				'required'         => true,
				'supports_tokens'  => true,
				'supports_tinymce' => false,
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SUCURI_LOGGED_MESSAGE'  => array(
				'name' => esc_html_x( 'Logged message', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUCURI_LOGGED_SEVERITY' => array(
				'name' => esc_html_x( 'Severity', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\SucuriScanEvent' ) ) {
			$this->add_log_error( esc_html_x( 'Sucuri Security is not active.', 'Sucuri Security', 'uncanny-automator' ) );
			return false;
		}

		$severity = strtolower( trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) ) );
		$message  = trim( wp_strip_all_tags( (string) ( $parsed['SUCURI_EVENT_MESSAGE'] ?? '' ) ) );

		if ( '' === $message ) {
			$this->add_log_error( esc_html_x( 'A message is required.', 'Sucuri Security', 'uncanny-automator' ) );
			return false;
		}

		$severities = wp_list_pluck( $this->item_helpers->get_severity_options(), 'value' );

		if ( ! in_array( $severity, $severities, true ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: The severity value saved on the action */
					esc_html_x( 'Unsupported severity: [%s].', 'Sucuri Security', 'uncanny-automator' ),
					$severity
				)
			);
			return false;
		}

		// The severity is allowlisted above and Sucuri's queue write cannot
		// report failure (see Sucuri_Helpers::report_event()), so there is no
		// failure path left to branch on here.
		$this->item_helpers->report_event( $severity, $message );

		$this->hydrate_tokens(
			array(
				'SUCURI_LOGGED_MESSAGE'  => $message,
				'SUCURI_LOGGED_SEVERITY' => $severity,
			)
		);

		return true;
	}
}
