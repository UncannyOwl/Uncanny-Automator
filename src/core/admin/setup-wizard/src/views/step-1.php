<?php
/**
 * Step 1 template file.
 */
?>

<div id="automator-setup-step-1">

	<div class="center row-1">

		<div class="automator-setup-wizard__branding">
			<img width="380" src="<?php echo esc_url( Uncanny_Automator\Utilities::automator_get_asset( 'backend/dist/img/logo-horizontal.svg' ) ); ?>" alt="" />
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

		<h2 class="title">
			<?php esc_html_e( 'Welcome to the Uncanny Automator Setup Wizard!', 'uncanny-automator' ); ?>
		</h2>

		<p>
			<?php esc_html_e( "You're just minutes away from building powerful automations that connect your plugins, sites and apps together. Connect a free account to try app integrations like Google Sheets, Facebook and Slack.", 'uncanny-automator' ); ?>
		</p>

		<p>
			<a href="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
				id="ua-connect-account-btn"
				class="ua-connect-account-btn-class uo-settings-btn uo-settings-btn--primary"
				>
				<?php esc_html_e( 'Connect your free account!', 'uncanny-automator' ); ?>
			</a>
		</p>
		
	</div>

	<div class="row-2">
		<h3>
			<?php
				esc_html_e(
					'Automator includes a forever free license for WordPress integrations',
					'uncanny-automator'
				);
				?>
		</h3>
		<p>
			<?php
				esc_html_e(
					'All WordPress plugin integrations in the free version are free and unlimited forever.
				Connecting a free account unlocks',
					'uncanny-automator'
				);
				?>
			<strong>
				<?php esc_html_e( '250 app credits', 'uncanny-automator' ); ?>
			</strong>
			<?php
				esc_html_e(
					'for app integrations, but a Pro account gets you',
					'uncanny-automator'
				);
				?>
			<strong>
				<?php esc_html_e( 'unlimited', 'uncanny-automator' ); ?>
			</strong>
			<?php
				esc_html_e(
					'credits plus hundreds of additional triggers and actions, as well as extra features
					like scheduled actions.',
					'uncanny-automator'
				);
				?>
		</p>
		<p class="footer-actions">
			<a target="_blank" href="<?php echo esc_url( $this->get_checkout_uri() ); ?>" class="uo-settings-btn uo-settings-btn--primary">
				<?php esc_html_e( 'Upgrade to Pro now and save up to $200', 'uncanny-automator' ); ?>
			</a>
			<span>
				<uap-setup-wizard-step-1-skip
					url-next-step="<?php echo esc_url( $this->get_dashboard_uri( 2 ) ); ?>"
					url-connect-account="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
				></uap-setup-wizard-step-1-skip>
			</span>
		</p>
	</div>
</div>

