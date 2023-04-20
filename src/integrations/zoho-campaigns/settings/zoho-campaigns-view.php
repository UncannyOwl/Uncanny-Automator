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

				<uo-icon integration="ZOHO_CAMPAIGNS"></uo-icon> 

				<?php esc_html_e( 'Zoho Campaigns', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! empty( $vars['errors'] ) ) { ?>
					<?php foreach ( $vars['errors'] as $client_error ) { ?>
						<uo-alert class="uap-spacing-bottom" type="error" heading="<?php echo esc_attr( $client_error['headline'] ); ?>">
							<?php echo esc_html( $client_error['body'] ); ?>
						</uo-alert>
					<?php } ?>	
				<?php } ?>

				<?php if ( false === $vars['is_connected'] ) { ?>
					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Zoho Campaigns', 'uncanny-automator' ); ?>
					</div>
					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( "Uncanny Automator is a powerful automation platform that makes it easy to build workflows that connect Zoho Campaigns with other applications. With Uncanny Automator's drag-and-drop interface, you can quickly and easily create automated workflows that can streamline your email marketing campaigns.", 'uncanny-automator' ); ?>
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

				<?php if ( true === $vars['is_connected'] ) { ?>

					<uo-alert type="info" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one Zoho Campaigns account at a time.', 'uncanny-automator' ); ?>">
						<?php esc_html_e( 'If you create recipes and then change the connected Zoho Campaigns account, your previous recipes may no longer work.', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo ! $vars['is_connected'] ? 'has-arrow' : ''; ?>>

			<?php if ( false === $vars['is_connected'] ) { ?>
				<uo-button href="<?php echo esc_url( $vars['connect_url'] ); ?>">
					<?php esc_html_e( 'Connect Zoho Campaigns account', 'uncanny-automator' ); ?>
				</uo-button>
			<?php } ?>

			<?php if ( true === $vars['is_connected'] ) { ?>
				<div class="uap-settings-panel-bottom-left">
					<div class="uap-settings-panel-user">
						<div class="uap-settings-panel-user__avatar">
							Z
						</div><!--.uap-settings-panel-user__avatar-->
						<div class="uap-settings-panel-user-info">
							<div class="uap-settings-panel-user-info__main">
								<?php esc_html_e( 'Zoho Campaigns account', 'uncanny-automator' ); ?>
								<uo-icon integration="ZOHO_CAMPAIGNS"></uo-icon>
							</div>
							<div class="uap-settings-panel-user-info__additional">
								<?php /* translators: %1$s The secret key. */ ?>
								<?php echo sprintf( esc_html__( 'Access token: %1$s', 'uncanny-automator' ), esc_html( $vars['redacted_token'] ) ); ?>
							</div>
						</div> <!--uap-settings-panel-user-info-->
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
