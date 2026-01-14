<?php

namespace Uncanny_Automator\Integrations\Wpjm;

// Backwards compatibility for old helper classes.
class_alias( 'Uncanny_Automator\Integrations\Wpjm\Wpjm_Helpers', 'Uncanny_Automator\Wpjm_Helpers' );

/**
 * Class Wpjm_Helpers
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Helpers {

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Wpjm_Helpers constructor.
	 */
	public function __construct() {
		// Constructor can be empty in new framework
	}

	/**
	 * Get job types options
	 *
	 * @return array
	 */
	public function list_wpjm_job_types() {
		$options = array();

		$options[] = array(
			'text' => esc_html_x( 'Any type', 'WP Job Manager', 'uncanny-automator' ),
			'value' => '-1',
		);

		if ( Automator()->helpers->recipe->load_helpers ) {
			// WP Job Manager is hiding terms on non job template
			$terms = get_terms(
				array(
					'taxonomy'   => 'job_listing_type',
					'hide_empty' => false,
					'public'     => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$options[] = array(
							'text' => esc_html( $term->name ),
							'value' => (string) $term->term_id,
						);
					}
				}
			}
		}

		return $options;
	}

	/**
	 * Get jobs options
	 *
	 * @return array
	 */
	public function list_wpjm_jobs() {
		$options = array();

		$options[] = array(
			'text' => esc_html_x( 'Any job', 'WP Job Manager', 'uncanny-automator' ),
			'value' => '-1',
		);

		if ( Automator()->helpers->recipe->load_helpers ) {
			// WP Job Manager is hiding terms on non job template
			$args = array(
				'post_type'      => 'job_listing',
				'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			);
			$jobs = get_posts( $args );
			if ( ! is_wp_error( $jobs ) ) {
				if ( ! empty( $jobs ) ) {
					foreach ( $jobs as $job ) {
						$options[] = array(
							'text' => esc_html( $job->post_title ),
							'value' => (string) $job->ID,
						);
					}
				}
			}
		}

		return $options;
	}
}
