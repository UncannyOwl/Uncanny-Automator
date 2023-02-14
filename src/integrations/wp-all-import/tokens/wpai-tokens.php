<?php

namespace Uncanny_Automator;

/**
 * Class Wpai_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpai_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wpai_tokens', array( $this, 'wpai_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wpai_tokens', array( $this, 'wpai_possible_post_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wpai_tokens' ), 20, 6 );
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
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wpai_save_common_triggers_tokens_for_posttype',
			array( 'WPAI_POSTTYPE_IMPORTED' ),
			$args
		);
		$trigger_code_validations = apply_filters(
			'automator_wpai_save_common_triggers_tokens_for_import',
			array( 'WPAI_IMPORT_COMPLETED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$post_id           = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $post_id ) ) {
				Automator()->db->token->save( 'post_id', $post_id, $trigger_log_entry );
			}
		}
		if ( in_array( $args['entry_args']['code'], $trigger_code_validations ) ) {
			$import_id         = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $import_id ) ) {
				Automator()->db->token->save( 'import_id', $import_id, $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function wpai_possible_post_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_wpai_common_possible_tokens_for_posttype',
			array( 'WPAI_POSTTYPE_IMPORTED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'POSTTITLE',
					'tokenName'       => __( 'Post title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTID',
					'tokenName'       => __( 'Post ID', 'uncanny_automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTURL',
					'tokenName'       => __( 'Post URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTCONTENT',
					'tokenName'       => __( 'Post content', 'uncanny_automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTEXCERPT',
					'tokenName'       => __( 'Post excerpt', 'uncanny_automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTTYPE',
					'tokenName'       => __( 'Post type', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTIMAGEURL',
					'tokenName'       => __( 'Post featured image URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTIMAGEID',
					'tokenName'       => __( 'Post featured image ID', 'uncanny_automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTAUTHORFN',
					'tokenName'       => __( 'Post author first name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTAUTHORLN',
					'tokenName'       => __( 'Post author last name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTAUTHORDN',
					'tokenName'       => __( 'Post author display name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTAUTHOREMAIL',
					'tokenName'       => __( 'Post author email', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'POSTAUTHORURL',
					'tokenName'       => __( 'Post author URL', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]|mixed
	 */
	public function wpai_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_wpai_common_possible_tokens_for_import',
			array( 'WPAI_IMPORT_COMPLETED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'IMPORT_ID',
					'tokenName'       => __( 'Import ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'IMPORT_FILE_NAME',
					'tokenName'       => __( 'Import file name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'IMPORT_DATE',
					'tokenName'       => __( 'Import date', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'IMPORT_TYPE',
					'tokenName'       => __( 'Import type', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'IMPORT_RUN_TIME',
					'tokenName'       => __( 'Import run time', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'IMPORT_SUMMARY',
					'tokenName'       => __( 'Import summary', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

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
	public function parse_wpai_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wpai_parse_common_tokens_post_related',
			array( 'WPAI_POSTTYPE_IMPORTED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		$trigger_code_validations = apply_filters(
			'automator_wpai_parse_common_tokens_related_import',
			array( 'WPAI_IMPORT_COMPLETED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) && ! array_intersect( $trigger_code_validations, $pieces ) ) {
			return $value;
		}

		$to_replace = $pieces[2];

		if ( in_array( $pieces[1], $trigger_code_validations, true ) ) {
			global $wpdb;
			$import_id      = Automator()->db->token->get( 'import_id', $replace_args );
			$import         = $wpdb->get_row( $wpdb->prepare( "SELECT `name`,last_activity from {$wpdb->prefix}pmxi_imports WHERE id = %d", $import_id ) );
			$import_history = $wpdb->get_row( $wpdb->prepare( "SELECT `type`,`time_run`,`summary` from {$wpdb->prefix}pmxi_history WHERE import_id = %d", $import_id ) );
		} elseif ( in_array( $pieces[1], $trigger_meta_validations, true ) ) {
			$post_id = Automator()->db->token->get( 'post_id', $replace_args );
			$post    = get_post( $post_id );
		}

		switch ( $to_replace ) {
			case 'POSTTITLE':
				$value = $post->post_title;
				break;
			case 'POSTTYPE':
				$value = $post->post_type;
				break;
			case 'POSTURL':
				$value = get_permalink( $post->ID );
				break;
			case 'POSTEXCERPT':
				$value = Automator()->utilities->automator_get_the_excerpt( $post->ID );
				break;
			case 'POSTCONTENT':
				$value = $post->post_content;
				break;
			case 'POSTIMAGEID':
				$value = get_post_thumbnail_id( $post->ID );
				break;
			case 'POSTIMAGEURL':
				$value = get_the_post_thumbnail_url( $post->ID, apply_filters( 'automator_token_post_featured_image_size', 'full', $post->ID, $to_replace ) );
				break;
			case 'POSTAUTHORFN':
				$value = get_the_author_meta( 'user_firstname', $post->post_author );
				break;
			case 'POSTAUTHORLN':
				$value = get_the_author_meta( 'user_lastname', $post->post_author );
				break;
			case 'POSTAUTHORDN':
				$value = get_the_author_meta( 'display_name', $post->post_author );
				break;
			case 'POSTAUTHOREMAIL':
				$value = get_the_author_meta( 'user_email', $post->post_author );
				break;
			case 'POSTAUTHORURL':
				$value = get_the_author_meta( 'url', $post->post_author );
				break;
			case 'NUMTIMES':
				$value = absint( $replace_args['run_number'] );
				break;
			case 'POSTID':
				$value = $post->ID;
				break;
			case 'IMPORT_ID':
				$value = $import_id;
				break;
			case 'IMPORT_FILE_NAME':
				$value = $import->name;
				break;
			case 'IMPORT_DATE':
				$value = get_date_from_gmt( $import->last_activity, 'm/d/Y g:i a' );
				break;
			case 'IMPORT_TYPE':
				$value = $import_history->type;
				break;
			case 'IMPORT_SUMMARY':
				$value = $import_history->summary;
				break;
			case 'IMPORT_RUN_TIME':
				$value = ( $import_history->time_run && is_numeric( $import_history->time_run ) ) ? gmdate( 'H:i:s', $import_history->time_run ) : '-';
				break;
		}

		return $value;
	}

}
