<?php

namespace Uncanny_Automator;

/**
 * Class Thrive_Quiz_Builder_Helpers
 *
 * @package Uncanny_Automator
 */
class Thrive_Quiz_Builder_Helpers {

	/**
	 * get_all_thrive_quizzes
	 *
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_all_thrive_quizzes( $args = array() ) {
		$defaults = array(
			'option_code'           => 'TQB_QUIZ',
			'label'                 => esc_attr__( 'Quiz', 'uncanny-automator' ),
			'is_any'                => false,
			'is_all'                => false,
			'supports_custom_value' => false,
			'relevant_tokens'       => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tqb_quiz',
			'post_status'    => 'publish',
		);

		$all_quizzes = Automator()->helpers->recipe->options->wp_query( $query_args );

		if ( true === $args['is_any'] ) {
			$all_quizzes = array( '-1' => __( 'Any quiz', 'uncanny-automator' ) ) + $all_quizzes;
		}

		if ( true === $args['is_all'] ) {
			$all_quizzes = array( '-1' => __( 'All quizzes', 'uncanny-automator' ) ) + $all_quizzes;
		}

		$option = array(
			'option_code'           => $args['option_code'],
			'label'                 => $args['label'],
			'input_type'            => 'select',
			'required'              => true,
			'options_show_id'       => false,
			'relevant_tokens'       => $args['relevant_tokens'],
			'options'               => $all_quizzes,
			'supports_custom_value' => $args['supports_custom_value'],
		);

		return apply_filters( 'uap_option_get_all_thrive_quizzes', $option );
	}

}
