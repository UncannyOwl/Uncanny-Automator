<?php

namespace Uncanny_Automator\Integrations\Twitter;

/**
 * Class TWITTER_POSTSTATUS ( deprecated )
 *
 * @package Uncanny_Automator
 *
 * @property Twitter_App_Helpers $helpers
 * @property Twitter_Api_Caller $api
 */
class TWITTER_POSTSTATUS extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'TWITTER' );
		$this->set_action_code( 'TWITTERPOSTSTATUS' );
		$this->set_action_meta( 'TWITTERSTATUSCONTENT' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/twitter/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tweet content
				esc_attr_x( 'Post {{a tweet:%1$s}} to X/Twitter', 'Twitter', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Post {{a tweet}} to X/Twitter', 'Twitter', 'uncanny-automator' ) );
		$this->set_background_processing( true );
		$this->set_is_deprecated( true );
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_recipe_status_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Process the Twitter action.
	 *
	 * @param int    $user_id
	 * @param array  $action_data
	 * @param int    $recipe_id
	 * @param array  $args
	 * @param array  $parsed
	 *
	 * @return bool
	 * @throws \Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$status   = $this->get_parsed_meta_value( $this->get_action_meta() );
		$response = $this->api->statuses_update( $status, '', $action_data );

		return true;
	}
}
