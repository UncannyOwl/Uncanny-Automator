<?php

namespace Uncanny_Automator\Integrations\Wordpress_Seo;

/**
 * Class Wordpress_Seo_Helpers
 *
 * @package Uncanny_Automator
 */
class Wordpress_Seo_Helpers {

	/**
	 * Yoast post meta prefix.
	 *
	 * @var string
	 */
	const META_PREFIX = '_yoast_wpseo_';

	/**
	 * Get all public post types as dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any post type" option.
	 *
	 * @return array
	 */
	public function get_all_post_types( $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any post type', 'Yoast SEO', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			$options[] = array(
				'text'  => $post_type->labels->singular_name,
				'value' => $post_type->name,
			);
		}

		return $options;
	}

	/**
	 * Get posts by post type as dropdown options.
	 *
	 * @param string $post_type   The post type slug.
	 * @param bool   $include_any Whether to include "Any post" option.
	 *
	 * @return array
	 */
	public function get_posts_by_type( $post_type = 'post', $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any post', 'Yoast SEO', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$args = array(
			'post_type'      => sanitize_text_field( $post_type ),
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {
			$options[] = array(
				'text'  => $post->post_title,
				'value' => (string) $post->ID,
			);
		}

		return $options;
	}

	/**
	 * AJAX handler to fetch posts by type (for actions — no "Any" option).
	 *
	 * @return void
	 */
	public function ajax_fetch_posts_by_type() {

		Automator()->utilities->verify_nonce();

		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$post_type = isset( $values['YOAST_POST_TYPE'] ) ? sanitize_text_field( $values['YOAST_POST_TYPE'] ) : '';

		if ( empty( $post_type ) ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => array(),
				)
			);
			die();
		}

		$options = $this->get_posts_by_type( $post_type, false );

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	/**
	 * AJAX handler to fetch posts by type (for triggers — includes "Any" option).
	 *
	 * @return void
	 */
	public function ajax_fetch_posts_for_triggers() {

		Automator()->utilities->verify_nonce();

		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$post_type = isset( $values['YOAST_POST_TYPE'] ) ? sanitize_text_field( $values['YOAST_POST_TYPE'] ) : '';

		if ( empty( $post_type ) ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => array(),
				)
			);
			die();
		}

		$options = $this->get_posts_by_type( $post_type, true );

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	/**
	 * Get post type + post selector options for triggers.
	 *
	 * @param string $meta The trigger meta key.
	 *
	 * @return array[]
	 */
	public function get_post_type_and_post_options_for_triggers( $meta ) {

		return array(
			array(
				'option_code'           => 'YOAST_POST_TYPE',
				'label'                 => esc_html_x( 'Post type', 'Yoast SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_all_post_types( true ),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => $meta,
				'label'                 => esc_html_x( 'Post', 'Yoast SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'ajax'                  => array(
					'endpoint'      => 'automator_yoast_fetch_posts_for_triggers',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'YOAST_POST_TYPE' ),
				),
			),
		);
	}

	/**
	 * Get post type + post selector options for actions.
	 *
	 * @param string $meta The action meta key.
	 *
	 * @return array[]
	 */
	public function get_post_type_and_post_options( $meta ) {

		return array(
			array(
				'option_code'           => 'YOAST_POST_TYPE',
				'label'                 => esc_html_x( 'Post type', 'Yoast SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_all_post_types( false ),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => $meta,
				'label'                 => esc_html_x( 'Post', 'Yoast SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'ajax'                  => array(
					'endpoint'      => 'automator_yoast_fetch_posts_by_type',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'YOAST_POST_TYPE' ),
				),
			),
		);
	}

	/**
	 * Get Yoast SEO data for a post from the indexable table.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return object|null The indexable row or null.
	 */
	public function get_yoast_indexable( $post_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'yoast_indexable';

		// Verify the table exists.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( null === $table_exists ) {
			return null;
		}

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . "` WHERE object_id = %d AND object_type = 'post'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $post_id )
			)
		);
	}

	/**
	 * Update Yoast SEO post meta using the WPSEO_Meta class.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix, e.g. 'title').
	 * @param string $value   The meta value.
	 *
	 * @return bool|int
	 */
	public function update_yoast_meta( $post_id, $key, $value ) {

		if ( class_exists( 'WPSEO_Meta' ) ) {
			return \WPSEO_Meta::set_value( $key, $value, $post_id );
		}

		// Fallback to standard post meta.
		return update_post_meta( $post_id, self::META_PREFIX . $key, $value );
	}

	/**
	 * Delete Yoast SEO post meta.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix, e.g. 'title').
	 *
	 * @return bool
	 */
	public function delete_yoast_meta( $post_id, $key ) {

		if ( class_exists( 'WPSEO_Meta' ) ) {
			return \WPSEO_Meta::delete( $key, $post_id );
		}

		return delete_post_meta( $post_id, self::META_PREFIX . $key );
	}

	/**
	 * Get Yoast SEO post meta value.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The meta key (without prefix, e.g. 'title').
	 *
	 * @return string
	 */
	public function get_yoast_meta( $post_id, $key ) {

		if ( class_exists( 'WPSEO_Meta' ) ) {
			return \WPSEO_Meta::get_value( $key, $post_id );
		}

		return get_post_meta( $post_id, self::META_PREFIX . $key, true );
	}
}
