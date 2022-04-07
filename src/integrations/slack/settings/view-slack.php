<?php

namespace Uncanny_Automator;

/**
 * Slack Settings
 * Settings > Premium Integrations > Slack
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $slack_workspace      The name of the Slack channel
 * $slack_id             The ID of the connected Slack account
 * $slack_is_connected   TRUE if Slack is connected
 * $connect_slack_url    URL to connect Slack
 * $disconnect_slack_url URL to disconnect Slack
 * $bot_name             The name of the bot
 * $bot_icon             The icon of the bot
 */

?>

<form method="POST" action="options.php" warn-unsaved>
	
	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon id="slack"></uo-icon> <?php esc_html_e( 'Slack', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php if ( $user_just_connected_site ) { ?>

					<?php

					// Alert title
					$alert_title = sprintf(
						/* translators: 1. The Slack workspace name */
						_x( 'Your workspace "%1$s" has been connected successfully!', 'Slack', 'uncanny-automator' ),
						$slack_workspace
					);

					?>

					<uo-alert
						type="success"
						heading="<?php echo esc_attr( $alert_title ); ?>"
						class="uap-spacing-bottom"
					></uo-alert>

				<?php } ?>

				<?php 

				// Check if Slack is NOT connected
				if ( !  $this->is_connected ) {

					?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Slack', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Integrate your WordPress site directly with Slack. Send messages to Slack channels or users when users make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a channel', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a direct message to a Slack user', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a message to a channel', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<?php

				}

				// Check what button we have to add
				if ( $this->is_connected ) {

					?>

					<div class="uap-slack-fields">
						
						<div class="uap-slack-fields-left">

							<div class="uap-settings-panel-content-subtitle">
								<?php esc_html_e( 'Bot setup', 'uncanny-automator' ); ?>
							</div>
							
							<uo-text-field
								id="uap_automator_slack_api_bot_name"
								value="<?php echo esc_attr( $bot_name ); ?>"

								label="<?php esc_attr_e( 'Bot name', 'uncanny-automator' ); ?>"

								class="uap-spacing-top"
							></uo-text-field>

							<uo-text-field
								id="uap_automator_alck_api_bot_icon"
								value="<?php echo esc_attr( $bot_icon ); ?>"

								label="<?php esc_attr_e( 'Bot icon', 'uncanny-automator' ); ?>"
								helper="<?php esc_attr_e( 'The bot icon should be a minimum of 512x512 pixels, but no larger than 1024x1024 pixels.', 'uncanny-automator' ); ?>"
								placeholder="https://..."

								class="uap-spacing-top"
							></uo-text-field>

						</div>

						<div class="uap-slack-fields-right">

							<div class="uap-settings-panel-content-subtitle">
								<?php esc_html_e( 'Preview', 'uncanny-automator' ); ?>
							</div>
							
							<div class="uap-slack-preview uap-spacing-top">
								
								<div class="uap-slack-preview-avatar">
									<img src="<?php echo esc_attr( $bot_icon ); ?>" id="uap-slack-preview-light-icon">
								</div>
								<div class="uap-slack-preview-details">
									<span class="uap-slack-preview-details__name" id="uap-slack-preview-light-name">
										<?php echo ! empty( $bot_name ) ? esc_attr( $bot_name ) : 'Uncanny Automator'; ?>
									</span>

									<span class="uap-slack-preview-details__tag">
										<?php echo esc_html_x( 'APP', 'Slack', 'uncanny-automator' ); ?>
									</span>

									<span class="uap-slack-preview-details__date">
										<?php esc_attr_e( date_i18n( 'g:i A' ) ); ?>
									</span>
								</div>
								<div class="uap-slack-preview-body">
									<?php esc_html_e( 'Hello, world!', 'uncanny-automator' ); ?>
								</div>

							</div>

							<div class="uap-slack-preview uap-slack-preview--dark uap-spacing-top">
								
								<div class="uap-slack-preview-avatar">
									<img src="<?php echo esc_attr( $bot_icon ); ?>" id="uap-slack-preview-dark-icon">
								</div>
								<div class="uap-slack-preview-details">
									<span class="uap-slack-preview-details__name" id="uap-slack-preview-dark-name">
										<?php echo ! empty( $bot_name ) ? esc_html( $bot_name ) : 'Uncanny Automator'; ?>
									</span>

									<span class="uap-slack-preview-details__tag">
										<?php echo esc_html_x( 'APP', 'Slack', 'uncanny-automator' ); ?>
									</span>

									<span class="uap-slack-preview-details__date">
										<?php esc_attr_e( date_i18n( 'g:i A' ) ); ?>
									</span>
								</div>
								<div class="uap-slack-preview-body">
									<?php esc_html_e( 'Hello, world!', 'uncanny-automator' ); ?>
								</div>

							</div>

						</div>

					</div>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert
						heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one Slack workspace.', 'uncanny-automator' ); ?>"
					>
						<?php esc_html_e( 'If you create recipes and then change the connected Slack workspace, your previous recipes may no longer work.', 'uncanny-automator' ); ?>
					</uo-alert>

					<?php

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
					if ( ! empty( $slack_workspace ) && ! empty( $slack_id ) ) {

						?>

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $slack_workspace[0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php

									printf(
										/* translators: 1. The name of the Slack channel */
										esc_html_x( '%1$s (workspace)', 'Slack', 'uncanny-automator' ),
										esc_html( $slack_workspace )
									);

									?>
									<uo-icon id="slack"></uo-icon>
								</div>
								<div class="uap-settings-panel-user-info__additional">
									<?php

									echo esc_html(
										sprintf(
											/* translators: 1. ID */
											__( 'ID: %1$s', 'uncanny-automator' ),
											$slack_id
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
						href="<?php echo esc_url( $disconnect_slack_url ); ?>"
						color="danger"
					>
						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>

					<uo-button
						type="submit"
					>
						<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>
					</uo-button>
				</div>

				<?php

			} else {

				?>

				<uo-button
					href="<?php echo esc_url( $connect_slack_url ); ?>"
				>
					<?php esc_html_e( 'Connect Slack workspace', 'uncanny-automator' ); ?>
				</uo-button>

				<?php

			}

			?>

		</div>

	</div>
</form>
