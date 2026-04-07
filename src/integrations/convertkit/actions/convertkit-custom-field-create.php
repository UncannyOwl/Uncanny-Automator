<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Create a custom field (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_CUSTOM_FIELD_CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_CUSTOM_FIELD_CREATE' );
		$this->set_action_meta( 'CONVERTKIT_CUSTOM_FIELD_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a custom field}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the custom field label
				esc_attr_x( 'Create {{a custom field:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Requires OAuth (v4) connection.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return ! $this->helpers->is_v3();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'Field label', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'CUSTOM_FIELD_ID'  => array(
				'name' => esc_html_x( 'Custom field ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			),
			'CUSTOM_FIELD_KEY' => array(
				'name' => esc_html_x( 'Custom field key', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$label = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( empty( $label ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide a field label.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		$response = $this->api->api_request(
			array(
				'action' => 'create_custom_field',
				'label'  => $label,
			),
			$action_data
		);

		$this->helpers->delete_prefixed_app_option( 'custom_fields' );

		$custom_field = $response['data']['custom_field'] ?? array();

		$this->hydrate_tokens(
			array(
				'CUSTOM_FIELD_ID'  => $custom_field['id'] ?? '',
				'CUSTOM_FIELD_KEY' => $custom_field['key'] ?? '',
			)
		);

		return true;
	}
}
