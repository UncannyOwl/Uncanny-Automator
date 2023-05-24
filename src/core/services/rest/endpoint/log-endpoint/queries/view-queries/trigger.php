<?php
/**
 * This file handles the long query when trigger views is not available.
 *
 * @var int[] $params The parameters sent to the trigger-logs-queries
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
		'recipe_id'      => 0,
		'run_number'     => 0,
		'recipe_log_id'  => 0,
		'trigger_id'     => 0,
		'trigger_log_id' => 0,
	)
);

return $wpdb->get_results(
	$wpdb->prepare(
		"SELECT 
            u.id                      AS user_id,
            u.user_email,
            u.display_name,
            t.automator_trigger_id,
            t.date_time               AS trigger_date,
            t.completed               AS trigger_completed,
            t.automator_recipe_id,
            t.id,
            pt.post_title             AS trigger_title,
            tm.meta_value             AS trigger_sentence,
            tm.run_number             AS trigger_run_number,
            tm.run_time               AS trigger_run_time,
            pm.meta_value             AS trigger_total_times,
            p.post_title              AS recipe_title,
            t.automator_recipe_log_id AS recipe_log_id,
            r.date_time               AS recipe_date_time,
            r.completed               AS recipe_completed,
            r.run_number              AS recipe_run_number
      FROM wp_uap_trigger_log t
            LEFT JOIN wp_users u
               ON u.id = t.user_id
            LEFT JOIN wp_posts p
               ON p.id = t.automator_recipe_id
            LEFT JOIN wp_posts pt
               ON pt.id = t.automator_trigger_id
            LEFT JOIN wp_uap_trigger_log_meta tm
               ON tm.automator_trigger_log_id = t.id
               AND tm.meta_key = 'sentence_human_readable'
            LEFT JOIN wp_uap_recipe_log r
               ON t.automator_recipe_log_id = r.id
            LEFT JOIN wp_postmeta pm
               ON pm.post_id = t.automator_trigger_id
               AND pm.meta_key = 'NUMTIMES'
      WHERE  ( 1 = 1
               AND t.automator_recipe_id = %d
               AND t.automator_recipe_log_id = %d
               AND r.run_number = %d 
               AND pt.ID = %d ) 
      ",
		$args['recipe_id'],
		$args['recipe_log_id'],
		$args['run_number'],
		$args['trigger_id']
	),
	ARRAY_A
);
