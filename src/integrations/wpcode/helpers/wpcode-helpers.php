<?php

namespace Uncanny_Automator;

/**
 * Class Wpcode_Helpers
 *
 * @package Uncanny_Automator
 */
class Wpcode_Helpers {

	/**
	 * get_wpcode_snippets
	 *
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_wpcode_snippets( $args = array() ) {
		$defaults = array(
			'option_code'           => 'WP_CODE_SNIPPETS',
			'label'                 => esc_attr__( 'Snippet', 'uncanny-automator' ),
			'is_any'                => false,
			'is_all'                => false,
			'supports_custom_value' => false,
			'relevant_tokens'       => array(),
			'snippet_status'        => 'publish',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 9999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'wpcode',
			'post_status'    => $args['snippet_status'],
		);

		$snippets = Automator()->helpers->recipe->options->wp_query( $query_args );

		if ( true === $args['is_any'] ) {
			$snippets = array( '-1' => __( 'Any snippet', 'uncanny-automator' ) ) + $snippets;
		}

		if ( true === $args['is_all'] ) {
			$snippets = array( '-1' => __( 'All snippets', 'uncanny-automator' ) ) + $snippets;
		}

		$option = array(
			'option_code'           => $args['option_code'],
			'label'                 => $args['label'],
			'input_type'            => 'select',
			'required'              => true,
			'options_show_id'       => false,
			'relevant_tokens'       => $args['relevant_tokens'],
			'options'               => $snippets,
			'supports_custom_value' => $args['supports_custom_value'],
		);

		return apply_filters( 'uap_option_get_wpcode_snippets', $option );
	}

}
