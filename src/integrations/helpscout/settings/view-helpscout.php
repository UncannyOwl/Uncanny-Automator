<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="HELPSCOUT"></uo-icon>

				<?php esc_html_e( 'Help Scout', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( $vars['is_connected'] ) { ?>

					<div class="uap-settings-panel-content">

						<uo-alert heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one Help Scout account at a time.', 'uncanny-automator' ); ?>"></uo-alert>

						<div class="uap-settings-panel-content-separator"></div>

						<uo-switch id="uap_helpscout_enable_webhook"  <?php echo esc_attr( $vars['enable_triggers'] ); ?> label="<?php esc_attr_e( 'Enable triggers', 'uncanny-automator' ); ?>"></uo-switch>

						<div id="uap-helpscout-webhook" style="display: none;">

							<uo-alert heading="Setup instructions" class="uap-spacing-top">

								<p>

								<?php esc_html_e( "Enabling Help Scout triggers requires setting up a webhook in your Help Scout account using the URL below. A few steps and you'll be up and running in no time. Visit our", 'uncanny-automator' ); ?>

									<a href="https://automatorplugin.com/knowledge-base/helpscout-triggers/?utm_source=uncanny_automator&amp;utm_medium=settings&amp;utm_content=active-campaign-triggers-kb_article" target="_blank">

										<?php esc_html_e( 'Knowledge Base article', 'uncanny-automator' ); ?>

										<uo-icon id="external-link"></uo-icon>

									</a>

									<?php esc_html_e( 'for simple instructions.	', 'uncanny-automator' ); ?>

								</p>

								<p>
									<uo-text-field
										disabled
										copy-to-clipboard
										id="uap_helpscout_webhook_key"
										value="<?php echo esc_attr( $vars['webhook_key'] ); ?>"
										label="<?php esc_attr_e( 'Secret key', 'uncanny-automator' ); ?>"
										helper="<?php esc_html_e( "You'll be asked to enter a secret key.", 'uncanny-automator' ); ?>">
									</uo-text-field>
								</p>

								<p>
									<uo-text-field
										disabled
										copy-to-clipboard
										value="<?php echo esc_url( $vars['webhook_url'] ); ?>"
										label="<?php esc_attr_e( 'Callback URL', 'uncanny-automator' ); ?>"
										helper="<?php esc_html_e( "You'll be asked to enter a webhook URL.", 'uncanny-automator' ); ?>">
									</uo-text-field>
								</p>
								<uo-button
									needs-confirmation
									confirmation-heading="<?php esc_attr_e( 'This action is irreversible', 'uncanny-automator' ); ?>"
									confirmation-content="<?php esc_attr_e( 'Regenerating the secret key will prevent Help Scout triggers from working until the new secret key is set in Help Scout. Continue?', 'uncanny-automator' ); ?>"
									confirmation-button-label="<?php esc_attr_e( 'Confirm', 'uncanny-automator' ); ?>"
									href="<?php echo esc_url( $vars['webhook_regenerate_url'] ); ?>"
									size="small"
									color="secondary"
									class="uap-spacing-bottom uap-spacing-top">

									<?php esc_html_e( 'Regenerate secret key', 'uncanny-automator' ); ?>

								</uo-button>

							</uo-alert>
						</div>

					</div>

				<?php } else { ?>

					<?php if ( ! empty( $vars['has_errors'] ) ) { ?>

						<uo-alert class="uap-spacing-bottom" type="error" heading="<?php echo esc_attr( $vars['error_message'] ); ?>">
						</uo-alert>

					<?php } ?>

					<div class="uap-settings-panel-content-subtitle">

						<?php esc_html_e( 'Connect Uncanny Automator to Help Scout', 'uncanny-automator' ); ?>

					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">

						<?php esc_html_e( "Use Uncanny Automator and Help Scout to automate your engagement with customers. Make complex things simple: create a conversation when a subscription expires, send a Slack notification when a conversation receives a Happiness rating, tag conversations based on a user's membership level.", 'uncanny-automator' ); ?>

					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( 'A conversation receives a reply from a customer', 'uncanny-automator' ); ?>

						</li>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( 'A satisfaction rating is received', 'uncanny-automator' ); ?>

						</li>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( 'A note is added to a conversation', 'uncanny-automator' ); ?>

						</li>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( "A conversation's tags are updated", 'uncanny-automator' ); ?>

						</li>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( 'Add a tag to a conversation', 'uncanny-automator' ); ?>

						</li>

						<li>

							<uo-icon id="bolt"></uo-icon>

							<strong>
								<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
							</strong>

							<?php esc_html_e( 'Create a conversation in a mailbox', 'uncanny-automator' ); ?>

						</li>

					</ul>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<?php if ( ! $vars['is_connected'] ) { ?>

				<uo-button href="<?php echo esc_url( $vars['connect_url'] ); ?>">

					<?php esc_html_e( 'Connect Help Scout account', 'uncanny-automator' ); ?>

				</uo-button>

			<?php } else { ?>

				<div class="uap-settings-panel-bottom-left">

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">

							<?php echo esc_html( substr( $vars['user']['firstName'], 0, 1 ) ); ?>

						</div>

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">
								<?php
								echo esc_html(
									implode(
										' ',
										array(
											$vars['user']['firstName'],
											$vars['user']['lastName'],
										)
									)
								);
								?>
								<uo-icon integration="HELPSCOUT"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php echo esc_html( $vars['user']['email'] ); ?>
							</div>

						</div>

					</div>
				</div>

				<div class="uap-settings-panel-bottom-right">

					<uo-button color="danger" href="<?php echo esc_url( $vars['disconnect_url'] ); ?>">

						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>

					</uo-button>


					<uo-button type="submit">

						<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>

					</uo-button>

				</div>

				<?php } ?>


		</div>

	</div>

</form>
