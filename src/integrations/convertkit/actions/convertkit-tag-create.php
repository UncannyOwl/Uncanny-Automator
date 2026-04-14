<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Create a tag (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_TAG_CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_TAG_CREATE' );
		$this->set_action_meta( 'CONVERTKIT_TAG_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a tag}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag name
				esc_attr_x( 'Create {{a tag:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
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
				'label'       => esc_attr_x( 'Tag name', 'ConvertKit', 'uncanny-automator' ),
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
			'TAG_ID'         => array(
				'name' => esc_html_x( 'Tag ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			),
			'TAG_CREATED_AT' => array(
				'name' => esc_html_x( 'Tag created date', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'date',
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

		$tag_name = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( empty( $tag_name ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide a tag name.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		$response = $this->api->api_request(
			array(
				'action'   => 'create_tag',
				'tag_name' => $tag_name,
			),
			$action_data
		);

		$this->helpers->delete_prefixed_app_option( 'tags' );

		$tag = $response['data']['tag'] ?? array();

		$this->hydrate_tokens(
			array(
				'TAG_ID'         => $tag['id'] ?? '',
				'TAG_CREATED_AT' => ! empty( $tag['created_at'] )
					? $this->helpers->get_formatted_time( $tag['created_at'] )
					: '',
			)
		);

		return true;
	}
}
