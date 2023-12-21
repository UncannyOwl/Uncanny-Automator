<?php

namespace Uncanny_Automator\Integrations\Ht_Knowledge_Base;

/**
 * Class Ht_Knowledge_Base_Helpers
 *
 * @package Uncanny_Automator
 */
class Ht_Knowledge_Base_Helpers {

	/**
	 * @param $is_any
	 * @param $is_all
	 *
	 * @return array
	 */
	public function get_all_ht_kb_articles( $is_any = false, $is_all = false ) {

		$args = array(
			'post_type'      => 'ht_kb',
			'posts_per_page' => 99999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options  = Automator()->helpers->recipe->options->wp_query( $args, $is_any, esc_attr__( 'Any article', 'uncanny-automator' ) );
		$articles = array();

		if ( true === $is_all ) {
			$articles[] = array(
				'text'  => 'All articles',
				'value' => '-1',
			);
		}
		foreach ( $options as $i => $option ) {
			$articles[] = array(
				'text'  => $option,
				'value' => $i,
			);
		}

		return $articles;

	}

	/**
	 * @param $type
	 *
	 * @return array[]
	 */
	public function common_tokens_for_article( $type = 'user' ) {

		$common_tokens = array(
			array(
				'tokenId'   => 'ARTICLE_TITLE',
				'tokenName' => __( 'Article title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARTICLE_ID',
				'tokenName' => __( 'Article ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ARTICLE_URL',
				'tokenName' => __( 'Article URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'ARTICLE_CATEGORIES',
				'tokenName' => __( 'Article categories', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARTICLE_TAGS',
				'tokenName' => __( 'Article tags', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARTICLE_COMMENT',
				'tokenName' => __( 'Comments', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'VOTE_DATE',
				'tokenName' => __( 'Date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
		);

		if ( 'user' === $type ) {
			$common_tokens[] = array(
				'tokenId'   => 'USERNAME',
				'tokenName' => __( 'Username', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
		}

		if ( 'anon' === $type ) {
			$common_tokens[] = array(
				'tokenId'   => 'USER_IP',
				'tokenName' => __( 'IP', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
		}

		return $common_tokens;

	}

	public function parse_common_token_values( $hook_args ) {
		list( $object, $article_id, $direction ) = $hook_args;

		// Generate array of empty default values.
		$defaults       = wp_list_pluck( $this->common_tokens_for_article(), 'tokenId' );
		$tokens         = array_fill_keys( $defaults, '' );
		$categories     = get_the_terms( $article_id, 'ht_kb_category' );
		$tags           = get_the_terms( $article_id, 'ht_kb_tag' );
		$all_categories = array();
		$all_tags       = array();
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$all_categories[] = $category->name;
			}
		}

		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$all_tags[] = $tag->name;
			}
		}

		$tokens['ARTICLE_TITLE']      = get_the_title( $article_id );
		$tokens['ARTICLE_ID']         = $article_id;
		$tokens['ARTICLE_URL']        = get_permalink( $article_id );
		$tokens['ARTICLE_CATEGORIES'] = $all_categories;
		$tokens['ARTICLE_TAGS']       = $all_tags;
		$tokens['ARTICLE_COMMENT']    = $object->comments;
		$tokens['USERNAME']           = get_userdata( $object->user_id )->user_login;
		$tokens['USER_IP']            = $object->ip;
		$tokens['VOTE_DATE']          = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $object->time );

		return $tokens;

	}
}
