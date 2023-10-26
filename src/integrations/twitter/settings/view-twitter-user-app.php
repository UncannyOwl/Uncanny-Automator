<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$user = get_option( 'automator_twitter_user', array() );

$twitter_name     = ! empty( $user['name'] ) ? $user['name'] : '';
$twitter_username = ! empty( $user['screen_name'] ) ? '@' . $user['screen_name'] : '';

// Get the link to disconnect X/Twitter
$this->disconnect_url = $this->functions->get_disconnect_url();

/**
 * X/Twitter user app settings
 * Settings > Premium Integrations > X/Twitter
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
				<uo-icon integration="TWITTER"></uo-icon> <?php esc_html_e( 'X/Twitter', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php

				// Check if X/Twitter is connected
				if ( $this->is_connected ) {

					?>

					<uo-alert
						heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one X/Twitter account at a time.', 'uncanny-automator' ); ?>"
					></uo-alert>

					<?php

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

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php
						echo sprintf(
								// translators: Link to X/Twitter knowledgebase article
							esc_html__(
								'To connect Automator to X/Twitter you will need to create a X/Twitter app first. %1$s.',
								'uncanny-automator'
							),
							'<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twitter/', 'settings', 'twitter-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Learn More', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
						);
						?>
					</div>

					<?php include trailingslashit( __DIR__ ) . 'view-twitter-form.php'; ?>

				<?php } ?>

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


				<uo-button type="submit">
					<?php esc_html_e( 'Connect X/Twitter account', 'uncanny-automator' ); ?>
				</uo-button>

				<?php

			}

			?>

		</div>

	</div>

</form>
