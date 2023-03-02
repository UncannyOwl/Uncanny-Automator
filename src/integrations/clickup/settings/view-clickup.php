<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="CLICKUP"></uo-icon> 

				<?php esc_html_e( 'ClickUp', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( $vars['is_connected'] ) { ?>

					<div class="uap-settings-panel-content">

						<uo-alert heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one ClickUp account at a time.', 'uncanny-automator' ); ?>"></uo-alert>

						<?php if ( ! empty( $vars['oauth_response'] ) ) { ?>
							<uo-alert class="uap-spacing-top" type="success" heading="<?php esc_html_e( 'You have successfully connected your account', 'uncanny-automator' ); ?>"></uo-alert>
						<?php } ?>

					</div>

				<?php } else { ?>

					<?php if ( ! empty( $vars['oauth_response'] ) ) { ?>
						<?php if ( isset( $vars['oauth_response']['response']['ECODE'] ) && isset( $vars['oauth_response']['response']['err'] ) ) { ?>
							<uo-alert 
								class="uap-spacing-bottom" 
								type="error" 
								heading="<?php echo esc_attr( $vars['oauth_response']['response']['ECODE'] ); ?>: <?php echo esc_attr( $vars['oauth_response']['response']['err'] ); ?>"
							>
							</uo-alert>
						<?php } ?>
					<?php } ?>

					<div class="uap-settings-panel-content-subtitle">

						<?php esc_html_e( 'Connect Uncanny Automator to ClickUp', 'uncanny-automator' ); ?>

					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">

						<?php esc_html_e( 'Connect Uncanny Automator to ClickUp to have WordPress site activity create tasks, add comments and more. Put Project Management workflows on autopilot by linking form submissions to new tasks, post site updates when comments are added in ClickUp, and keep users engaged with ClickUp activity.', 'uncanny-automator' ); ?>

					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>

						<?php foreach ( $vars['actions'] as $ua_action ) { ?>
							<li>
								<uo-icon id="bolt"></uo-icon>
								<strong>
									<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
								</strong>
								<?php echo esc_html( $ua_action ); ?>
							</li>
						<?php } ?>

					</ul>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo ! $vars['is_connected'] ? 'has-arrow' : ''; ?>>

			<?php if ( ! $vars['is_connected'] ) { ?>

				<uo-button href="<?php echo esc_url( $vars['connect_url'] ); ?>">

					<?php esc_html_e( 'Connect ClickUp account', 'uncanny-automator' ); ?>

				</uo-button>

			<?php } else { ?>

				<div class="uap-settings-panel-bottom-left">

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar" style="background-color: <?php echo esc_html( $vars['user']['color'] ); ?>; font-size: 12px; color: #fff;">
							<?php echo esc_html( $vars['user']['initials'] ); ?>
						</div>

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">
								<?php echo esc_html( $vars['user']['username'] ); ?>
								<uo-icon integration="CLICKUP"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php echo esc_html( $vars['user']['email'] ); ?> 
							</div>

						</div>

					</div>
				</div>

				<div class="uap-settings-panel-bottom-right">

					<uo-button color="danger" href="<?php echo esc_url( $vars['disconnect_url'] ); ?>">

						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>

					</uo-button>

				</div>

				<?php } ?>

		</div>

	</div>

</form>
