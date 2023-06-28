<?php
/**
 * This file handles the long query when recipe log view is not available.
 *
 * @var int[] $params The parameters sent to the action-logs-queries
 *
 * @since 4.15
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$args = wp_parse_args(
	$params,
	array(
		'recipe_id'     => 0,
		'run_number'    => 0,
		'recipe_log_id' => 0,
	)
);

global $wpdb;

return $wpdb->get_row(
	$wpdb->prepare(
		"SELECT 
            r.id                  AS recipe_log_id,
            r.user_id             AS user_id,
            r.date_time           AS recipe_date_time,
            r.completed           AS recipe_completed,
            r.run_number          AS run_number,
            r.completed           AS completed,
            r.automator_recipe_id AS automator_recipe_id,
            u.user_email          AS user_email,
            u.display_name        AS display_name,
            p.post_title          AS recipe_title
        FROM {$wpdb->prefix}uap_recipe_log r
            LEFT JOIN {$wpdb->prefix}users u ON u.id = r.user_id
            JOIN {$wpdb->prefix}posts p ON p.id = r.automator_recipe_id
            -- The log details specifics.
            WHERE r.id = %d 
                AND r.run_number = %d
                AND r.automator_recipe_id = %d
        ",
		$args['recipe_log_id'],
		$args['run_number'],
		$args['recipe_id']
	),
	ARRAY_A
);


