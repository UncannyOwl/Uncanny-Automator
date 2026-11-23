<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use AsgarosForum;
use AsgarosForumUserGroups;
use Uncanny_Automator\Recipe\Abstract_Helpers;
use WP_Error;
use WP_Term;

/**
 * Class Asgaros_Forum_Helpers
 *
 * Pickers, entity lookups and plugin calls shared by the Asgaros Forum parts.
 *
 * Asgaros keeps forums, topics and posts in its own tables, so the pickers
 * are remote-data queries on `$asgarosforum->tables`. Its `content->get_*()`
 * methods interpolate the ID into SQL, so every ID is `absint()`-ed before it
 * is handed over. Usergroups are terms, read through `AsgarosForumUserGroups`.
 *
 * @package Uncanny_Automator
 */
class Asgaros_Forum_Helpers extends Abstract_Helpers {

	/**
	 * The "Any …" dropdown value.
	 *
	 * @var string
	 */
	const ANY_VALUE = '-1';

	/**
	 * Maximum rows returned by any picker query.
	 *
	 * @var int
	 */
	const OPTIONS_LIMIT = 500;

	// =========================================================================
	// Remote-data segments. The bare segment puts "Any" first for triggers; the
	// `_strict` twin has none, for actions.
	// =========================================================================

	/**
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forums( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->build_forum_options( true ) );
	}

	/**
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forums_strict( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->build_forum_options( false ) );
	}

	/**
	 * A server-side topic search, for actions that pick a topic directly.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_strict( $request ): array {
		return $this->remote_data_success( $this->build_topic_options( $request->get_search_query() ) );
	}

	/**
	 * The chosen forum's topics, "Any topic" first.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_from_forum( $request ): array {
		return $this->remote_data_success( $this->topics_from_forum_options( $this->parent_value( $request, 'ASGAROS_FORUM_FORUM' ), true ) );
	}

	/**
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_from_forum_strict( $request ): array {
		return $this->remote_data_success( $this->topics_from_forum_options( $this->parent_value( $request, 'ASGAROS_FORUM_FORUM' ), false ) );
	}

	/**
	 * The chosen topic's posts, "Any post" first.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_from_topic( $request ): array {
		return $this->remote_data_success( $this->posts_from_topic_options( $this->parent_value( $request, 'ASGAROS_FORUM_TOPIC' ), true, true ) );
	}

	/**
	 * The chosen topic's replies. The first post is left out: "Delete a post"
	 * refuses it (Q5), and Asgaros offers no Delete on it either.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_from_topic_strict( $request ): array {
		return $this->remote_data_success( $this->posts_from_topic_options( $this->parent_value( $request, 'ASGAROS_FORUM_TOPIC' ), false, false ) );
	}

	/**
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_usergroups( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->build_usergroup_options( true ) );
	}

	/**
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_usergroups_strict( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->build_usergroup_options( false ) );
	}

	/**
	 * A parent field's value, with a custom value unwrapped (R15).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 * @param string              $code    The parent field's option code.
	 *
	 * @return string
	 */
	private function parent_value( $request, $code ) {

		$value = $request->get_field_value( $code );

		if ( 'automator_custom_value' === $value ) {
			$value = $request->get_field_value( $code . '_custom' );
		}

		return trim( (string) $value );
	}

	// =========================================================================
	// Option builders.
	// =========================================================================

	/**
	 * Forum options. A subforum is labelled with its parent forum, so nested
	 * forums sharing a name stay distinguishable.
	 *
	 * @param bool $include_any Prepend "Any forum".
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	public function build_forum_options( $include_any = true ) {

		$options = $include_any ? array( $this->any_option( esc_html_x( 'Any forum', 'Asgaros Forum', 'uncanny-automator' ) ) ) : array();
		$tables  = $this->get_tables();

		if ( null === $tables ) {
			return $options;
		}

		global $wpdb;

		$prepared = $wpdb->prepare(
			'SELECT id, name, parent_forum FROM `' . esc_sql( $tables->forums ) . '` ORDER BY sort ASC, name ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::OPTIONS_LIMIT
		);
		$forums   = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		$names = wp_list_pluck( (array) $forums, 'name', 'id' );

		foreach ( (array) $forums as $forum ) {
			$parent    = absint( $forum->parent_forum );
			$label     = isset( $names[ $parent ] ) ? $names[ $parent ] . ' › ' . $forum->name : $forum->name;
			$options[] = array(
				'text'  => esc_html( $label ),
				'value' => (string) absint( $forum->id ),
			);
		}

		return $options;
	}

	/**
	 * Topic options for an action's topic search, labelled with their forum.
	 * A numeric query looks one topic up by ID.
	 *
	 * @param string $search_query The typed search.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	public function build_topic_options( $search_query = '' ) {

		$options      = array();
		$search_query = trim( (string) $search_query );
		$tables       = $this->get_tables();

		if ( null === $tables ) {
			return $options;
		}

		global $wpdb;

		$sql = 'SELECT t.id, t.name, f.name AS forum_name FROM `' . esc_sql( $tables->topics ) . '` AS t'
			. ' LEFT JOIN `' . esc_sql( $tables->forums ) . '` AS f ON t.parent_id = f.id';

		if ( '' !== $search_query && is_numeric( $search_query ) ) {
			$prepared = $wpdb->prepare( $sql . ' WHERE t.id = %d ORDER BY t.id DESC LIMIT %d', absint( $search_query ), self::OPTIONS_LIMIT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( '' !== $search_query ) {
			$prepared = $wpdb->prepare( $sql . ' WHERE t.name LIKE %s ORDER BY t.id DESC LIMIT %d', '%' . $wpdb->esc_like( $search_query ) . '%', self::OPTIONS_LIMIT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$prepared = $wpdb->prepare( $sql . ' ORDER BY t.id DESC LIMIT %d', self::OPTIONS_LIMIT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		foreach ( (array) $wpdb->get_results( $prepared ) as $topic ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$options[] = array(
				'text'  => esc_html( empty( $topic->forum_name ) ? $topic->name : sprintf( '%s (%s)', $topic->name, $topic->forum_name ) ),
				'value' => (string) absint( $topic->id ),
			);
		}

		return $options;
	}

	/**
	 * Topics of one forum, newest first. "Any" or an unresolved forum offers
	 * only "Any topic" (or nothing, for an action).
	 *
	 * @param string $forum_id    The parent field's value.
	 * @param bool   $include_any Prepend "Any topic".
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function topics_from_forum_options( $forum_id, $include_any ) {

		$options = $include_any ? array( $this->any_option( esc_html_x( 'Any topic', 'Asgaros Forum', 'uncanny-automator' ) ) ) : array();
		$tables  = $this->get_tables();

		if ( null === $tables || ! ctype_digit( (string) $forum_id ) || 0 === absint( $forum_id ) ) {
			return $options;
		}

		global $wpdb;

		$prepared = $wpdb->prepare(
			'SELECT id, name FROM `' . esc_sql( $tables->topics ) . '` WHERE parent_id = %d ORDER BY id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			absint( $forum_id ),
			self::OPTIONS_LIMIT
		);
		$topics   = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		foreach ( (array) $topics as $topic ) {
			$options[] = array(
				'text'  => esc_html( $topic->name ),
				'value' => (string) absint( $topic->id ),
			);
		}

		return $options;
	}

	/**
	 * Posts of one topic, newest first, labelled by ID and an excerpt. Asgaros's
	 * own `get_posts()` reads only the current topic, paginated.
	 *
	 * @param string $topic_id        The parent field's value.
	 * @param bool   $include_any     Prepend "Any post".
	 * @param bool   $with_first_post List the topic's first post.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function posts_from_topic_options( $topic_id, $include_any, $with_first_post ) {

		$options = $include_any ? array( $this->any_option( esc_html_x( 'Any post', 'Asgaros Forum', 'uncanny-automator' ) ) ) : array();
		$tables  = $this->get_tables();

		if ( null === $tables || ! ctype_digit( (string) $topic_id ) || 0 === absint( $topic_id ) ) {
			return $options;
		}

		global $wpdb;

		$prepared = $wpdb->prepare(
			'SELECT id, text FROM `' . esc_sql( $tables->posts ) . '` WHERE parent_id = %d ORDER BY id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			absint( $topic_id ),
			self::OPTIONS_LIMIT
		);
		$posts    = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		foreach ( (array) $posts as $post ) {
			if ( ! $with_first_post && $this->get_forum()->is_first_post( absint( $post->id ), absint( $topic_id ) ) ) {
				continue;
			}

			$options[] = array(
				'text'  => esc_html( sprintf( '#%d: %s', absint( $post->id ), wp_trim_words( wp_strip_all_tags( (string) $post->text ), 10, '…' ) ) ),
				'value' => (string) absint( $post->id ),
			);
		}

		return $options;
	}

	/**
	 * Usergroup options: every usergroup, hidden ones included, without the
	 * usergroup categories.
	 *
	 * @param bool $include_any Prepend "Any usergroup".
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	public function build_usergroup_options( $include_any = true ) {

		$options = $include_any ? array( $this->any_option( esc_html_x( 'Any usergroup', 'Asgaros Forum', 'uncanny-automator' ) ) ) : array();

		if ( ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return $options;
		}

		foreach ( (array) AsgarosForumUserGroups::getUserGroups() as $usergroup ) {
			$options[] = array(
				'text'  => esc_html( $usergroup->name ),
				'value' => (string) absint( $usergroup->term_id ),
			);
		}

		return $options;
	}

	/**
	 * Reaction options. Other plugins add keys through `asgarosforum_reactions`.
	 *
	 * @param bool $include_any Prepend "Any reaction".
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	public function build_reaction_options( $include_any = true ) {

		$options = $include_any ? array( $this->any_option( esc_html_x( 'Any reaction', 'Asgaros Forum', 'uncanny-automator' ) ) ) : array();

		foreach ( $this->get_reaction_labels() as $value => $label ) {
			$options[] = array(
				'text'  => $label,
				'value' => (string) $value,
			);
		}

		return $options;
	}

	/**
	 * Labels for the reactions Asgaros offers: its own two, passed through
	 * `asgarosforum_reactions` as Asgaros does when reactions are enabled, so
	 * a key another plugin adds or removes is added or removed here too.
	 *
	 * @return array<string,string>
	 */
	public function get_reaction_labels() {

		$reactions = array(
			'up'   => array(),
			'down' => array(),
		);
		$forum     = $this->get_forum();

		if ( null !== $forum && ! empty( $forum->options['enable_reactions'] ) ) {
			$reactions = (array) apply_filters( 'asgarosforum_reactions', $reactions );
		}

		$own    = array(
			'up'   => esc_html_x( 'Thumbs up', 'Asgaros Forum', 'uncanny-automator' ),
			'down' => esc_html_x( 'Thumbs down', 'Asgaros Forum', 'uncanny-automator' ),
		);
		$labels = array();

		foreach ( $reactions as $key => $reaction ) {
			$key            = (string) $key;
			$labels[ $key ] = $own[ $key ] ?? ( isset( $reaction['screen_reader_text'] ) ? (string) $reaction['screen_reader_text'] : $key );
		}

		return $labels;
	}

	/**
	 * A positive ID from a field or hook value: an int, or a string of
	 * digits. Anything else (`-1`, `12abc`, a blank token, an array, a
	 * WP_Error) is 0, so a present but invalid value never picks another row
	 * (R15, R1).
	 *
	 * @param mixed $value The value.
	 *
	 * @return int
	 */
	public function parse_id( $value ) {

		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( is_string( $value ) && ctype_digit( trim( $value ) ) ) {
			return (int) trim( $value );
		}

		return 0;
	}

	/**
	 * The fields of the Forum → Topic → Post drill-down a trigger filters
	 * posts with. "Any" at each level; an "Any" parent offers only "Any" below.
	 * The Post field's code is the trigger's meta.
	 *
	 * @return array[]
	 */
	public function post_drilldown_fields() {
		return array(
			array(
				'option_code'     => 'ASGAROS_FORUM_FORUM',
				'label'           => esc_html_x( 'Forum', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->remote_data_load_config( 'forums' ),
			),
			array(
				'option_code'     => 'ASGAROS_FORUM_TOPIC',
				'label'           => esc_html_x( 'Topic', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->remote_data_parent_config( 'topics_from_forum', array( 'ASGAROS_FORUM_FORUM' ) ),
			),
			array(
				'option_code'     => 'ASGAROS_FORUM_POST',
				'label'           => esc_html_x( 'Post', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->remote_data_parent_config( 'posts_from_topic', array( 'ASGAROS_FORUM_TOPIC' ) ),
			),
		);
	}

	/**
	 * Whether a post matches a recipe's drill-down: the most specific level
	 * picked decides. A key missing from a recipe saved before the parent
	 * fields existed counts as "Any"; a present but empty or invalid value
	 * never does (R15).
	 *
	 * @param array $meta    The trigger's saved meta.
	 * @param int   $post_id The post ID from the hook.
	 *
	 * @return bool
	 */
	public function post_matches_drilldown( array $meta, $post_id ) {

		$post_id = $this->parse_id( $post_id );
		$post    = $this->get_post_row( $post_id );

		if ( null === $post ) {
			return false;
		}

		$levels = array(
			'ASGAROS_FORUM_POST'  => $post_id,
			'ASGAROS_FORUM_TOPIC' => absint( $post->parent_id ),
			'ASGAROS_FORUM_FORUM' => absint( $post->forum_id ),
		);

		foreach ( $levels as $code => $actual ) {

			$selected = (string) ( $meta[ $code ] ?? self::ANY_VALUE );

			if ( self::ANY_VALUE !== $selected ) {
				return $this->parse_id( $selected ) === $actual;
			}
		}

		return true;
	}

	/**
	 * @param string $label The option's label.
	 *
	 * @return array{text:string,value:string}
	 */
	private function any_option( $label ) {
		return array(
			'text'  => $label,
			'value' => self::ANY_VALUE,
		);
	}

	// =========================================================================
	// Plugin access.
	// =========================================================================

	/**
	 * The global `$asgarosforum` instance.
	 *
	 * @return AsgarosForum|null
	 */
	public function get_forum() {

		global $asgarosforum;

		return ( $asgarosforum instanceof AsgarosForum ) ? $asgarosforum : null;
	}

	/**
	 * Asgaros's table names (`->forums`, `->topics`, `->posts`, …).
	 *
	 * @return object|null
	 */
	public function get_tables() {

		$forum = $this->get_forum();

		return ( null !== $forum && isset( $forum->tables ) ) ? $forum->tables : null;
	}

	/**
	 * @param int $forum_id The forum ID.
	 *
	 * @return object|null
	 */
	public function get_forum_row( $forum_id ) {

		$forum_id = absint( $forum_id );
		$forum    = $this->get_forum();

		if ( 0 === $forum_id || null === $forum ) {
			return null;
		}

		$row = $forum->content->get_forum( $forum_id );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * @param int $topic_id The topic ID.
	 *
	 * @return object|null
	 */
	public function get_topic_row( $topic_id ) {

		$topic_id = absint( $topic_id );
		$forum    = $this->get_forum();

		if ( 0 === $topic_id || null === $forum ) {
			return null;
		}

		$row = $forum->content->get_topic( $topic_id );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * @param int $post_id The post ID.
	 *
	 * @return object|null
	 */
	public function get_post_row( $post_id ) {

		$post_id = absint( $post_id );
		$forum   = $this->get_forum();

		if ( 0 === $post_id || null === $forum ) {
			return null;
		}

		$row = $forum->content->get_post( $post_id );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * @param int $topic_id The topic ID.
	 *
	 * @return string
	 */
	public function get_topic_url( $topic_id ) {

		$topic_id = absint( $topic_id );
		$forum    = $this->get_forum();

		if ( 0 === $topic_id || null === $forum ) {
			return '';
		}

		return (string) html_entity_decode( (string) $forum->rewrite->get_link( 'topic', $topic_id ) );
	}

	/**
	 * @param int $post_id  The post ID.
	 * @param int $topic_id The post's topic ID.
	 *
	 * @return string
	 */
	public function get_post_url( $post_id, $topic_id ) {

		$post_id = absint( $post_id );
		$forum   = $this->get_forum();

		if ( 0 === $post_id || null === $forum ) {
			return '';
		}

		return (string) html_entity_decode( (string) $forum->rewrite->get_post_link( $post_id, absint( $topic_id ) ) );
	}

	/**
	 * A usergroup term, or null for a missing term or a usergroup category
	 * (Asgaros's top-level terms).
	 *
	 * @param int $usergroup_id The usergroup term ID.
	 *
	 * @return WP_Term|null
	 */
	public function get_usergroup( $usergroup_id ) {

		$usergroup_id = absint( $usergroup_id );

		if ( 0 === $usergroup_id || ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return null;
		}

		$term = AsgarosForumUserGroups::getUserGroup( $usergroup_id );

		return ( $term instanceof WP_Term && 0 !== absint( $term->parent ) ) ? $term : null;
	}

	/**
	 * @param int $user_id      The user ID.
	 * @param int $usergroup_id The usergroup term ID.
	 *
	 * @return bool
	 */
	public function is_user_in_usergroup( $user_id, $usergroup_id ) {

		$user_id      = absint( $user_id );
		$usergroup_id = absint( $usergroup_id );

		if ( 0 === $user_id || 0 === $usergroup_id || ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return false;
		}

		// is_object_in_term() returns a WP_Error when the taxonomy is missing.
		return true === AsgarosForumUserGroups::isUserInUserGroup( $user_id, $usergroup_id );
	}

	/**
	 * @param int $user_id The user ID.
	 *
	 * @return int[]
	 */
	public function get_user_usergroup_ids( $user_id ) {

		$user_id = absint( $user_id );

		if ( 0 === $user_id || ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return array();
		}

		$ids = AsgarosForumUserGroups::getUserGroupsOfUser( $user_id, 'ids' );

		return is_wp_error( $ids ) ? array() : array_map( 'absint', (array) $ids );
	}

	/**
	 * Add or remove one usergroup through `insertUserGroupsOfUsers()`, so
	 * Asgaros fires its own `asgarosforum_usergroup_{id}_add_user` /
	 * `_remove_user` hook.
	 *
	 * @param int  $user_id      The user ID.
	 * @param int  $usergroup_id The usergroup term ID.
	 * @param bool $add          True to add, false to remove.
	 *
	 * @return bool True when the membership changed.
	 */
	public function set_user_usergroup( $user_id, $usergroup_id, $add = true ) {

		$user_id      = absint( $user_id );
		$usergroup_id = absint( $usergroup_id );

		if ( 0 === $user_id || 0 === $usergroup_id || ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return false;
		}

		$current = $this->get_user_usergroup_ids( $user_id );
		$updated = $add
			? array_unique( array_merge( $current, array( $usergroup_id ) ) )
			: array_diff( $current, array( $usergroup_id ) );

		if ( count( $current ) === count( $updated ) ) {
			return false;
		}

		// Integers: a numeric string would be taken as a term name and create a new term.
		AsgarosForumUserGroups::insertUserGroupsOfUsers( $user_id, array_values( array_map( 'intval', $updated ) ) );

		return true;
	}

	/**
	 * The author of a topic or reply an action creates: the Author field when
	 * it is set, else the recipe user. Never a guest, never a banned user.
	 *
	 * @param string $author_value The Author field's parsed value.
	 * @param int    $user_id      The recipe user's ID.
	 * @param string $saved_value  The Author field as saved in the recipe, before tokens are parsed.
	 *
	 * @return int|WP_Error
	 */
	public function resolve_author( $author_value, $user_id, $saved_value = '' ) {

		$value = trim( (string) $author_value );

		// Only a field left empty falls back to the recipe user, not a token that came back empty.
		if ( '' === $value && '' !== trim( (string) $saved_value ) ) {
			return new WP_Error( 'asgaros_forum_author', esc_html_x( 'The Author field is set but came back empty, so nothing was posted.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( '' !== $value && ! ctype_digit( $value ) ) {
			return new WP_Error(
				'asgaros_forum_author',
				sprintf(
					/* translators: %s: The Author field's value */
					esc_html_x( 'The author %s is not a user ID.', 'Asgaros Forum', 'uncanny-automator' ),
					$value
				)
			);
		}

		$author_id = '' !== $value ? absint( $value ) : absint( $user_id );

		if ( 0 === $author_id ) {
			return new WP_Error( 'asgaros_forum_author', esc_html_x( 'No author: set the Author field or run the recipe for a user.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( false === get_userdata( $author_id ) ) {
			return new WP_Error(
				'asgaros_forum_author',
				sprintf(
					/* translators: %d: User ID */
					esc_html_x( 'User %d does not exist.', 'Asgaros Forum', 'uncanny-automator' ),
					$author_id
				)
			);
		}

		$forum = $this->get_forum();

		if ( null !== $forum && $forum->permissions->isBanned( $author_id ) ) {
			return new WP_Error(
				'asgaros_forum_author',
				sprintf(
					/* translators: %d: User ID */
					esc_html_x( 'User %d is banned from the forum.', 'Asgaros Forum', 'uncanny-automator' ),
					$author_id
				)
			);
		}

		return $author_id;
	}

	/**
	 * Send the mails Asgaros sends for a programmatic topic
	 * (`create_blog_topic()`): subscribers for an approved topic, the site
	 * owner for a held one. The author is left off their own topic's mail.
	 *
	 * @param int $topic_id  The new topic's ID.
	 * @param int $forum_id  Its forum's ID.
	 * @param int $author_id Its author's user ID.
	 *
	 * @return void
	 */
	public function notify_new_topic( $topic_id, $forum_id, $author_id ) {

		$forum = $this->get_forum();

		if ( null === $forum ) {
			return;
		}

		$previous = $forum->category_access_level;

		try {
			$forum->category_access_level = $this->category_access_level( $forum_id );

			if ( $forum->approval->is_topic_approved( absint( $topic_id ) ) ) {
				$forum->notifications->notify_about_new_topic( absint( $topic_id ), $this->author_ignore_list( $author_id ) );
			} else {
				$forum->approval->notify_about_new_unapproved_topic( absint( $topic_id ) );
			}
		} finally {
			$forum->category_access_level = $previous;
		}
	}

	/**
	 * Send the subscriber mail the reply form sends. The author is left off
	 * their own reply's mail.
	 *
	 * @param int $post_id   The new reply's ID.
	 * @param int $forum_id  Its forum's ID.
	 * @param int $author_id Its author's user ID.
	 *
	 * @return void
	 */
	public function notify_new_post( $post_id, $forum_id, $author_id ) {

		$forum = $this->get_forum();

		if ( null === $forum ) {
			return;
		}

		$previous = $forum->category_access_level;

		try {
			$forum->category_access_level = $this->category_access_level( $forum_id );
			$forum->notifications->notify_about_new_post( absint( $post_id ), $this->author_ignore_list( $author_id ) );
		} finally {
			$forum->category_access_level = $previous;
		}
	}

	/**
	 * The `category_access` of a forum's category, which Asgaros's mails read
	 * to keep a moderator-only category's mail to moderators. Asgaros sets it
	 * in `check_access()` on its own pages; a recipe run never gets there.
	 *
	 * @param int $forum_id The forum ID.
	 *
	 * @return mixed
	 */
	private function category_access_level( $forum_id ) {

		$row = $this->get_forum_row( $forum_id );

		return null !== $row ? get_term_meta( absint( $row->parent_id ), 'category_access', true ) : false;
	}

	/**
	 * Asgaros's mails take an ignore list of email addresses.
	 *
	 * @param int $author_id The author's user ID.
	 *
	 * @return string[]|false
	 */
	private function author_ignore_list( $author_id ) {

		$author = get_userdata( absint( $author_id ) );

		return false !== $author ? array( $author->user_email ) : false;
	}
}
