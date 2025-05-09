<?php
/**
 * Duplicates a WordPress post
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class WP_DUPLICATE_POST
 *
 * Duplicates an existing WordPress post with all its content and metadata.
 *
 * @package Uncanny_Automator
 */
class WP_DUPLICATE_POST extends Action {

	/**
	 * Setup action method.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DUPLICATE_POST' );
		$this->set_action_meta( 'WP_POST' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the post to be duplicated
				esc_html_x( 'Duplicate {{a post:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Duplicate {{a post}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define the action's options.
	 *
	 * @return array The options configuration.
	 */
	public function options() {
		$post_options = Automator()->helpers->recipe->wp->options->all_posts(
			esc_html_x( 'Post', 'WordPress', 'uncanny-automator' ),
			$this->get_action_meta(),
			array(
				'token'      => false,
				'is_ajax'    => false,
				'any_option' => false,
			)
		);

		$post_type_options = array();
		if ( isset( $post_options['options'] ) && is_array( $post_options['options'] ) ) {
			foreach ( $post_options['options'] as $value => $text ) {
				if ( '-1' === $value || -1 === $value ) {
					continue;
				}
				$post_type_options[] = array(
					'value' => $value,
					'text'  => $text,
				);
			}
		}

		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_attr_x( 'Post', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $post_type_options,
				'supports_tokens' => true,
				'is_ajax'         => false,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array Token definitions.
	 */
	public function define_tokens() {
		return array(
			'DUPLICATED_POST_ID'    => array(
				'name' => esc_html_x( 'Duplicated post ID', 'WordPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'DUPLICATED_POST_TITLE' => array(
				'name' => esc_html_x( 'Duplicated post title', 'WordPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'DUPLICATED_POST_URL'   => array(
				'name' => esc_html_x( 'Duplicated post URL', 'WordPress', 'uncanny-automator' ),
				'type' => 'url',
			),
			'ORIGINAL_POST_ID'      => array(
				'name' => esc_html_x( 'Original post ID', 'WordPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'ORIGINAL_POST_TITLE'   => array(
				'name' => esc_html_x( 'Original post title', 'WordPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'ORIGINAL_POST_URL'     => array(
				'name' => esc_html_x( 'Original post URL', 'WordPress', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Process the action: Duplicate a post.
	 *
	 * @param int    $user_id     User ID.
	 * @param array  $action_data Action data.
	 * @param int    $recipe_id   Recipe ID.
	 * @param array  $args        Extra arguments.
	 * @param array  $parsed      Parsed meta values.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Get required parsed values
		$post_id      = absint( $this->get_parsed_meta_value( $this->get_action_meta(), 0 ) );
		$title_suffix = sanitize_text_field( $this->get_parsed_meta_value( 'POST_TITLE_SUFFIX', esc_html_x( '(Copy)', 'WordPress', 'uncanny-automator' ) ) );
		$post_status  = sanitize_text_field( $this->get_parsed_meta_value( 'POST_STATUS', 'draft' ) );

		// Fetch original post
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			$this->add_log_error(
				sprintf(
				/* translators: %d - Post ID */
					esc_html_x( 'Post with ID %d not found.', 'WordPress', 'uncanny-automator' ),
					$post_id
				)
			);
			return false;
		}

		// Prepare new post args
		$new_post_args = array(
			'post_title'     => $post->post_title . ' ' . $title_suffix,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post_status,
			'post_type'      => $post->post_type,
			'post_author'    => $post->post_author,
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		);

		$new_post_id = wp_insert_post( $new_post_args );

		if ( is_wp_error( $new_post_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create duplicate post.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		// Copy post meta
		$post_meta = get_post_meta( $post_id );
		foreach ( $post_meta as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		// Copy taxonomies
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		// Hydrate tokens
		$this->hydrate_tokens(
			array(
				'DUPLICATED_POST_ID'    => $new_post_id,
				'DUPLICATED_POST_TITLE' => get_the_title( $new_post_id ),
				'DUPLICATED_POST_URL'   => get_permalink( $new_post_id ),
				'ORIGINAL_POST_ID'      => $post_id,
				'ORIGINAL_POST_TITLE'   => get_the_title( $post_id ),
				'ORIGINAL_POST_URL'     => get_permalink( $post_id ),
			)
		);

		return true;
	}
}
