<?php
/**
 * Drip Settings
 * Settings > Premium Integrations > Drip
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$client = $this->functions->get_client();
?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="DRIP"></uo-icon>

				<?php esc_html_e( 'Drip', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php if ( ! $client ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Drip', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect Uncanny Automator to Drip to supercharge your marketing automation and email campaigns. Once configured, Automator recipes can create and manage subscribers, add and remove tags, plus much more.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create or update a subscriber', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a tag to a subscriber', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a tag from a subscriber', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Unsubscribe a subscriber from all mailings', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Delete a subscriber', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a subscriber from a campaign', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a subscriber to a campaign', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Uncanny Automator only supports connecting to one Drip account at a time.', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
						<?php esc_html_e( 'You can only connect to a Drip account for which you have read and write access.', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo $client ? '' : 'has-arrow'; ?>>

				<?php if ( ! $client ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button class="uap-settings-button-drip" href="<?php echo esc_url( $auth_url ); ?>">
							<?php esc_html_e( 'Connect Drip account', 'uncanny-automator' ); ?>
						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

					<?php
				} else {



					if ( ! empty( $client['account'] ) ) {

						$account = $client['account'];

						?>

						<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">
							<div class="uap-settings-panel-user__avatar">
								<?php echo esc_html( strtoupper( $account['name'][0] ) ); ?>
							</div>

							<div class="uap-settings-panel-user-info">
								<div class="uap-settings-panel-user-info__main">
									<?php echo esc_html( $account['url'] ); ?>
									<uo-icon integration="DRIP"></uo-icon>
								</div>

								<div class="uap-settings-panel-user-info__additional">
									<?php

									printf(
										/* translators: 1. URL address */
										esc_html__( 'Account URL: %1$s', 'uncanny-automator' ),
										esc_html( $account['url'] )
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
