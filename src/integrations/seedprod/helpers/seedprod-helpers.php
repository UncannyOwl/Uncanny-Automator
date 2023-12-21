<?php
namespace Uncanny_Automator;

/**
 * Class Seedprod_Helpers
 *
 * @package Uncanny_Automator
 */
class Seedprod_Helpers {

	/**
	 * Retrieves all the landing pages.
	 *
	 * SeedProd does not have a function that does this so we have to query them up.
	 *
	 * @return string[]
	 */
	public static function get_landing_pages() {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID as `value`, post_title as `text` FROM $wpdb->posts p
				    LEFT JOIN $wpdb->postmeta pm ON( pm . post_id = p . ID )
				        WHERE post_type = %s
                            AND post_status <> %s
				            AND meta_key    = %s
                                ORDER BY post_title ASC
                                LIMIT 9999",
				'page',
				'trash',
				'_seedprod_page'
			),
			ARRAY_A
		);

		return array_merge(
			array(
				array(
					'value' => -1,
					'text'  => esc_attr_x(
						'Any landing page',
						'Seedprod',
						'uncanny-automator'
					),
				),
			),
			(array) $results
		);
	}
}
