<?php
/**
 * Facebook Settings
 * Settings > Premium Integrations > Facebook
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $is_user_connected   Boolean. True if user is connected to Facebook. Otherwise, false.
 * $error_status        URL query parameter for handling error status (e.g. user has cancelled the OAuth dialog).
 * $login_dialog_uri    The Facebook login dialog uri from Automator API.
 * $facebook_user       The Facebook user object from Facebook Graph.
 * $disconnect_uri      The URI for disconnecting the currect Facebook user.
 * $connection          Returns 'new' if user just came back from successful OAuth dialog. Otherwise, null.
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

				<uo-icon id="facebook"></uo-icon> 

				<?php esc_html_e( 'Facebook Groups', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! $is_user_connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Facebook Groups', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Use Uncanny Automator to automatically share updates, news and blog posts from your WordPress site to your Facebook Group(s) in the form of posts, images and links.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Publish a post with an image to a Facebook group', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Publish a post to a Facebook group', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Share a link with a message to a Facebook group', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } ?>

				<?php if ( 'new' === $connection ) { ?>
					<uo-alert class="uap-spacing-bottom" type="success" heading="<?php esc_attr_e( 'Your Facebook groups have been connected successfully.', 'uncanny-automator' ); ?>"></uo-alert>
				<?php } ?>

				<?php if ( 'error' === $status ) { ?>

					<?php if ( ! empty( $error_message ) ) { ?>

						<?php /* translators: Error code */ ?>
						<uo-alert class="uap-spacing-bottom" type="error" heading="<?php echo sprintf( esc_attr__( 'Error %s', 'uncanny-automator' ), absint( $error_code ) ); ?>">
							<?php echo esc_html( $error_message ); ?>
						</uo-alert>

					<?php } else { ?>

						<uo-alert class="uap-spacing-bottom" type="error" heading="<?php esc_attr_e( 'Error 403', 'uncanny-automator' ); ?>">

							<?php esc_html_e( 'An unexpected error was encountered while authenticating. Permission is denied.', 'uncanny-automator' ); ?>

						</uo-alert>

					<?php } ?>

				<?php } ?>	

				<?php if ( ! $is_credentials_valid && $is_user_connected && 'new' !== $connection ) { ?>

					<uo-alert type="warning" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_html_e( 'Warning: Your Facebook authentication has expired!', 'uncanny-automator' ); ?>">

						<?php esc_html_e( 'Due to limitations in the Facebook Groups API, your authentication must be renewed every 60 days. To reauthenticate, click "Reconnect account" below.', 'uncanny-automator' ); ?>

					</uo-alert>

				<?php } ?>

				<?php if ( $is_user_connected ) { ?>

					<uo-alert class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one Facebook account at a time.', 'uncanny-automator' ); ?>">
						<?php esc_html_e( 'The group you select in the Facebook group action must have the "Uncanny Automator" app installed.', 'uncanny-automator' ); ?>
					</uo-alert>

					<div class="uap-settings-panel-content-subtitle uap-spacing-top">
						<?php esc_html_e( 'Linked groups', 'uncanny-automator' ); ?>
					</div>

					<div id="facebook-groups-preloader">
						<uo-button loading color="secondary" class="loading" class="uap-spacing-bottom uap-spacing-bottom--big">
							<?php esc_html_e( 'Fetching groups...', 'uncanny-automator' ); ?>
						</button>
					</div>

					<uo-alert style="display:none;" id="facebook-groups-errors" class="uap-spacing-top uap-spacing-bottom uap-spacing-bottom--big" type="error" heading="<?php esc_html_e( 'An unexpected error has occurred', 'uncanny-automator' ); ?>"></uo-alert>

					<div id="facebook-groups-list"></div>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<?php if ( ! $is_user_connected ) { ?>

				<uo-button href="<?php echo esc_url( $login_dialog_uri ); ?>">

					<?php esc_html_e( 'Connect Facebook account', 'uncanny-automator' ); ?>

				</uo-button>

			<?php } else { ?>

				<div class="uap-settings-panel-bottom-left">

				<?php if ( ! $is_credentials_valid && 'new' !== $connection ) { ?>

					<uo-button color="primary" href="<?php echo esc_url( $login_dialog_uri ); ?>">

						<uo-icon id="exchange"></uo-icon>

						<?php esc_html_e( 'Reconnect account', 'uncanny-automator' ); ?>

					</uo-button>

				<?php } else { ?>

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">

							<img src="<?php echo esc_url( $facebook_user->picture ); ?>" alt="<?php echo esc_attr( $facebook_user->name ); ?>" />

						</div>

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">

								<?php echo esc_html( $facebook_user->name ); ?>

								<uo-icon id="facebook"></uo-icon>

							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php
									echo esc_html(
										sprintf(
											/* translators: 1. ID */
											__( 'ID: %1$d', 'uncanny-automator' ),
											$facebook_user->user_id
										)
									);

								?>
							</div>

						</div>

					</div>

				<?php } ?>

				</div>

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
