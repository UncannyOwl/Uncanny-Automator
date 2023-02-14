<?php
/**
 * LinkedIn Settings
 * Settings > Premium Integrations > LinkedIn
 *
 * @since   4.3
 * @version 1.0
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="LINKEDIN"></uo-icon> 

				<?php esc_html_e( 'LinkedIn Pages', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! $is_user_connected ) { ?>

					<?php if ( 'error' === automator_filter_input( 'status' ) ) { ?>
						<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Unexpected error has occured', 'uncanny-automator' ) ) ); ?>" type="error" class="uap-spacing-bottom">
							<?php esc_html_e( 'Permission not granted or you may have cancelled the request during Authentication. Please try again later.', 'uncanny-automator' ); ?>
						</uo-alert>
					<?php } ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to LinkedIn Pages', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php
							esc_html_e(
								'Use Uncanny Automator to automatically share updates, news and blog posts from your WordPress site to your LinkedIn page(s) in the form of posts.',
								'uncanny-automator'
							);
						?>
					</div>

					<p>
						<strong>
							<?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> 
							<strong>
								<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'Publish a post to a LinkedIn page', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } ?>

				<?php if ( $is_user_connected && 200 === absint( automator_filter_input( 'code' ) ) ) { ?>

					<?php /* translators: Success message */ ?>
					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Your account has been connected successfully!', 'uncanny-automator' ) ) ); ?>" type="success" class="uap-spacing-bottom"></uo-alert>

				<?php } ?>

				<?php if ( $is_user_connected ) { ?>

					<uo-alert class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one LinkedIn account at a time.', 'uncanny-automator' ); ?>">
						<?php esc_html_e( 'If you create recipes and then change the connected LinkedIn account, your previous recipes may no longer work.', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<?php if ( ! $is_user_connected ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button href="<?php echo esc_url( $this->helpers->get_authentication_url() ); ?>">

							<?php esc_html_e( 'Connect LinkedIn account', 'uncanny-automator' ); ?>

						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

						<?php if ( ! empty( $user['id'] ) ) { ?>

							<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">

								<?php echo esc_html( substr( $display_name, 0, 1 ) ); ?>

							</div><!--.uap-settings-panel-user__avatar-->

								<div class="uap-settings-panel-user-info">

									<div class="uap-settings-panel-user-info__main">

										<?php echo esc_html( $display_name ); ?>

										<uo-icon integration="LINKEDIN"></uo-icon>

									</div>

									<div class="uap-settings-panel-user-info__additional">
										<?php /* translators: The user linkedin id */ ?>
										<?php echo sprintf( esc_html__( 'ID: %s' ), esc_html( $user['id'] ) ); ?>

									</div>

								</div> <!--uap-settings-panel-user-info-->

						</div> <!--.uap-settings-panel-user-->	
						<?php } ?>

					</div> <!--.uap-settings-panel-bottom-left -->

					<div class="uap-settings-panel-bottom-right">

						<uo-button color="danger" href="<?php echo esc_url( $this->helpers->get_disconnect_url() ); ?>">
							<uo-icon id="sign-out"></uo-icon>
							<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
						</uo-button>

					</div>

				<?php } ?>

		</div>

	</div>

</form>
