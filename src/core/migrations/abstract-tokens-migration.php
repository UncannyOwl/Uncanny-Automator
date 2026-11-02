<?php

namespace Uncanny_Automator\Migrations;

/**
 * Tokens_Migration.
 *
 * @package Uncanny_Automator
 */
abstract class Tokens_Migration extends Migration {

	/**
	 * strings_to_replace
	 *
	 * Override this method and return an array of string pairs to replace.
	 *
	 * @return array
	 */
	public function strings_to_replace() {

		return array(
			'{{EXAMPLE' => '{{UT:ADVANCED:EXAMPLE',
		);
	}


	/**
	 * migrate
	 *
	 * @return mixed
	 */
	public function migrate() {

		foreach ( array( AUTOMATOR_POST_TYPE_ACTION, AUTOMATOR_POST_TYPE_RECIPE, AUTOMATOR_POST_TYPE_LOOP, AUTOMATOR_POST_TYPE_LOOP_FILTER ) as $post_type ) {
			if ( ! $this->loop_through_post_type( $post_type ) ) {
				return;
			}
		}

		$this->complete();
	}
	/**
	 * Loop through post type.
	 *
	 * @param mixed $post_type The type.
	 * @return bool Whether every post was read and rewritten successfully.
	 */
	public function loop_through_post_type( $post_type ) {

		$posts = $this->get_all_posts( $post_type );
		if ( false === $posts ) {
			return false;
		}

		foreach ( $posts as $post ) {
			if ( ! $this->migrate_tokens_in_post( $post ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Every post of a type, in any status, the trash included.
	 *
	 * 'any' leaves out the trash, and a recipe restored from it would keep the old
	 * spellings for good.
	 *
	 * @since 8.0.0 Includes trashed posts.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return \WP_Post[]|false Posts, or false when their query failed.
	 */
	public function get_all_posts( $post_type ) {
		global $wpdb;

		$args = array(
			'post_type'   => $post_type,
			'numberposts' => -1,
			'post_status' => array( 'any', 'trash' ),
		);

		$actions = get_posts( $args );

		return '' === $wpdb->last_error ? $actions : false;
	}

	/**
	 * Rewrite the tokens in every meta row and in the content of a post.
	 *
	 * Row by row: a key may hold several values, and each one is rewritten on its own.
	 *
	 * @since 8.0.0 Rewrites each meta row, not the first value of each key.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool Whether every row and the content were rewritten successfully.
	 */
	public function migrate_tokens_in_post( $post ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; the meta cache has no row IDs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE post_id = %d ORDER BY meta_id ASC",
				$post->ID
			)
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			return false;
		}

		foreach ( $rows as $row ) {
			if ( ! $this->maybe_update_meta_row( (int) $row->meta_id, $row->meta_value ) ) {
				return false;
			}
		}

		return $this->migrate_tokens_in_content( $post );
	}

	/**
	 * Rewrite the tokens in the content of a post.
	 *
	 * Written to the row directly: wp_update_post() passes the content through kses
	 * whenever the request's user may not post unfiltered HTML, and the request that
	 * ends the upgrade is often a visitor's.
	 *
	 * @since 8.0.0 Writes the content without kses.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool Whether the content was unchanged or written successfully.
	 */
	public function migrate_tokens_in_content( $post ) {

		global $wpdb;

		$initial_content = $post->post_content;
		$updated_content = $this->replace_strings( $initial_content );

		// If nothing changed in the value, move on
		if ( $updated_content === $initial_content ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- One-off migration; see above.
		$updated = $wpdb->update( $wpdb->posts, array( 'post_content' => $updated_content ), array( 'ID' => $post->ID ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			return false;
		}

		clean_post_cache( $post->ID );
		return true;
	}

	/**
	 * Rewrite the tokens in one stored meta row, if it holds any.
	 *
	 * The stored value is decoded first and the strings inside it rewritten, so an
	 * array keeps its shape and is serialized once. The row is written by its ID,
	 * which leaves the other values of its key and any backslashes alone.
	 *
	 * @since 8.0.0
	 *
	 * @param int    $meta_id      The meta row.
	 * @param string $stored_value Its value as stored.
	 *
	 * @return bool Whether the value was unchanged or written successfully.
	 */
	public function maybe_update_meta_row( $meta_id, $stored_value ) {

		$initial_value = automator_safe_unserialize( $stored_value );
		$updated_value = $this->replace_strings_in_value( $initial_value );

		// If nothing changed in the value, move on
		if ( $updated_value === $initial_value ) {
			return true;
		}

		return update_metadata_by_mid( 'post', $meta_id, $updated_value );
	}

	/**
	 * Rewrite the strings in a value, and in every level of an array.
	 *
	 * Anything else, objects included, is returned as it is.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $value The decoded value.
	 *
	 * @return mixed
	 */
	public function replace_strings_in_value( $value ) {

		if ( is_string( $value ) ) {
			return $this->replace_strings( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->replace_strings_in_value( $item );
		}

		return $value;
	}

	/**
	 * replace_strings
	 *
	 * @param  string $initial_value
	 * @return string
	 */
	public function replace_strings( $initial_value ) {

		$updated_value = strtr( $initial_value, $this->strings_to_replace() );

		return $updated_value;
	}

	/**
	 * replace_strings_in_imports
	 *
	 * @return mixed
	 */
	public function replace_strings_in_imports( $value, $post_id, $new_post_id, $key ) {

		if ( is_string( $value ) ) {
			$value = $this->replace_strings( $value );
		}

		return $value;
	}
}
