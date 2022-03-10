<?php
/**
 * Instagram Settings
 * Settings > Premium Integrations > Instagram
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 *
 * $is_user_connected               Boolean. True if user is connected to Facebook. Otherwise, false.
 * $facebook_pages_settings_uri     The uri of the Facebook Pages settings.
 * $disconnect_uri                  The Facebook Pages disconnect uri.
 * $facebook_pages_oauth_dialog_uri The Facebook Pages Oauth dialog uri.
 * $facebook_user                   The Connected Facebook User.
 */

namespace Uncanny_Automator;

?>

<form method="POST" action="options.php">

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon id="instagram"></uo-icon> <?php esc_html_e( 'Instagram', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! $is_user_connected ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Instagram Business', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Automatically post photos, hashtags and text to Instagram when a new blog post is published, or when users perform any other supported actions on your site.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Publish a photo to an Instagram account', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert
						type="notice"
						heading="<?php esc_attr_e( 'To connect Uncanny Automator to Instagram Business, you must first connect Facebook Pages.', 'uncanny-automator' ); ?>"
					>

						<p>
							<?php esc_html_e( "Due to Instagram limitations, to use Uncanny Automator with Instagram you'll first need to connect a Facebook Page that's associated with your Instagram Professional or Business Account.", 'uncanny-automator' ); ?>
						</p>

						<uo-button
							href="<?php echo esc_url( $facebook_pages_oauth_dialog_uri ); ?>"
							size="small"
							color="secondary"
						>
							<?php esc_html_e( 'Connect Facebook Pages', 'uncanny-automator' ); ?>
						</uo-button>

					</uo-alert>

				<?php } else { ?>

					<div class="uap-settings-panel-content-subtitle uap-spacing-top">
						<?php esc_html_e( 'Linked Instagram accounts', 'uncanny-automator' ); ?>
					</div>

					<div id="facebook-pages-list"></div>

					<uo-button 
						href="<?php echo esc_url( $facebook_pages_oauth_dialog_uri ); ?>"
						id="facebook-pages-update-button"
						class="uap-spacing-top uap-spacing-top--big"
						color="secondary"
					>
						<?php esc_html_e( 'Update linked Facebook pages', 'uncanny-automator' ); ?>
					</uo-button>

					<div id="facebook-pages-errors" class="uap-spacing-top"></div>

				<?php } ?>

			</div>
		</div>

		<?php if ( $fb_helper ) { ?> 

			<div class="uap-settings-panel-bottom">

				<?php if ( ! $is_user_connected ) { ?>

					<uo-button href="<?php echo esc_url( $facebook_pages_oauth_dialog_uri ); ?>">

						<?php esc_html_e( 'Connect Facebook account', 'uncanny-automator' ); ?>

					</uo-button>

				<?php } else { ?>

					<div class="uap-settings-panel-bottom-left">

						<div class="uap-settings-panel-user">

							<div class="uap-settings-panel-user__avatar">

								<img src="<?php echo esc_url( $facebook_user->picture ); ?>" alt="<?php echo esc_attr( $facebook_user->name ); ?>" />

							</div>

							<div class="uap-settings-panel-user-info">

								<div class="uap-settings-panel-user-info__main">

									<?php echo esc_html( $facebook_user->name ); ?>

									<uo-icon id="facebook"></uo-icon>

								</div>

								<div class="uap-settings-panel-user-info__additional">
									<?php
										echo esc_html(
											sprintf(
												/* translators: 1. ID */
												__( 'ID: %1$d', 'uncanny-automator' ),
												$facebook_user->user_id
											)
										);
									?>
								</div>

							</div>

						</div>

					</div>

					<div class="uap-settings-panel-bottom-right">

						<uo-button color="danger" href="<?php echo esc_url( $disconnect_uri ); ?>">

							<uo-icon id="sign-out"></uo-icon>

							<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>

						</uo-button>

					</div>

				<?php } ?>

			</div>

		<?php } ?>

	</div>

</form>
