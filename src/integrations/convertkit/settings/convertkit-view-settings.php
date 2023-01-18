<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<form id="uaConvertKitSettingsForm" method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="CONVERTKIT"></uo-icon>

				<?php esc_html_e( 'ConvertKit', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( false === $vars['is_connected'] ) { ?>

					<?php if ( ! empty( $vars['alerts'] ) ) { ?>

						<?php foreach ( $vars['alerts'] as $alert ) { ?>

							<uo-alert class="uap-spacing-bottom" class="uap-spacing-top" type="<?php echo esc_attr( $alert['type'] ); ?>" heading="<?php echo esc_attr( $alert['code'] ); ?>">

								<?php echo esc_html( $alert['message'] ); ?>

							</uo-alert>

						<?php } ?>

					<?php } ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to ConvertKit', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect Uncanny Automator to ConvertKit to better segment and engage with your audience. Once configured, Automator recipes can add or remove ConvertKit tags for subscribers based on activity on your WordPress site, plus add subscribers to ConvertKit forms and sequences.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<?php foreach ( $vars['actions'] as $available_action ) { ?>
							<li>
								<uo-icon id="bolt"></uo-icon>
								<strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong>
								<?php echo esc_html( $available_action ); ?>
							</li>
						<?php } ?>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>">

					<p>
						<?php esc_html_e( 'To retrieve your ConvertKit API keys, perform the following:', 'uncanny-automator' ); ?>

						<ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
							<li><?php esc_html_e( 'Sign in to your ConvertKit account.', 'uncanny-automator' ); ?></li>
							<li><?php esc_html_e( 'Click on your avatar in the upper right corner.', 'uncanny-automator' ); ?></li>
							<li><?php esc_html_e( 'Click the "Settings" link.', 'uncanny-automator' ); ?></li>
							<li><?php esc_html_e( 'On the Settings page, click the "Advanced" menu entry on the left side.', 'uncanny-automator' ); ?></li>
							<li><?php esc_html_e( 'In the API section, click the "Show" link to reveal your ConvertKit API Secret key. The API Key and API Secret Key values are both needed to connect Automator to ConvertKit.', 'uncanny-automator' ); ?></li>							
						</ol>

					</p>

					</uo-alert>

				<?php } ?>

				<?php if ( true === $vars['is_connected'] ) { ?>
					<uo-alert heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one ConvertKit account at a time', 'uncanny-automator' ); ?>." class="uap-spacing-bottom">
					</uo-alert>    
				<?php } ?>

				<uo-text-field
					id="automator_convertkit_api_key"
					value="<?php echo esc_attr( $vars['api_key'] ); ?>"

					label="<?php esc_attr_e( 'API key', 'uncanny-automator' ); ?>"
					required

					<?php echo $vars['is_connected'] ? 'hidden disabled' : ''; ?>

					helper="<?php esc_attr_e( 'Paste the API key value in this field.', 'uncanny-automator' ); ?>"

					class="uap-spacing-top"
				></uo-text-field>


				<uo-text-field
					id="automator_convertkit_api_secret"
					value="<?php echo esc_attr( $vars['api_secret'] ); ?>"

					label="<?php esc_attr_e( 'API secret', 'uncanny-automator' ); ?>"
					required

					<?php echo $vars['is_connected'] ? 'hidden disabled' : ''; ?>

					helper="<?php esc_attr_e( 'Paste the API secret value in this field.', 'uncanny-automator' ); ?>"

					class="uap-spacing-top"
				></uo-text-field>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<div class="uap-settings-panel-bottom-left">

					<?php if ( true === $vars['is_connected'] ) { ?>

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $vars['client']['name'][0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $vars['client']['name'] ); ?>
									<uo-icon integration="CONVERTKIT"></uo-icon>
								</div>
								<div class="uap-settings-panel-user-info__additional">
									<?php echo esc_html( $vars['client']['primary_email_address'] ); ?>
								</div>
							</div>

						</div>

					<?php } else { ?>

						<uo-button id="convertKitConnectBtn" type="submit">
							<?php esc_html_e( 'Connect ConvertKit account', 'uncanny-automator' ); ?>
						</uo-button>

					<?php } ?>

				</div>

				<div class="uap-settings-panel-bottom-right">

				<?php if ( true === $vars['is_connected'] ) { ?>

					<uo-button
						href="<?php echo esc_url( $vars['disconnect_url'] ); ?>"
						color="danger"
					>

						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>

					</uo-button>

				<?php } ?>

				</div>

		</div>

	</div>

</form>
