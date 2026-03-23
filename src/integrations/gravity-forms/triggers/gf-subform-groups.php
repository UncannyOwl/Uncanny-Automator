<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class GF_SUBFORM_GROUPS
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM_GROUPS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * The UncannyGroups field type for Gravity forms.
	 *
	 * @var string.
	 */
	const UO_GROUP_FIELD_TYPE = 'ulgm_code';

	private $gf;
	/**
	 * Requirements met.
	 *
	 * @return mixed
	 */
	private $legacy_tokens;

	/**
	 * requirements_met
	 *
	 * @return bool
	 */
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
		$this->set_trigger_code( 'GF_SUBFORM_GROUPS' );
		$this->set_trigger_meta( 'GF_SUBFORM_GROUPS_METADATA' );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );
		$this->set_sentence(
			sprintf(
			/* translators: 1: Form name 2: Group name */
				esc_attr_x( '{{A form:%1$s}} is submitted with a key from {{a specific group:%2$s}}', 'Gravity Forms', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				sprintf( '%s_GROUPS', $this->get_trigger_meta() )
			)
		);
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr_x( '{{A form}} is submitted with a key from {{a specific group}}', 'Gravity Forms', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( 'gform_after_submission' );
		$this->set_action_args_count( 2 );
	}

	/**
	 * @return array[]
	 *
	 * We are leaving this in a legacy form for now because the learndash integration must be converted to the new framework first.
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( esc_attr_x( 'Form', 'Gravity Forms', 'uncanny-automator' ), $this->get_trigger_meta(), array( 'uncanny_groups_specific' => true ), true ),
					Automator()->helpers->recipe->learndash->options->all_ld_groups( esc_attr_x( 'Group', 'Gravity Forms', 'uncanny-automator' ), sprintf( '%s_GROUPS', $this->get_trigger_meta() ), true ),
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

		$form_tokens  = $this->gf->tokens->possible_tokens->form_tokens( $form_id, $this->trigger_meta );
		$entry_tokens = $this->gf->tokens->possible_tokens->entry_tokens( 'GFENTRYTOKENS' );

		$tokens = array_merge( $tokens, $form_tokens, $entry_tokens );

		// Note that some tokens are defined by the all_ld_groups method as "relevant_tokens"
		// i.e. GF_SUBFORM_GROUPS_METADATA_GROUPS_THUMB_URL

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

		$selected_form_id = intval( $trigger['meta'][ $this->trigger_meta ] );

		if ( -1 === $selected_form_id ) {
			return true;
		}

		if ( intval( $form['id'] ) !== $selected_form_id ) {
			return false;
		}

		if ( ! $this->uncanny_group_field_exists( $form['fields'] ) ) {
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

		$selected_group = intval( $trigger['meta'][ $this->trigger_meta . '_GROUPS' ] );

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

		$group_ld_id = $this->get_ld_group_id_from_gf_entry( $group_key );
		$group_id    = $this->get_ld_group_id( $group_ld_id );

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

		$entry_tokens = $this->gf->tokens->parser->parsed_entry_tokens( $entry );
		$this->save_tokens( 'GFENTRYTOKENS', $entry_tokens );

		$group_id = $this->get_group_id( $form, $entry );

		$tokens = array(
			$this->trigger_meta . '_ID'               => $form['id'],
			$this->trigger_meta . '_GROUPS'           => get_the_title( $group_id ),
			$this->trigger_meta . '_GROUPS_ID'        => $group_id,
			$this->trigger_meta . '_GROUPS_URL'       => get_the_permalink( $group_id ),
			$this->trigger_meta . '_GROUPS_URL'       => get_the_permalink( $group_id ),
			$this->trigger_meta . '_GROUPS_THUMB_ID'  => get_post_thumbnail_id( $group_id ),
			$this->trigger_meta . '_GROUPS_THUMB_URL' => get_the_post_thumbnail_url( $group_id ),
		);

		$fields_tokens = $this->gf->tokens->parser->parsed_fields_tokens( $form, $entry );

		return $tokens + $fields_tokens;
	}

	/**
	 * Get all code fields via `gform_after_submission` action hook.
	 *
	 * @return array The code fields.
	 */
	public static function get_code_fields_for_groups( $entry, $form ) {

		// Get all the codes field.
		$uo_groups_fields = array_filter(
			$form['fields'],
			function ( $field ) use ( $entry ) {
				return GF_SUBFORM_GROUPS::UO_GROUP_FIELD_TYPE === $field->type && ! empty( $entry[ $field->id ] );
			}
		);

		return $uo_groups_fields;
	}

	/**
	 * Get group_id from the group_key.
	 *
	 * @return integer group id.
	 */
	public function get_ld_group_id_from_gf_entry( $group_key ) {

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `group_id` FROM {$wpdb->prefix}ulgm_group_codes
				WHERE `code` = %s",
				$group_key
			)
		);
	}

	/**
	 * Get group_id from the group_key.
	 *
	 * @return integer group id.
	 */
	public function get_ld_group_id( $group_id ) {

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id` FROM $wpdb->postmeta WHERE meta_key = '_ulgm_code_group_id' AND  meta_value = %s LIMIT 1",
				$group_id
			)
		);
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
			if ( $this->uncanny_group_field_exists( $form['fields'] ) ) {
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
	public function uncanny_group_field_exists( $fields ) {
		$uo_groups_fields = false;
		foreach ( $fields as $field ) {
			if ( self::UO_GROUP_FIELD_TYPE !== $field->type ) {
				continue;
			}
			$uo_groups_fields = true;
			break;
		}

		return $uo_groups_fields;
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
	public static function get_batch_by_value_for_groups( $group_field, $entry ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ulgm_group_details as tbl_groups
				INNER JOIN {$wpdb->prefix}ulgm_group_codes as tbl_batch
				WHERE tbl_groups.ID = tbl_batch.group_id
				AND tbl_batch.code = %s",
				$entry[ $group_field->id ]
			)
		);
	}
}
