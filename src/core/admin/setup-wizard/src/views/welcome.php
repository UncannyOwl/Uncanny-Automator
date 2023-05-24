<?php
/**
 * Setup-wizard main template file.
 */
?>

<div class="automator-setup-wizard-wrap">

	<div class="automator-setup-wizard <?php echo esc_attr( $this->get_step() ); ?>">

		<?php $step = sanitize_file_name( $this->get_step() ); ?>

		<?php $view = $this->get_view_path() . sprintf( '%s.php', $step ); ?>

		<?php if ( ! is_file( $view ) ) : ?>

			<?php $view = $this->get_view_path() . sprintf( '%s.php', 'step-1' ); ?>

		<?php endif; ?>

		<?php require $view; ?>

	</div>

</div>

<?php $this->set_has_tried_connecting( false ); ?>

<div class="automator-setup-wizard__footer">
	
	<a href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>" title="<?php esc_attr_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>">

		&larr; <?php esc_html_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>

	</a>

</div>
