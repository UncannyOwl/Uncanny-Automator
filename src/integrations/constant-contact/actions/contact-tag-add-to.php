<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CONTACT_TAG_ADD_TO
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 * @property Constant_Contact_Api_Caller $api
 */
class CONTACT_TAG_ADD_TO extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'CONTACT_TAG_ADD_TO' );
		$this->set_action_meta( 'CONTACT_TAG_ADD_TO_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Tag, %2$s: Contact
				esc_attr_x(
					'Add {{a tag:%1$s}} to {{a contact:%2$s}}',
					'Constant Contact',
					'uncanny-automator'
				),
				'TAG:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a contact}}', 'Constant Contact', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_config( $this->get_action_meta() ),
			array(
				'option_code' => 'TAG',
				'label'       => esc_html_x( 'Tag', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'event'    => 'on_load',
					'endpoint' => 'automator_constant_contact_tags_get',
				),
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
		// Get the email from the parsed data - throws an exception if invalid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Get / validate the tag.
		$tag = sanitize_text_field( $parsed['TAG'] ?? '' );
		if ( empty( $tag ) ) {
			throw new Exception(
				esc_html_x( 'Tag is required', 'Constant Contact', 'uncanny-automator' )
			);
		}

		// Add tag to contact.
		$this->api->contact_tag_add_to( $email, $tag, $action_data );
	}
}
