<?php

namespace Uncanny_Automator\Integrations\Thrive_Quiz_Builder;

/**
 * Class Thrive_Quiz_Builder_Helpers
 *
 * @package Uncanny_Automator
 */
class Thrive_Quiz_Builder_Helpers {

	/**
	 * get_dropdown_options_quizzes
	 * Returns quiz options in modern format: [ [ value => ID, text => Label ], ... ]
	 *
	 * @param bool $include_any Whether to include the "Any" option.
	 * @param bool $include_all Whether to include the "All" option.
	 *
	 * @return array
	 */
	public function get_dropdown_options_quizzes( $include_any = true, $include_all = false ) {
		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any quiz', 'Thrive Quiz Builder', 'uncanny-automator' ),
			);
		}

		if ( $include_all ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'All quizzes', 'Thrive Quiz Builder', 'uncanny-automator' ),
			);
		}

		$query_args = array(
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tqb_quiz',
			'post_status'    => 'publish',
		);

		$quizzes = Automator()->helpers->recipe->options->wp_query( $query_args );

		foreach ( $quizzes as $id => $label ) {
			$options[] = array(
				'value' => $id,
				'text'  => $label,
			);
		}

		return $options;
	}
}
