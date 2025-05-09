<?php
/**
 * Duplicates a WordPress page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class WP_DUPLICATE_PAGE
 *
 * @package Uncanny_Automator
 */
class WP_DUPLICATE_PAGE extends Action {

	/**
	 * Setup action method.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DUPLICATE_PAGE' );
		$this->set_action_meta( 'WP_PAGE' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the page to be duplicated
				esc_html_x( 'Duplicate {{a page:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Duplicate {{a page}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define the action's options.
	 *
	 * @return array The options configuration.
	 */
	public function options() {
		$page_options = Automator()->helpers->recipe->wp->options->all_pages(
			esc_html_x( 'Page', 'WordPress', 'uncanny-automator' ),
			$this->get_action_meta(),
			array(
				'token'      => false,
				'is_ajax'    => false,
				'any_option' => false,
			)
		);

		$page_type_options = array();
		if ( isset( $page_options['options'] ) && is_array( $page_options['options'] ) ) {
			foreach ( $page_options['options'] as $value => $text ) {
				if ( '-1' === $value || -1 === $value ) {
					continue;
				}
				$page_type_options[] = array(
					'value' => $value,
					'text'  => $text,
				);
			}
		}

		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_attr_x( 'Page', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $page_type_options,
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
			'DUPLICATED_PAGE_ID'    => array(
				'name' => esc_html_x( 'Duplicated page ID', 'WordPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'DUPLICATED_PAGE_TITLE' => array(
				'name' => esc_html_x( 'Duplicated page title', 'WordPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'DUPLICATED_PAGE_URL'   => array(
				'name' => esc_html_x( 'Duplicated page URL', 'WordPress', 'uncanny-automator' ),
				'type' => 'url',
			),
			'ORIGINAL_PAGE_ID'      => array(
				'name' => esc_html_x( 'Original page ID', 'WordPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'ORIGINAL_PAGE_TITLE'   => array(
				'name' => esc_html_x( 'Original page title', 'WordPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'ORIGINAL_PAGE_URL'     => array(
				'name' => esc_html_x( 'Original page URL', 'WordPress', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
 * Process the action: Duplicate a page.
 *
 * @param int    $user_id     The user ID.
 * @param array  $action_data The action data.
 * @param int    $recipe_id   The recipe ID.
 * @param array  $args        The arguments.
 * @param array  $parsed      The parsed meta values.
 *
 * @return bool True if successful, false otherwise.
 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$page_id      = absint( $this->get_parsed_meta_value( $this->get_action_meta(), 0 ) );
		$title_suffix = sanitize_text_field( $this->get_parsed_meta_value( 'PAGE_TITLE_SUFFIX', esc_html_x( '(Copy)', 'WordPress', 'uncanny-automator' ) ) );
		$page_status  = sanitize_text_field( $this->get_parsed_meta_value( 'PAGE_STATUS', 'draft' ) );

		$page = get_post( $page_id );

		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type ) {
			$this->add_log_error(
				sprintf(
				/* translators: %d - Page ID */
					esc_html_x( 'Page with ID %d not found or is not a page.', 'WordPress', 'uncanny-automator' ),
					$page_id
				)
			);
			return false;
		}

		$new_page_args = array(
			'post_title'     => $page->post_title . ' ' . $title_suffix,
			'post_content'   => $page->post_content,
			'post_excerpt'   => $page->post_excerpt,
			'post_status'    => $page_status,
			'post_type'      => 'page',
			'post_author'    => $page->post_author,
			'post_parent'    => $page->post_parent,
			'menu_order'     => $page->menu_order,
			'comment_status' => $page->comment_status,
			'ping_status'    => $page->ping_status,
		);

		$new_page_id = wp_insert_post( $new_page_args );

		if ( is_wp_error( $new_page_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create duplicate page.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		// Duplicate all meta
		$page_meta = get_post_meta( $page_id );
		foreach ( $page_meta as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $new_page_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		// Duplicate page template
		$template = get_page_template_slug( $page_id );
		if ( ! empty( $template ) ) {
			update_post_meta( $new_page_id, '_wp_page_template', $template );
		}

		// Set token values
		$this->hydrate_tokens(
			array(
				'DUPLICATED_PAGE_ID'    => $new_page_id,
				'DUPLICATED_PAGE_TITLE' => get_the_title( $new_page_id ),
				'DUPLICATED_PAGE_URL'   => get_permalink( $new_page_id ),
				'ORIGINAL_PAGE_ID'      => $page_id,
				'ORIGINAL_PAGE_TITLE'   => get_the_title( $page_id ),
				'ORIGINAL_PAGE_URL'     => get_permalink( $page_id ),
			)
		);

		return true;
	}
}
