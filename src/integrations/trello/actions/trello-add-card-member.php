<?php

namespace Uncanny_Automator;

/**
 * Class TRELLO_ADD_CARD_MEMBER
 *
 * @package Uncanny_Automator
 */
class TRELLO_ADD_CARD_MEMBER {

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
		$this->set_action_code( 'ADD_CARD_MEMBER' );
		$this->set_action_meta( 'CARD_NAME' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );
		/* translators: card name */
		$this->set_sentence( sprintf( esc_attr__( 'Add a member to {{a card:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Add a member to {{a card}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$user_boards_field = array(
			'option_code'           => 'BOARD',
			'label'                 => __( 'Board', 'uncanny-automator' ),
			'token_name'            => __( 'Board ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
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
			'option_code'           => 'CARD',
			'label'                 => __( 'Card', 'uncanny-automator' ),
			'token_name'            => __( 'Card ID', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => true,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_list_cards',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'LIST' ),
			),
		);

		$member_field = array(
			'option_code'              => 'MEMBER',
			'input_type'               => 'select',
			'supports_multiple_values' => false,
			'options'                  => array(),
			'label'                    => __( 'Member', 'uncanny-automator' ),
			'token_name'               => __( 'Member ID', 'uncanny-automator' ),
			'placeholder'              => __( 'Select a member', 'uncanny-automator' ),
			'supports_custom_value'    => true,
			'required'                 => true,
			'default'                  => '',
			'ajax'                     => array(
				'endpoint'      => 'automator_trello_get_board_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$user_boards_field,
					$board_lists_field,
					$list_cards_field,
					$member_field,
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

		$card_id = Automator()->parse->text( $action_data['meta']['CARD'], $recipe_id, $user_id, $args );

		$member_id = Automator()->parse->text( $action_data['meta']['MEMBER'], $recipe_id, $user_id, $args );

		$error_msg = '';

		try {

			$response = $this->functions->api->add_card_member( $card_id, $member_id );

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );
	}
}
