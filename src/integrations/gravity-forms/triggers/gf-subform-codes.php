<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class GF_SUBFORM_CODES
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_CODES extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * The UncannyCodes field type for Gravity forms.
	 *
	 * @var string.
	 */
	const UO_CODES_FIELD_TYPE = 'uncanny_enrollment_code';
	/**
	 * Requirements met.
	 *
	 * @return mixed
	 */
	private $gf;

	/**
	 * requirements_met
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'UNCANNY_LEARNDASH_CODES_VERSION' );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );
		$this->set_trigger_code( 'GF_SUBFORM_CODES' );
		$this->set_trigger_meta( 'GF_SUBFORM_CODES_METADATA' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );
		$this->set_sentence(
			sprintf(
			/* translators: 1: Form name 2: Codes batch name */
				esc_attr_x( 'A user submits {{a form:%1$s}} with a code from {{a specific batch:%2$s}}', 'Gravity Forms', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				sprintf( '%s_CODES', $this->get_trigger_meta() )
			)
		);

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr_x( 'A user submits {{a form}} with a code from {{a specific batch}}', 'Gravity Forms', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( 'gform_after_submission' );
		$this->set_action_args_count( 2 );
	}

	/**
	 * @return array
	 *
	 * We are leaving this in a legacy form for now because the uncanny codes integration must be converted to the new framework first.
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( esc_attr_x( 'Form', 'Gravity Forms', 'uncanny-automator' ), $this->get_trigger_meta(), array( 'uncanny_code_specific' => true ) ),
					Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch( esc_attr_x( 'Batch', 'Gravity Forms', 'uncanny-automator' ), sprintf( '%s_CODES', $this->get_trigger_meta() ), true ),
				),
			)
		);
	}

	/**
	 * define_tokens
	 *
	 * @param  array $trigger
	 * @param  array $tokens
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$form_tokens  = $this->gf->tokens->possible_tokens->form_tokens( $form_id );
		$entry_tokens = $this->gf->tokens->possible_tokens->entry_tokens( 'GFENTRYTOKENS' );

		$tokens = array_merge( $tokens, $form_tokens, $entry_tokens );

		// Note that some tokens are defined by the get_all_code_batch method as "relevant_tokens"
		// i.e. UNCANNYCODESBATCHEXPIRY

		return $tokens;
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
	 * @param array $trigger
	 * @param array $form
	 *
	 * @return bool
	 */
	public function is_correct_form( $trigger, $form ) {

		$selected_form_id = intval( $trigger['meta'][ $this->trigger_meta ] );

		if ( intval( '-1' ) === intval( $selected_form_id ) ) {
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
	 * @param array $trigger
	 * @param array $form
	 * @param array $entry
	 *
	 * @return bool
	 */
	public function is_correct_batch( $trigger, $form, $entry ) {

		$selected_code_batch = intval( $trigger['meta'][ $this->trigger_meta . '_CODES' ] );

		if ( intval( '-1' ) === intval( $selected_code_batch ) ) {
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
	 * @param array $form
	 * @param array $entry
	 *
	 * @return int
	 */
	public function get_batch( $form, $entry ) {

		$code_fields = self::get_code_fields( $entry, $form );

		if ( empty( $code_fields ) ) {
			return false;
		}

		$code_field = array_shift( $code_fields );

		if ( empty( $code_field ) ) {
			return false;
		}

		$batch = self::get_batch_by_value( $code_field, $entry );

		return intval( $batch->ID );
	}

	/**
	 * get_batch_expiration
	 *
	 * @param int $batch_id
	 *
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
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $entry, $form ) = $hook_args;

		$entry_tokens = $this->gf->tokens->parser->parsed_entry_tokens( $entry );

		$this->save_tokens( 'GFENTRYTOKENS', $entry_tokens );

		$batch = $this->get_batch( $form, $entry );

		$tokens = array(
			'GF_SUBFORM_CODES_METADATA_CODES' => $batch,
			'UNCANNYCODESBATCHEXPIRY'         => $this->get_batch_expiration( $batch ),
			'CODE_BATCH_ID'                   => $batch,
		);

		$fields_tokens = $this->gf->tokens->parser->parsed_fields_tokens( $form, $entry );

		return $tokens + $fields_tokens;
	}

	/**
	 * Retrieves all forms as option fields.
	 *
	 * @return array The list of option fields from Gravity forms.
	 */
	public function get_forms_as_options( $add_any = false ) {

		if ( ! class_exists( '\GFAPI' ) || ! is_admin() ) {

			return array();

		}

		$forms = \GFAPI::get_forms();

		$options = array();

		if ( true === $add_any ) {
			$options[- 1] = esc_html_x( 'Any form', 'Gravity Forms', 'uncanny-automator' );
		}

		foreach ( $forms as $form ) {
			if ( $this->uncanny_codes_field_exists( $form['fields'] ) ) {
				$options[ absint( $form['id'] ) ] = $form['title'];
			}
		}

		return $options;
	}

	/**
	 * @param $forms
	 *
	 * @return mixed
	 */
	public function uncanny_codes_field_exists( $fields ) {

		foreach ( $fields as $field ) {
			if ( self::UO_CODES_FIELD_TYPE === $field->type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all code fields via `gform_after_submission` action hook.
	 *
	 * @return array The code fields.
	 */
	public static function get_code_fields( $entry, $form ) {

		// Get all the codes field.
		$uo_codes_fields = array_filter(
			$form['fields'],
			function ( $field ) use ( $entry ) {
				return self::UO_CODES_FIELD_TYPE === $field->type && ! empty( $entry[ $field->id ] );
			}
		);

		return $uo_codes_fields;
	}

	/**
	 * Get the batch object by code value.
	 *
	 * @param $code_field The code field entry inside Gravity forms object.
	 * @param $entry The GF entry passed from `gform_after_submission` action
	 *     hook.
	 *
	 * @return object The batch.
	 */
	public static function get_batch_by_value( $code_field, $entry ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uncanny_codes_codes as tbl_codes
				INNER JOIN {$wpdb->prefix}uncanny_codes_groups as tbl_batch
				WHERE tbl_codes.code_group = tbl_batch.ID
				AND tbl_codes.code = %s",
				$entry[ $code_field->id ]
			)
		);
	}
}
