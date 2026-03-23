<?php

namespace Uncanny_Automator\Integrations\Seo_By_Rank_Math;

/**
 * Class Seo_By_Rank_Math_Helpers
 *
 * @package Uncanny_Automator
 */
class Seo_By_Rank_Math_Helpers {

	/**
	 * Meta values captured from the current REST API save request.
	 *
	 * Populated by the `rank_math/filter_metadata` filter before
	 * `rank_math/pre_update_metadata` fires, so triggers can read
	 * the incoming (new) values before they hit the database.
	 *
	 * @var array
	 */
	private $pending_meta = array();

	/**
	 * Register hooks that bridge Rank Math save events into a single
	 * custom action the triggers can listen to.
	 *
	 * @return void
	 */
	public function register_metadata_hooks() {
		// Capture the incoming meta before it is written.
		add_filter( 'rank_math/filter_metadata', array( $this, 'capture_pending_meta' ), 10, 2 );

		// REST API / Gutenberg save path.
		add_action( 'rank_math/pre_update_metadata', array( $this, 'on_rest_metadata_save' ), 10, 3 );
	}

	/**
	 * Store the meta array that Rank Math is about to persist.
	 *
	 * @param array            $meta    Associative array of meta keys → values.
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return array Unchanged meta (pass-through filter).
	 */
	public function capture_pending_meta( $meta, $request ) {
		$this->pending_meta = is_array( $meta ) ? $meta : array();
		return $meta;
	}

	/**
	 * Bridge the Rank Math REST save event into a custom action.
	 *
	 * @param int    $object_id   The post / term / user ID.
	 * @param string $object_type The object type (post, term, user).
	 * @param string $content     The post content.
	 *
	 * @return void
	 */
	public function on_rest_metadata_save( $object_id, $object_type, $content ) {
		if ( 'post' !== $object_type ) {
			return;
		}

		do_action( 'automator_rank_math_seo_data_saved', (int) $object_id );
	}

	/**
	 * Return the best-available value for a Rank Math meta key.
	 *
	 * Checks the pending (not-yet-written) meta first, then falls
	 * back to the database.  This lets triggers that fire *before*
	 * the meta is persisted still read the incoming values.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $meta_key The full meta key (e.g. `rank_math_title`).
	 *
	 * @return mixed
	 */
	public function get_meta_value( $post_id, $meta_key ) {
		if ( ! empty( $this->pending_meta ) && isset( $this->pending_meta[ $meta_key ] ) ) {
			return $this->pending_meta[ $meta_key ];
		}

		return get_post_meta( $post_id, $meta_key, true );
	}

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
				'text'  => esc_html_x( 'Any post type', 'Rank Math SEO', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

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
				'text'  => esc_html_x( 'Any post', 'Rank Math SEO', 'uncanny-automator' ),
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
		$post_type = isset( $values['RANK_MATH_POST_TYPE'] ) ? sanitize_text_field( $values['RANK_MATH_POST_TYPE'] ) : '';

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
		$post_type = isset( $values['RANK_MATH_POST_TYPE'] ) ? sanitize_text_field( $values['RANK_MATH_POST_TYPE'] ) : '';

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
	 * @param string $meta The trigger/action meta key.
	 *
	 * @return array[]
	 */
	public function get_post_type_and_post_options_for_triggers( $meta ) {

		return array(
			array(
				'option_code'           => 'RANK_MATH_POST_TYPE',
				'label'                 => esc_html_x( 'Post type', 'Rank Math SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_all_post_types( true ),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => $meta,
				'label'                 => esc_html_x( 'Post', 'Rank Math SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'ajax'                  => array(
					'endpoint'      => 'automator_rank_math_fetch_posts_for_triggers',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'RANK_MATH_POST_TYPE' ),
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
				'option_code'           => 'RANK_MATH_POST_TYPE',
				'label'                 => esc_html_x( 'Post type', 'Rank Math SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_all_post_types( false ),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => $meta,
				'label'                 => esc_html_x( 'Post', 'Rank Math SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'ajax'                  => array(
					'endpoint'      => 'automator_rank_math_fetch_posts_by_type',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'RANK_MATH_POST_TYPE' ),
				),
			),
		);
	}

}
