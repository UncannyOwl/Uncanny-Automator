<?php

namespace Uncanny_Automator\Integrations\Trello;

/**
 * Class TRELLO_ADD_CHECKLIST_ITEM
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 */
class TRELLO_ADD_CHECKLIST_ITEM extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'ADD_CHECKLIST_ITEM' );
		$this->set_action_meta( 'CARD' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s is the card name */
				esc_attr_x( 'Create a checklist item in {{a card:%1$s}}', 'Trello', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create a checklist item in {{a card}}', 'Trello', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'ID' => array(
					'name' => esc_html_x( 'Checklist item ID', 'Trello', 'uncanny-automator' ),
					'type' => 'text',
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
		$card_meta  = $this->get_action_meta();

		return array(
			$this->helpers->get_board_option_config( $board_meta ),
			$this->helpers->get_list_option_config( $list_meta, $board_meta ),
			$this->helpers->get_card_option_config( $card_meta, $list_meta, false ),
			$this->helpers->get_checklist_option_config( 'CHECKLIST', $card_meta ),
			array(
				'option_code' => 'NAME',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Checklist item name', 'Trello', 'uncanny-automator' ),
				'tokens'      => true,
				'default'     => '',
			),
			array(
				'option_code'     => 'DUE',
				'input_type'      => 'date',
				'label'           => esc_html_x( 'Due date', 'Trello', 'uncanny-automator' ),
				'supports_tokens' => true,
				'default'         => '',
			),
			$this->helpers->get_member_option_config( 'MEMBER', $board_meta, false, false ),
			array(
				'option_code' => 'CHECKED',
				'input_type'  => 'checkbox',
				'label'       => esc_html_x( 'Checked', 'Trello', 'uncanny-automator' ),
				'default'     => '',
			),
			array(
				'option_code'           => 'POS',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Position', 'Trello', 'uncanny-automator' ),
				'options'               => array(
					array(
						'value' => 'top',
						'text'  => 'Top',
					),
					array(
						'value' => 'bottom',
						'text'  => 'Bottom',
					),
				),
				'default'               => 'top',
				'options_show_id'       => false,
				'supports_custom_value' => false,
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

		$checklist = $action_data['meta']['CHECKLIST'];

		$item = array(
			'name'     => Automator()->parse->text( $action_data['meta']['NAME'], $recipe_id, $user_id, $args ),
			'due'      => Automator()->parse->text( $action_data['meta']['DUE'], $recipe_id, $user_id, $args ),
			'checked'  => $action_data['meta']['CHECKED'],
			'idMember' => $action_data['meta']['MEMBER'],
			'pos'      => $action_data['meta']['POS'],
		);

		$response = $this->api->api_request(
			array(
				'action'       => 'create_checklist_item',
				'checklist_id' => $checklist,
				'item'         => wp_json_encode( $item ),
			),
			$action_data,
			array( 'error_message' => esc_html_x( 'Unable to create checklist item.', 'Trello', 'uncanny-automator' ) )
		);

		if ( ! empty( $response['data']['id'] ) ) {
			$this->hydrate_tokens(
				array(
					'ID' => $response['data']['id'],
				)
			);
		}

		return true;
	}
}
