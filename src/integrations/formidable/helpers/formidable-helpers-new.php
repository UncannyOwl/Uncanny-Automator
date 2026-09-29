<?php

namespace Uncanny_Automator\Integrations\Formidable;

use FrmDb;
use FrmField;
use FrmForm;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Helpers for the Formidable integration.
 *
 * The legacy `\Uncanny_Automator\Formidable_Helpers` is a different class and
 * stays on disk untouched: an un-updated Pro resolves it when it declares its
 * own helper, so removing or namespacing it would fatal on plugin load. Nothing
 * here extends or calls it.
 *
 * @package Uncanny_Automator
 */
class Formidable_Helpers extends Abstract_Helpers {

	/**
	 * Lazily built token definitions.
	 *
	 * @var Formidable_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Token definitions and hydration for this integration.
	 *
	 * @return Formidable_Tokens
	 */
	public function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Formidable_Tokens( $this );
		}

		return $this->tokens;
	}

	// -------------------------------------------------------------------------
	// Remote data
	// -------------------------------------------------------------------------

	/**
	 * Forms available to a recipe.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forms( $request ): array {
		return $this->remote_data_success( $this->get_form_options() );
	}

	/**
	 * Forms available to an action.
	 *
	 * Actions take the `_strict` variant by convention: a trigger's segment may
	 * carry an "Any" sentinel that an action must never offer. This list has
	 * never had one, so the two agree today — the split keeps them free to differ.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forms_strict( $request ): array {
		return $this->remote_data_success( $this->get_form_options() );
	}

	/**
	 * Selectable forms, newest first as Formidable orders them.
	 *
	 * Child forms are excluded: they hold a repeater's rows and are never
	 * submitted on their own, so they cannot start a recipe.
	 *
	 * @return array
	 */
	public function get_form_options() {

		$query = array(
			array(
				'or'               => 1,
				'parent_form_id'   => null,
				'parent_form_id <' => 1,
			),
			'is_template' => 0,
			'status !'    => 'trash',
		);

		$options = array();

		foreach ( (array) FrmForm::getAll( $query, '', ' 0, 999' ) as $form ) {
			$options[] = array(
				'value' => (string) $form->id,
				'text'  => $form->name,
			);
		}

		return $options;
	}

	// -------------------------------------------------------------------------
	// Field configuration
	// -------------------------------------------------------------------------

	/**
	 * The form select, which every trigger and the create-entry action share.
	 *
	 * The option code differs per item - it is the trigger meta, so `FIFORM` on
	 * the logged-in triggers and `ANONFIFORM` on the anonymous ones - and actions
	 * take the `forms_strict` segment so they never inherit a trigger's "Any"
	 * sentinel. Everything else about the field is identical.
	 *
	 * @param string $option_code Code the selected form is stored under.
	 * @param string $segment     Remote-data segment serving the list.
	 *
	 * @return array
	 */
	public function get_form_option_config( $option_code, $segment = 'forms' ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Form', 'Formidable', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'remote_data'     => $this->remote_data_load_config( $segment ),
			// Form title and id are declared in define_tokens() with their storage bucket.
			'relevant_tokens' => array(),
		);
	}

	// -------------------------------------------------------------------------
	// Form and field reads
	// -------------------------------------------------------------------------

	/**
	 * A single form.
	 *
	 * @param int $form_id
	 *
	 * @return object|null
	 */
	public function get_form( $form_id ) {

		$form = FrmForm::getOne( absint( $form_id ) );

		return $form ? $form : null;
	}

	/**
	 * Reduce a form's fields to the descriptors the token builder reads.
	 *
	 * Formidable returns the fields of a repeater's child form inline, ordered
	 * between the section that opens it and the one that closes it, which is
	 * what lets the builder tell which fields belong to the repeater.
	 *
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function describe_form_fields( $form_id ) {

		$fields = array();

		foreach ( (array) FrmField::get_all_for_form( absint( $form_id ) ) as $field ) {

			$name_parts = array();

			if ( Formidable_Field_Tokens::NAME_TYPE === $field->type ) {
				foreach ( Formidable_Field_Tokens::NAME_PARTS as $part ) {
					if ( ! empty( $field->field_options[ $part . '_desc' ] ) ) {
						$name_parts[ $part ] = $field->field_options[ $part . '_desc' ];
					}
				}
			}

			$fields[] = array(
				'id'           => $field->id,
				'type'         => $field->type,
				'label'        => $field->name . ( '' !== $field->description ? ' (' . $field->description . ') ' : '' ),
				'is_repeating' => FrmField::is_repeating_field( $field ),
				'name_parts'   => $name_parts,
			);
		}

		return $fields;
	}

	/**
	 * Whether a field of a form stores an uploaded file.
	 *
	 * @param int $form_id
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public function is_file_field( $form_id, $field_id ) {

		foreach ( (array) FrmField::get_all_for_form( absint( $form_id ) ) as $field ) {
			if ( (int) $field->id === (int) $field_id ) {
				return isset( $field->type ) && 'file' === $field->type;
			}
		}

		return false;
	}

	/**
	 * Swap an attachment id for the url of the file it points at.
	 *
	 * @param string $media_id Attachment id as stored by Formidable.
	 *
	 * @return string The id itself when it points at no attachment.
	 */
	public function attachment_url( $media_id ) {

		if ( ! get_post( $media_id ) ) {
			return $media_id;
		}

		$url = wp_get_attachment_url( $media_id );

		return $url ? esc_url( $url ) : $media_id;
	}

	// -------------------------------------------------------------------------
	// Entry state
	// -------------------------------------------------------------------------

	/**
	 * Determine whether an entry is a completed submission.
	 *
	 * Formidable stores 0 for a submitted entry and 1 for a save-and-continue draft, and
	 * reserves 2 and 3 for the Form Abandonment add-on's "In progress" and "Abandoned"
	 * entries. All of them fire frm_after_create_entry. The hook fires again once the entry
	 * is completed - from Formidable Pro for status 1, and from the abandonment add-on for
	 * 2 and 3 - so skipping the incomplete ones still leaves one trigger run per entry.
	 *
	 * Not FrmEntry::getOne(): the add-on creates the entry as submitted and only then
	 * flips it to "In progress" with a raw query, on this same hook at priority 1. That
	 * leaves getOne() serving a cached is_draft of 0 by the time our triggers run.
	 * FrmDb::get_var() caches under its own key, which nothing has populated by then.
	 *
	 * @param int $entry_id
	 *
	 * @return bool
	 */
	public function is_completed_entry( $entry_id ) {

		$is_draft = FrmDb::get_var( 'frm_items', array( 'id' => $entry_id ), 'is_draft' );

		return null !== $is_draft && 0 === (int) $is_draft;
	}
}
