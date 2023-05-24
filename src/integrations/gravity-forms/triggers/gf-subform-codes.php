<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class GF_SUBFORM_CODES
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_CODES extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'GF_SUBFORM_CODES';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'GF_SUBFORM_CODES_METADATA';

	/**
	 * The UncannyCodes field type for Gravity forms.
	 *
	 * @var string.
	 */
	const UO_CODES_FIELD_TYPE = 'uncanny_enrollment_code';

	private $gf;

	public function requirements_met() {
		return defined( 'UNCANNY_LEARNDASH_CODES_VERSION' );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_login_required( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );
		$this->set_sentence(
			sprintf(
			/* Translators: Trigger sentence */
				esc_attr__( 'A user submits {{a form:%1$s}} with a code from {{a specific batch:%2$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				sprintf( '%s_CODES', $this->get_trigger_meta() )
			)
		);

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A user submits {{a form}} with a code from {{a specific batch}}', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( 'gform_after_submission' );
		$this->set_action_args_count( 2 );

	}

	/**
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( esc_attr__( 'Form', 'uncanny-automator' ), $this->get_trigger_meta(), array( 'uncanny_code_specific' => true ) ),
					Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch( esc_attr__( 'Batch', 'uncanny-automator' ), sprintf( '%s_CODES', $this->get_trigger_meta() ), true ),
				),
			)
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $entry, $form ) = $hook_args;

		if ( ! $this->is_correct_form( $trigger, $form ) ) {
			return false;
		}

		if ( ! $this->is_correct_batch( $trigger, $form, $entry ) ) {
			return false;
		}

		return true;
	}

	/**
	 * is_correct_form
	 *
	 * @param  array $trigger
	 * @param  array $form
	 * @return bool
	 */
	public function is_correct_form( $trigger, $form ) {

		$selected_form_id = intval( $trigger['meta'][ self::TRIGGER_META ] );

		if ( -1 === $selected_form_id ) {
			return true;
		}

		if ( intval( $form['id'] ) === $selected_form_id ) {
			return true;
		}

		return false;
	}

	/**
	 * is_correct_batch
	 *
	 * @param  array $trigger
	 * @param  array $form
	 * @param  array $entry
	 * @return bool
	 */
	public function is_correct_batch( $trigger, $form, $entry ) {

		$selected_code_batch = intval( $trigger['meta'][ self::TRIGGER_META . '_CODES' ] );

		if ( -1 === $selected_code_batch ) {
			return true;
		}

		$batch = $this->get_batch( $form, $entry );

		if ( $batch !== $selected_code_batch ) {
			return false;
		}

		return true;
	}

	/**
	 * get_batch
	 *
	 * @param  array $form
	 * @param  array $entry
	 * @return int
	 */
	public function get_batch( $form, $entry ) {

		$code_fields = \Uncanny_Automator\Gravity_Forms_Helpers::get_code_fields( $entry, $form );

		if ( empty( $code_fields ) ) {
			return false;
		}

		$code_field = array_shift( $code_fields );

		if ( empty( $code_field ) ) {
			return false;
		}

		$batch = \Uncanny_Automator\Gravity_Forms_Helpers::get_batch_by_value( $code_field, $entry );

		return intval( $batch );
	}

	/**
	 * get_batch_expiration
	 *
	 * @param  int $batch_id
	 * @return string
	 */
	public function get_batch_expiration( $batch_id ) {

		global $wpdb;

		$expiry_date      = $wpdb->get_var( $wpdb->prepare( "SELECT expire_date FROM `{$wpdb->prefix}uncanny_codes_groups` WHERE ID = %d", $batch_id ) );
		$expiry_timestamp = strtotime( $expiry_date );

		// Check if the date is in future to filter out empty dates
		if ( $expiry_timestamp > time() ) {
			// Get the format selected in general WP settings
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );

			// Return the formattted time according to the selected time zone
			$value = date_i18n( "$date_format $time_format", strtotime( $expiry_date ) );

			return $value;
		}

		return '';
	}

	/**
	 * hydrate_tokens
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $entry, $form ) = $hook_args;

		$this->gf->tokens->save_legacy_trigger_tokens( $this->trigger_records, $entry, $form );

		$batch = $this->get_batch( $form, $entry );

		$tokens = array(
			'GF_SUBFORM_CODES_METADATA_CODES' => $batch,
			'UNCANNYCODESBATCHEXPIRY'         => $this->get_batch_expiration( $batch ),
		);

		return $tokens;
	}
}
