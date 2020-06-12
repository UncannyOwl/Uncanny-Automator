<?php

namespace Uncanny_Automator;

/**
 * Class WPFF_SUBFORM
 * @package Uncanny_Automator
 */
class WPFF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPFF';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPFFSUBFORM';
		$this->trigger_meta = 'WPFFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ninja Forms */
			'sentence'            => sprintf( __( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Ninja Forms */
			'select_option_name'  => __( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'fluentform_before_insert_submission',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpffform_submit' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wp_fluent_forms->options->list_wp_fluent_forms(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $inser_data
	 * @param $data
	 * @param $form
	 */
	public function wpffform_submit( $inser_data, $data, $form ) {
		global $uncanny_automator;

		if ( empty( $form ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form->id ),
			'user_id' => $user_id,
		];

		$result = $uncanny_automator->maybe_add_trigger_entry( $args, false );

		if ( $result ) {
			foreach ( $result as $r ) {
				if ( true === $r['result'] ) {
					if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
						//Saving form values in trigger log meta for token parsing!
						$wp_ff_args = [
							'trigger_id'     => (int) $r['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta,
							'user_id'        => $user_id,
							'trigger_log_id' => $r['args']['get_trigger_id'],
							'run_number'     => $r['args']['run_number'],
						];
						$uncanny_automator->helpers->recipe->wp_fluent_forms->extract_save_wp_fluent_form_fields( $data, $form, $wp_ff_args );
					}

					$uncanny_automator->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
