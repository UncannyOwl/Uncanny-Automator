<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$user = get_option( 'automator_twitter_user', array() );

$twitter_name     = ! empty( $user['name'] ) ? $user['name'] : '';
$twitter_username = ! empty( $user['screen_name'] ) ? '@' . $user['screen_name'] : '';

// Get the link to disconnect Twitter
$this->disconnect_url = $this->functions->get_disconnect_url();

$this->api_key = get_option( 'automator_twitter_api_key', '' );

$this->api_secret = get_option( 'automator_twitter_api_secret', '' );

$this->access_token = get_option( 'automator_twitter_access_token', '' );

$this->access_token_secret = get_option( 'automator_twitter_access_token_secret', '' );

/**
 * Twitter user app settings
 * Settings > Premium Integrations > Twitter
 *
 * @since   4.8
 * @version 4.8
 * @package Uncanny_Automator
 *
 */

?>
<form method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon integration="TWITTER"></uo-icon> <?php esc_html_e( 'Twitter', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php

				// Check if Twitter is connected
				if ( $this->is_connected ) {

					?>

					<uo-alert
						heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one Twitter account at a time.', 'uncanny-automator' ); ?>"
					></uo-alert>

					<?php

				}

				?>

				<?php

				// Check if Twitter is not connected
				if ( ! $this->is_connected ) {

					?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Twitter', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Post to Twitter directly from your WordPress site – no third-party software or per-transaction fees required. Automatically tweet new articles, sales and other milestones based on any combination of triggers.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a tweet', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php
						echo sprintf(
								// translators: Link to Twitter knowledgebase article
							esc_html__(
								'To connect Automator to Twitter you will need to create a Twitter app first. %1$s.',
								'uncanny-automator'
							),
							'<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twitter/', 'settings', 'twitter-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Learn More', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
						);
						?>
					</div>

					<?php

					$hide_fields = $this->is_connected ? true : '';

					$this->text_input_html(
						array(
							'id'       => 'automator_twitter_api_key',
							'value'    => $this->api_key,
							'label'    => __( 'API key', 'uncanny-automator' ),
							'required' => true,
							'class'    => 'uap-spacing-top',
							'hidden'   => $hide_fields,
							'disabled' => $hide_fields,
						)
					);

					$this->text_input_html(
						array(
							'id'       => 'automator_twitter_api_secret',
							'value'    => $this->api_secret,
							'label'    => __( 'API key secret', 'uncanny-automator' ),
							'required' => true,
							'class'    => 'uap-spacing-top',
							'hidden'   => $hide_fields,
							'disabled' => $hide_fields,
						)
					);

					$this->text_input_html(
						array(
							'id'       => 'automator_twitter_access_token',
							'value'    => $this->access_token,
							'label'    => __( 'Access token', 'uncanny-automator' ),
							'required' => true,
							'class'    => 'uap-spacing-top',
							'hidden'   => $hide_fields,
							'disabled' => $hide_fields,
						)
					);

					$this->text_input_html(
						array(
							'id'       => 'automator_twitter_access_token_secret',
							'value'    => $this->access_token_secret,
							'label'    => __( 'Access token secret', 'uncanny-automator' ),
							'required' => true,
							'class'    => 'uap-spacing-top',
							'hidden'   => $hide_fields,
							'disabled' => $hide_fields,
						)
					);
				}

				?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<?php

			// Check what button we have to add
			if ( $this->is_connected ) {

				?>

				<div class="uap-settings-panel-bottom-left">

					<?php

					// Check if we have the username and the ID
					if ( ! empty( $twitter_username ) && ! empty( $twitter_name ) ) {

						?>

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $twitter_name[0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $twitter_name ); ?>
									<uo-icon integration="TWITTER"></uo-icon>
								</div>
								<div class="uap-settings-panel-user-info__additional">
									<?php echo esc_html( $twitter_username ); ?>
								</div>
							</div>
						</div>

						<?php

					}

					?>

				</div>

				<div class="uap-settings-panel-bottom-right">
					<uo-button
						href="<?php echo esc_url( $this->disconnect_url ); ?>"
						color="danger"
					>
						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>
				</div>

				<?php

			} else {

				?>


				<uo-button
					type="submit"
				>
					<?php esc_html_e( 'Connect Twitter account', 'uncanny-automator' ); ?>
				</uo-button>

				<?php

			}

			?>

		</div>

	</div>

</form>
