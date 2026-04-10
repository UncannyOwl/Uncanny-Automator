<?php

namespace Uncanny_Automator\Integrations\Trello;

/**
 * Class TRELLO_ADD_CARD_COMMENT
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 */
class TRELLO_ADD_CARD_COMMENT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'ADD_CARD_COMMENT' );
		$this->set_action_meta( 'CARD' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s is the card name */
				esc_attr_x( 'Add a comment to {{a card:%1$s}}', 'Trello', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add a comment to {{a card}}', 'Trello', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'ID'  => array(
					'name' => esc_html_x( 'Comment ID', 'Trello', 'uncanny-automator' ),
					'type' => 'text',
				),
				'URL' => array(
					'name' => esc_html_x( 'Comment URL', 'Trello', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
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

		$board_meta = Trello_App_Helpers::ACTION_BOARD_META_KEY;
		$list_meta  = Trello_App_Helpers::ACTION_LIST_META_KEY;

		return array(
			$this->helpers->get_board_option_config( $board_meta ),
			$this->helpers->get_list_option_config( $list_meta, $board_meta ),
			$this->helpers->get_card_option_config( $this->get_action_meta(), $list_meta, true ),
			array(
				'option_code' => 'COMMENT',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'Comment', 'Trello', 'uncanny-automator' ),
				'placeholder' => '',
				'description' => '',
				'tokens'      => true,
				'default'     => '',
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

		$card_id = Automator()->parse->text( $action_data['meta'][ $this->get_action_meta() ], $recipe_id, $user_id, $args );
		$comment = Automator()->parse->text( $action_data['meta']['COMMENT'], $recipe_id, $user_id, $args );

		$response = $this->api->api_request(
			array(
				'action'  => 'add_card_comment',
				'card_id' => $card_id,
				'comment' => $comment,
			),
			$action_data,
			array( 'error_message' => esc_html_x( 'Unable to add card comment.', 'Trello', 'uncanny-automator' ) )
		);

		$comment_data = $response['data'];

		if ( ! empty( $comment_data['id'] ) ) {
			$short_link = $comment_data['data']['card']['shortLink'] ?? '';

			$this->hydrate_tokens(
				array(
					'ID'  => $comment_data['id'],
					'URL' => ! empty( $short_link )
						? sprintf( 'https://trello.com/c/%s#comment-%s', $short_link, $comment_data['id'] )
						: '-',
				)
			);
		}

		return true;
	}
}
