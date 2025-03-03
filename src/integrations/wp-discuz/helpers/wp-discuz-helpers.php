<?php

namespace Uncanny_Automator\Integrations\Wp_Discuz;

use Uncanny_Automator\Wp_Helpers;

/**
 * Class Wp_Discuz_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Discuz_Helpers {

	/**
	 * @return array
	 */
	public function get_all_post_types_options() {
		$wp_helper      = new Wp_Helpers();
		$all_post_types = $wp_helper->all_wp_post_types();
		$options        = array();
		foreach ( $all_post_types['options'] as $k => $option ) {
			$options[] = array(
				'text'  => $option,
				'value' => $k,
			);
		}

		return $options;
	}

	/**
	 * @return array
	 */
	public function get_all_posts_options() {
		$wp_helper      = new Wp_Helpers();
		$all_post_types = $wp_helper->all_posts();
		$options        = array();
		foreach ( $all_post_types['options'] as $k => $option ) {
			$options[] = array(
				'text'  => $option,
				'value' => $k,
			);
		}

		return $options;
	}

	/**
	 * @return void
	 */
	public function get_all_posts_by_post_type() {
		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();
		$options = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) || empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
			echo wp_json_encode( $options );
			die();
		}
		$post_type = automator_filter_input( 'value', INPUT_POST );

		$args       = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => apply_filters( 'automator_select_posts_by_post_type_limit', 999, $post_type ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);
		$posts_list = Automator()->helpers->recipe->options->wp_query( $args, true, esc_html__( 'Any post', 'uncanny-automator' ) );

		foreach ( $posts_list as $post_id => $post_title ) {
			// Check if the post title is defined
			// translators: 1: Post ID
			$post_title = ! empty( $post_title ) ? $post_title : sprintf( esc_html__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post_id );

			$options[] = array(
				'value' => $post_id,
				'text'  => $post_title,
			);
		}

		echo wp_json_encode( $options );
		die();
	}

	/**
	 * @return array[]
	 */
	public function wpDiscuz_common_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTID',
				'tokenName' => esc_html__( 'Post ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTTITLE',
				'tokenName' => esc_html__( 'Post title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTURL',
				'tokenName' => esc_html__( 'Post URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTCONTENT',
				'tokenName' => esc_html__( 'Post content (raw)', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCONTENT_BEAUTIFIED',
				'tokenName' => esc_html__( 'Post content (formatted)', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTEXCERPT',
				'tokenName' => esc_html__( 'Post excerpt', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPPOSTTYPES',
				'tokenName' => esc_html__( 'Post type', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTIMAGEURL',
				'tokenName' => esc_html__( 'Post featured image URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTIMAGEID',
				'tokenName' => esc_html__( 'Post featured image ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTAUTHORID',
				'tokenName' => esc_html__( 'Post author ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTAUTHORURL',
				'tokenName' => esc_html__( 'Post author URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTAUTHORFN',
				'tokenName' => esc_html__( 'Post author first name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHORLN',
				'tokenName' => esc_html__( 'Post author last name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHORDN',
				'tokenName' => esc_html__( 'Post author display name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHOREMAIL',
				'tokenName' => esc_html__( 'Post author email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'POSTCOMMENTID',
				'tokenName' => esc_html__( 'Comment ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTCOMMENTERNAME',
				'tokenName' => esc_html__( 'Commenter name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTEREMAIL',
				'tokenName' => esc_html__( 'Commenter email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'POSTCOMMENTERWEBSITE',
				'tokenName' => esc_html__( 'Commenter website', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTCONTENT',
				'tokenName' => esc_html__( 'Comment content', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTURL',
				'tokenName' => esc_html__( 'Comment URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTCOMMENTDATE',
				'tokenName' => esc_html__( 'Comment submitted date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'POSTCOMMENTSTATUS',
				'tokenName' => esc_html__( 'Comment status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $post_id
	 * @param $comment_id
	 * @param $author_id
	 *
	 * @return array
	 */
	public function parse_common_token_values( $post_id, $comment_id, $author_id ) {
		$post    = get_post( $post_id );
		$comment = get_comment( $comment_id );

		return array(
			'POSTTITLE'              => $post->post_title,
			'POSTID'                 => $post_id,
			'POSTURL'                => get_permalink( $post_id ),
			'POSTCONTENT'            => $post->post_content,
			'POSTCONTENT_BEAUTIFIED' => str_replace( ']]>', ']]&gt;', $post->post_content ),
			'POSTEXCERPT'            => $post->post_excerpt,
			'WPPOSTTYPES'            => $post->post_type,
			'POSTIMAGEURL'           => get_the_post_thumbnail_url( $post_id ),
			'POSTIMAGEID'            => get_post_thumbnail_id( $post_id ),
			'POSTAUTHORID'           => $author_id,
			'POSTAUTHORFN'           => get_the_author_meta( 'user_firstname', $author_id ),
			'POSTAUTHORLN'           => get_the_author_meta( 'user_lastname', $author_id ),
			'POSTAUTHORDN'           => get_the_author_meta( 'display_name', $author_id ),
			'POSTAUTHOREMAIL'        => get_the_author_meta( 'user_email', $author_id ),
			'POSTAUTHORURL'          => get_the_author_meta( 'url', $author_id ),
			'POSTCOMMENTID'          => $comment_id,
			'POSTCOMMENTERNAME'      => $comment->comment_author,
			'POSTCOMMENTEREMAIL'     => $comment->comment_author_email,
			'POSTCOMMENTERWEBSITE'   => $comment->comment_author_url,
			'POSTCOMMENTCONTENT'     => $comment->comment_content,
			'POSTCOMMENTURL'         => get_comment_link( $comment_id ),
			'POSTCOMMENTDATE'        => esc_html(
				sprintf(
					// translators: 1: Date, 2: Time
					__( '%1$s at %2$s', 'uncanny-automator' ),
					date_i18n(
						_x( 'M j, Y', 'publish box date format', 'uncanny-automator' ),
						strtotime( $comment->comment_date )
					),
					date_i18n(
						_x( 'H:i', 'publish box time format', 'uncanny-automator' ),
						strtotime( $comment->comment_date )
					)
				)
			),
			'POSTCOMMENTSTATUS'      => $comment->comment_approved ? 'approved' : 'pending',
		);

	}
}
