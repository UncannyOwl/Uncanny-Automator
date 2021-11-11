<?php

namespace Uncanny_Automator;

/**
 * Class Wp_Post_Tokens
 * @package Uncanny_Automator
 */
class Wp_Post_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * WP_Anon_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_wp_post_tokens' ], 20, 6 );
		add_filter( 'automator_maybe_trigger_wp_userspost_tokens', [ $this, 'wp_possible_tokens' ], 20, 2 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {

			$status = true;
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wp_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'POSTTITLE',
				'tokenName'       => __( "Post title", 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'POSTID',
				'tokenName'       => __( "Post ID", 'uncanny_automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'POSTURL',
				'tokenName'       => __( "Post URL", 'uncanny-automator' ),
				'tokenType'       => 'url',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'POSTCONTENT',
				'tokenName'       => __( "Post content", 'uncanny_automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'POSTIMAGEURL',
				'tokenName'       => __( "Post featured image URL", 'uncanny-automator' ),
				'tokenType'       => 'url',
				'tokenIdentifier' => $trigger_meta,
			],
			[
				'tokenId'         => 'POSTIMAGEID',
				'tokenName'       => __( "Post featured image ID", 'uncanny_automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			],
		];

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 *
	 * @param int $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_wp_post_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id = 0, $replace_args = array() ) {
		$tokens = [
			'WPTAXONOMIES',
			'SPECIFICTAXONOMY',
			'POSTSTATUSUPDATED',
			'POSTAUTHORFN',
			'POSTAUTHORLN',
			'POSTAUTHORDN',
			'POSTAUTHOREMAIL',
			'POSTCONTENT',
			'POSTURL',
			'POSTID',
			'POSTTITLE',
			'POSTIMAGEURL',
			'POSTIMAGEID',
			'WPTAXONOMYTERM',
			'WPPOSTTYPES',
			'POSTCOMMENTCONTENT',
			'POSTCOMMENTDATE',
			'POSTCOMMENTEREMAIL',
			'POSTCOMMENTERNAME',
			'POSTCOMMENTSTATUS',
			'WPPOST',
			'WPPOST_ID',
			'WPPOST_URL'
		];

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];

			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						switch ( $meta_field ) {
							case 'WPTAXONOMIES':
								$value = $trigger['meta']['WPTAXONOMIES_readable'];
								break;
							case 'SPECIFICTAXONOMY':
								$value = $trigger['meta']['SPECIFICTAXONOMY_readable'];
								break;
							default:
								global $wpdb;
								$trigger_id = $trigger['ID'];
								$meta_value = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE '%{$meta_field}' AND automator_trigger_id = {$trigger_id} ORDER BY ID DESC LIMIT 0,1" );
								if ( ! empty( $meta_value ) ) {
									$value = maybe_unserialize( $meta_value );
								}
						}
					}
				}
			}
		}

		return $value;
	}

}
