<?php

namespace Uncanny_Automator\Integrations\Aioseo;

/**
 * Class Aioseo_Helpers
 *
 * @package Uncanny_Automator
 */
class Aioseo_Helpers {

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
				'text'  => esc_html_x( 'Any post type', 'All in One SEO', 'uncanny-automator' ),
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
				'text'  => esc_html_x( 'Any post', 'All in One SEO', 'uncanny-automator' ),
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
	 * AJAX handler to fetch posts by type (for actions - no "Any" option).
	 *
	 * @return void
	 */
	public function ajax_fetch_posts_by_type() {

		Automator()->utilities->verify_nonce();

		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$post_type = isset( $values['AIOSEO_POST_TYPE'] ) ? sanitize_text_field( $values['AIOSEO_POST_TYPE'] ) : '';

		if ( empty( $post_type ) ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => array(),
				)
			);
			die();
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $this->get_posts_by_type( $post_type, false ),
			)
		);
		die();
	}

	/**
	 * AJAX handler to fetch posts by type (for triggers - with "Any" option).
	 *
	 * @return void
	 */
	public function ajax_fetch_posts_for_triggers() {

		Automator()->utilities->verify_nonce();

		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$post_type = isset( $values['AIOSEO_POST_TYPE'] ) ? sanitize_text_field( $values['AIOSEO_POST_TYPE'] ) : '';

		// If no type or "Any" selected, return only the "Any post" option.
		if ( empty( $post_type ) || '-1' === $post_type ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => array(
						array(
							'text'  => esc_html_x( 'Any post', 'All in One SEO', 'uncanny-automator' ),
							'value' => '-1',
						),
					),
				)
			);
			die();
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $this->get_posts_by_type( $post_type, true ),
			)
		);
		die();
	}

	/**
	 * Get the common post type + post selector options for actions.
	 *
	 * @param string $action_meta The action meta code for the post field.
	 *
	 * @return array
	 */
	public function get_post_type_and_post_options( $action_meta ) {

		return array(
			array(
				'option_code'           => 'AIOSEO_POST_TYPE',
				'label'                 => esc_html_x( 'Post type', 'All in One SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_all_post_types( false ),
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => $action_meta,
				'label'                 => esc_html_x( 'Post', 'All in One SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'ajax'                  => array(
					'endpoint'      => 'automator_aioseo_fetch_posts',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'AIOSEO_POST_TYPE' ),
				),
			),
		);
	}

	/**
	 * Get the post type + post selector options for triggers (with "Any" support).
	 *
	 * @param string $trigger_meta The trigger meta code for the post field.
	 *
	 * @return array
	 */
	public function get_trigger_post_type_and_post_options( $trigger_meta ) {

		return array(
			array(
				'option_code' => 'AIOSEO_POST_TYPE',
				'label'       => esc_html_x( 'Post type', 'All in One SEO', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->get_all_post_types( true ),
			),
			array(
				'option_code' => $trigger_meta,
				'label'       => esc_html_x( 'Post', 'All in One SEO', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'ajax'        => array(
					'endpoint'      => 'automator_aioseo_fetch_posts_for_trigger',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'AIOSEO_POST_TYPE' ),
				),
			),
		);
	}

	/**
	 * Get the AIOSEO data for a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return object|null
	 */
	public function get_aioseo_post( $post_id ) {

		if ( ! class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			return null;
		}

		return \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
	}
}
