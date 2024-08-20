<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;

/**
 * Class Ontraport_Create_Tag
 *
 * @package Uncanny_Automator
 */
class Ontraport_Create_Tag extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'ONTRAPORT_CREATE_TAG';

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );

		$sentence = sprintf(
			/* translators: Action sentence */
			esc_attr_x( 'Create {{a tag:%1$s}}', 'Ontraport', 'uncanny-automator' ),
			$this->get_action_meta()
		);

		$this->set_sentence( $sentence );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a tag}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$tag = array(
			'option_code' => $this->get_action_meta(),
			'label'       => _x( 'Tag', 'Ontraport', 'uncanny-automator' ),
			'token_name'  => _x( 'Tag name', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,

		);

		return array( $tag );
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

		$body = array(
			'tag_name' => $tag_name,
		);

		$this->helpers->api_request( 'create_tag', $body, $action_data );

	}

}
