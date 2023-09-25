<?php
/**
 * @var \Uncanny_Automator\Integrations\Mautic\Mautic_Settings $this
 * @var mixed[] $vars
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>
<script>
let automatorMauticFieldResolver = () => {
	let resolver = new MauticSettingsResolver();
	return resolver.resolve();
}
</script>

<form onsubmit="return automatorMauticFieldResolver();" method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon integration="MAUTIC"></uo-icon> Mautic
			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! empty( $vars['alerts'] ) ) { ?>

					<?php foreach ( (array) $vars['alerts'] as $alert ) { ?>

						<uo-alert class="uap-spacing-bottom" type="<?php echo esc_attr( $alert['type'] ); ?>" heading="<?php echo esc_attr( $alert['code'] ); ?>">
							<?php echo esc_html( $alert['message'] ); ?>
						</uo-alert>

					<?php } ?>

				<?php } ?>

				<?php if ( $vars['is_connected'] ) { ?>
					<uo-alert type="info" heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one Mautic account.', 'uncanny-automator' ); ?>">
						<?php echo esc_html_x( 'If you create recipes and then change the connected Mautic account, your previous recipes may no longer work.', 'Mautic', 'uncanny-automator' ); ?>
					</uo-alert>	
				<?php } ?>

				<?php if ( ! $vars['is_connected'] ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to Mautic', 'Mautic', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php echo esc_html_x( "Use Uncanny Automator to connect your WordPress site to Mautic and create or update contacts. With this integration, it's easy to create new Mautic contacts when a purchase is made, update contacts when a form is submitted and more.", 'Mautic', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong>
							<?php echo esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<ul>
					<?php foreach ( $vars['actions'] as $action_sentence ) { ?>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php echo esc_html_x( 'Action:', 'Mautic', 'uncanny-automator' ); ?>
							</strong>
								<?php echo esc_html( $action_sentence ); ?>
						</li>
					<?php } ?>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert heading="<?php esc_html_x( 'Setup instructions', 'Mautic', 'uncanny-automator' ); ?>">
						<?php echo esc_html_x( 'Uncanny Automator uses basic authentication to connect with your Mautic website. Your credentials will be stored in the wp_options table and can be removed at anytime.', 'Mautic', 'uncanny-automator' ); ?>
					</uo-alert>

					<uo-text-field
						id="automator_mautic_base_url"
						value="<?php echo esc_url( $vars['fields']['base_url'] ); ?>"
						label="<?php echo esc_attr_x( 'Base URL', 'Mautic', 'uncanny-automator' ); ?>"
						required
						class="uap-spacing-top"
						<?php if ( $vars['is_connected'] ) { ?>
							hidden
						<?php } ?>
						helper="<?php echo esc_attr_x( 'The URL of your Mautic site starting with https://. For example, https://mymauticsite.com', 'Mautic', 'uncanny-automator' ); ?>"
					></uo-text-field>

					<uo-text-field
						id="automator_mautic_username"
						value="<?php echo esc_attr( $vars['fields']['username'] ); ?>"
						label="<?php echo esc_attr_x( 'Username', 'Mautic', 'uncanny-automator' ); ?>"
						required
						<?php if ( $vars['is_connected'] ) { ?>
							hidden
						<?php } ?>
						class="uap-spacing-top"
					></uo-text-field>

					<uo-text-field
						id="automator_mautic_password"
						value="<?php echo esc_attr( $vars['fields']['password'] ); ?>"
						label="<?php echo esc_attr_x( 'Password', 'Mautic', 'uncanny-automator' ); ?>"
						required
						<?php if ( $vars['is_connected'] ) { ?>
							hidden
						<?php } ?>
						class="uap-spacing-top"
					></uo-text-field>

					<uo-text-field
						style="visibility:hidden; height:1px; display: block; position: fixed; z-index: -999;"
						id="automator_mautic_credentials"
						value="<?php echo esc_attr( $vars['fields']['credentials'] ); ?>"
						required
						class="uap-spacing-top"
					></uo-text-field>
				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">
			<?php if ( ! $vars['is_connected'] ) { ?>
				<div class="uap-settings-panel-bottom-left">
					<uo-button id="automator-mautic-connect-btn" type="submit">
						<?php echo esc_html_x( 'Connect Mautic account', 'Mautic', 'uncanny-automator' ); ?>
					</uo-button>
				</div>
			<?php } ?>

			<?php if ( $vars['is_connected'] ) { ?>

				<div class="uap-settings-panel-bottom-left">
					<?php if ( ! empty( $vars['resource_owner']['id'] ) ) { ?>

						<div class="uap-settings-panel-user">
							<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( substr( $vars['resource_owner']['username'], 0, 1 ) ); ?>
						</div><!--.uap-settings-panel-user__avatar-->

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $vars['resource_owner']['username'] ); ?>
									<uo-icon integration="MAUTIC"></uo-icon>
								</div>

								<div class="uap-settings-panel-user-info__additional">
									<?php /* translators: The user email */ ?>
									<?php echo sprintf( esc_html__( 'Email: %s' ), esc_html( $vars['resource_owner']['email'] ) ); ?>
								</div>
							</div> <!--uap-settings-panel-user-info-->
						</div> <!--.uap-settings-panel-user-->	
					<?php } ?>

				</div>
				<div class="uap-settings-panel-bottom-right">
					<uo-button color="danger" href="<?php echo esc_url( $vars['disconnect_url'] ); ?>">
						<uo-icon id="sign-out"></uo-icon>
						<?php echo esc_html_x( 'Disconnect', 'Mautic', 'uncanny-automator' ); ?>
					</uo-button>
				</div>
			<?php } ?>
		</div>

	</div>

</form>

