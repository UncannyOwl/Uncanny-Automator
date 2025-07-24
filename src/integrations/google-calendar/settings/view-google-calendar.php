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

				<uo-icon integration="GOOGLE_CALENDAR"></uo-icon> 

				<?php echo esc_html_x( 'Google Calendar', 'Google Calendar', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! empty( $auth_error ) ) { ?>
					<?php /* translators: Error message */ ?>
					<uo-alert heading="<?php echo esc_attr( esc_html_x( 'Authentication Error', 'Google Calendar', 'uncanny-automator' ) ); ?>" type="error" class="uap-spacing-bottom">
						<?php echo esc_html( $auth_error ); ?>
					</uo-alert>
				<?php } ?>

				<?php if ( $client && ! empty( $auth_success ) ) { ?>
					<?php /* translators: Success message */ ?>
					<uo-alert heading="<?php echo esc_attr( sprintf( esc_html_x( 'Your account "%s" has been connected successfully!', 'Google Calendar', 'uncanny-automator' ), $user_info['email'] ) ); ?>" type="success" class="uap-spacing-bottom"></uo-alert>
				<?php } ?>

				<?php if ( ! $client ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to Google Calendar', 'Google Calendar', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to Google Calendar to automatically create events and add and remove attendees when users perform actions like submitting forms, joining groups and making purchases on your site. Turn Google Calendar into a powerful event and appointment booking engine for your WordPress site.', 'Google Calendar', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Google Calendar', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Google Calendar', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add an event to a Google Calendar', 'Google Calendar', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Google Calendar', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add an attendee to an event in a Google Calendar', 'Google Calendar', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Google Calendar', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Remove an attendee from an event in a Google Calendar', 'Google Calendar', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php echo esc_attr( esc_html_x( 'Uncanny Automator only supports connecting to one Google Calendar account at a time.', 'Google Calendar', 'uncanny-automator' ) ); ?>" class="uap-spacing-bottom">
						<?php echo esc_html_x( 'You can only link Google Calendars that you have read and write access to.', 'Google Calendar', 'uncanny-automator' ); ?>
					</uo-alert>

					<div class="uap-settings-panel-content-subtitle uap-spacing-bottom">
						<?php echo esc_html_x( 'Linked calendars', 'Google Calendar', 'uncanny-automator' ); ?>
					</div>

					<div id="google-calendar-preloader">
						<uo-button loading color="secondary" class="loading uap-spacing-bottom uap-spacing-bottom--big">
							<?php echo esc_html_x( 'Fetching calendars...', 'Google Calendar', 'uncanny-automator' ); ?>
						</uo-button>
					</div>

					<uo-alert style="display:none;" id="google-calendar-errors" class="uap-spacing-bottom uap-spacing-bottom--big" type="error" heading="<?php echo esc_attr( esc_html_x( 'An unexpected error has occurred', 'Google Calendar', 'uncanny-automator' ) ); ?>"></uo-alert>

					<div id="google-calendar-list"></div>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo $is_user_connected ? '' : 'has-arrow'; ?>>

				<?php if ( ! $is_user_connected ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button 
							class="uap-settings-button-google" 
							href="<?php echo esc_url( $authentication_url ); ?>" 
							target="_self" 
							unsafe-force-target
						>
							<uo-icon id="google"></uo-icon><?php echo esc_html_x( 'Sign in with Google', 'Google Calendar', 'uncanny-automator' ); ?>
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
							<uo-icon id="right-from-bracket"></uo-icon>
							<?php echo esc_html_x( 'Disconnect', 'Google Calendar', 'uncanny-automator' ); ?>
						</uo-button>
					</div>

				<?php } ?>

		</div>

	</div>

</form>
