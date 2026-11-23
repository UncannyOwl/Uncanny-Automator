<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use WP_Term;

/**
 * Class Asgaros_Forum_Tokens
 *
 * Token groups shared by more than one trigger, as define/hydrate pairs. A
 * token ID is stored in recipes, so two triggers exposing "the same" token
 * must take it from the same pair.
 *
 * Hydrators read through the helper they are given: Free's helper, or Pro's,
 * which wraps the same lookups.
 *
 * @package Uncanny_Automator
 */
class Asgaros_Forum_Tokens {

	/**
	 * Tokens describing a forum.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function forum_tokens() {
		return array(
			array(
				'tokenId'   => 'FORUM_ID',
				'tokenName' => esc_html_x( 'Forum ID', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORUM_NAME',
				'tokenName' => esc_html_x( 'Forum name', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FORUM_CATEGORY',
				'tokenName' => esc_html_x( 'Forum category', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param Asgaros_Forum_Helpers $helpers  Free's helper or Pro's wrapper.
	 * @param int                   $forum_id The forum ID.
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_forum_tokens( $helpers, $forum_id ) {

		$forum_id = absint( $forum_id );
		$row      = $helpers->get_forum_row( $forum_id );
		$forum    = $helpers->get_forum();
		$category = '';

		if ( null !== $row && null !== $forum && absint( $row->parent_id ) > 0 ) {
			$category = (string) $forum->get_category_name( absint( $row->parent_id ) );
		}

		return array(
			'FORUM_ID'       => $forum_id,
			'FORUM_NAME'     => null !== $row ? (string) $row->name : '',
			'FORUM_CATEGORY' => $category,
		);
	}

	/**
	 * Tokens describing a topic and its forum.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function topic_tokens() {
		return array_merge(
			array(
				array(
					'tokenId'   => 'TOPIC_ID',
					'tokenName' => esc_html_x( 'Topic ID', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'TOPIC_TITLE',
					'tokenName' => esc_html_x( 'Topic title', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'TOPIC_URL',
					'tokenName' => esc_html_x( 'Topic URL', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'url',
				),
			),
			self::forum_tokens()
		);
	}

	/**
	 * @param Asgaros_Forum_Helpers $helpers  Free's helper or Pro's wrapper.
	 * @param int                   $topic_id The topic ID.
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_topic_tokens( $helpers, $topic_id ) {

		$topic_id = absint( $topic_id );
		$row      = $helpers->get_topic_row( $topic_id );

		return array_merge(
			array(
				'TOPIC_ID'    => $topic_id,
				'TOPIC_TITLE' => null !== $row ? (string) $row->name : '',
				'TOPIC_URL'   => null !== $row ? $helpers->get_topic_url( $topic_id ) : '',
			),
			self::hydrate_forum_tokens( $helpers, null !== $row ? absint( $row->parent_id ) : 0 )
		);
	}

	/**
	 * Tokens describing a post, its topic and its forum.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function post_tokens() {
		return array_merge(
			array(
				array(
					'tokenId'   => 'POST_ID',
					'tokenName' => esc_html_x( 'Post ID', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'POST_CONTENT',
					'tokenName' => esc_html_x( 'Post content', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'POST_URL',
					'tokenName' => esc_html_x( 'Post URL', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'url',
				),
			),
			self::topic_tokens()
		);
	}

	/**
	 * @param Asgaros_Forum_Helpers $helpers Free's helper or Pro's wrapper.
	 * @param int                   $post_id The post ID.
	 * @param string|null           $content The submitted body, when the hook passes it.
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_post_tokens( $helpers, $post_id, $content = null ) {

		$post_id  = absint( $post_id );
		$row      = $helpers->get_post_row( $post_id );
		$topic_id = null !== $row ? absint( $row->parent_id ) : 0;

		if ( null === $content ) {
			$content = null !== $row ? (string) $row->text : '';
		}

		return array_merge(
			array(
				'POST_ID'      => $post_id,
				'POST_CONTENT' => (string) $content,
				'POST_URL'     => null !== $row ? $helpers->get_post_url( $post_id, $topic_id ) : '',
			),
			self::hydrate_topic_tokens( $helpers, $topic_id )
		);
	}

	/**
	 * Tokens describing the author of a topic or post.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function author_tokens() {
		return array(
			array(
				'tokenId'   => 'AUTHOR_ID',
				'tokenName' => esc_html_x( 'Author ID', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'AUTHOR_NAME',
				'tokenName' => esc_html_x( 'Author display name', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'AUTHOR_EMAIL',
				'tokenName' => esc_html_x( 'Author email', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);
	}

	/**
	 * The name comes from Asgaros, so a guest or deleted author reads as the
	 * forum shows them. Null means the topic or post itself is gone: every
	 * value is empty.
	 *
	 * @param Asgaros_Forum_Helpers $helpers Free's helper or Pro's wrapper.
	 * @param int|null              $user_id The author's user ID (0 for a guest), or null.
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_author_tokens( $helpers, $user_id ) {

		if ( null === $user_id ) {
			return array(
				'AUTHOR_ID'    => 0,
				'AUTHOR_NAME'  => '',
				'AUTHOR_EMAIL' => '',
			);
		}

		$user_id = absint( $user_id );
		$forum   = $helpers->get_forum();
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		return array(
			'AUTHOR_ID'    => $user_id,
			'AUTHOR_NAME'  => null !== $forum ? (string) $forum->get_plain_username( $user_id ) : '',
			'AUTHOR_EMAIL' => false !== $user ? (string) $user->user_email : '',
		);
	}

	/**
	 * Tokens describing a reaction: who reacted and the reaction key.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function reaction_tokens() {
		return array(
			array(
				'tokenId'   => 'REACTOR_ID',
				'tokenName' => esc_html_x( 'Reactor user ID', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'REACTION',
				'tokenName' => esc_html_x( 'Reaction type', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param int    $user_id  The reactor's user ID.
	 * @param string $reaction The reaction key Asgaros stores (`up`, `down`, or a filtered key).
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_reaction_tokens( $user_id, $reaction ) {
		return array(
			'REACTOR_ID' => absint( $user_id ),
			'REACTION'   => (string) $reaction,
		);
	}

	/**
	 * Tokens describing a usergroup membership change.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public static function usergroup_tokens() {
		return array(
			array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'USERGROUP_ID',
				'tokenName' => esc_html_x( 'Usergroup ID', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'USERGROUP_NAME',
				'tokenName' => esc_html_x( 'Usergroup name', 'Asgaros Forum', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param Asgaros_Forum_Helpers $helpers      Free's helper or Pro's wrapper.
	 * @param int                   $user_id      The member's user ID.
	 * @param int                   $usergroup_id The usergroup term ID.
	 *
	 * @return array<string,mixed>
	 */
	public static function hydrate_usergroup_tokens( $helpers, $user_id, $usergroup_id ) {

		$usergroup = $helpers->get_usergroup( $usergroup_id );

		return array(
			'USER_ID'        => absint( $user_id ),
			'USERGROUP_ID'   => absint( $usergroup_id ),
			'USERGROUP_NAME' => $usergroup instanceof WP_Term ? (string) $usergroup->name : '',
		);
	}
}
