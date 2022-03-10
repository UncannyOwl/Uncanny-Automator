<?php
/**
 * Facebook Settings
 * Settings > Premium Integrations > Facebook
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $client   The Mailchimp Client.
 * $auth_uri The URI of Mailchimp OAuth Dialog.
 * $disconnect_uri The disconnect url.
 * $connect_code Holds an integer value which is used to identify if connection is successful or not.
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

				<uo-icon id="mailchimp"></uo-icon> 
				<?php esc_html_e( 'Mailchimp', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( 1 === $connect_code && false !== $client ) { ?>
					<?php /* translators: Success message */ ?>
					<uo-alert class="uap-spacing-bottom" type="success" heading="<?php echo esc_attr( sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $client->login->login_name ) ); ?>"></uo-alert>
				<?php } ?>
				
				<?php if ( 2 === $connect_code ) { ?>
					<uo-alert type="error" class="uap-spacing-bottom">
						<?php esc_html_e( 'Something went wrong while connecting to application. Please try again.', 'uncanny-automator' ); ?>
					</uo-alert>
				<?php } ?>

				<?php if ( $client ) { ?>

					<uo-alert
						heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one Mailchimp account at a time.', 'uncanny-automator' ); ?>"
					></uo-alert>

				<?php } ?>

				<?php if ( false === $client ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Mailchimp', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect Uncanny Automator to Mailchimp to better segment and engage with your customers, or automatically send an email to subscribers when a new blog post is published. Add users to audiences and manage user tags based on activity on your WordPress site.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a note to the user', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a tag to the user', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add the user to an audience', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create and send a campaign', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a tag from the user', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Unsubscribe the user from an audience', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<?php if ( false === $client ) { ?>

					<uo-button href="<?php echo esc_url( $auth_uri ); ?>">
						<?php esc_html_e( 'Connect Mailchimp account', 'uncanny-automator' ); ?>
					</uo-button>

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">

								<?php if ( isset( $client->login->avatar ) ) { ?>

									<img src="<?php echo esc_url( $client->login->avatar ); ?>" alt="<?php echo esc_url( $client->login->login_name ); ?>" />
							   
								<?php } else { ?>

									<?php echo esc_html( strtoupper( $client->login->login_name[0] ) ); ?>

								<?php } ?>

							</div>

							<div class="uap-settings-panel-user-info">

								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $client->login->login_name ); ?>

									<uo-icon id="mailchimp"></uo-icon>

								</div>

								<div class="uap-settings-panel-user-info__additional">
									<?php echo esc_html( $client->login->email ); ?>
								</div>

							</div>

						</div>

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
