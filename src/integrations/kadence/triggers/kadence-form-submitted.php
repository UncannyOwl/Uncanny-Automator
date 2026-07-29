<?php

namespace Uncanny_Automator\Integrations\Kadence;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class KADENCE_FORM_SUBMITTED
 *
 * A user submits a Kadence form — classic form block or advanced (CPT) form.
 *
 * @property Kadence_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Kadence
 */
class KADENCE_FORM_SUBMITTED extends Trigger {

	/**
	 * Declare the trigger's static metadata so the engine can register its WP
	 * hooks from the build-time cache without constructing the integration.
	 *
	 * Demand-driven loading skips Kadence_Integration::load() on frontend and
	 * generic-AJAX requests — exactly where Kadence form submissions arrive —
	 * so both submission hooks are declared here rather than added in load().
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition The trigger definition.
	 */
	public static function definition() {
		return self::new_definition( 'KADENCE_USER_SUBMITTED_FORM', 'KADENCE' )
			->trigger_meta( 'KADENCE_FORMS' )
			->hook( 'kadence_blocks_form_submission', 10, 4 )
			->hook( 'kadence_blocks_advanced_form_submission', 10, 3 );
	}

	/**
	 * Configure the trigger's sentences. Identity fields (integration, code,
	 * trigger meta, and type) are applied automatically from definition().
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: 1: Form name
		$this->set_sentence( sprintf( esc_attr_x( 'A user submits {{a form:%1$s}}', 'Kadence', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user submits {{a form}}', 'Kadence', 'uncanny-automator' ) );
	}

	/**
	 * Define the trigger's configurable option fields.
	 *
	 * @return array[] Field-definition arrays consumed by the recipe builder.
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Form', 'Kadence', 'uncanny-automator' ),
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->get_item_helpers()->remote_data_load_config( 'forms' ),
			),
		);
	}

	/**
	 * Decide whether a submission fires this trigger by matching the submitted
	 * form against the recipe's selected form ("Any form" always matches).
	 *
	 * @param array $trigger   The trigger's configured recipe data.
	 * @param array $hook_args Raw args from either Kadence submission hook.
	 *
	 * @return bool True when the submission matches the configured form.
	 */
	public function validate( $trigger, $hook_args ) {

		list( $fields_data, $unique_id, $post_id ) = $this->get_item_helpers()->normalize_submission_hook_args( $hook_args );
		unset( $fields_data );

		$form_id = is_null( $unique_id ) ? $post_id : $unique_id;

		if ( empty( $form_id ) || ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		return ( intval( '-1' ) === intval( $selected_form_id ) ) || ( (string) $selected_form_id === (string) $form_id );
	}

	/**
	 * Declare the tokens this trigger exposes: the form ID and title, plus one
	 * token per field of the selected form (skipped for "Any form").
	 *
	 * @param array $trigger The trigger's configured recipe data.
	 * @param array $tokens  Tokens already registered by the framework.
	 *
	 * @return array The token definitions.
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens[] = array(
			'tokenId'   => 'KADENCE_FORM_ID',
			'tokenName' => esc_html_x( 'Form ID', 'Kadence', 'uncanny-automator' ),
			'tokenType' => 'int',
		);
		$tokens[] = array(
			'tokenId'   => 'KADENCE_FORM_TITLE',
			'tokenName' => esc_html_x( 'Form title', 'Kadence', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) || intval( '-1' ) === intval( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return $tokens;
		}

		$form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		return $this->get_item_helpers()->get_kadence_form_tokens( $form_id, $tokens );
	}

	/**
	 * Resolve runtime token values from the submitted form data.
	 *
	 * @param array $trigger   The trigger's configured recipe data.
	 * @param array $hook_args Raw args from either Kadence submission hook.
	 *
	 * @return array Map of token ID => runtime value.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $fields_data, $unique_id, $post_id ) = $this->get_item_helpers()->normalize_submission_hook_args( $hook_args );

		if ( is_null( $unique_id ) ) {
			$form_id   = $post_id;
			$form_post = get_post( $post_id );
			$form_name = $form_post instanceof \WP_Post ? $form_post->post_title : '';
		} else {
			$form_uid  = explode( '_', $unique_id );
			$form_id   = $unique_id;
			$form_post = get_post( $form_uid[0] );
			$form_name = ( $form_post instanceof \WP_Post ? $form_post->post_title : '' ) . ' - ' . $unique_id;
		}

		$trigger_token_values = array(
			'KADENCE_FORM_ID'    => $form_id,
			'KADENCE_FORM_TITLE' => $form_name,
		);

		foreach ( $fields_data as $field_data ) {
			if ( ! isset( $field_data['label'] ) ) {
				continue;
			}
			$trigger_token_values[ 'KADENCE_' . str_replace( ' ', '_', $field_data['label'] ) ] = isset( $field_data['value'] ) ? $field_data['value'] : '';
		}

		return $trigger_token_values;
	}
}
