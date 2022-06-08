<?php

namespace Uncanny_Automator;

/**
 * Autonami Tokens file
 */
class AUTONAMI_TOKENS {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_autonami_list_tokens', array( $this, 'add_list_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_autonami_tag_tokens', array( $this, 'add_tag_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 20, 6 );

	}

	/**
	 * get_list_triggers
	 *
	 * @return array
	 */
	public function get_list_triggers() {

		$list_triggers = array(
			'CONTACT_ADDED_TO_LIST',
			'USER_ADDED_TO_LIST',
		);

		return apply_filters( 'automator_autonami_list_triggers', $list_triggers );
	}

	/**
	 * get_tag_triggers
	 *
	 * @return array
	 */
	public function get_tag_triggers() {

		$tag_triggers = array(
			'USER_TAG_ADDED',
			'CONTACT_TAG_ADDED',
		);

		return apply_filters( 'automator_autonami_tag_triggers', $tag_triggers );
	}

	/**
	 * is_userless
	 *
	 * @param  mixed $trigger_code
	 * @return void
	 */
	public function is_userless( $trigger_code ) {
		return false === strpos( $trigger_code, 'USER_' );
	}

	/**
	 * Method list_tokens
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return void
	 */
	public function add_list_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		if ( empty( $args['triggers_meta']['code'] ) ) {
			return $tokens;
		}

		$current_trigger = $args['triggers_meta']['code'];

		if ( ! in_array( $current_trigger, $this->get_list_triggers(), true ) ) {
			return $tokens;
		}

		$tokens[] = array(
			'tokenId'         => 'LIST_ID',
			'tokenName'       => __( 'List ID', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => $current_trigger,
		);

		$tokens = $this->contact_tokens( $tokens, $current_trigger );

		if ( $this->is_userless( $current_trigger ) ) {
			$tokens = $this->additional_contact_tokens( $tokens, $current_trigger );
		}

		return $tokens;
	}

	/**
	 * Method add_tag_tokens
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return void
	 */
	public function add_tag_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		if ( empty( $args['triggers_meta']['code'] ) ) {
			return $tokens;
		}

		$current_trigger = $args['triggers_meta']['code'];

		if ( ! in_array( $current_trigger, $this->get_tag_triggers(), true ) ) {
			return $tokens;
		}

		$tokens[] = array(
			'tokenId'         => 'TAG_ID',
			'tokenName'       => __( 'Tag ID', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => $current_trigger,
		);

		$tokens = $this->contact_tokens( $tokens, $current_trigger );

		if ( $this->is_userless( $current_trigger ) ) {
			$tokens = $this->additional_contact_tokens( $tokens, $current_trigger );
		}

		return $tokens;
	}

	/**
	 * Method contact_tokens
	 *
	 * @param  mixed $tokens
	 * @param  mixed $identifier
	 * @return void
	 */
	public function contact_tokens( $tokens, $trigger_code ) {

		$prefix = __( 'User', 'uncanny-automator' );

		if ( $this->is_userless( $trigger_code ) ) {
			$prefix = __( 'Contact', 'uncanny-automator' );
		}

		$contact_tokens = array(
			array(
				'tokenId'   => 'CONTACT_TAGS',
				/* translators: 1. User or Contact */
				'tokenName' => sprintf( __( "%s's tags", 'uncanny-automator' ), $prefix ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_LISTS',
				/* translators: 1. User or Contact */
				'tokenName' => sprintf( __( "%s's lists", 'uncanny-automator' ), $prefix ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_STATUS',
				/* translators: 1. User or Contact */
				'tokenName' => sprintf( __( "%s's status", 'uncanny-automator' ), $prefix ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_STATUS_ID',
				/* translators: 1. User or Contact */
				'tokenName' => sprintf( __( "%s's status ID", 'uncanny-automator' ), $prefix ),
				'tokenType' => 'text',
			),
		);

		foreach ( $contact_tokens as &$token ) {
			$token['tokenIdentifier'] = $trigger_code;
		}

		return array_merge( $tokens, $contact_tokens );
	}

	/**
	 * Method additional_contact_tokens
	 *
	 * @param  mixed $tokens
	 * @param  mixed $identifier
	 * @return void
	 */
	public function additional_contact_tokens( $tokens, $trigger_code ) {

		$contact_tokens = array(
			array(
				'tokenId'   => 'CONTACT_ID',
				'tokenName' => __( "Contact's ID", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_EMAIL',
				'tokenName' => __( "Contact's email address", 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'CONTACT_FNAME',
				'tokenName' => __( "Contact's first name", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_LNAME',
				'tokenName' => __( "Contact's last name", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_PHONE',
				'tokenName' => __( "Contact's phone number", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_COUNTRY',
				'tokenName' => __( "Contact's country", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_STATE',
				'tokenName' => __( "Contact's state", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_TIMEZONE',
				'tokenName' => __( "Contact's timezone", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_TYPE',
				'tokenName' => __( "Contact's type", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_SOURCE',
				'tokenName' => __( "Contact's source", 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTACT_CREATION_DATE',
				'tokenName' => __( "Contact's creation date", 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'CONTACT_MODIFIED_DATE',
				'tokenName' => __( "Contact's last modified date", 'uncanny-automator' ),
				'tokenType' => 'date',
			),
		);

		foreach ( $contact_tokens as &$token ) {
			$token['tokenIdentifier'] = $trigger_code;
		}

		return array_merge( $tokens, $contact_tokens );
	}

	/**
	 * Method save_token_data
	 *
	 * @param  mixed $args
	 * @param  mixed $trigger
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {

		if ( 'AUTONAMI' !== $trigger->get_integration() ) {
			return;
		}

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_code = $args['entry_args']['code'];

		$log_entry = $args['trigger_entry'];

		$data = array_shift( $args['trigger_args'] );

		if ( in_array( $trigger_code, $this->get_list_triggers(), true ) ) {

			if ( $data instanceof \BWFCRM_Lists ) {
				$this->save_list_tokens( $data, $log_entry );
			}
		}

		if ( in_array( $trigger_code, $this->get_tag_triggers(), true ) ) {

			if ( $data instanceof \BWFCRM_Tag ) {
				$this->save_tag_tokens( $data, $log_entry );
			}
		}

		$bwfcrm_contact = array_shift( $args['trigger_args'] );

		if ( $bwfcrm_contact instanceof \BWFCRM_Contact ) {
			$this->save_contact_tokens( $bwfcrm_contact, $log_entry );
		}

		do_action( 'automator_autonami_save_tokens', $data, $bwfcrm_contact, $trigger_code, $log_entry );

	}

	/**
	 * Method save_list_tokens
	 *
	 * @param  mixed $list
	 * @param  mixed $entry
	 * @return void
	 */
	public function save_list_tokens( $list, $entry ) {
		Automator()->db->token->save( 'LIST', $list->get_name(), $entry );
		Automator()->db->token->save( 'LIST_ID', $list->get_id(), $entry );
	}

	/**
	 * Method save_tag_tokens
	 *
	 * @param  mixed $tag
	 * @param  mixed $entry
	 * @return void
	 */
	public function save_tag_tokens( $tag, $entry ) {
		Automator()->db->token->save( 'TAG', $tag->get_name(), $entry );
		Automator()->db->token->save( 'TAG_ID', $tag->get_id(), $entry );
	}

	/**
	 * Method save_contact_tokens
	 *
	 * @param  mixed $bwfcrm_contact
	 * @param  mixed $entry
	 * @return void
	 */
	public function save_contact_tokens( $bwfcrm_contact, $entry ) {

		$contact = $bwfcrm_contact->contact;

		Automator()->db->token->save( 'CONTACT_ID', $contact->get_id(), $entry );
		Automator()->db->token->save( 'CONTACT_EMAIL', $contact->get_email(), $entry );
		Automator()->db->token->save( 'CONTACT_FNAME', $contact->get_f_name(), $entry );
		Automator()->db->token->save( 'CONTACT_LNAME', $contact->get_l_name(), $entry );
		Automator()->db->token->save( 'CONTACT_PHONE', $contact->get_contact_no(), $entry );
		Automator()->db->token->save( 'CONTACT_COUNTRY', $contact->get_country(), $entry );
		Automator()->db->token->save( 'CONTACT_STATE', $contact->get_state(), $entry );
		Automator()->db->token->save( 'CONTACT_TIMEZONE', $contact->get_timezone(), $entry );
		Automator()->db->token->save( 'CONTACT_TYPE', $contact->get_type(), $entry );
		Automator()->db->token->save( 'CONTACT_SOURCE', $contact->get_source(), $entry );
		Automator()->db->token->save( 'CONTACT_TAGS', $this->comma_separated_tags( $bwfcrm_contact ), $entry );
		Automator()->db->token->save( 'CONTACT_LISTS', $this->comma_separated_lists( $bwfcrm_contact ), $entry );
		Automator()->db->token->save( 'CONTACT_CREATION_DATE', $contact->get_creation_date(), $entry );
		Automator()->db->token->save( 'CONTACT_MODIFIED_DATE', $contact->get_last_modified(), $entry );
		Automator()->db->token->save( 'CONTACT_STATUS', $this->get_status_name( $contact ), $entry );
		Automator()->db->token->save( 'CONTACT_STATUS_ID', $contact->get_status() . ' ', $entry );
	}

	/**
	 * Method get_status_name
	 *
	 * @param  mixed $contact
	 * @return void
	 */
	public function get_status_name( $contact ) {

		$status_id = absint( $contact->get_status() );

		switch ( $status_id ) {
			case 0:
				return __( 'Unverified', 'uncanny-automator' );
			case 1:
				return __( 'Subscribed', 'uncanny-automator' );
			case 2:
				return __( 'Bounced', 'uncanny-automator' );
			case 3:
				return __( 'Unsubscribed', 'uncanny-automator' );
			default:
				return __( 'Unknown', 'uncanny-automator' );
		}
	}



	/**
	 * Method comma_separated_lists
	 *
	 * @param  mixed $bwfcrm_contact
	 * @return string
	 */
	public function comma_separated_lists( $bwfcrm_contact ) {

		$output = array();

		$lists = $bwfcrm_contact->contact->get_lists();

		$list_objects = \BWFCRM_Lists::get_lists( $lists );

		foreach ( $list_objects as $list_obj ) {
			$output[] = $list_obj['name'];
		}

		return implode( ', ', $output );
	}

	/**
	 * Method comma_separated_lists
	 *
	 * @param  mixed $bwfcrm_contact
	 * @return string
	 */
	public function comma_separated_tags( $bwfcrm_contact ) {

		$output = array();

		$tags = $bwfcrm_contact->contact->get_tags();

		$tag_objects = \BWFCRM_Tag::get_tags( $tags );

		foreach ( $tag_objects as $tag_obj ) {
			$output[] = $tag_obj['name'];
		}

		return implode( ', ', $output );
	}

	/**
	 * Method parse_tokens
	 *
	 * @param  mixed $value
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return void
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$autonami_parse_tokens = array_merge(
			$this->get_list_triggers(),
			$this->get_tag_triggers()
		);

		if ( ! in_array( $pieces[1], $autonami_parse_tokens, true ) ) {
			return $value;
		}

		$meta_key = $pieces[2];

		$value = Automator()->db->token->get( $meta_key, $replace_args );

		return $value;

	}

}
