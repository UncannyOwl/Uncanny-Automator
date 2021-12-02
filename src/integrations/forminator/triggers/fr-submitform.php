<?php

namespace Uncanny_Automator;

/**
 * Class FR_SUBMITFORM
 *
 * @package Uncanny_Automator
 */
class FR_SUBMITFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FR';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'FRSUBMITFORM';
		$this->trigger_meta = 'FRFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/forminator/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Forminator */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Forminator */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'forminator_custom_form_submit_before_set_fields',
			'priority'            => 100,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'fr_submit_form' ),
			'options'             => array(
				Automator()->helpers->recipe->forminator->options->all_forminator_forms( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param int $form_id submitted form id.
	 * @param array $response response array.
	 * @param       $method
	 */
	public function fr_submit_form( $entry, $form_id, $field_data_array ) {

		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return;
		}

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form_id ),
			'user_id' => intval( $user_id ),
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );

		//Adding an action to save contact form submission in trigger meta
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		do_action( 'automator_save_forminator_form_entry', $form_id, $recipes, $args );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					if ( ! empty( $field_data_array ) ) {
						$trigger_id     = (int) $result['args']['trigger_id'];
						$user_id        = (int) $user_id;
						$trigger_log_id = (int) $result['args']['get_trigger_id'];
						$run_number     = (int) $result['args']['run_number'];
						$meta_key       = (string) $this->trigger_meta;
						foreach ( $field_data_array as $entry_field ) {
							$field_meta = "{$trigger_id}:{$meta_key}:{$form_id}|" . $entry_field['name'];
							$insert     = array(
								'user_id'        => $user_id,
								'trigger_id'     => $trigger_id,
								'trigger_log_id' => $trigger_log_id,
								'meta_key'       => $field_meta,
								'meta_value'     => maybe_serialize( $entry_field['value'] ),
								'run_number'     => $run_number,
							);
							Automator()->insert_trigger_meta( $insert );
						}
					}
					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
