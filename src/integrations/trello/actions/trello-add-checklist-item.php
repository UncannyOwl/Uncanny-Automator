<?php

namespace Uncanny_Automator;

/**
 * Class TRELLO_ADD_CHECKLIST_ITEM
 *
 * @package Uncanny_Automator
 */
class TRELLO_ADD_CHECKLIST_ITEM {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * @var Trello_Functions
	 */
	private $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->functions = new Trello_Functions();

		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->set_integration( 'TRELLO' );
		$this->set_action_code( 'ADD_CHECKLIST_ITEM' );
		$this->set_action_meta( 'CARD' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );
		/* translators: card name */
		$this->set_sentence( sprintf( esc_attr__( 'Create a checklist item in {{a card:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Create a checklist item in {{a card}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'ID' => array(
					'name' => __( 'Checklist item ID', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->action_code
		);

		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		$user_boards_field = array(
			'option_code'           => 'BOARD',
			'label'                 => __( 'Board', 'uncanny-automator' ),
			'token_name'            => __( 'Board ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $this->functions->user_boards_options(),
			'supports_custom_value' => false,
		);

		$board_lists_field = array(
			'option_code'           => 'LIST',
			'label'                 => __( 'List', 'uncanny-automator' ),
			'token_name'            => __( 'List ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_lists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$list_cards_field = array(
			'option_code'           => $this->get_action_meta(),
			'label'                 => __( 'Card', 'uncanny-automator' ),
			'token_name'            => __( 'Card ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => array(),
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_list_cards',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'LIST' ),
			),
		);

		$card_checklists_field = array(
			'option_code'           => 'CHECKLIST',
			'label'                 => __( 'Checklist', 'uncanny-automator' ),
			'token_name'            => __( 'Checklist ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => true,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_card_checklists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'CARD' ),
			),
		);

		$item_name_field = array(
			'option_code' => 'NAME',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Checklist item name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		$item_due_date_field = array(
			'option_code'     => 'DUE',
			'input_type'      => 'date',
			'label'           => esc_attr__( 'Due date', 'uncanny-automator' ),
			'placeholder'     => '',
			'description'     => '',
			'supports_tokens' => true,
			'default'         => '',
		);

		$item_member_field = array(
			'option_code'           => 'MEMBER',
			'input_type'            => 'select',
			'options'               => array(),
			'label'                 => esc_attr__( 'Member', 'uncanny-automator' ),
			'token_name'            => __( 'Member ID', 'uncanny-automator' ),
			'placeholder'           => esc_attr__( 'No member', 'uncanny-automator' ),
			'default'               => '',
			'supports_custom_value' => false,
			'options_show_id'       => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$item_checked_field = array(
			'option_code' => 'CHECKED',
			'input_type'  => 'checkbox',
			'label'       => esc_attr__( 'Checked', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'default'     => '',
		);

		$item_position_field = array(
			'option_code'           => 'POS',
			'input_type'            => 'select',
			'label'                 => esc_attr__( 'Position', 'uncanny-automator' ),
			'placeholder'           => '',
			'description'           => '',
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
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$user_boards_field,
					$board_lists_field,
					$list_cards_field,
					$card_checklists_field,
					$item_name_field,
					$item_due_date_field,
					$item_member_field,
					$item_checked_field,
					$item_position_field,
				),
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
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

		$error_msg = '';

		try {

			$response = $this->functions->api->create_checklist_item( $checklist, $item );
			$this->process_tokens( $response );

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * process_tokens
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function process_tokens( $response ) {

		$tokens = array();

		if ( ! empty( $response['data']['id'] ) ) {
			$tokens['ID'] = $response['data']['id'];
		}

		if ( ! empty( $tokens ) ) {
			$this->hydrate_tokens( $tokens );
		}
	}
}
