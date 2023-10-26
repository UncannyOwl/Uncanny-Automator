<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * X/Twitter Settings
 * Settings > Premium Integrations > X/Twitter
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $twitter_username       The username of the connected X/Twitter account
 * $twitter_id             The ID of the connected X/Twitter account
 * $twitter_is_connected   TRUE if X/Twitter is connected
 * $connect_twitter_url    URL to connect X/Twitter
 * $disconnect_twitter_url URL to disconnect X/Twitter
 */
try {
	$this->client       = $this->functions->get_client();
	$this->is_connected = true;
} catch ( \Exception $th ) {
	$this->client       = false;
	$this->is_connected = false;
}

// Get the X/Twitter username
$twitter_username = ! empty( $this->client['screen_name'] ) ? $this->client['screen_name'] : '';
// Get the X/Twitter ID
$twitter_id = ! empty( $this->client['user_id'] ) ? $this->client['user_id'] : '';

	// Get the link to connect X/Twitter
$connect_twitter_url = $this->functions->get_connect_url();

// Get the link to disconnect X/Twitter
$disconnect_twitter_url = $this->functions->get_disconnect_url();

// Check if the user JUST connected the workspace and returned
// from the Slack connection page
$user_just_connected_site = automator_filter_input( 'connect' ) === '1';

?>

<form method="POST" action="options.php">
	<div class="uap-settings-panel">
		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon integration="TWITTER"></uo-icon> <?php esc_html_e( 'X/Twitter', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php
				if ( $user_just_connected_site ) {


					$this->alert_html(
						array(
							'type'    => 'success',
							'heading' => sprintf(
								/* translators: 1. The account username */
								_x( 'Your account "%1$s" has been connected successfully!', 'X/Twitter', 'uncanny-automator' ),
								$twitter_username
							),
						)
					);

				}

				// Check if X/Twitter is connected
				if ( $this->is_connected ) {

					$this->alert_html(
						array(
							'content' => esc_html__( 'Uncanny Automator only supports connecting to one X/Twitter account at a time.', 'uncanny-automator' ),
						)
					);

					// $this->alert_html(
					// 	array(
					// 		'type'    => 'error',
					// 		'heading' => __( 'Warning!', 'uncanny-automator' ),
					// 		'content' => sprintf(
					// 			// translators: Link to Tweitter knowledgebase article
					// 			esc_html__( 'Please reconnect your site to X/Twitter to address the recent API issues. %1$s.', 'uncanny-automator' ),
					// 			'<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twitter/', 'settings', 'twitter-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Learn More', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
					// 		),
					// 	)
					// );

				}

				?>

				<?php

				// Check if X/Twitter is not connected
				if ( ! $this->is_connected ) {

					?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to X/Twitter', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Post to X/Twitter directly from your WordPress site – no third-party software or per-transaction fees required. Automatically tweet new articles, sales and other milestones based on any combination of triggers.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a tweet', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-connection-method">
						<div class="uap-settings-panel-content-connection-method__title">
							<?php esc_html_e( 'Connection method', 'uncanny-automator' ); ?>
						</div>
						<div class="uap-settings-panel-content-connection-method__options">
							<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--quick-connect">
								<input <?php echo checked( $this->get_default_connection_type(), 'hybrid' ); ?> type="radio" value="quick-connect" id="uap-quick-connect-radio" name="uap-twitter-connect-method">
								<label class="uap-custom-app-label" for="uap-quick-connect-radio">
									<span class="uap-settings-panel-content-connection-method__option-title">
										<?php esc_html_e( 'Quick connect', 'uncanny-automator' ); ?>
									</span>
									<p class="uap-settings-panel-content-paragraph">
										<?php esc_html_e( 'The most convenient option: Connect our app to your X/Twitter account', 'uncanny-automator' ); ?>
									</p>
									<p class="uap-settings-panel-content-paragraph">
										<strong><?php esc_html_e( 'Limit: 5 tweets/day', 'uncanny-automator' ); ?></strong>
									</p>
								</label>
							</div>
							<div class="uap-settings-panel-content-connection-method__option uap-settings-panel-content-connection-method--custom-app">
								<input <?php echo checked( $this->get_default_connection_type(), 'self-hosted' ); ?> type="radio" id="uap-custom-app-radio" value="custom-app" name="uap-twitter-connect-method">
								<label class="uap-custom-app-label" for="uap-custom-app-radio">
									<span class="uap-settings-panel-content-connection-method__option-title">
										<?php esc_html_e( 'Custom app', 'uncanny-automator' ); ?>
									</span>
									<p class="uap-settings-panel-content-paragraph">
										<?php esc_html_e( 'The most powerful option: Use your own app to unlock more tweets', 'uncanny-automator' ); ?>
									</p>
									<p class="uap-settings-panel-content-paragraph">
										<strong><?php esc_html_e( 'Limit: 100 tweets/day', 'uncanny-automator' ); ?></strong>
									</p>
								</label>
							</div>
						</div>
					</div>

					<div class="uap-connection-app-fields uap-settings-panel-content-connection-custom-app">
						<?php include trailingslashit( __DIR__ ) . 'view-twitter-form.php'; ?>
					</div>

				<?php } ?>

			</div>

		</div>
		
		<div class="uap-settings-panel-bottom">
			<?php if ( $this->is_connected ) { ?>

				<div class="uap-settings-panel-bottom-left">

					<?php if ( ! empty( $twitter_username ) && ! empty( $twitter_id ) ) { ?>

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $twitter_username[0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $twitter_username ); ?>
									<uo-icon integration="TWITTER"></uo-icon>
								</div>
								<div class="uap-settings-panel-user-info__additional">
									<?php

									echo esc_html(
										sprintf(
											/* translators: 1. ID */
											__( 'ID: %1$s', 'uncanny-automator' ),
											$twitter_id
										)
									);

									?>
								</div>
							</div>
						</div>

						<?php

					}

					?>

				</div>

				<div class="uap-settings-panel-bottom-right">
					<uo-button
						href="<?php echo esc_url( $disconnect_twitter_url ); ?>"
						color="danger"
					>
						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>
				</div>

			<?php } else { ?> 

				<div class="uap-settings-panel-content-connection-custom-app">
					<uo-button type="submit">
						<?php esc_html_e( 'Connect X/Twitter account', 'uncanny-automator' ); ?>
					</uo-button>
				</div>
			
				<div class="uap-settings-panel-content-connection-quick-connect">
					<uo-button href="<?php echo esc_url( $connect_twitter_url ); ?>" >
						<?php esc_html_e( 'Connect X/Twitter account', 'uncanny-automator' ); ?>
					</uo-button>
				</div>

			<?php } ?>
		</div> <!--.uap-settings-panel-bottom-->
	</div>
</form>
