<?php

namespace Uncanny_Automator;

/**
 * Whatsapp Settings
 * Settings > Premium Integrations > Whatsapp
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 */

?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="WHATSAPP"></uo-icon> 

				<?php esc_html_e( 'WhatsApp', 'uncanny-automator' ); ?>

			</div>

			<?php if ( ! empty( $alerts ) ) { ?>

				<?php foreach ( $alerts as $alert ) { ?>

					<uo-alert class="uap-spacing-top" type="<?php echo esc_attr( $alert['type'] ); ?>" heading="<?php echo esc_attr( $alert['code'] ); ?>">

						<?php echo esc_html( $alert['message'] ); ?>

					</uo-alert>

				<?php } ?>

			<?php } ?>

			<div class="uap-settings-panel-content">

				<?php if ( ! $is_connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">

						<?php esc_html_e( 'Connect Uncanny Automator to WhatsApp', 'uncanny-automator' ); ?>

					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">

						<?php esc_html_e( 'Integrate your WordPress site directly with WhatsApp. Send WhatsApp messages to users when they make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'uncanny-automator' ); ?>

					</div>

					<p>
						<strong>
							<?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<ul>

						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'A message is received', 'uncanny-automator' ); ?>
						</li>

						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'A message to a recipient is not delivered because they have not opted in', 'uncanny-automator' ); ?>
						</li>

						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'A message to a recipient was not delivered', 'uncanny-automator' ); ?>
						</li>

						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'A message to a recipient is set to a specific status', 'uncanny-automator' ); ?>
						</li>

						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'Send a WhatsApp message to a number', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<strong>
								<?php esc_html_e( 'Action:', 'uncanny-automator' ); ?>
							</strong> 
							<?php esc_html_e( 'Send a WhatsApp message template to a number', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>">
						<?php
							echo sprintf(
								'%2$s <a target="_blank" href="%1$s" title="%3$s">%3$s <uo-icon id="external-link"></uo-icon></a> %4$s',
								automator_utm_parameters( self::KNOWLEDGEBASE_URL, 'premium-integrations', 'whatsapp' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								esc_html__( 'Connecting to WhatsApp requires creating a business Meta application and getting 3 values from inside your account', 'uncanny-automator' ),
								esc_html__( 'Visit our Knowledge Base article', 'uncanny-automator' ),
								esc_html__( 'for instructions.', 'uncanny-automator' )
							);
						?>
					</uo-alert>

				<?php } ?>

				<uo-alert heading="<?php esc_attr_e( 'Error validating access token' ); ?>" id="whatsup-errors" type="error" class="uap-spacing-top" style="display:none;"></uo-alert>

				<!-- Access token ID -->
				<uo-text-field
					id="automator_whatsapp_access_token"
					name="automator_whatsapp_access_token"
					value="<?php echo esc_attr( $access_token ); ?>"
					required
					label="<?php esc_attr_e( 'Access token', 'uncanny-automator' ); ?>"
					class="uap-spacing-top"
					helper='<?php echo wp_kses_post( $access_token_description ); ?>'
					<?php echo $is_connected ? 'hidden disabled' : ''; ?>
				></uo-text-field>

				<!-- Phone number ID -->
				<uo-text-field
					id="automator_whatsapp_phone_id"
					name="automator_whatsapp_phone_id"
					value="<?php echo esc_attr( $phone_id ); ?>"
					label="<?php esc_attr_e( 'Phone number ID', 'uncanny-automator' ); ?>"
					helper=''
					required
					class="uap-spacing-top"
				></uo-text-field>

				<!-- WhatsApp Business Account ID -->
				<uo-text-field
					id="automator_whatsapp_business_account_id"
					name="automator_whatsapp_business_account_id"
					value="<?php echo esc_attr( $business_id ); ?>"
					placeholder=""
					label="<?php esc_attr_e( 'WhatsApp Business Account ID', 'uncanny-automator' ); ?>"
					helper='<?php echo wp_kses_post( $phone_business_description ); ?>'
					required
					class="uap-spacing-top"
				></uo-text-field>

				<?php if ( $is_connected ) { ?>

					<hr class="uap-spacing-top" />

					<uo-text-field
						value="<?php echo esc_url( $webhook_url ); ?>"
						label="<?php esc_attr_e( 'Webhook URL', 'uncanny-automator' ); ?>"
						helper='<?php esc_html_e( 'This is the URL Meta will be sending the events to. Copy and paste this value in your WhatsApp webhook configuration.', 'uncanny-automator' ); ?>'
						disabled
						class="uap-spacing-top"
					></uo-text-field>


					<uo-text-field
						value="<?php echo esc_attr( $verify_token ); ?>"
						label="<?php esc_attr_e( 'Verify token', 'uncanny-automator' ); ?>"
						helper="<?php esc_html_e( 'Copy and paste this value in your WhatsApp webhook configuration under Verify token.', 'uncanny-automator' ); ?>"
						class="uap-spacing-top"
						disabled
					></uo-text-field>

					<uo-button
						onclick="return confirm('<?php echo esc_html( $regenerate_alert ); ?>');"
						href="<?php echo esc_url( $regenerate_key_url ); ?>"
						size="small"
						color="secondary"
						class="uap-spacing-top"
					>

						<uo-icon id="sync"></uo-icon>

						<?php esc_attr_e( 'Regenerate webhook URL', 'uncanny-automator' ); ?>

					</uo-button>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<?php if ( $is_connected ) { ?>

				<div class="uap-settings-panel-bottom-left">

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( substr( $client['application'], 0, 1 ) ); ?>
						</div>

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">
								<?php echo esc_html( $client['application'] ); ?>
								<uo-icon integration="WHATSAPP"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								ID: <?php echo esc_html( $client['app_id'] ); ?>
							</div>

						</div>

					</div>

				</div>

				<div class="uap-settings-panel-bottom-right">

					<uo-button href="<?php echo esc_url( $disconnect_url ); ?>" color="danger">

						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>

					</uo-button>

					<uo-button type="submit">

						<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>

					</uo-button>

				</div>

			<?php } else { ?>

				<uo-button id="automator-whatsapp-connect-btn" type="submit">

					<?php esc_html_e( 'Connect WhatsApp account', 'uncanny-automator' ); ?>

				</uo-button>

			<?php } ?>

		</div>

	</div>

</form>
