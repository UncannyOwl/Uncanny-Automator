<?php
/**
 * Step 3 Template file — usage tracking opt-in.
 *
 * Only reached when the user has not connected an account; connecting opts the
 * user in automatically, so connected users go straight to the final step.
 *
 * @package Uncanny_Automator
 */

?>
<div class="center row-1">
	<div class="automator-setup-wizard__branding">
		<img width="380" src="<?php echo esc_url( Uncanny_Automator\Utilities::automator_get_asset( 'build/img/logo-horizontal.svg' ) ); ?>" alt="" />
	</div>
	<div class="automator-setup-wizard__steps">
		<div class="automator-setup-wizard__steps__inner-wrap">
			<ol>
				<?php foreach ( $this->get_steps() as $step ) : ?>
					<li class="<?php echo implode( ' ', $step['classes'] ); ?>"> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span>
							<?php
							// translators: %s: Step number.
							printf( esc_html__( 'Step %s', 'uncanny-automator' ), esc_html( $step['label'] ) );
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>

	<h2 class="title">
		<?php esc_html_e( 'Help us make Uncanny Automator even better!', 'uncanny-automator' ); ?>
	</h2>

	<p>
		<?php esc_html_e( 'Tracking of anonymous usage data helps us decide where to focus our development efforts.', 'uncanny-automator' ); ?>
	</p>

	<p>
		<uo-button
			href="<?php echo esc_url( $this->get_next_step_uri( array( 'automator_reporting' => 'true' ) ) ); ?>"
		>
			<?php esc_html_e( 'Count me in!', 'uncanny-automator' ); ?>
		</uo-button>
	</p>

	<div class="automator-setup-wizard__step-nav">
		<span class="automator-setup-wizard__step-nav-start">
			<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_previous_step_uri() ); ?>">
				&larr; <?php esc_html_e( 'Back', 'uncanny-automator' ); ?>
			</a>
		</span>
		<span class="automator-setup-wizard__step-nav-center">
			<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_next_step_uri( array( 'automator_reporting' => 'false' ) ) ); ?>">
				<?php esc_html_e( 'Maybe later', 'uncanny-automator' ); ?>
			</a>
		</span>
	</div>

</div>
