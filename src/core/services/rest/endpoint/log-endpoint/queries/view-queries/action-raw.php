<?php
/**
 * This file handles the long query when action views is not available.
 *
 * @var int[] $params The parameters sent to the action-logs-queries
 *
 * @since 4.12
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

global $wpdb;

$args = wp_parse_args(
	$params,
	array(
		'recipe_id'     => 0,
		'run_number'    => 0,
		'recipe_log_id' => 0,
	)
);

return $wpdb->get_results(
	$wpdb->prepare(
		"SELECT a.automator_action_id,
                a.date_time               AS action_date,
                a.completed               AS action_completed,
                a.error_message,
                a.automator_recipe_id,
                a.id                      AS action_log_id,
                a.automator_recipe_log_id AS recipe_log_id,
                r.date_time               AS recipe_date_time,
                r.completed               AS recipe_completed,
                r.run_number              AS recipe_run_number,
                pa.post_title             AS action_title,
                am.meta_value             AS action_sentence,
                p.post_title              AS recipe_title,
                u.id                      AS user_id,
                u.user_email,
                u.display_name
        FROM   wp_uap_action_log a
                LEFT JOIN wp_uap_recipe_log r
                    ON a.automator_recipe_log_id = r.id
                LEFT JOIN wp_posts p
                    ON p.id = a.automator_recipe_id
                JOIN wp_posts pa
                ON pa.id = a.automator_action_id
                LEFT JOIN wp_uap_action_log_meta am
                    ON a.automator_action_id = am.automator_action_id
                    AND am.automator_action_log_id = a.id
                    AND am.user_id = a.user_id
                    AND am.meta_key = 'sentence_human_readable_html'
                LEFT JOIN wp_users u
                    ON a.user_id = u.id
        WHERE  ( 1 = 1
                AND a.automator_recipe_id = %d
                AND a.automator_recipe_log_id = %d
                AND r.run_number = %d )
        GROUP  BY a.id ",
		$args['recipe_id'],
		$args['recipe_log_id'],
		$args['run_number']
	),
	ARRAY_A
);

