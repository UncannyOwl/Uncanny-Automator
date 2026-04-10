<?php

namespace Uncanny_Automator\Integrations\Trello;

/**
 * Class TRELLO_ADD_CARD_LABEL
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 */
class TRELLO_ADD_CARD_LABEL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'ADD_CARD_LABEL' );
		$this->set_action_meta( 'CARD' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s is the card name */
				esc_attr_x( 'Add a label to {{a card:%1$s}}', 'Trello', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add a label to {{a card}}', 'Trello', 'uncanny-automator' ) );

		$this->set_background_processing( true );
	}

	/**
	 * Get the action options.
	 *
	 * @return array
	 */
	public function options() {

		$board_meta = Trello_App_Helpers::ACTION_BOARD_META_KEY;
		$list_meta  = Trello_App_Helpers::ACTION_LIST_META_KEY;

		return array(
			$this->helpers->get_board_option_config( $board_meta ),
			$this->helpers->get_list_option_config( $list_meta, $board_meta ),
			$this->helpers->get_card_option_config( $this->get_action_meta(), $list_meta, true ),
			array_merge(
				$this->helpers->get_label_option_config( 'LABEL', $board_meta, true, false ),
				array( 'required' => true )
			),
		);
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

		$card_id  = Automator()->parse->text( $action_data['meta'][ $this->get_action_meta() ], $recipe_id, $user_id, $args );
		$label_id = Automator()->parse->text( $action_data['meta']['LABEL'], $recipe_id, $user_id, $args );

		$this->api->api_request(
			array(
				'action'   => 'add_card_label',
				'card_id'  => $card_id,
				'label_id' => $label_id,
			),
			$action_data,
			array( 'error_message' => esc_html_x( 'Unable to add card label.', 'Trello', 'uncanny-automator' ) )
		);

		return true;
	}
}
