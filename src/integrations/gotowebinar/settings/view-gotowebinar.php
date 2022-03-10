<?php
namespace Uncanny_Automator;

/**
 * GoTo Webinar Settings
 * Settings > Premium Integrations > GoTo Webinar
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $key                 String. The consumer key.
 * $secret              String. The consumer secret.
 * $tab_url             String. The url of the tab.
 * $disconnect_url      String. The disconnect url.
 * $connection          String. Query parameter 'connect'
 * $user                Array. The user settings saved in options.
 * $is_connected        Boolean. False if user is not connected. Otherwise, true.
 * $user_first_name     String. The first name of the user.
 * $user_last_name      String. The last name of the user..
 * $user_display_name   String. The furst name and last name of the user separated by space.
 * $user_email_address  String. The user's email address.
 */
?>

<form method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon id="gotowebinar"></uo-icon> <?php esc_html_e( 'GoTo Webinar', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php if ( $is_connected ) { ?>

					<?php if ( '1' === $connection ) { ?>
						<uo-alert type="success" heading="<?php esc_attr_e( 'You have successfully connected your GoTo Webinar account', 'uncanny-automator' ); ?>" class="uap-spacing-bottom"></uo-alert>
					<?php } ?>

					<uo-alert 
						heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one GoTo Webinar account.', 'uncanny-automator' ); ?>"
					></uo-alert>

				<?php } else { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to GoTo Webinar', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Automatically register users for GoTo Webinar sessions when they complete actions on your site, such as completing a course, filling out a form, or even simply clicking a button! ', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add the user to a webinar', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove the user from a webinar', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert
						heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>"
					>

						<p>
							<?php

								echo sprintf(
									esc_html__( "Connecting to GoTo Webinar requires setting up an application and getting 2 values from inside your account. It's really easy, we promise! Visit our %1\$s for simple instructions.", 'uncanny-automator-pro' ),
									'<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/gotowebinar/', 'settings', 'gotowebinar-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Knowledge Base article', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
								);

							?>
						</p>

						<uo-text-field
							id="uap_automator_gotowebinar_tab_url"
							value="<?php echo esc_url( $tab_url ); ?>"
							label="<?php esc_attr_e( 'Redirect URL', 'uncanny-automator' ); ?>"
							helper="<?php esc_attr_e( "You'll be asked to enter a redirect URL.", 'uncanny-automator' ); ?>"

							disabled
						></uo-text-field>

					</uo-alert>

					<?php if ( 'disconnected' === $connection ) { ?>
						<uo-alert type="error" class="uap-spacing-top" heading="<?php esc_attr_e( 'You have successfully disconnected your account.', 'uncanny-automator' ); ?>"></uo-alert>
					<?php } ?>

				<?php } ?>

				<uo-text-field required id="uap_automator_gtw_api_consumer_key" 
					value="<?php echo esc_attr( $key ); ?>" 
					label="<?php esc_attr_e( 'Client ID', 'uncanny-automator' ); ?>"
					class="uap-spacing-top"

					<?php echo $is_connected ? 'hidden disabled' : ''; ?>
				>
				</uo-text-field>

				<uo-text-field required id="uap_automator_gtw_api_consumer_secret" 
					value="<?php echo esc_attr( $secret ); ?>" 
					label="<?php esc_attr_e( 'Client secret', 'uncanny-automator' ); ?>"
					class="uap-spacing-top"

					<?php echo $is_connected ? 'hidden disabled' : ''; ?>
				>
				</uo-text-field>
			</div>

		</div>

		<div class="uap-settings-panel-bottom">

		<div class="uap-settings-panel-bottom-left">
			<?php if ( $is_connected ) { ?>
				<div class="uap-settings-panel-user">
					<?php if ( ! empty( trim( $user_display_name ) ) ) { ?>
						<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( $user_display_name[0] ); ?>
						</div>
					<?php } ?>

					<div class="uap-settings-panel-user-info">
						<div class="uap-settings-panel-user-info__main">
							<?php if ( ! empty( trim( $user_display_name ) ) ) { ?>
								<?php echo esc_html( $user_display_name ); ?>
								<uo-icon id="gotowebinar"></uo-icon>
							<?php } ?>
						</div>
						<div class="uap-settings-panel-user-info__additional">
							<?php if ( ! empty( trim( $user_email_address ) ) ) { ?>
								<?php /* translators: Settings user email */ ?>
								<?php echo esc_html( $user_email_address ); ?>
							<?php } ?>
						</div>
					</div>
				</div>
			<?php } else { ?>
				<uo-button type="submit">
					<?php esc_html_e( 'Connect GoTo Webinar account', 'uncanny-automator' ); ?>
				</uo-button>
			<?php } ?>
		</div>
			<div class="uap-settings-panel-bottom-right">
				<?php if ( $is_connected ) { ?>
					<uo-button href="<?php echo esc_url( $disconnect_url ); ?>" color="danger">
						<uo-icon id="sign-out"></uo-icon>
						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>
				<?php } ?>
			   
			</div>
		</div>

	</div>
</form>
