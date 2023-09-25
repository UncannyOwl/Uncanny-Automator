<?php
/**
 * Telegram Settings
 * Settings > Premium Integrations > Telegram
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$this->functions = new Uncanny_Automator\Telegram_Functions();

$is_connected   = $this->functions->integration_connected();
$bot_token      = $this->functions->get_bot_token();
$disconnect_url = $this->functions->disconnect_url();
$bot_info       = $this->functions->get_bot_info();
$bot_name       = ! empty( $bot_info['first_name'] ) ? $bot_info['first_name'] : '';
$bot_username   = ! empty( $bot_info['username'] ) ? $bot_info['username'] : '';

$kb_url  = esc_attr( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/telegram/', 'settings', 'telegram-kb_article' ) );
$kb_link = sprintf( '<a href="%s" target="_blank">%s %s</a>', $kb_url, esc_attr( __( 'Knowledge Base article', 'uncanny-automator' ) ), '<uo-icon id="external-link"></uo-icon>' );


?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="TELEGRAM"></uo-icon>

				<?php esc_html_e( 'Telegram', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php if ( ! $is_connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Telegram', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect your WordPress site to Telegram to run automations when messages are received and send Telegram messages in your recipes.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Receive a Telegram message', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send a Telegram message', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Setup instructions', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
						<?php
						echo sprintf(
							/* translators: Knowledge base article link */
							_x( 'Connecting to Telegram requires creating a Telegram bot and retrieving an HTTP access token value (a.k.a. "Bot secret"). Visit our %s for instructions.', 'Telegram', 'uncanny-automator' ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$kb_link //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</uo-alert>

					<?php

					$hide_fields = ! $is_connected ? '' : true;

					$this->text_input_html(
						array(
							'id'       => 'automator_telegram_bot_secret',
							'value'    => $bot_token ? $bot_token : '',
							'label'    => __( 'Bot secret', 'uncanny-automator' ),
							'required' => true,
							'class'    => 'uap-spacing-top',
							'hidden'   => $hide_fields,
							'disabled' => $hide_fields,
						)
					);

				} else {
					?>

					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Uncanny Automator only supports connecting to one Telegram account at a time.', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
						<?php esc_html_e( 'You can only connect to a Telegram bot for which you have read and write access.', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<?php if ( ! $is_connected ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button class="uap-settings-button-telegram" type="submit">
							<?php esc_html_e( 'Connect Telegram account', 'uncanny-automator' ); ?>
						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

					<?php
				} else {

					if ( ! empty( $bot_name ) ) {

						?>

						<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">
							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $bot_name[0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $bot_name ); ?>
									<uo-icon integration="TELEGRAM"></uo-icon>
								</div>

								<div class="uap-settings-panel-user-info__additional">
									<?php

									printf(
										/* translators: 1. URL address */
										esc_html__( 'Bot username: %1$s', 'uncanny-automator' ),
										esc_html( $bot_username )
									);

									?>
								</div>
							</div>
						</div>
							<?php
					}

					?>

					</div> <!--.uap-settings-panel-bottom-left -->

					<div class="uap-settings-panel-bottom-right">
						<uo-button color="danger" href="<?php echo esc_url( $disconnect_url ); ?>">
							<uo-icon id="sign-out"></uo-icon>
						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
						</uo-button>
					</div>

				<?php } ?>

		</div>

	</div>

</form>
