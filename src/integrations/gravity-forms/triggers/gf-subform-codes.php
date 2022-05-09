<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class GF_SUBFORM_CODES
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_CODES {

	use Recipe\Triggers;

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

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! defined( 'UNCANNY_LEARNDASH_CODES_VERSION' ) ) {
			return;
		}
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

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
		$this->set_options(
			array(
				Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( esc_attr__( 'Form', 'uncanny-automator' ), $this->get_trigger_meta(), array( 'uncanny_code_specific' => true ) ),
				Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch( esc_attr__( 'Batch', 'uncanny-automator' ), sprintf( '%s_CODES', $this->get_trigger_meta() ), true ),
			)
		);
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {

		$args = array_shift( $args );

		if ( empty( $args[0] ) || empty( $args[1] ) ) {
			return false;
		}

		$entry  = $args[0];
		$form   = $args[1];
		$fields = $form['fields'];

		// Get all the codes field.
		$uo_codes_fields = Gravity_Forms_Helpers::is_uncanny_code_field_exist( $fields );

		// True if theres a codes field and that code field has a value.
		return $uo_codes_fields;

	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}


	/**
	 * Validate if trigger matches the condition.
	 *
	 * @param $args
	 *
	 * @return array
	 */
	protected function validate_conditions( $args ) {

		$matched_recipe_ids = array();

		list ( $entry, $form ) = $args;

		if ( empty( $entry ) || empty( $form ) ) {
			return $matched_recipe_ids;
		}

		$recipes = $this->trigger_recipes();

		if ( empty( $recipes ) ) {
			return $matched_recipe_ids;
		}

		$required_form  = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_batch = Automator()->get->meta_from_recipes( $recipes, sprintf( '%s_CODES', $this->trigger_meta ) );

		if ( empty( $required_form ) ) {
			return $matched_recipe_ids;
		}

		$code_fields = Gravity_Forms_Helpers::get_code_fields( $entry, $form );

		// Bailout if there are no UncannyCodes fields in the form.
		if ( empty( $code_fields ) ) {
			return $matched_recipe_ids;
		}

		$form_id = $form['id'];

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_form[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_form[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}

				$code_field = array_shift( $code_fields );
				if ( empty( $code_field ) || null === $code_field ) {
					continue;
				}
				$batch = Gravity_Forms_Helpers::get_batch_by_value( $code_field, $entry );

				if (
					absint( $form_id ) === absint( $required_form[ $recipe_id ][ $trigger_id ] ) &&
					(
						intval( '-1' ) === intval( $required_batch[ $recipe_id ][ $trigger_id ] ) ||
						absint( $batch->code_group ) === absint( $required_batch[ $recipe_id ][ $trigger_id ] )
					)
				) {
					$matched_recipe_ids[ $recipe_id ] = $trigger_id;
				}
			}
		}

		return $matched_recipe_ids;

	}
}
