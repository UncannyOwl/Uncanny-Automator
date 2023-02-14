<?php
/**
 * Trello Settings
 * Settings > Premium Integrations > Microsoft Teams
 *
 * @since   4.9
 * @version 4.9
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$auth_url       = $this->helpers->functions->get_auth_url();
$user           = $this->helpers->functions->get_user();
$disconnect_url = $this->helpers->functions->get_disconnect_url();
$connected      = $this->helpers->functions->get_client();

?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="TRELLO"></uo-icon> 

				<?php esc_html_e( 'Trello', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php if ( ! $connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Trello', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Connect Uncanny Automator to Trello to automatically manage your projects from inside your WordPress site. Have form submissions create new checklist items or new comments in a forum discussion automatically update a Trello card; you might even have new group members automatically added to a card.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a card', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create a checklist item in a card', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a label to your card', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Update a card', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a comment to a card', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a member to a card', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php echo esc_attr( sprintf( __( 'Uncanny Automator only supports connecting to one Trello account at a time.', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo $connected ? '' : 'has-arrow'; ?>>

				<?php if ( ! $connected ) { ?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button class="uap-settings-button-trello" href="<?php echo esc_url( $auth_url ); ?>">
							<?php esc_html_e( 'Connect Trello account', 'uncanny-automator' ); ?>
						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( strtoupper( $user['initials'][0] ) ); ?>
						</div>

						<div class="uap-settings-panel-user-info">
							<div class="uap-settings-panel-user-info__main">
								<?php echo esc_html( $user['fullName'] ); ?>
								<uo-icon integration="TRELLO"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php

								printf(
									/* translators: 1. Username */
									esc_html__( 'Username: %1$s', 'uncanny-automator' ),
									esc_html( $user['username'] )
								);

								?>
							</div>
						</div>
						</div>

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
