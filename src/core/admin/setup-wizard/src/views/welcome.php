<?php
/**
 * Setup-wizard main template file.
 *
 * The card shell: logo, four-step stepper, the current screen, and the
 * dashboard link under the card.
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$screen = $this->get_screen();
$view   = $this->get_screen_view();
?>

<div class="automator-setup-wizard-wrap">

	<div class="automator-setup-wizard <?php echo esc_attr( $this->get_step() ); ?> automator-setup-wizard--<?php echo esc_attr( $screen ); ?>" data-screen="<?php echo esc_attr( $screen ); ?>">

		<div class="automator-setup-wizard__branding">
			<img width="300" src="<?php echo esc_url( Uncanny_Automator\Utilities::automator_get_asset( 'build/img/logo-horizontal.svg' ) ); ?>" alt="<?php esc_attr_e( 'Uncanny Automator', 'uncanny-automator' ); ?>" />
		</div>

		<ol class="automator-setup-wizard__stepper" aria-label="<?php echo esc_attr_x( 'Setup progress', 'Setup wizard stepper', 'uncanny-automator' ); ?>">
			<?php foreach ( $this->get_stepper() as $index => $stepper_step ) : ?>
				<li class="automator-setup-wizard__stepper-step <?php echo '' !== $stepper_step['state'] ? esc_attr( 'automator-setup-wizard__stepper-step--' . $stepper_step['state'] ) : ''; ?>" <?php echo 'now' === $stepper_step['state'] ? 'aria-current="step"' : ''; ?>>
					<b><?php echo esc_html( $stepper_step['tag'] ); ?></b>
					<span><?php echo esc_html( $stepper_step['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php require apply_filters( 'automator_setup_wizard_view_path', $view, array( 'step' => $screen ) ); ?>

	</div>

	<?php // The Try it screens carry the dashboard link as a button. ?>
	<?php if ( ! $this->is_try_it_screen() ) : ?>
		<div class="automator-setup-wizard__footer">
			<a href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>">
				<?php echo esc_html_x( 'Go to the Dashboard', 'Setup wizard footer link', 'uncanny-automator' ); ?>
			</a>
		</div>
	<?php endif; ?>

</div>
