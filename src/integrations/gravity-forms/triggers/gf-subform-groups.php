<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class GF_SUBFORM_GROUPS
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_GROUPS extends \Uncanny_Automator\Recipe\Trigger {

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

	private $gf;
	private $legacy_tokens;

	public function requirements_met() {
		return defined( 'UNCANNY_GROUPS_VERSION' ) && defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf            = array_shift( $this->dependencies );
		$this->legacy_tokens = array_shift( $this->dependencies );

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

		if ( ! $this->is_correct_group( $trigger, $form, $entry ) ) {
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

		if ( intval( $form['id'] ) !== $selected_form_id ) {
			return false;
		}

		if ( ! \Uncanny_Automator\Gravity_Forms_Helpers::is_uncanny_group_field_exist( $form['fields'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * is_correct_group
	 *
	 * @param  array $trigger
	 * @param  array $form
	 * @param  array $entry
	 * @return bool
	 */
	public function is_correct_group( $trigger, $form, $entry ) {

		$selected_group = intval( $trigger['meta'][ self::TRIGGER_META . '_GROUPS' ] );

		if ( -1 === $selected_group ) {
			return true;
		}

		$group_id = $this->get_group_id( $form, $entry );

		if ( $group_id !== $selected_group ) {
			return false;
		}

		return true;
	}

	/**
	 * get_group_id
	 *
	 * @param  array $form
	 * @param  array $entry
	 * @return int
	 */
	public function get_group_id( $form, $entry ) {

		foreach ( $form['fields'] as $field ) {
			if ( self::UO_GROUP_FIELD_TYPE === $field->get_input_type() ) {
				$group_key = rgar( $entry, $field->id );
			}
		}

		if ( empty( $group_key ) ) {
			return;
		}

		$group_ld_id = Automator()->helpers->recipe->gravity_forms->options->get_ld_group_id_from_gf_entry( $group_key );
		$group_id    = Automator()->helpers->recipe->gravity_forms->options->get_ld_group_id( $group_ld_id );

		return intval( $group_id );
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

		$group_id = $this->get_group_id( $form, $entry );

		$tokens = array(
			self::TRIGGER_META . '_ID'               => $form['id'],
			self::TRIGGER_META . '_GROUPS'           => get_the_title( $group_id ),
			self::TRIGGER_META . '_GROUPS_ID'        => $group_id,
			self::TRIGGER_META . '_GROUPS_URL'       => get_the_permalink( $group_id ),
			self::TRIGGER_META . '_GROUPS_URL'       => get_the_permalink( $group_id ),
			self::TRIGGER_META . '_GROUPS_THUMB_ID'  => get_post_thumbnail_id( $group_id ),
			self::TRIGGER_META . '_GROUPS_THUMB_URL' => get_the_post_thumbnail_url( $group_id ),
		);

		return $tokens;
	}
}

