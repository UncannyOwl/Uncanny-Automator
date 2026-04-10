<?php

namespace Uncanny_Automator\Integrations\Trello;

/**
 * Class TRELLO_CREATE_CARD
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 */
class TRELLO_CREATE_CARD extends \Uncanny_Automator\Recipe\App_Action {

	use Trello_Card_Data;

	/**
	 * Whether this is an update action.
	 *
	 * @var bool
	 */
	protected $is_update_action = false;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'CREATE_CARD' );
		$this->set_action_meta( 'CARD_NAME' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s is the card name */
				esc_attr_x( 'Create {{a card:%1$s}}', 'Trello', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a card}}', 'Trello', 'uncanny-automator' ) );

		$this->set_action_tokens(
			$this->get_card_token_definitions(),
			$this->get_action_code()
		);

		$this->set_background_processing( true );
	}

	/**
	 * Get the action options.
	 *
	 * @return array
	 */
	public function options() {
		return $this->get_card_options();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$card = $this->build_card_data( $action_data, $recipe_id, $user_id, $args, $parsed );

		$response = $this->api->api_request(
			array(
				'action' => 'create_card',
				'card'   => wp_json_encode( $card ),
			),
			$action_data,
			array( 'error_message' => esc_html_x( 'Unable to create card.', 'Trello', 'uncanny-automator' ) )
		);

		$this->hydrate_card_tokens( $response );

		return true;
	}
}
