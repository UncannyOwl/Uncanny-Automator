<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class GF_SUBFORM_GROUPS
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_GROUPS {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'GF_SUBFORM_GROUPS';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'GF_SUBFORM_GROUPS_METADATA';

	/**
	 * The UncannyGroups field type for Gravity forms.
	 *
	 * @var string.
	 */
	const UO_GROUP_FIELD_TYPE = 'ulgm_code';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! defined( 'UNCANNY_GROUPS_VERSION' ) || ! defined( 'LEARNDASH_VERSION' ) ) {
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
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );
		$this->set_sentence(
			sprintf(
			/* Translators: Trigger sentence */
				esc_attr__( '{{A form:%1$s}} is submitted with a key from {{a specific group:%2$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				sprintf( '%s_GROUPS', $this->get_trigger_meta() )
			)
		);
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( '{{A form}} is submitted with a key from {{a specific group}}', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( 'gform_after_submission' );
		$this->set_action_args_count( 2 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( esc_attr__( 'Form', 'uncanny-automator' ), $this->get_trigger_meta(), array( 'uncanny_groups_specific' => true ), true ),
					Automator()->helpers->recipe->learndash->options->all_ld_groups( esc_attr__( 'Group', 'uncanny-automator' ), sprintf( '%s_GROUPS', $this->get_trigger_meta() ), true ),
				),
			)
		);
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
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
		$uo_groups_fields = Gravity_Forms_Helpers::is_uncanny_group_field_exist( $fields );

		// True if there's a codes field and that code field has a value.
		return $uo_groups_fields;

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
	protected function validate_conditions( ...$args ) {

		list( $entry, $form ) = $args[0];

		foreach ( $form['fields'] as $field ) {
			if ( 'ulgm_code' === $field->get_input_type() ) {
				$group_key = rgar( $entry, $field->id );
			}
		}

		if ( empty( $group_key ) ) {
			return;
		}

		$group_ld_id = Automator()->helpers->recipe->gravity_forms->options->get_ld_group_id_from_gf_entry( $group_key );
		$group_id    = Automator()->helpers->recipe->gravity_forms->options->get_ld_group_id( $group_ld_id );

		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.

		$data = $this->find_all( $this->trigger_recipes() )->where(
			array(
				$this->get_trigger_meta(),
				$this->get_trigger_meta() . '_GROUPS',
			)
		)->match(
			array(
				rgar( $form, 'id' ),
				$group_id,
			)
		)->format( array( 'absint', 'absint' ) )->get();

		return $data;

	}

}

