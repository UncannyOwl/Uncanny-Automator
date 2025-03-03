<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;

/**
 * Class Ontraport_Add_Contact_Tag
 *
 * @package Uncanny_Automator
 */
class Ontraport_Add_Contact_Tag extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'ONTRAPORT_ADD_CONTACT_TAG';

	/**
	 * @var Ontraport_Helpers
	 */
	protected $helpers;

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
			esc_attr_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'Ontraport', 'uncanny-automator' ),
			$this->get_action_meta(),
			'CONTACT_EMAIL:' . $this->get_action_meta()
		);

		$this->set_sentence( $sentence );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$tags = array(
			'option_code'              => $this->get_action_meta(),
			'label'                    => _x( 'Tags', 'Ontraport', 'uncanny-automator' ),
			'input_type'               => 'select',
			'multiple'                 => true,
			'required'                 => true,
			'ajax'                     => array(
				'endpoint' => 'automator_ontraport_list_tags',
				'event'    => 'on_load',
			),
			'supports_multiple_values' => true,
			'options'                  => array(),
		);

		$email = array(
			'option_code' => 'CONTACT_EMAIL',
			'label'       => _x( 'Email', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'options'     => array(),
		);

		return array( $tags, $email );
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

		$email = $this->get_parsed_meta_value( 'CONTACT_EMAIL', '' );
		$tags  = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				sprintf(
				/* translators: %s: Email address */
					esc_html__( 'Invalid email provided: %s', 'uncanny-automator' ),
					esc_html( $email )
				),
				400
			);
		}

		$body = array(
			'email_address' => $email,
			'tags'          => implode( ',', (array) json_decode( $tags, true ) ),
		);

		$this->helpers->api_request( 'contact_add_tag', $body, $action_data );
	}
}
