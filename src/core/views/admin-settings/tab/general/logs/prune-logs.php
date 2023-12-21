<?php

namespace Uncanny_Automator;

/**
 * Prune logs template.
 * Logs Settings > General > Logs > Prune logs.
 *
 * @package Uncanny_Automator
 *
 * @since   5.4 - Moved the form action to wp-ajax and added error message handler.
 * @since   3.7 - Added.
 *
 * @author  Daniela R. & Agustin B.
 *
 * @var  string $number_of_days          The value of the field, if there is any.
 * @var  bool   $user_pruned_before      TRUE if the user manually pruned the logs before.
 * @var  string $last_manual_prune_date  Date of the last time the user manually pruned the logs.
 * @var  bool   $user_just_pruned_logs   TRUE if the user JUST pruned the logs.
 */

?>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

	<?php wp_nonce_field( 'uncanny_automator' ); ?>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Prune recipe logs', 'uncanny-automator' ); ?>
	</div>

	<?php if ( $user_just_pruned_logs && ! automator_filter_has_var( 'error_message' ) ) { ?>
		<uo-alert
			type="success"
			heading="<?php esc_attr_e( 'Activity logs successfully pruned', 'uncanny-automator' ); ?>"
			class="uap-spacing-top"
		></uo-alert>
	<?php } ?>

	<?php if ( automator_filter_has_var( 'error_message' ) ) { ?>
		<uo-alert
			type="error"
			heading="<?php esc_attr_e( 'An error has occured while pruning logs', 'uncanny-automator' ); ?>"
			class="uap-spacing-top"
		>
			<?php echo esc_html( rawurldecode( automator_filter_input( 'error_message' ) ) ); ?>
		</uo-alert>
	<?php } ?>

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

	<?php } ?>

	<input type="hidden" name="action" value="prune_logs" />

	<uo-text-field
		id="automator_manual_purge_days"
		value="<?php echo esc_attr( $number_of_days ); ?>"
		required

		label="<?php esc_attr_e( 'Days', 'uncanny-automator' ); ?>"
		helper="<?php esc_attr_e( 'Log entries older than the number of days specified above will be automatically purged (unless they are In Progress). Decimals are supported, so a value of 0.5 will remove log entries older than 12 hours.', 'uncanny-automator' ); ?>"
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
