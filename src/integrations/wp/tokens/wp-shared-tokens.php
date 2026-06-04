<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class Wp_Shared_Tokens
 *
 * Provides paired define/hydrate methods for all WordPress token groups.
 * Token IDs match the existing manifest exactly as they are stored in recipe databases.
 *
 * @package Uncanny_Automator\Integrations\Wp
 */
class Wp_Shared_Tokens {

	// =========================================================================
	// Canonical orthogonal token helpers.
	//
	// Each helper is one bounded concept (post-core, post-author, post-date,
	// etc.) and emits the canonical tokenId set for that concept. Triggers
	// compose the helpers they need; aliases live in Wp_Token_Aliases and the
	// parser rewrites them at runtime.
	// =========================================================================

	/**
	 * Canonical post-core token set.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_core_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTID',
				'tokenName' => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTTITLE',
				'tokenName' => esc_html_x( 'Post title', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTURL',
				'tokenName' => esc_html_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTNAME',
				'tokenName' => esc_html_x( 'Post slug', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCONTENT',
				'tokenName' => esc_html_x( 'Post content (raw)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCONTENT_BEAUTIFIED',
				'tokenName' => esc_html_x( 'Post content (formatted)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTEXCERPT',
				'tokenName' => esc_html_x( 'Post excerpt', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPPOSTTYPES',
				'tokenName' => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTPARENT_ID',
				'tokenName' => esc_html_x( 'Post parent ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTMENUORDER',
				'tokenName' => esc_html_x( 'Post menu order', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the canonical post-core token set from a post ID.
	 *
	 * Returns the same keys the helper declares, in the same order. If the
	 * post cannot be resolved the keys are still present, populated with
	 * empty-but-typed defaults so downstream token consumers never see
	 * missing keys.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_post_core_tokens( int $post_id ) {
		$empty = array(
			'POSTID'                 => 0,
			'POSTTITLE'              => '',
			'POSTURL'                => '',
			'POSTNAME'               => '',
			'POSTCONTENT'            => '',
			'POSTCONTENT_BEAUTIFIED' => '',
			'POSTEXCERPT'            => '',
			'WPPOSTTYPES'            => '',
			'POSTPARENT_ID'          => 0,
			'POSTMENUORDER'          => 0,
		);

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		$content_beautified = get_the_content( null, false, $post->ID );
		$content_beautified = apply_filters( 'the_content', $content_beautified );
		$content_beautified = str_replace( ']]>', ']]&gt;', $content_beautified );

		$post_type_obj   = get_post_type_object( $post->post_type );
		$post_type_label = null !== $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

		return array(
			'POSTID'                 => (int) $post->ID,
			'POSTTITLE'              => $post->post_title,
			'POSTURL'                => (string) get_permalink( $post->ID ),
			'POSTNAME'               => $post->post_name,
			'POSTCONTENT'            => $post->post_content,
			'POSTCONTENT_BEAUTIFIED' => $content_beautified,
			'POSTEXCERPT'            => get_the_excerpt( $post->ID ),
			'WPPOSTTYPES'            => $post_type_label,
			'POSTPARENT_ID'          => (int) $post->post_parent,
			'POSTMENUORDER'          => (int) $post->menu_order,
		);
	}

	/**
	 * Canonical post-author token set.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_author_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTAUTHORID',
				'tokenName' => esc_html_x( 'Post author ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTAUTHORFN',
				'tokenName' => esc_html_x( 'Post author first name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHORLN',
				'tokenName' => esc_html_x( 'Post author last name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHORDN',
				'tokenName' => esc_html_x( 'Post author display name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHOREMAIL',
				'tokenName' => esc_html_x( 'Post author email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'POSTAUTHORURL',
				'tokenName' => esc_html_x( 'Post author URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTAUTHORROLE',
				'tokenName' => esc_html_x( 'Post author role', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTAUTHORREGISTERED',
				'tokenName' => esc_html_x( 'Post author registered date', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical post-author token set from a post ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_post_author_tokens( int $post_id ) {
		$empty = array(
			'POSTAUTHORID'         => 0,
			'POSTAUTHORFN'         => '',
			'POSTAUTHORLN'         => '',
			'POSTAUTHORDN'         => '',
			'POSTAUTHOREMAIL'      => '',
			'POSTAUTHORURL'        => '',
			'POSTAUTHORROLE'       => '',
			'POSTAUTHORREGISTERED' => '',
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		$author_id = (int) $post->post_author;
		$user      = $author_id > 0 ? get_userdata( $author_id ) : false;

		if ( false === $user ) {
			return array_merge( $empty, array( 'POSTAUTHORID' => $author_id ) );
		}

		$role_label = '';
		if ( ! empty( $user->roles ) ) {
			$roles      = (array) $user->roles;
			$first_role = (string) reset( $roles );
			if ( '' !== $first_role ) {
				$wp_roles   = wp_roles();
				$role_label = isset( $wp_roles->role_names[ $first_role ] )
					? translate_user_role( $wp_roles->role_names[ $first_role ] )
					: '';
			}
		}

		return array(
			'POSTAUTHORID'         => $author_id,
			'POSTAUTHORFN'         => (string) get_the_author_meta( 'user_firstname', $author_id ),
			'POSTAUTHORLN'         => (string) get_the_author_meta( 'user_lastname', $author_id ),
			'POSTAUTHORDN'         => (string) get_the_author_meta( 'display_name', $author_id ),
			'POSTAUTHOREMAIL'      => (string) get_the_author_meta( 'user_email', $author_id ),
			'POSTAUTHORURL'        => (string) get_the_author_meta( 'url', $author_id ),
			'POSTAUTHORROLE'       => $role_label,
			'POSTAUTHORREGISTERED' => (string) $user->user_registered,
		);
	}

	/**
	 * Canonical post-date token set.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_date_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTDATE',
				'tokenName' => esc_html_x( 'Post published date', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTDATE_GMT',
				'tokenName' => esc_html_x( 'Post published date (GMT)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTMODIFIED',
				'tokenName' => esc_html_x( 'Post modified date', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTMODIFIED_GMT',
				'tokenName' => esc_html_x( 'Post modified date (GMT)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTDATE_TIMESTAMP',
				'tokenName' => esc_html_x( 'Post published date (Unix timestamp)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTDATE_FORMATTED',
				'tokenName' => esc_html_x( 'Post published date (formatted)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTTIME_FORMATTED',
				'tokenName' => esc_html_x( 'Post published time (formatted)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical post-date token set from a post ID.
	 *
	 * - POSTDATE / POSTDATE_GMT / POSTMODIFIED / POSTMODIFIED_GMT use the site
	 *   "date_format time_format" combination (legacy compatibility).
	 * - POSTDATE_TIMESTAMP is the Unix-seconds value of `post_date` (local time
	 *   stored value).
	 * - POSTDATE_FORMATTED uses the WP "date_format" option only.
	 * - POSTTIME_FORMATTED uses the WP "time_format" option only.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_post_date_tokens( int $post_id ) {
		$empty = array(
			'POSTDATE'           => '',
			'POSTDATE_GMT'       => '',
			'POSTMODIFIED'       => '',
			'POSTMODIFIED_GMT'   => '',
			'POSTDATE_TIMESTAMP' => 0,
			'POSTDATE_FORMATTED' => '',
			'POSTTIME_FORMATTED' => '',
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );
		$combo       = $date_format . ' ' . $time_format;

		$pub_ts_gmt = get_post_time( 'U', true, $post );
		$pub_local  = (int) mysql2date( 'U', $post->post_date );

		$post_date = false !== $pub_ts_gmt ? wp_date( $combo, $pub_ts_gmt ) : '';
		$post_date_gmt = false !== $pub_ts_gmt
			? wp_date( $combo, $pub_ts_gmt, new \DateTimeZone( 'UTC' ) )
			: '';

		$mod_ts_gmt = get_post_modified_time( 'U', true, $post );
		$post_mod   = false !== $mod_ts_gmt ? wp_date( $combo, $mod_ts_gmt ) : '';
		$post_mod_gmt = false !== $mod_ts_gmt
			? wp_date( $combo, $mod_ts_gmt, new \DateTimeZone( 'UTC' ) )
			: '';

		$date_formatted = $pub_local > 0 ? date_i18n( $date_format, $pub_local ) : '';
		$time_formatted = $pub_local > 0 ? date_i18n( $time_format, $pub_local ) : '';

		return array(
			'POSTDATE'           => $post_date,
			'POSTDATE_GMT'       => $post_date_gmt,
			'POSTMODIFIED'       => $post_mod,
			'POSTMODIFIED_GMT'   => $post_mod_gmt,
			'POSTDATE_TIMESTAMP' => $pub_local,
			'POSTDATE_FORMATTED' => $date_formatted,
			'POSTTIME_FORMATTED' => $time_formatted,
		);
	}

	/**
	 * Canonical post-featured-image token set.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_featured_image_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTIMAGEID',
				'tokenName' => esc_html_x( 'Post featured image ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTIMAGEURL',
				'tokenName' => esc_html_x( 'Post featured image URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate the canonical post-featured-image token set from a post ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_post_featured_image_tokens( int $post_id ) {
		$empty = array(
			'POSTIMAGEID'  => 0,
			'POSTIMAGEURL' => '',
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		$thumb_url = '';
		if ( $thumb_id > 0 ) {
			// Stage 1: fire the legacy filter for backwards compatibility. Its third
			// argument (the legacy tokenId) is no longer trigger-specific now that
			// the canonical hydrator always emits POSTIMAGEURL. Sites that filter by
			// tokenId should migrate to the new filter below.
			$size = apply_filters_deprecated(
				'automator_token_post_featured_image_size',
				array( 'full', $post_id, 'POSTIMAGEURL' ),
				'7.5.0',
				'automator_post_featured_image_size',
				esc_html_x( 'The third argument (token ID) is no longer trigger-specific. Migrate to the new filter; the legacy filter will be removed in a future release.', 'WordPress', 'uncanny-automator' )
			);

			// Stage 2: fire the canonical filter with the cleaner (size, post_id)
			// signature. Runs on the legacy filter's output so both are composable.
			$size = apply_filters( 'automator_post_featured_image_size', $size, $post_id );

			$thumb_url = (string) get_the_post_thumbnail_url( $post_id, $size );
		}

		return array(
			'POSTIMAGEID'  => $thumb_id,
			'POSTIMAGEURL' => $thumb_url,
		);
	}

	/**
	 * Canonical post-status token set.
	 *
	 * Three canonical IDs:
	 *  - POSTSTATUS: the post's current status (read on demand).
	 *  - POSTSTATUSUPDATED: the new status emitted by transition_post_status.
	 *  - SPECIFICPOSTTYPESTATUSUPDATED: same value as POSTSTATUSUPDATED but
	 *    scoped to a specific-post-type trigger; kept as a distinct picker
	 *    entry per the Step 0 Decision-2 ruling.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_status_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTSTATUS',
				'tokenName' => esc_html_x( 'Post status', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTSTATUSUPDATED',
				'tokenName' => esc_html_x( 'Post status (after change)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SPECIFICPOSTTYPESTATUSUPDATED',
				'tokenName' => esc_html_x( 'Post status (after change, specific type)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical post-status token set.
	 *
	 * @param int    $post_id    The post ID (source of POSTSTATUS).
	 * @param string $new_status Optional. The new status emitted by
	 *                           transition_post_status (the value for the two
	 *                           after-change canonicals). Empty string for
	 *                           triggers that don't carry a transition.
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_post_status_tokens( int $post_id, string $new_status = '' ) {
		$post = get_post( $post_id );

		$current = $post instanceof \WP_Post ? (string) $post->post_status : '';

		return array(
			'POSTSTATUS'                    => $current,
			'POSTSTATUSUPDATED'              => $new_status,
			'SPECIFICPOSTTYPESTATUSUPDATED'  => $new_status,
		);
	}

	/**
	 * Canonical post-taxonomy token set (flat text).
	 *
	 * Display names "Post categories" / "Post tags" pair with the loopable
	 * variants emitted by `post_taxonomy_loopable_tokens()` whose names carry
	 * a "(loop)" suffix — eliminating the picker display-name collision per
	 * Step 0 Decision 6.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_taxonomy_tokens() {
		return array(
			array(
				'tokenId'   => 'WPTAXONOMIES',
				'tokenName' => esc_html_x( 'Post taxonomies', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPTAXONOMYTERM',
				'tokenName' => esc_html_x( 'All post taxonomy terms', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCATEGORIES',
				'tokenName' => esc_html_x( 'Post categories', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTTAGS',
				'tokenName' => esc_html_x( 'Post tags', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical flat post-taxonomy token set from a post ID.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_post_taxonomy_tokens( int $post_id ) {
		$empty = array(
			'WPTAXONOMIES'   => '',
			'WPTAXONOMYTERM' => '',
			'POSTCATEGORIES' => '',
			'POSTTAGS'       => '',
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $empty;
		}

		$all_taxonomy_names = array();
		$all_term_names     = array();
		$category_names     = array();
		$tag_names          = array();

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax_obj ) {
			$post_terms = wp_get_post_terms( $post_id, $tax_obj->name );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				continue;
			}
			$all_taxonomy_names[] = $tax_obj->labels->singular_name;
			foreach ( $post_terms as $term ) {
				$all_term_names[] = $term->name;
				if ( 'category' === $tax_obj->name ) {
					$category_names[] = $term->name;
				} elseif ( 'post_tag' === $tax_obj->name ) {
					$tag_names[] = $term->name;
				}
			}
		}

		return array(
			'WPTAXONOMIES'   => implode( ', ', $all_taxonomy_names ),
			'WPTAXONOMYTERM' => implode( ', ', $all_term_names ),
			'POSTCATEGORIES' => implode( ', ', $category_names ),
			'POSTTAGS'       => implode( ', ', $tag_names ),
		);
	}

	/**
	 * Canonical post-taxonomy loopable token declaration.
	 *
	 * These IDs are loopable tokens (one row per category/tag) used by the
	 * recipe-builder's loop-filter UI. Hydration is owned by the per-token
	 * loopable classes in `loopable/trigger/post-categories.php` and
	 * `loopable/trigger/post-tags.php` — this helper only declares the picker
	 * entries so triggers can compose the loopable IDs alongside the flat
	 * `post_taxonomy_tokens()` variants without depending on the loopable
	 * registration files.
	 *
	 * Display names carry an explicit "(loop)" suffix to disambiguate from
	 * the flat-text POSTCATEGORIES/POSTTAGS picker entries per Step 0
	 * Decision 6.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_taxonomy_loopable_tokens() {
		return array(
			array(
				'tokenId'   => 'WP_POST_CATEGORIES',
				'tokenName' => esc_html_x( 'Post categories (loop)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'json',
			),
			array(
				'tokenId'   => 'WP_POST_TAGS',
				'tokenName' => esc_html_x( 'Post tags (loop)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'json',
			),
		);
	}

	/**
	 * Canonical user token set.
	 *
	 * Order is stable so the recipe-builder picker shows tokens in a
	 * user-recognized order.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function user_tokens() {
		return array(
			array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'USER_LOGIN',
				'tokenName' => esc_html_x( 'User username', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'USER_DISPLAY_NAME',
				'tokenName' => esc_html_x( 'User display name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_FIRST_NAME',
				'tokenName' => esc_html_x( 'User first name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_LAST_NAME',
				'tokenName' => esc_html_x( 'User last name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_REGISTERED_DATE',
				'tokenName' => esc_html_x( 'User registered date', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_ROLES',
				'tokenName' => esc_html_x( 'User roles', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical user token set from a user ID.
	 *
	 * USER_ROLES is a comma-separated list of translated role display labels.
	 *
	 * Missing-entity contract: returns typed empties — `int 0` for USER_ID and
	 * empty strings for the rest — so the picker remains stable for triggers
	 * that fire on a user_id that no longer resolves.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_user_tokens( int $user_id ) {
		$empty = array(
			'USER_ID'              => 0,
			'USER_LOGIN'           => '',
			'USER_EMAIL'           => '',
			'USER_DISPLAY_NAME'    => '',
			'USER_FIRST_NAME'      => '',
			'USER_LAST_NAME'       => '',
			'USER_REGISTERED_DATE' => '',
			'USER_ROLES'           => '',
		);

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return $empty;
		}

		$role_labels = array();
		if ( ! empty( $user->roles ) ) {
			$wp_roles = wp_roles();
			foreach ( $user->roles as $role_slug ) {
				$role_labels[] = isset( $wp_roles->role_names[ $role_slug ] )
					? translate_user_role( $wp_roles->role_names[ $role_slug ] )
					: $role_slug;
			}
		}

		return array(
			'USER_ID'              => (int) $user->ID,
			'USER_LOGIN'           => (string) $user->user_login,
			'USER_EMAIL'           => (string) $user->user_email,
			'USER_DISPLAY_NAME'    => (string) $user->display_name,
			'USER_FIRST_NAME'      => (string) $user->first_name,
			'USER_LAST_NAME'       => (string) $user->last_name,
			'USER_REGISTERED_DATE' => (string) $user->user_registered,
			'USER_ROLES'           => implode( ', ', $role_labels ),
		);
	}

	/**
	 * Canonical single-role token set.
	 *
	 * Per Decision 22 the legacy role-tokens shape was split: this helper emits
	 * WPROLE only, paired with the add/remove-style triggers whose WP hook
	 * (`add_user_role`, `remove_user_role`) carries one role at a time. The
	 * three-token role-change shape lives on `role_change_tokens()`.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function role_tokens() {
		return array(
			array(
				'tokenId'   => 'WPROLE',
				'tokenName' => esc_html_x( 'Role', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical single-role token from a role slug.
	 *
	 * Returns the translated display label for known roles, the raw slug as a
	 * fallback for unknown roles (so the picker never shows a blank pill), or
	 * an empty string when the slug itself is empty.
	 *
	 * @param string $role_slug The role slug emitted by the trigger's WP hook.
	 *
	 * @return array<string, string> WPROLE => display label.
	 */
	public static function hydrate_role_tokens( string $role_slug ) {
		return array( 'WPROLE' => self::translate_role_slug( $role_slug ) );
	}

	/**
	 * Canonical role-change token set (three tokens).
	 *
	 * Per Decision 22 the legacy `role_tokens()` was split: this helper emits
	 * WPROLE + WPROLENEW + WPROLEOLD — paired with role-change triggers
	 * (`set_user_role`, role-changed-from-to) whose WP hook passes
	 * `($user_id, $new_role, $old_roles[])`.
	 *
	 * WPROLE doubles as the new role for parity with single-role triggers; the
	 * disambiguating "(new)" / "(old)" suffixes on the display names keep the
	 * picker readable.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function role_change_tokens() {
		return array(
			array(
				'tokenId'   => 'WPROLE',
				'tokenName' => esc_html_x( 'Role', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPROLENEW',
				'tokenName' => esc_html_x( 'Role (new)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPROLEOLD',
				'tokenName' => esc_html_x( 'Role (old)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical role-change token set.
	 *
	 * Mirrors the `set_user_role` hook signature: pass the new role slug and
	 * the array of old role slugs. Only the first old role is surfaced — the
	 * hook contract treats $old_roles as a list but only the primary slot is
	 * exposed as a token.
	 *
	 * Unknown role slugs fall back to the raw slug (printable pill); the
	 * empty-input cases return '' for that slot.
	 *
	 * @param string                  $new_role  The new role slug.
	 * @param array<int|string, string> $old_roles The old role slugs from
	 *                                            `set_user_role`.
	 *
	 * @return array<string, string> Token ID => translated display label.
	 */
	public static function hydrate_role_change_tokens( string $new_role, array $old_roles ) {
		$new_label = self::translate_role_slug( $new_role );

		$first_old = '';
		foreach ( $old_roles as $slug ) {
			$first_old = (string) $slug;
			break;
		}
		$old_label = self::translate_role_slug( $first_old );

		return array(
			'WPROLE'    => $new_label,
			'WPROLENEW' => $new_label,
			'WPROLEOLD' => $old_label,
		);
	}

	/**
	 * Translate a role slug into its display label.
	 *
	 * Shared helper for the role hydrators: known slugs are translated via
	 * `translate_user_role()`; unregistered slugs fall back to the raw slug so
	 * triggers don't emit blank pills; an empty input returns ''.
	 *
	 * @param string $slug The role slug.
	 *
	 * @return string The translated display label or fallback.
	 */
	private static function translate_role_slug( string $slug ) {
		if ( '' === $slug ) {
			return '';
		}

		$wp_roles = wp_roles();
		return isset( $wp_roles->role_names[ $slug ] )
			? translate_user_role( $wp_roles->role_names[ $slug ] )
			: $slug;
	}

	/**
	 * Canonical comment-core token set.
	 *
	 * Comment-domain split: this helper carries the comment-as-object fields
	 * (ID, content, URL, date, status, parent). The commenter-identity fields
	 * (name, email, website, IP) live on `comment_author_tokens()`.
	 *
	 * Per Decision 12 this helper gains POSTCOMMENTPARENT_ID for threaded-reply
	 * triggers that need to discover the comment being replied to.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function comment_core_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTCOMMENTID',
				'tokenName' => esc_html_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POSTCOMMENTCONTENT',
				'tokenName' => esc_html_x( 'Comment content', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTURL',
				'tokenName' => esc_html_x( 'Comment URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POSTCOMMENTDATE',
				'tokenName' => esc_html_x( 'Comment submitted date', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTSTATUS',
				'tokenName' => esc_html_x( 'Comment status', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTPARENT_ID',
				'tokenName' => esc_html_x( 'Parent comment ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the canonical comment-core token set from a comment ID.
	 *
	 * POSTCOMMENTDATE mirrors the legacy format — "M j, Y at H:i" — so
	 * downstream consumers don't see drift. POSTCOMMENTSTATUS is the
	 * normalized 'approved' / 'pending' label (not the raw 1/0 column).
	 * Missing-comment contract returns typed empties.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_comment_core_tokens( int $comment_id ) {
		$empty = array(
			'POSTCOMMENTID'        => 0,
			'POSTCOMMENTCONTENT'   => '',
			'POSTCOMMENTURL'       => '',
			'POSTCOMMENTDATE'      => '',
			'POSTCOMMENTSTATUS'    => '',
			'POSTCOMMENTPARENT_ID' => 0,
		);

		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof \WP_Comment ) {
			return $empty;
		}

		return array(
			'POSTCOMMENTID'        => (int) $comment->comment_ID,
			'POSTCOMMENTCONTENT'   => (string) $comment->comment_content,
			'POSTCOMMENTURL'       => (string) get_comment_link( $comment ),
			'POSTCOMMENTDATE'      => self::format_comment_date( $comment->comment_date ),
			'POSTCOMMENTSTATUS'    => self::normalize_comment_status( $comment->comment_approved ),
			'POSTCOMMENTPARENT_ID' => (int) $comment->comment_parent,
		);
	}

	/**
	 * Format a comment-date string using the legacy "M j, Y at H:i" shape.
	 *
	 * Shared between the canonical comment hydrator and any future variant —
	 * keeps the i18n format strings declared once.
	 *
	 * @param string $comment_date The raw `wp_comments.comment_date` value.
	 *
	 * @return string Formatted date, or '' when input is empty.
	 */
	private static function format_comment_date( string $comment_date ) {
		if ( '' === $comment_date ) {
			return '';
		}

		$timestamp = strtotime( $comment_date );
		if ( false === $timestamp ) {
			return '';
		}

		return sprintf(
			/* translators: 1: Comment date, 2: Comment time. */
			_x( '%1$s at %2$s', 'WordPress', 'uncanny-automator' ),
			date_i18n(
				/* translators: Publish box date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'M j, Y', 'publish box date format', 'uncanny-automator' ),
				$timestamp
			),
			date_i18n(
				/* translators: Publish box time format, see https://www.php.net/manual/datetime.format.php */
				_x( 'H:i', 'publish box time format', 'uncanny-automator' ),
				$timestamp
			)
		);
	}

	/**
	 * Normalize the raw `comment_approved` column to 'approved' / 'pending'.
	 *
	 * The 'approved' / 'pending' string contract is preserved so downstream
	 * actions that key on these values keep working.
	 *
	 * @param string|int $comment_approved The raw column value.
	 *
	 * @return string 'approved' or 'pending'.
	 */
	private static function normalize_comment_status( $comment_approved ) {
		return ( '1' === $comment_approved || 1 === $comment_approved )
			? 'approved'
			: 'pending';
	}

	/**
	 * Canonical commenter-identity token set.
	 *
	 * Comment-domain split (second half): this helper carries the
	 * commenter-author fields — name, email, website. The comment-as-object
	 * fields (ID, content, URL, date, status, parent) live on
	 * `comment_core_tokens()`.
	 *
	 * Token IDs follow the legacy `comment_tokens()` declaration —
	 * `POSTCOMMENTER*` not `POSTCOMMENTAUTHOR*` — and the audit doc. No
	 * commenter-IP token exists in the legacy helper or anywhere in the
	 * Free/Pro grep, so this helper likewise omits it.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function comment_author_tokens() {
		return array(
			array(
				'tokenId'   => 'POSTCOMMENTERNAME',
				'tokenName' => esc_html_x( 'Commenter name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTCOMMENTEREMAIL',
				'tokenName' => esc_html_x( 'Commenter email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'POSTCOMMENTERWEBSITE',
				'tokenName' => esc_html_x( 'Commenter website', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate the canonical commenter-identity token set from a comment ID.
	 *
	 * Anonymous comments may carry empty `comment_author*` columns — the
	 * hydrator preserves those as empty strings (no synthesis or fallback)
	 * because the trigger that fired chose to accept the anonymous shape.
	 * Missing-comment contract returns the same all-empty shape.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_comment_author_tokens( int $comment_id ) {
		$empty = array(
			'POSTCOMMENTERNAME'    => '',
			'POSTCOMMENTEREMAIL'   => '',
			'POSTCOMMENTERWEBSITE' => '',
		);

		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof \WP_Comment ) {
			return $empty;
		}

		return array(
			'POSTCOMMENTERNAME'    => (string) $comment->comment_author,
			'POSTCOMMENTEREMAIL'   => (string) $comment->comment_author_email,
			'POSTCOMMENTERWEBSITE' => (string) $comment->comment_author_url,
		);
	}

	/**
	 * Canonical number-of-times token (single token — name is singular per
	 * PLAN.md 2.1 rename of the original `numtimes_tokens()` plural).
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function numtimes_token() {
		return array(
			array(
				'tokenId'   => 'NUMTIMES',
				'tokenName' => esc_html_x( 'Number of times', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the canonical NUMTIMES token from the trigger settings array.
	 *
	 * Reads the saved threshold via `get_post_meta` on the trigger ID rather
	 * than relying on `$trigger['meta']['NUMTIMES']`. The legacy recipe
	 * pipeline populated `$trigger['meta']` via `get_post_custom`, but the
	 * modern Recipe_Runner can pass a `$trigger` shape whose meta key is
	 * absent — falling back silently to '1' and shadowing the user-saved
	 * value. Defaults to '1' when no trigger ID is available so downstream
	 * actions always receive a usable value.
	 *
	 * @param array $trigger The trigger settings (must contain `ID`).
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_numtimes_token( $trigger ) {

		$trigger_id = isset( $trigger['ID'] ) ? absint( $trigger['ID'] ) : 0;

		if ( 0 >= $trigger_id ) {
			return array( 'NUMTIMES' => '1' );
		}

		$value = get_post_meta( $trigger_id, 'NUMTIMES', true );

		return array(
			'NUMTIMES' => '' === $value ? '1' : (string) $value,
		);
	}

	/**
	 * Canonical archive token set. Used by archive-view triggers (e.g.
	 * `WPVIEWARCHIVEPOSTTYPE`) — the visited taxonomy archive's URL, the
	 * underlying term ID, and the term's published post count.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function archive_tokens() {
		return array(
			array(
				'tokenId'   => 'ARCHIVEURL',
				'tokenName' => esc_html_x( 'Archive URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'TERMID',
				'tokenName' => esc_html_x( 'Term ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TERMPOSTCOUNT',
				'tokenName' => esc_html_x( 'Term post count', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the canonical archive token set for a given taxonomy term.
	 *
	 * Missing-term contract returns typed empties — empty string for the URL,
	 * `0` for the integer IDs — so downstream consumers always see the same
	 * keys. `get_term_link()` may return a WP_Error; that path also coerces
	 * to empty string.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $term_id  The term ID.
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public static function hydrate_archive_tokens( string $taxonomy, int $term_id ) {
		$empty = array(
			'ARCHIVEURL'    => '',
			'TERMID'        => 0,
			'TERMPOSTCOUNT' => 0,
		);

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return $empty;
		}

		$archive_url = get_term_link( $term );

		return array(
			'ARCHIVEURL'    => is_wp_error( $archive_url ) ? '' : (string) $archive_url,
			'TERMID'        => (int) $term->term_id,
			'TERMPOSTCOUNT' => (int) $term->count,
		);
	}

	/**
	 * Canonical post-meta token set (three tokens).
	 *
	 * Emits the canonical (POST_META_KEY, POST_META_VALUE) pair plus the
	 * legacy POSTSPECIFICUMETAVAL kept for back-compat (Decision 23).
	 *
	 * POSTSPECIFICUMETAVAL keeps its static `tokenName` of "Meta value" —
	 * the trigger that emits it overrides the runtime display per recipe
	 * with the user-selected meta key. This helper-emitted shape is what
	 * lets stored recipes keep resolving the token after migration; the
	 * trigger-level rewrite is independent of the helper declaration.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function post_meta_tokens() {
		return array(
			array(
				'tokenId'   => 'POST_META_KEY',
				'tokenName' => esc_html_x( 'Post meta key', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_META_VALUE',
				'tokenName' => esc_html_x( 'Post meta value', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POSTSPECIFICUMETAVAL',
				'tokenName' => esc_html_x( 'Meta value', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical post-meta token set for a given post and meta key.
	 *
	 * Reads the meta value via `get_post_meta($post_id, $meta_key, true)`.
	 * Array/object values serialize to JSON for safe token output. Both
	 * POST_META_VALUE and POSTSPECIFICUMETAVAL receive the same value — the
	 * former is the canonical id, the latter is the legacy id kept per
	 * Decision 23.
	 *
	 * Missing-post contract: POST_META_KEY echoes the user's selection,
	 * POST_META_VALUE and POSTSPECIFICUMETAVAL are empty strings.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $meta_key The meta key.
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_post_meta_tokens( int $post_id, string $meta_key ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'POST_META_KEY'        => $meta_key,
				'POST_META_VALUE'      => '',
				'POSTSPECIFICUMETAVAL' => '',
			);
		}

		$raw = get_post_meta( $post_id, $meta_key, true );

		$value = ( is_array( $raw ) || is_object( $raw ) )
			? (string) wp_json_encode( $raw )
			: (string) $raw;

		return array(
			'POST_META_KEY'        => $meta_key,
			'POST_META_VALUE'      => $value,
			'POSTSPECIFICUMETAVAL' => $value,
		);
	}

	/**
	 * Canonical user-meta token set.
	 *
	 * Uses the underscore-prefixed `USER_META_KEY` / `USER_META_VALUE` shape
	 * per PLAN.md 2.1, matching the rest of the canonical user-domain naming
	 * (USER_ID, USER_LOGIN, USER_EMAIL, etc.). The legacy `UMETAKEY` /
	 * `UMETAVALUE` IDs are kept alive via the alias map (Step 3) so stored
	 * recipes keep resolving.
	 *
	 * @return array<int, array{tokenId: string, tokenName: string, tokenType: string}>
	 */
	public static function user_meta_tokens() {
		return array(
			array(
				'tokenId'   => 'USER_META_KEY',
				'tokenName' => esc_html_x( 'User meta key', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_META_VALUE',
				'tokenName' => esc_html_x( 'User meta value', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the canonical user-meta token set for a given user and meta key.
	 *
	 * Reads the meta value via `get_user_meta($user_id, $meta_key, true)`.
	 * Array/object values serialize to JSON for safe token output.
	 *
	 * Missing-user contract: USER_META_KEY echoes the user's selection,
	 * USER_META_VALUE is the empty string.
	 *
	 * @param int    $user_id  The user ID.
	 * @param string $meta_key The meta key.
	 *
	 * @return array<string, string> Token ID => value pairs.
	 */
	public static function hydrate_user_meta_tokens( int $user_id, string $meta_key ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'USER_META_KEY'   => $meta_key,
				'USER_META_VALUE' => '',
			);
		}

		$raw = get_user_meta( $user_id, $meta_key, true );

		$value = ( is_array( $raw ) || is_object( $raw ) )
			? (string) wp_json_encode( $raw )
			: (string) $raw;

		return array(
			'USER_META_KEY'   => $meta_key,
			'USER_META_VALUE' => $value,
		);
	}

	/**
	 * Convert trigger-format token definitions to action-format token definitions.
	 *
	 * Trigger format:  array( 'tokenId' => 'X', 'tokenName' => '...', 'tokenType' => 'text' )
	 * Action format:   'TOKEN_ID' => array( 'name' => '...', 'type' => 'text' )
	 *
	 * @param array<int, array{tokenId: string, tokenName: string, tokenType?: string}> $trigger_tokens Trigger-format tokens.
	 *
	 * @return array<string, array{name: string, type: string}> Action-format tokens.
	 */
	public static function to_action_tokens( array $trigger_tokens ) {
		$out = array();

		foreach ( $trigger_tokens as $t ) {
			$out[ $t['tokenId'] ] = array(
				'name' => $t['tokenName'],
				'type' => $t['tokenType'] ?? 'text',
			);
		}

		return $out;
	}

	/**
	 * Resolve a stored option_code value into a token display label.
	 *
	 * Modern remote_data dropdowns emit '-1' as the "Any X" sentinel; legacy
	 * dropdowns sometimes left the value empty. Either way, surfacing the raw
	 * sentinel in the action log is unhelpful — render the caller-supplied
	 * "Any X" label instead. A concrete slug / ID is passed through unchanged.
	 *
	 * @param mixed  $value     The stored meta value (string, int, null, etc.).
	 * @param string $any_label The translated "Any X" label to substitute when
	 *                          $value is the sentinel.
	 *
	 * @return string
	 */
	public static function any_or_value( $value, string $any_label ): string {
		$value = (string) $value;

		return ( '-1' === $value || '' === $value ) ? $any_label : $value;
	}
}
