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

$connected = $this->helpers->functions->get_client();

?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="TRELLO"></uo-icon> 

				<?php echo esc_html_x( 'Trello', 'Trello', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php $this->display_alerts(); ?>

				<?php if ( ! $connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to Trello', 'Trello', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to Trello to automatically manage your projects from inside your WordPress site. Have form submissions create new checklist items or new comments in a forum discussion automatically update a Trello card; you might even have new group members automatically added to a card.', 'Trello', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Trello', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create a card', 'Trello', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create a checklist item in a card', 'Trello', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a label to your card', 'Trello', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Update a card', 'Trello', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a comment to a card', 'Trello', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Trello', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Add a member to a card', 'Trello', 'uncanny-automator' ); ?>
						</li>
					</ul>

				<?php } else { ?>

					<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Trello account at a time.', 'Trello', 'uncanny-automator' ); ?>" class="uap-spacing-bottom">
					</uo-alert>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom" <?php echo $connected ? '' : 'has-arrow'; ?>>

				<?php
				if ( ! $connected ) {

					$auth_url = $this->helpers->functions->get_auth_url();
					?>

					<div class="uap-settings-panel-bottom-left">

						<uo-button class="uap-settings-button-trello" href="<?php echo esc_url( $auth_url ); ?>" target="_self" unsafe-force-target>
							<?php echo esc_html_x( 'Connect Trello account', 'Trello', 'uncanny-automator' ); ?>
						</uo-button>

					</div> <!--.uap-settings-panel-bottom-left -->

					<?php
				} else {

					$user           = $this->helpers->functions->get_user();
					$disconnect_url = $this->helpers->functions->get_disconnect_url();

					?>

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
									esc_html_x( 'Username: %1$s', 'Trello', 'uncanny-automator' ),
									esc_html( $user['username'] )
								);

								?>
							</div>
						</div>
						</div>

					</div> <!--.uap-settings-panel-bottom-left -->

					<div class="uap-settings-panel-bottom-right">
						<uo-button color="danger" href="<?php echo esc_url( $disconnect_url ); ?>">
							<uo-icon id="right-from-bracket"></uo-icon>
							<?php echo esc_html_x( 'Disconnect', 'Trello', 'uncanny-automator' ); ?>
						</uo-button>
					</div>

				<?php } ?>

		</div>

	</div>

</form>
