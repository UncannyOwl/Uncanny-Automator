<?php

namespace Uncanny_Automator\Integrations\Learndash_Achievements;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Ld_Achievements_Helpers
 *
 * @package Uncanny_Automator\Integrations\Learndash_Achievements
 */
class Ld_Achievements_Helpers extends Abstract_Helpers {

	/**
	 * Remote-data handler: load achievements for dropdown options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_achievements( $request ): array {

		$achievements = get_posts(
			array(
				'post_type'      => 'ld-achievement',
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$options = array();

		foreach ( $achievements as $achievement ) {
			$title = $achievement->post_title;

			if ( empty( $title ) ) {
				$title = sprintf( esc_html_x( 'ID: %d (no title)', 'LearnDash', 'uncanny-automator' ), $achievement->ID );
			}

			$options[] = array(
				'value' => (string) $achievement->ID,
				'text'  => $title,
			);
		}

		return $this->remote_data_success( $options );
	}
}
