<?php

namespace Uncanny_Automator;

/**
 * Class TRELLO_ADD_CARD_COMMENT
 *
 * @package Uncanny_Automator
 */
class TRELLO_ADD_CARD_COMMENT {

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
		$this->set_action_code( 'ADD_CARD_COMMENT' );
		$this->set_action_meta( 'CARD' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/trello/' ) );
		$this->set_requires_user( false );
		/* translators: card name */
		$this->set_sentence( sprintf( esc_attr__( 'Add a comment to {{a card:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Add a comment to {{a card}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'ID'  => array(
					'name' => __( 'Comment ID', 'uncanny-automator' ),
					'type' => 'text',
				),
				'URL' => array(
					'name' => __( 'Comment URL', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->action_code
		);

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
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $this->functions->user_boards_options(),
			'supports_custom_value' => false,
			'token_name'            => __( 'Board ID', 'uncanny-automator' ),
		);

		$board_lists_field = array(
			'option_code'           => 'LIST',
			'label'                 => __( 'List', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => array(),
			'supports_custom_value' => false,
			'token_name'            => __( 'List ID', 'uncanny-automator' ),
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_lists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'BOARD' ),
			),
		);

		$list_cards_field = array(
			'option_code'           => $this->get_action_meta(),
			'label'                 => __( 'Card', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => true,
			'token_name'            => __( 'Card ID', 'uncanny-automator' ),
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_list_cards',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'LIST' ),
			),
		);

		$card_comment_field = array(
			'option_code' => 'COMMENT',
			'input_type'  => 'textarea',
			'label'       => esc_attr__( 'Comment', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'tokens'      => true,
			'default'     => '',
		);

		return array(
			'options_group' => array(
				$this->action_meta => array(
					$user_boards_field,
					$board_lists_field,
					$list_cards_field,
					$card_comment_field,
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

		$card_id = Automator()->parse->text( $action_data['meta'][ $this->get_action_meta() ], $recipe_id, $user_id, $args );
		$comment = Automator()->parse->text( $action_data['meta']['COMMENT'], $recipe_id, $user_id, $args );

		$error_msg = '';

		try {

			$response = $this->functions->api->add_card_comment( $card_id, $comment );
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

		$comment = $response['data'];

		if ( ! empty( $comment['id'] ) ) {

			$tokens['ID'] = $comment['id'];

			$tokens['URL'] = sprintf( 'https://trello.com/c/%s#comment-%s', $comment['data']['card']['shortLink'], $comment['id'] );
		}

		if ( ! empty( $tokens ) ) {
			$this->hydrate_tokens( $tokens );
		}
	}
}
