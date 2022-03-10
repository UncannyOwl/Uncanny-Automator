<?php

namespace Uncanny_Automator;

/**
 * Prune Logs
 * Settings > General > Logs > Prune logs
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $number_of_days          The value of the field, if there is any
 * $user_pruned_before      TRUE if the user manually pruned the logs before
 * $last_manual_prune_date  Date of the last time the user manually pruned the logs
 * $user_just_pruned_logs   TRUE if the user JUST pruned the logs
 */

?>

<form method="POST">

	<?php

	// Add setting fields
	wp_nonce_field( 'uncanny_automator' );

	?>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Prune activity logs', 'uncanny-automator' ); ?>
	</div>

	<?php

	// If the user JUST pruned the logs
	// Then add a notice
	if ( $user_just_pruned_logs ) {

		?>

		<uo-alert
			type="success"
			heading="<?php esc_attr_e( 'Activity logs successfully pruned.', 'uncanny-automator' ); ?>"
			class="uap-spacing-top"
		></uo-alert>

		<?php

	}

	?>

	<?php

	// If the user pruned the logs before,
	// Then add a notice
	if ( $user_pruned_before ) {

		?>

		<uo-alert
			heading="<?php esc_attr_e( 'Action last performed on:', 'uncanny-automator' ); ?>"
			class="uap-spacing-top"
		>
			<?php echo esc_html( gmdate( get_option( 'date_format' ), $last_manual_prune_date ) ); ?>
		</uo-alert>

		<?php

	}

	?>

	<uo-text-field
		id="automator_manual_purge_days"
		value="<?php echo esc_attr( $number_of_days ); ?>"
		required

		label="<?php esc_attr_e( 'Days', 'uncanny-automator' ); ?>"
		helper="<?php esc_attr_e( 'Enter a number of days to delete recipe log entries older than the specified number of days. Logs will only be deleted for recipes that are not In Progress.', 'uncanny-automator' ); ?>"
		placeholder="<?php esc_attr_e( 'Ex: 10', 'uncanny-automator' ); ?>"

		class="uap-spacing-top"
	></uo-text-field>

	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Delete logs', 'uncanny-automator' ); ?>
	</uo-button>

</form>
