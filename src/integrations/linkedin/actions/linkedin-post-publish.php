<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\Automator;

/**
 * Class LINKEDIN_POST_PUBLISH
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 * @property Linkedin_Api_Caller $api
 */
class LINKEDIN_POST_PUBLISH extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'LINKEDIN' );
		$this->set_action_code( 'LINKEDIN_POST_PUBLISH' );
		$this->set_action_meta( 'LINKEDIN_POST_PUBLISH_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/linkedin/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_readable_sentence( esc_attr_x( 'Publish a post to {{a LinkedIn page}}', 'LinkedIn', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the LinkedIn page name
				esc_attr_x( 'Publish a post to {{a LinkedIn page:%1$s}}', 'LinkedIn', 'uncanny-automator' ),
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
			$this->helpers->get_page_option_config( $this->get_action_meta() ),
			$this->helpers->get_message_option_config( 'BODY' ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional args.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 * @throws \Exception If API call fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$content = $this->helpers->format_post_content( $parsed['BODY'] ?? '' );
		$urn     = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$this->api->publish_post( $content, $urn, $action_data );

		return true;
	}
}
