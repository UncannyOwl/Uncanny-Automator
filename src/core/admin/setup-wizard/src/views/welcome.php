<?php
/**
 * Setup-wizard main template file.
 *
 * @package Uncanny_Automator
 */

?>

<div class="automator-setup-wizard-wrap">

	<div class="automator-setup-wizard <?php echo esc_attr( $this->get_step() ); ?>">

		<?php $step = sanitize_file_name( $this->get_step() ); ?>

		<?php $view = $this->get_step_view(); ?>

		<?php require apply_filters( 'automator_setup_wizard_view_path', $view, array( 'step' => $step ) ); ?>

	</div>

</div>

<?php $this->set_has_tried_connecting( false ); ?>

<div class="automator-setup-wizard__footer">

	<?php // The final step de-emphasizes the dashboard link so the start-here options stand out. ?>
	<?php if ( $this->get_step_number() === $this->get_total_steps() ) : ?>

		<a href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>" title="<?php esc_attr_e( 'Return to dashboard', 'uncanny-automator' ); ?>">

			<?php esc_html_e( 'Return to dashboard', 'uncanny-automator' ); ?>

		</a>

	<?php else : ?>

		<a href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>" title="<?php esc_attr_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>">

			&larr; <?php esc_html_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>

		</a>

	<?php endif; ?>

</div>
