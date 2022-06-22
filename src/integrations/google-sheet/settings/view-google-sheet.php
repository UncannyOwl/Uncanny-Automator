<?php
/**
 * Google Sheet Settings
 * Settings > Premium Integrations > Google Sheet
 *
 * @since   3.7
 * @version 3.7
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

				<uo-icon id="google-sheets"></uo-icon>

				<?php esc_html_e( 'Google Sheets', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( $this->client && 1 === $connect ) { ?>

					<?php /* translators: Success message */ ?>
					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $user_info['email'] ) ); ?>" type="success" class="uap-spacing-bottom"></uo-alert>

				<?php } ?>

				<?php // Show some error message in case there is an error. ?>

				<?php if ( 2 === $connect ) { ?>

					<uo-alert heading="<?php esc_attr_e( 'An error has occured while connecting to Google API. Please try again later.', 'uncanny-automator' ); ?>" type="error" class="uap-spacing-bottom"></uo-alert>

				<?php } ?>

				<?php // Show missing_auth error ?>

				<?php if ( 3 === $connect ) { ?>

					<uo-alert heading="<?php esc_attr_e( 'Required permissions not granted.', 'uncanny-automator' ); ?>" type="error" class="uap-spacing-bottom">

						<?php esc_html_e( 'Make sure everything is checked off in the list of required permissions. Sometimes the last 2 checkboxes are unchecked by default.', 'uncanny-automator' ); ?>

					</uo-alert>

				<?php } ?>

				<?php if ( ! $this->client ) { ?>

					<div class="uap-settings-panel-content-subtitle">

						<?php esc_html_e( 'Connect Uncanny Automator to Google Sheets', 'uncanny-automator' ); ?>

					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">

						<?php esc_html_e( 'Connect Uncanny Automator to Google Sheets to automatically send data to Google Sheets when users perform actions like submitting forms, making purchases or completing courses on your site. Turn Google Sheets into a powerful reporting tool for your WordPress site.', 'uncanny-automator' ); ?>

					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a row in a Google Sheet', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Update a row in a Google Sheet', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one Google account.', 'uncanny-automator' ); ?>">
						<?php esc_html_e( 'If you create recipes and then change the connected Google account, your previous recipes may no longer work.', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<?php if ( ! $this->client ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button href="<?php echo esc_url( $auth_url ); ?>">

							<?php esc_html_e( 'Connect Google account', 'uncanny-automator' ); ?>

						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">

								<img alt="<?php echo esc_attr( $user_info['name'] ); ?>" src="<?php echo esc_url( $user_info['avatar_uri'] ); ?>" />

							</div><!--.uap-settings-panel-user__avatar-->

							<div class="uap-settings-panel-user-info">

								<div class="uap-settings-panel-user-info__main">

									<?php echo esc_html( $user_info['name'] ); ?>

									<uo-icon id="google"></uo-icon>

								</div>

								<div class="uap-settings-panel-user-info__additional">

									<?php echo esc_html( $user_info['email'] ); ?>

								</div>

							</div> <!--uap-settings-panel-user-info-->

						</div> <!--.uap-settings-panel-user-->

					</div> <!--.uap-settings-panel-bottom-left -->

					<div class="uap-settings-panel-bottom-right">
						<uo-button color="danger" href="<?php echo esc_url( $disconnect_uri ); ?>">
							<uo-icon id="sign-out"></uo-icon>
							<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
						</uo-button>
					</div>

				<?php } ?>

		</div>

	</div>

</form>
