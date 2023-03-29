<?php

namespace Uncanny_Automator;

/**
 * Class Thrive_Ovation_Tokens
 *
 * @package Uncanny_Automator
 */
class Thrive_Ovation_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_thrive_ovation_tokens',
			array(
				$this,
				'thrive_ovation_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_thrive_ovation_tokens' ), 20, 6 );
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
			'automator_thrive_ovation_validate_common_triggers_tokens_save',
			array( 'TVO_TESTIMONIAL_SUBMITTED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations, true ) ) {
			$testimonial_data  = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $testimonial_data ) ) {
				Automator()->db->token->save( 'testimonial_data', maybe_serialize( $testimonial_data ), $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function thrive_ovation_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code             = (string) $args['triggers_meta']['code'];
		$trigger_meta_validations = apply_filters(
			'automator_thrive_ovation_validate_common_possible_triggers_tokens',
			array( 'TVO_TESTIMONIAL_SUBMITTED' ),
			$args
		);

		if ( ! in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			return $tokens;
		}

		$fields = array(
			array(
				'tokenId'         => 'TESTIMONIAL_ID',
				'tokenName'       => __( 'Testimonial ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_TITLE',
				'tokenName'       => __( 'Testimonial title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_CONTENT',
				'tokenName'       => __( 'Testimonial content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_DATE',
				'tokenName'       => __( 'Date', 'uncanny-automator' ),
				'tokenType'       => 'date',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_AUTHOR',
				'tokenName'       => __( 'Full name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_AUTHOR_EMAIL',
				'tokenName'       => __( 'Email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_AUTHOR_ROLE',
				'tokenName'       => __( 'Role/Occupation', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_AUTHOR_WEBSITE',
				'tokenName'       => __( 'Website URL', 'uncanny-automator' ),
				'tokenType'       => 'url',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'TESTIMONIAL_STATUS',
				'tokenName'       => __( 'Testimonial status', 'uncanny-automator' ),
				'tokenType'       => 'text',
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
	public function parse_thrive_ovation_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1], $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_thrive_ovation_validate_common_triggers_tokens_parse',
			array( 'TVO_TESTIMONIAL_SUBMITTED' ),
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

		$testimonial_data = maybe_unserialize( Automator()->db->token->get( 'testimonial_data', $replace_args ) );

		switch ( $pieces[2] ) {
			case 'TESTIMONIAL_ID':
				$value = $testimonial_data['testimonial_id'];
				break;
			case 'TESTIMONIAL_TITLE':
				$value = $testimonial_data['testimonial_title'];
				break;
			case 'TESTIMONIAL_CONTENT':
				$value = $testimonial_data['testimonial_content'];
				break;
			case 'TESTIMONIAL_DATE':
				$value = get_the_date( '', $testimonial_data['testimonial_id'] );
				break;
			case 'TESTIMONIAL_AUTHOR':
				$value = $testimonial_data['testimonial_author'];
				break;
			case 'TESTIMONIAL_AUTHOR_EMAIL':
				$value = $testimonial_data['testimonial_author_email'];
				break;
			case 'TESTIMONIAL_AUTHOR_ROLE':
				$value = $testimonial_data['testimonial_author_role'];
				break;
			case 'TESTIMONIAL_AUTHOR_WEBSITE':
				$value = $testimonial_data['testimonial_author_website'];
				break;
			case 'TESTIMONIAL_STATUS':
				$value = tvo_get_testimonial_status_text( get_post_meta( $testimonial_data['testimonial_id'], TVO_STATUS_META_KEY, true ) );
				break;
		}

		return $value;
	}

}
