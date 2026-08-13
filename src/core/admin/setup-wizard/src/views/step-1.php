<?php
/**
 * Step 1 template file.
 *
 * @package Uncanny_Automator
 */

?>

<div id="automator-setup-step-1">

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
			<?php esc_html_e( 'Welcome to the Uncanny Automator Setup Wizard!', 'uncanny-automator' ); ?>
		</h2>

		<p>
			<?php esc_html_e( "You're just seconds away from building powerful automations that connect your plugins, sites and apps together. Connect a free Uncanny Automator account to unlock:", 'uncanny-automator' ); ?>
		</p>

		<ul class="automator-setup-wizard__benefits">
			<li>
				<uo-icon id="plug"></uo-icon>
				<div>
					<strong><?php esc_html_e( 'App integrations', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Use app integrations like CRMs, social media, Google apps and more with 250 free app credits.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
			<li>
				<uo-icon-ai></uo-icon-ai>
				<div>
					<strong><?php esc_html_e( 'Uncanny Agent', 'uncanny-automator' ); ?></strong>
					<p><?php esc_html_e( 'Free Uncanny Agent usage to get started with business analysis, site management, and building recipes and pages via conversation.', 'uncanny-automator' ); ?></p>
				</div>
			</li>
		</ul>

		<p>
			<uo-button
				href="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
				unsafe-force-target
				target="_self"
			>
				<?php esc_html_e( 'Connect your free account!', 'uncanny-automator' ); ?>
			</uo-button>
		</p>

	</div>

	<div class="row-2">
		<p class="footer-actions footer-actions--center">
			<span>
				<uap-setup-wizard-step-1-skip
					url-next-step="<?php echo esc_url( $this->get_dashboard_uri( 2 ) ); ?>&skip=true"
					url-connect-account="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
				></uap-setup-wizard-step-1-skip>
			</span>
		</p>
	</div>
</div>
