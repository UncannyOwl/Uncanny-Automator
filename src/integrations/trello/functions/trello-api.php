<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Trello_Api
 *
 * @package Uncanny_Automator
 */
class Trello_Api {

	/**
	 * client
	 *
	 * @var array
	 */
	private $client;

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/trello';

	public function __construct( $client ) {
		$this->client = $client;
	}

	/**
	 * get_url
	 *
	 * @return string
	 */
	public function get_url() {
		return AUTOMATOR_API_URL . self::API_ENDPOINT;
	}

	/**
	 * api_request
	 *
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @return array
	 */
	public function api_request( $body, $action_data = null ) {

		$body['api_token'] = $this->client;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		return $response;
	}

	/**
	 * get_user
	 *
	 * @return array
	 */
	public function get_user() {

		$args = array(
			'action' => 'user_info',
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch user info.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response['data'];
	}

	/**
	 * get_boards
	 *
	 * @return array
	 */
	public function get_boards() {

		$user = $this->get_user();

		$args = array(
			'action'    => 'user_boards',
			'member_id' => $user['id'],
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch user boards.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$boards = $response['data'];

		return $boards;
	}

	/**
	 * get_board_lists
	 *
	 * @param  mixed $board
	 * @return array
	 */
	public function get_board_lists( $board ) {

		$args = array(
			'action'   => 'board_lists',
			'board_id' => $board,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch board lists.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$lists = $response['data'];

		return $lists;
	}

	/**
	 * get_board_members
	 *
	 * @param  string $board_id
	 * @return array
	 */
	public function get_board_members( $board_id ) {

		$args = array(
			'action'   => 'board_members',
			'board_id' => $board_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch board members.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$lists = $response['data'];

		return $lists;
	}

	/**
	 * get_board_labels
	 *
	 * @param  string $board_id
	 * @return array
	 */
	public function get_board_labels( $board_id ) {

		$args = array(
			'action'   => 'board_labels',
			'board_id' => $board_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch board lables.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$lists = $response['data'];

		return $lists;
	}

	/**
	 * create_card
	 *
	 * @param  array $card
	 * @return mixed
	 */
	public function create_card( $card ) {

		$card['idMembers'] = $this->comma_separated( $card['idMembers'] );
		$card['idLabels']  = $this->comma_separated( $card['idLabels'] );

		$args = array(
			'action' => 'create_card',
			'card'   => wp_json_encode( $card ),
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to create a card.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response;
	}

	/**
	 * get_custom_fields
	 *
	 * @param  string $board_id
	 * @return array
	 */
	public function get_custom_fields( $board_id ) {

		$args = array(
			'action'   => 'get_custom_fields',
			'board_id' => $board_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to get custom fields.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$fields = $response['data'];

		return $fields;
	}

	/**
	 * get_list_cards
	 *
	 * @param  string $list_id
	 * @return array
	 */
	public function get_list_cards( $list_id ) {

		$args = array(
			'action'  => 'list_cards',
			'list_id' => $list_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch list cards.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$lists = $response['data'];

		return $lists;
	}

	/**
	 * get_card_checklists
	 *
	 * @param  string $card
	 * @return array
	 */
	public function get_card_checklists( $card_id ) {

		$args = array(
			'action'  => 'card_checklists',
			'card_id' => $card_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to fetch checklists.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		$lists = $response['data'];

		return $lists;
	}

	/**
	 * create_checklist_item
	 *
	 * @param  string $checklist_id
	 * @param  array $item
	 * @return mixed
	 */
	public function create_checklist_item( $checklist_id, $item ) {

		$args = array(
			'action'       => 'create_checklist_item',
			'checklist_id' => $checklist_id,
			'item'         => wp_json_encode( $item ),
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to create a checklist item.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response;
	}

	/**
	 * add_card_label
	 *
	 * @param  string $card_id
	 * @param  string $label_id
	 * @return mixed
	 */
	public function add_card_label( $card_id, $label_id ) {

		$args = array(
			'action'   => 'add_card_label',
			'card_id'  => $card_id,
			'label_id' => $label_id,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to add card label.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response;
	}

	/**
	 * update_card
	 *
	 * @param  string $card_id
	 * @param  array $card
	 * @return mixed
	 */
	public function update_card( $card_id, $card ) {

		$card['idMembers'] = $this->comma_separated( $card['idMembers'] );
		$card['idLabels']  = $this->comma_separated( $card['idLabels'] );

		$args = array(
			'action'  => 'update_card',
			'card_id' => $card_id,
			'card'    => wp_json_encode( $card ),
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to update a card.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response;
	}

	/**
	 * add_card_comment
	 *
	 * @param  string $card_id
	 * @param  string $label_id
	 * @return mixed
	 */
	public function add_card_comment( $card_id, $comment ) {

		$args = array(
			'action'  => 'add_card_comment',
			'card_id' => $card_id,
			'comment' => $comment,
		);

		$response = $this->api_request( $args );

		$default_error = __( 'Unable to add card comment.', 'uncanny-automator' );

		$this->check_for_errors( $response, $default_error );

		return $response;
	}

	/**
	 * add_card_member
	 *
	 * @param  string $card_id
	 * @param  string $label_id
	 * @return mixed
	 */
	public function add_card_member( $card_id, $member_id ) {

		$args = array(
			'action'    => 'add_card_member',
			'card_id'   => $card_id,
			'member_id' => $member_id,
		);

		$response = $this->api_request( $args );

		$this->check_for_errors( $response, __( 'Unable to add card member.', 'uncanny-automator' ) );

		return $response;
	}

	/**
	 * check_for_errors
	 *
	 * @param  mixed $response
	 * @param  string $default
	 * @return string
	 */
	public function check_for_errors( $response, $error_message ) {

		if ( 200 === $response['statusCode'] ) {
			return;
		}

		if ( isset( $response['data']['message'] ) ) {
			$error_message = $response['data']['message'];
		}

		throw new \Exception( $error_message );
	}

	/**
	 * comma_separated
	 *
	 * @param  string $json_string
	 * @return string
	 */
	public function comma_separated( $json_string ) {

		$array = json_decode( $json_string, true );

		$string = implode( ',', $array );

		return $string;
	}
}
