<?php
/**
 * Step 2 template file.
 *
 * @package Uncanny_Automator
 */

?>
<div class="automator-setup-wizard-step-2-wrap">
	<div class="center automator-setup-wizard__branding">
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

	<div class="center row-1">

		<?php if ( $this->is_user_connected() ) : ?>

			<uo-alert type="success" class="automator-setup-wizard__status" heading="<?php esc_attr_e( 'Connected successfully!', 'uncanny-automator' ); ?>"></uo-alert>

		<?php else : ?>

			<?php $error_message = get_transient( 'automator_setup_wizard_error' ); ?>
			<?php if ( ! empty( $error_message ) && ! isset( $_GET['skip'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<uo-alert type="error" class="automator-setup-wizard__status" heading="<?php echo esc_attr( $error_message ); ?>"></uo-alert>
			<?php } ?>

		<?php endif; ?>

		<h2 class="title">
			<?php esc_html_e( 'Do more with Uncanny Automator Pro', 'uncanny-automator' ); ?>
		</h2>

		<ul class="automator-setup-wizard__benefits">
			<li>
				<uo-icon-ai></uo-icon-ai>
				<div>
					<strong><?php esc_html_e( 'Generous Uncanny Agent usage', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Make Uncanny Agent your full-time WordPress assistant.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="infinity"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'Unlimited app credits', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Use app integrations like CRMs, social media, Google apps, and more with no per-transaction fees.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'Over 15,000 triggers, actions and tokens', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Get access to the most powerful triggers and actions in all of our integrations.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="clock"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'Delays and schedules', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Schedule actions to run at a specific date and time or after a set delay.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="code-branch"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'Conditions', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Run actions only when specific conditions are met.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="repeat"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'User, Post and Token loops', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Run bulk actions on users and posts that match specific criteria.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon id="puzzle-piece"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'Automator Addons', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( "Get powerful addons that extend Automator's capabilities (Plus and Elite plans).", 'uncanny-automator' ); ?></p>
				</div>
			</li>
		</ul>

		<p>
			<uo-button
				href="<?php echo esc_url( $this->get_checkout_uri() ); ?>"
			>
				<?php esc_html_e( 'Upgrade to Pro now', 'uncanny-automator' ); ?>
			</uo-button>
		</p>

	</div>

	<div class="row-2 automator-setup-wizard__step-nav">
		<span class="automator-setup-wizard__step-nav-start">
			<?php // Step 1 redirects back here once it is complete, so Back would be a no-op. ?>
			<?php if ( ! $this->is_step_1_complete() ) : ?>
				<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_previous_step_uri() ); ?>">
					&larr; <?php esc_html_e( 'Back', 'uncanny-automator' ); ?>
				</a>
			<?php endif; ?>
		</span>
		<span class="automator-setup-wizard__step-nav-center">
			<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_next_step_uri() ); ?>">
				<?php esc_html_e( 'Maybe later', 'uncanny-automator' ); ?>
			</a>
		</span>
	</div>

	<?php delete_transient( 'automator_setup_wizard_error' ); ?>

</div>
