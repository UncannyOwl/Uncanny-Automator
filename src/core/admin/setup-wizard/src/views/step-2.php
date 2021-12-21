<?php
/**
 * Step 2 template file.
 */
?>
<div class="automator-setup-wizard-step-2-wrap">
	<div class="center automator-setup-wizard__branding">
		<img width="380" src="<?php echo esc_url( plugins_url( '../../assets/images/logo.svg', __FILE__ ) ); ?>" alt="" />
	</div>
	<div class="automator-setup-wizard__steps">
		<div class="automator-setup-wizard__steps__inner-wrap">
			<ol>
				<?php foreach ( $this->get_steps() as $step ) : ?>
					<li class="<?php echo implode( ' ', $step['classes'] ); ?>"> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span>
							<?php // translators: The step ?>
							<?php echo sprintf( esc_html__( 'Step %s', 'uncanny-automator' ), esc_html( $step['label'] ) ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>

	<?php if ( $this->is_user_connected() ) : ?>

		<div class="center row-1">
			<h2 class="title">
				<?php esc_html_e( 'Connected successfully!', 'uncanny-automator' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'You may now connect your recipes to any service supported by Uncanny Automator.', 'uncanny-automator' ); ?>
			</p>
		</div>

		<div class="row-2">

			<h4>
				<?php esc_html_e( 'Help us make Uncanny Automator even better!', 'uncanny-automator' ); ?>
			</h4>

			<p>
				<?php esc_html_e( 'Tracking of anonymous usage data helps us decide where to focus our development efforts.', 'uncanny-automator' ); ?>
			</p>

			<p style="margin-top: 20px;">
				<a
					href="<?php echo esc_url( $this->get_dashboard_uri( 3 ) ); ?>"
					title="<?php esc_html_e( 'Maybe later', 'uncanny-automator' ); ?>"
					class="uo-settings-btn uo-settings-btn--secondary">
					<?php esc_html_e( 'Maybe later!', 'uncanny-automator' ); ?>
				</a>
				<a
					href="<?php echo esc_url( $this->get_dashboard_uri( 3 ) ); ?>"
					title="<?php esc_html_e( 'Count me in!', 'uncanny-automator' ); ?>"
					class="uo-settings-btn uo-settings-btn--primary">
					<?php esc_html_e( 'Count me in!', 'uncanny-automator' ); ?>
				</a>
			</p>

		</div>

	<?php else : ?>
		<?php // Not connected. ?>
		<div class="center row-1">
			<h2 class="title">
				<?php esc_html_e( 'Not connected', 'uncanny-automator' ); ?>
			</h2>
			<p>
				<?php
					esc_html_e(
						'Your site is not connected to an Uncanny Automator account.
                    You can still create recipes (automations) with any of our built-in integrations.
                    To use third-party integrations (like Facebook, Slack, MailChimp and more), connect
                    your site with a free Uncanny Automator account.',
						'uncanny-automator'
					);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
					id="ua-connect-account-btn"
					class="ua-connect-account-btn-class uo-settings-btn uo-settings-btn--primary"
					target="popup"
					>
					<?php esc_html_e( 'Connect your free account!', 'uncanny-automator' ); ?>
				</a>
			</p>
		</div>

		<div class="row-2">

			<h3>
				<?php esc_html_e( 'Help us make Uncanny Automator even better!', 'uncanny-automator' ); ?>
			</h3>

			<p>
				<?php esc_html_e( 'Tracking of anonymous usage data helps us decide where to focus our development efforts.', 'uncanny-automator' ); ?>
			</p>

			<p style="margin-top: 20px;">
				<a
					href="<?php echo esc_url( $this->get_dashboard_uri( 3 ) ); ?>"
					title="<?php esc_html_e( 'Maybe later', 'uncanny-automator' ); ?>"
					class="uo-settings-btn uo-settings-btn--secondary">
					<?php esc_html_e( 'Maybe later!', 'uncanny-automator' ); ?>
				</a>
				<a
					href="<?php echo esc_url( $this->get_dashboard_uri( 3 ) ); ?>"
					title="<?php esc_html_e( 'Count me in!', 'uncanny-automator' ); ?>"
					class="uo-settings-btn uo-settings-btn--primary">
					<?php esc_html_e( 'Count me in!', 'uncanny-automator' ); ?>
				</a>
			</p>

		</div>
	<?php endif; ?>

</div>
