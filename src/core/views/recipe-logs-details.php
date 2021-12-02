<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Logs_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Set orderby.
$_GET['orderby'] = 'automator_trigger_id';

$current_tab = 'trigger-log';
?>

<div class="uap-logs-minimal">
	<h2><?php esc_html_e( 'Recipe run details', 'uncanny-automator' ); ?></h2>

	<ul class="recipe-details__list">
		<li>
			<div class="recipe-details__label recipe-details__column-1">
				<?php esc_html_e( 'Recipe:', 'uncanny-automator' ); ?>
			</div>
			<div class="recipe-details__recipe-title recipe-details__column-2"></div>
		</li>
		<li>
			<div class="recipe-details__label recipe-details__column-1">
				<?php esc_html_e( 'Status:', 'uncanny-automator' ); ?>
			</div>
			<div class="recipe-details__recipe-status recipe-details__column-2"></div>
		</li>
		<li>
			<div class="recipe-details__label recipe-details__column-1">
				<?php esc_html_e( 'Completion date:', 'uncanny-automator' ); ?>
			</div>
			<div class="recipe-details__recipe-date recipe-details__column-2"></div>
		</li>
		<li>
			<div class="recipe-details__label recipe-details__column-1">
				<?php esc_html_e( 'Run #:', 'uncanny-automator' ); ?>
			</div>
			<div class="recipe-details__recipe-run-number recipe-details__column-2"></div>
		</li>
		<li>
			<div class="recipe-details__label recipe-details__column-1">
				<?php esc_html_e( 'User:', 'uncanny-automator' ); ?>
			</div>
			<div class="recipe-details__recipe-user recipe-details__column-2"></div>
		</li>
	</ul>

	<?php

		// Remove sorting feature.
		add_filter( 'automator_setup_trigger_logs_sortables', '__return_empty_array' );

		// Display the trigger logs table.
		automator_setup_trigger_logs(
			$current_tab,
			array(
				'trigger_title'      => esc_html__( 'Trigger activity', 'uncanny-automator' ),
				'trigger_date'       => esc_html__( 'Completion date:', 'uncanny-automator' ),
				'trigger_run_number' => esc_attr__( 'Trigger run #:', 'uncanny-automator' ),
			)
		);
		?>

	<?php
		// Action Activity.
		$_GET['orderby'] = 'automator_action_id';
		$current_tab     = 'action-log';

		// Remove sorting feature.
		add_filter( 'automator_setup_action_logs_sortables', '__return_empty_array' );

		// Display the action logs table.
		automator_setup_action_logs(
			$current_tab,
			array(
				'action_title'     => esc_html__( 'Action activity', 'uncanny-automator' ),
				'action_date'      => esc_html__( 'Completion date:', 'uncanny-automator' ),
				'action_completed' => esc_html__( 'Status:', 'uncanny-automator' ),
				'error_message'    => esc_html__( 'Notes:', 'uncanny-automator' ),
			)
		);
		?>
</div>

<script>

(function($) {
	"use strict";

	var is_automator_loaded_via_iframe = function() {
		try {
			return window.self !== window.top;
		} catch (e) {
			return true;
		}
	};

	if (!is_automator_loaded_via_iframe()) {
		$('.recipe-details__list').remove();
		return;
	}

	$('table > tbody > tr').addClass('is-expanded');

	// Read the first data (we're taking the same fields that has the same data).
	var $data_source = $('table.wp_list_logs_links > tbody > tr:first'),
		$title = $data_source.find('> td.recipe_title').html(),
		$status = $data_source.find('> td[data-colname="Recipe status"]').html(),
		$date = $data_source.find('> td.recipe_date_time').html(),
		$run = $data_source.find('> td.recipe_run_number').html(),
		$user = $data_source.find('> td[data-colname="User"]').html();

	if (0 === $date.trim().length) {
		$date = '&ndash;';
	}

	$('.recipe-details__recipe-title').html($title);
	$('.recipe-details__recipe-status').html($status);
	$('.recipe-details__recipe-date').html($date);
	$('.recipe-details__recipe-run-number').html($run);
	$('.recipe-details__recipe-user').html($user);

	// Remove/hide the table data.
	var $data_source_trs = $('table.wp_list_logs_links > tbody > tr:not(.no-items)');
	var $data_source_tds = $data_source_trs.find('> td');
	var allowed_data = [
		// Trigger Table.
		'.trigger_date',
		'.trigger_run_number',
		'.trigger_title',
		// Actions Table.
		'.action_date',
		'.action_title',
		'.action_completed',
		'.error_message',
	];

	$.each($data_source_tds, function() {

		if (!$(this).is(allowed_data.join())) {
			$(this).addClass('hidden');
		}

		// Check if there is a note.
		if ( $(this).is('.error_message') ) {
			if ( 0 === $(this).html().trim().length ) {
				$(this).addClass('hidden');
			}
		}

	});

	// Move paging
	$.each($('thead th.column-primary'), function() {
		var $page = $(this).closest('table').next().find('.displaying-num');
		$(this).append('<span class="item-count">' + $page.html() + '</span>');
		$(this).closest('table').next().remove();
	});

	// Open all links to target window.
	$('.recipe-details__list a').attr('target', '_BLANK');
})(jQuery);
</script>
