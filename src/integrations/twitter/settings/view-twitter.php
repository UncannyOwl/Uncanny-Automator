<?php

namespace Uncanny_Automator;

/**
 * Twitter Settings
 * Settings > Premium Integrations > Twitter
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $twitter_username       The username of the connected Twitter account
 * $twitter_id             The ID of the connected Twitter account
 * $twitter_is_connected   TRUE if Twitter is connected
 * $connect_twitter_url    URL to connect Twitter
 * $disconnect_twitter_url URL to disconnect Twitter
 */

?>

<div class="uap-settings-panel">
	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<uo-icon id="twitter"></uo-icon> <?php esc_html_e( 'Twitter', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

			<?php if ( $user_just_connected_site ) { ?>

				<?php

				// Alert title
				$alert_title = sprintf(
					/* translators: 1. The account username */
					_x( 'Your account "%1$s" has been connected successfully!', 'Twitter', 'uncanny-automator' ),
					$twitter_username
				);

				?>

				<uo-alert
					type="success"
					heading="<?php echo esc_attr( $alert_title ); ?>"
					class="uap-spacing-bottom"
				></uo-alert>

			<?php } ?>

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

				<?php

			}

			?>

		</div>

	</div>

	<div
		class="uap-settings-panel-bottom"

		<?php 

		// Check if we have to add the arrow
		if ( ! $this->is_connected ) {
			// echo 'has-arrow';
		}

		?>
	>

		<?php

		// Check what button we have to add
		if ( $this->is_connected ) {

			?>

			<div class="uap-settings-panel-bottom-left">

				<?php

				// Check if we have the username and the ID
				if ( ! empty( $twitter_username ) && ! empty( $twitter_id ) ) {

					?>

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( strtoupper( $twitter_username[0] ) ); ?>
						</div>

						<div class="uap-settings-panel-user-info">
							<div class="uap-settings-panel-user-info__main">
								<?php echo esc_html( $twitter_username ); ?>
								<uo-icon id="twitter"></uo-icon>
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

			<?php

		} else {

			?>

			<uo-button
				href="<?php echo esc_url( $connect_twitter_url ); ?>"
			>
				<?php esc_html_e( 'Connect Twitter account', 'uncanny-automator' ); ?>
			</uo-button>

			<?php

		}

		?>

	</div>

</div>
