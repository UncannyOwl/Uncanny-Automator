<?php
/**
 * Google Sheet Settings
 * Settings > Premium Integrations > Google Calendar
 *
 * @since   4.1
 * @version 4.1
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

				<uo-icon id="google-calendar"></uo-icon> 

				<?php esc_html_e( 'Google Calendar', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! empty( $auth_error ) ) { ?>
					<?php /* translators: Error message */ ?>
					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Authentication Error', 'uncanny-automator' ) ) ); ?>" type="error" class="uap-spacing-bottom">
						<?php echo esc_html( $auth_error ); ?>
					</uo-alert>
				<?php } ?>

				<?php if ( $client && ! empty( $auth_success ) ) { ?>
					<?php /* translators: Success message */ ?>
					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $user_info['email'] ) ); ?>" type="success" class="uap-spacing-bottom"></uo-alert>
				<?php } ?>

				<?php if ( ! $client ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Google Calendar', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect Uncanny Automator to Google Calendar to automatically create events and add and remove attendees when users perform actions like submitting forms, joining groups and making purchases on your site. Turn Google Calendar into a powerful event and appointment booking engine for your WordPress site.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add an event to a Google Calendar', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add an attendee to an event in a Google Calendar', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove an attendee from an event in a Google Calendar', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Uncanny Automator only supports connecting to one Google Calendar account at a time.', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
						<?php esc_html_e( 'You can only link Google Calendars that you have read and write access to.', 'uncanny-automator' ); ?>
					</uo-alert>

					<div class="uap-settings-panel-content-subtitle uap-spacing-bottom">
						<?php esc_html_e( 'Linked Calendars', 'uncanny-automator' ); ?>
					</div>

					<div id="google-calendar-preloader">
						<uo-button loading color="secondary" class="loading" class="uap-spacing-bottom uap-spacing-bottom--big">
							<?php esc_html_e( 'Fetching calendars...', 'uncanny-automator' ); ?>
						</button>
					</div>

					<uo-alert style="display:none;" id="google-calendar-errors" class="uap-spacing-bottom uap-spacing-bottom--big" type="error" heading="<?php esc_html_e( 'An unexpected error has occurred', 'uncanny-automator' ); ?>"></uo-alert>

					<div id="google-calendar-list"></div>



				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<?php if ( ! $is_user_connected ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button href="<?php echo esc_url( $authentication_url ); ?>">

							<?php esc_html_e( 'Connect Google account', 'uncanny-automator' ); ?>

						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">

							<?php if ( ! empty( $user_info['avatar_uri'] ) ) { ?>
								<div class="uap-settings-panel-user__avatar">
									<img alt="<?php echo esc_attr( $user_info['name'] ); ?>" src="<?php echo esc_url( $user_info['avatar_uri'] ); ?>" />
								</div><!--.uap-settings-panel-user__avatar-->
							<?php } ?>

							<?php if ( ! empty( $user_info['name'] ) && ! empty( $user_info['email'] ) ) { ?>
								<div class="uap-settings-panel-user-info">

									<div class="uap-settings-panel-user-info__main">

										<?php echo esc_html( $user_info['name'] ); ?>

										<uo-icon id="google"></uo-icon>

									</div>

									<div class="uap-settings-panel-user-info__additional">

										<?php echo esc_html( $user_info['email'] ); ?>

									</div>

								</div> <!--uap-settings-panel-user-info-->
							<?php } ?>

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
