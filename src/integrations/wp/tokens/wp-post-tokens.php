<?php

namespace Uncanny_Automator;

/**
 * Class Wp_Post_Tokens
 *
 * @package Uncanny_Automator
 */
class Wp_Post_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * WP_Anon_Tokens constructor.
	 */
	public function __construct() {
		$codes = array(
			'userspost',
			'wpviewposttype',
			'viewcustompost',
			'WP_POST_PUBLISHED',
			'ELEM_POST_PUBLISHED',
			'WP_USER_POST_UPDATED',
			'ANON_POST_UPDATED_IN_TAXONOMY',
			'WP_ANON_POST_UPDATED',
			'WP_POST_PUBLISHED_IN_TAXONOMY',
			'WP_USER_POST_PUBLISHED',
		);
		foreach ( $codes as $code ) {
			$code = strtolower( $code );
			add_filter(
				'automator_maybe_trigger_wp_' . $code . '_tokens',
				array(
					$this,
					'wp_possible_tokens',
				),
				20,
				2
			);
		}

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wp_comments_tokens' ), 9000, 6 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wp_post_tokens' ), 9000, 6 );
		add_filter(
			'automator_maybe_trigger_wp_wpcommentreceived_tokens',
			array(
				$this,
				'wp_comment_possible_tokens',
			),
			20,
			2
		);

		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
	}

	/**
	 * @param $args
	 * @param $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$triggers = array( 'WP_POST_PUBLISHED', 'ELEM_POST_PUBLISHED' );

		if ( in_array( $args['entry_args']['code'], $triggers, true ) ) {
			$wp_post_data                                        = $args['trigger_args'];
			list( $post_id, $wp_post, $update, $wp_post_before ) = $wp_post_data;
			if ( isset( $post_id ) && ! empty( $post_id ) ) {
				Automator()->db->token->save( 'post_id', $post_id, $args['trigger_entry'] );
			}
		}

		$post_update_triggers = array(
			'WP_ANON_POST_UPDATED',
			'WP_USER_POST_UPDATED',
			'ANON_POST_UPDATED_IN_TAXONOMY',
			'WP_USER_POST_PUBLISHED',
		);

		if ( in_array( $args['entry_args']['code'], $post_update_triggers, true ) ) {
			$wp_post_data                                     = $args['trigger_args'];
			list( $post_id, $wp_post_after, $wp_post_before ) = $wp_post_data;
			if ( isset( $post_id ) && ! empty( $post_id ) ) {
				Automator()->db->token->save( 'post_id', $post_id, $args['trigger_entry'] );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wp_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$post_title   = 'POSTTITLE';
		$post_id      = 'POSTID';
		$post_url     = 'POSTURL';
		$post_excerpt = 'POSTEXCERPT';

		$trigger_code = $args['triggers_meta']['code'];

		if ( 'WPVIEWPOSTTYPE' === $trigger_code ) {
			$post_title = 'WPPOST';
			$post_id    = 'WPPOST_ID';
			$post_url   = 'WPPOST_URL';
		}
		if ( 'VIEWCUSTOMPOST' === $trigger_code ) {
			$post_title   = 'WPCUSTOMPOST';
			$post_id      = 'WPCUSTOMPOST_ID';
			$post_url     = 'WPCUSTOMPOST_URL';
			$post_excerpt = 'WPCUSTOMPOST_EXCERPT';
		}

		$fields = array(
			array(
				'tokenId'         => $post_title,
				'tokenName'       => __( 'Post title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => $post_id,
				'tokenName'       => __( 'Post ID', 'uncanny_automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => $post_url,
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
				'tokenId'         => $post_excerpt,
				'tokenName'       => __( 'Post excerpt', 'uncanny_automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'WPPOSTTYPES',
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
				'tokenId'         => 'POSTAUTHORID',
				'tokenName'       => __( 'Post author ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
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

		return $tokens;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]
	 */
	public function wp_comment_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_code = $args['triggers_meta']['code'];

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
				'tokenId'         => 'WPPOSTTYPES',
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
				'tokenId'         => 'POSTAUTHORID',
				'tokenName'       => __( 'Post author ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTAUTHORURL',
				'tokenName'       => __( 'Post author URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTID',
				'tokenName'       => __( 'Comment ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTERNAME',
				'tokenName'       => __( 'Commenter name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTEREMAIL',
				'tokenName'       => __( 'Commenter email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTERWEBSITE',
				'tokenName'       => __( 'Commenter website', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTCONTENT',
				'tokenName'       => __( 'Comment content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTURL',
				'tokenName'       => __( 'Comment URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTDATE',
				'tokenName'       => __( 'Comment submitted date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'POSTCOMMENTSTATUS',
				'tokenName'       => __( 'Comment status', 'uncanny-automator' ),
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
	 * @return false|int|mixed|string|\WP_Error
	 */
	public function parse_wp_comments_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( ! in_array( 'WPCOMMENTRECEIVED', $pieces, true ) && ! in_array( 'WPSUBMITCOMMENT', $pieces, true ) ) {
			return $value;
		}

		$to_replace = $pieces[2];
		$comment_id = Automator()->db->token->get( 'comment_id', $replace_args );
		$comment    = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			return $value;
		}

		switch ( $to_replace ) {
			case 'POSTTITLE':
			case 'WPPOSTCOMMENTS':
				$value = get_the_title( $comment->comment_post_ID );
				break;
			case 'WPPOSTTYPES':
				$value = get_post_type( $comment->comment_post_ID );
				break;
			case 'POSTURL':
			case 'WPPOSTCOMMENTS_URL':
				$value = get_permalink( $comment->comment_post_ID );
				break;
			case 'POSTEXCERPT':
			case 'WPPOSTCOMMENTS_EXCERPT':
				$value = get_the_excerpt( $comment->comment_post_ID );
				break;
			case 'POSTCONTENT':
				$value = get_the_content( $comment->comment_post_ID );
				break;
			case 'POSTIMAGEID':
			case 'WPPOSTCOMMENTS_THUMB_ID':
				$value = get_post_thumbnail_id( $comment->comment_post_ID );
				break;
			case 'POSTIMAGEURL':
			case 'WPPOSTCOMMENTS_THUMB_URL':
				$value = get_the_post_thumbnail_url( $comment->comment_post_ID, apply_filters( 'automator_token_post_featured_image_size', 'full', $comment->comment_post_ID, $to_replace ) );
				break;
			case 'POSTAUTHORFN':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = get_the_author_meta( 'user_firstname', $author_id );
				break;
			case 'POSTAUTHORLN':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = get_the_author_meta( 'user_lastname', $author_id );
				break;
			case 'POSTAUTHORDN':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = get_the_author_meta( 'display_name', $author_id );
				break;
			case 'POSTAUTHOREMAIL':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = get_the_author_meta( 'user_email', $author_id );
				break;
			case 'POSTAUTHORID':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = $author_id;
				break;
			case 'POSTAUTHORURL':
				$author_id = get_post_field( 'post_author', $comment->comment_post_ID );
				$value     = get_the_author_meta( 'url', $author_id );
				break;
			case 'POSTCOMMENTID':
			case 'POSTCOMMENT_ID':
			case 'COMMENTID':
				$value = $comment->comment_ID;
				break;
			case 'POSTCOMMENTCONTENT':
				$value = $comment->comment_content;
				break;
			case 'POSTCOMMENTERNAME':
				$value = $comment->comment_author;
				break;
			case 'POSTCOMMENTEREMAIL':
				$value = $comment->comment_author_email;
				break;
			case 'POSTCOMMENTERWEBSITE':
				$value = $comment->comment_author_url;
				break;
			case 'COMMENTPARENT':
				$value = get_comment_link( $comment->comment_parent );
				break;
			case 'POSTCOMMENTDATE':
				$value = sprintf(
				/* translators: 1: Comment date, 2: Comment time. */
					__( '%1$s at %2$s' ),
					/* translators: Publish box date format, see https://www.php.net/manual/datetime.format.php */
					date_i18n( _x( 'M j, Y', 'publish box date format' ), strtotime( $comment->comment_date ) ),
					/* translators: Publish box time format, see https://www.php.net/manual/datetime.format.php */
					date_i18n( _x( 'H:i', 'publish box time format' ), strtotime( $comment->comment_date ) )
				);
				break;
			case 'POSTCOMMENTURL':
				$value = get_comment_link( $comment );
				break;
			case 'POSTCOMMENTSTATUS':
				$value = ( $comment->comment_approved === 1 ) ? 'approved' : 'pending';
				break;
			case 'NUMTIMES':
				$value = absint( $replace_args['run_number'] );
				break;
			case 'POSTID':
			case 'WPPOSTCOMMENTS_ID':
			default:
				$value = $comment->comment_post_ID;
				break;
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return false|int|mixed|string|\WP_Error
	 */
	public function parse_wp_post_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		if (
			! in_array( 'USERSPOST', $pieces, true ) &&
			! in_array( 'WPVIEWPOSTTYPE', $pieces, true ) &&
			! in_array( 'VIEWPOST', $pieces, true ) &&
			! in_array( 'VIEWPAGE', $pieces, true ) &&
			! in_array( 'VIEWCUSTOMPOST', $pieces, true ) &&
			! in_array( 'WP_POST_PUBLISHED', $pieces, true ) &&
			! in_array( 'ELEM_POST_PUBLISHED', $pieces, true ) &&
			! in_array( 'WP_USER_POST_UPDATED', $pieces, true ) &&
			! in_array( 'WP_POST_PUBLISHED_IN_TAXONOMY', $pieces, true ) &&
			! in_array( 'WP_ANON_POST_UPDATED', $pieces, true ) &&
			! in_array( 'WP_USER_POST_PUBLISHED', $pieces, true ) &&
			! in_array( 'ANON_POST_UPDATED_IN_TAXONOMY', $pieces, true )
		) {
			return $value;
		}

		$to_replace = $pieces[2];
		$post_id    = Automator()->db->token->get( 'post_id', $replace_args );
		$post       = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return $value;
		}

		switch ( $to_replace ) {
			case 'POSTTITLE':
			case 'WPPOST':
			case 'WPPAGE':
			case 'WPCUSTOMPOST':
				$value = $post->post_title;
				break;
			case 'WPPOST_TYPE':
			case 'WPPOSTTYPES':
			case 'WPPOSTTYPES_TYPE':
			case 'POSTTYPE':
				$value = $post->post_type;
				break;
			case 'WPPOSTTYPES_URL':
			case 'POSTURL':
			case 'WPPOST_URL':
			case 'WPPAGE_URL':
			case 'WPCUSTOMPOST_URL':
				$value = get_permalink( $post->ID );
				break;
			case 'POSTEXCERPT':
			case 'WPPOST_EXCERPT':
			case 'WPCUSTOMPOST_EXCERPT':
			case 'WPPAGE_EXCERPT':
			case 'WPPOSTTYPES_EXCERPT':
				$value = Automator()->utilities->automator_get_the_excerpt( $post->ID );
				break;
			case 'POSTSTATUSUPDATED':
				$value = $post->post_status;
				break;
			case 'POSTCONTENT':
			case 'WPPOST_CONTENT':
			case 'WPPAGE_CONTENT':
			case 'WPPOSTTYPES_CONTENT':
				$value = $post->post_content;
				break;
			case 'WPPOSTTYPES_THUMB_ID':
			case 'WPPOST_THUMB_ID':
			case 'WPPAGE_THUMB_ID':
			case 'POSTIMAGEID':
				$value = get_post_thumbnail_id( $post->ID );
				break;
			case 'WPPOSTTYPES_THUMB_URL':
			case 'WPPOST_THUMB_URL':
			case 'WPPAGE_THUMB_URL':
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
			case 'POSTAUTHORID':
				$value = $post->post_author;
				break;
			case 'POSTAUTHORURL':
				$value = get_the_author_meta( 'url', $post->post_author );
				break;
			case 'NUMTIMES':
				$value = absint( $replace_args['run_number'] );
				break;
			case 'POSTID':
			case 'WPPOSTTYPES_ID':
			case 'WPPOST_ID':
			case 'WPPAGE_ID':
			case 'WPCUSTOMPOST_ID':
				$value = $post->ID;
				break;
			case 'WPTAXONOMYTERM':
				$value = Automator()->db->token->get( 'WPTAXONOMYTERM', $replace_args );
				$value = maybe_unserialize( $value );
				break;
			case 'WPTAXONOMIES':
				$value = Automator()->db->token->get( 'WPTAXONOMIES', $replace_args );
				$value = maybe_unserialize( $value );
				break;
			default:
				global $wpdb;
				$trigger_id = absint( $pieces[0] );
				$entry      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$to_replace", $trigger_id ) );
				$value      = maybe_unserialize( $entry );
				break;
		}

		return $value;
	}
}
