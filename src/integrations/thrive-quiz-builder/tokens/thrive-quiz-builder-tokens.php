<?php

namespace Uncanny_Automator;

class Thrive_Quiz_Builder_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_thrive_qb_tokens',
			array(
				$this,
				'thrive_qb_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_thrive_qb_tokens' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'], $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_thrive_qb_validate_common_triggers_tokens_save',
			array( 'TQB_QUIZ_COMPLETED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations, true ) ) {
			$quiz_data         = array_shift( $args['trigger_args'] );
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $quiz_data ) ) {
				Automator()->db->token->save( 'quiz_data', maybe_serialize( $quiz_data ), $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function thrive_qb_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code             = (string) $args['triggers_meta']['code'];
		$trigger_meta_validations = apply_filters(
			'automator_thrive_qb_validate_common_possible_triggers_tokens',
			array( 'TQB_QUIZ_COMPLETED' ),
			$args
		);

		if ( ! in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			return $tokens;
		}

		$fields = array(
			array(
				'tokenId'         => 'TQB_QUIZ_ID',
				'tokenName'       => __( 'Quiz ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TQB_QUIZ_TITLE',
				'tokenName'       => __( 'Quiz title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TQB_QUIZ_RESULT',
				'tokenName'       => __( 'Quiz result', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TQB_QUIZ_TYPE',
				'tokenName'       => __( 'Quiz type', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TQB_USER_ID',
				'tokenName'       => __( 'User ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TQB_USER_EMAIL',
				'tokenName'       => __( 'User email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_code,
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_thrive_qb_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1], $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_thrive_qb_validate_common_triggers_tokens_parse',
			array( 'TQB_QUIZ_COMPLETED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$quiz_data  = maybe_unserialize( Automator()->db->token->get( 'quiz_data', $replace_args ) );
		$to_replace = $pieces[2];

		switch ( $to_replace ) {
			case 'TQB_QUIZ_ID':
				$value = $quiz_data['quiz_id'];
				break;
			case 'TQB_QUIZ_TITLE':
				$value = $quiz_data['quiz_name'];
				break;
			case 'TQB_QUIZ_RESULT':
				$value = $quiz_data['result'];
				break;
			case 'TQB_USER_EMAIL':
				$value = $quiz_data['user_email'];
				break;
			case 'TQB_USER_ID':
				$value = $quiz_data['user_id'];
				break;
			case 'TQB_QUIZ_TYPE':
				$quiz_type = get_post_meta( $quiz_data['quiz_id'], 'tqb_quiz_type', true );
				if ( is_array( $quiz_type ) ) {
					$quiz_type = array_shift( $quiz_type );
				}
				$value = ucfirst( str_replace( '_', ' ', $quiz_type ) );
				break;
		}

		return $value;
	}

}
