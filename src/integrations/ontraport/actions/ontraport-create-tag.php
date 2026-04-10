<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Create_Tag
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Create_Tag extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( 'ONTRAPORT_CREATE_TAG_CODE' );
		$this->set_action_meta( 'ONTRAPORT_CREATE_TAG_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a tag}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Tag name
				esc_attr_x( 'Create {{a tag:%1$s}}', 'Ontraport', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
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
				'label'       => esc_html_x( 'Tag', 'Ontraport', 'uncanny-automator' ),
				'token_name'  => esc_html_x( 'Tag name', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$tag_name = $this->get_parsed_meta_value( $this->get_action_meta(), '' );
		$body     = array(
			'tag_name' => $tag_name,
		);

		$this->api->send_request( 'create_tag', $body, $action_data );

		// Clear the tags cache so it loads the new tag.
		automator_delete_option( $this->helpers->get_option_key( 'tags' ) );

		return true;
	}
}
