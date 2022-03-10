<?php

namespace Uncanny_Automator;

/**
 * Premium integrations
 * Settings > Premium integrations > Not connected
 * 
 * Tab panel displayed when the user doesn't have an
 * automatorplugin.com account connected
 *
 * @since   3.8
 * @version 3.8
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $upgrade_to_pro_url  URL to upgrade to Automator Pro
 * $credits_article_url URL to an article with information about the credits
 * $connect_site_url    URL to connect the site to automatorplugin.com
 */

?>

<uo-tab-panel active>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-placeholder">

			<uo-icon id="sync"></uo-icon>

			<div class="uap-settings-panel-title">
				<?php esc_html_e( 'Premium integrations use credits', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-settings-panel-content-paragraph">

					<?php

						printf(
							/* translators: 1. Highlighted text */
							esc_html__( 'Connect your site and start using third-party integrations! The free version of Uncanny Automator includes %1$s to use with our third-party integrations.', 'uncanny-automator' ),
							/* translators: 1. Integer. Number of credits */
							'<strong>' . sprintf( esc_html__( '%1$s free credits', 'uncanny-automator' ), '1,000' ) . '</strong>'
						);

					?>

					<a 
						href="<?php echo esc_url( $upgrade_to_pro_url ); ?>"
						target="_blank"
					>
						<?php esc_html_e( 'Buy Pro to get unlimited credits!', 'uncanny-automator' ); ?> <uo-icon id="external-link"></uo-icon>
					</a>

				</div>

				<div class="uap-settings-panel-content-buttons">

					<uo-button
						href="<?php echo esc_url( $connect_site_url ); ?>"
					>
						<?php esc_html_e( 'Connect your site', 'uncanny-automator' ); ?>
					</uo-button>

					<uo-button 
						href="<?php echo esc_url( $credits_article_url ); ?>"
						target="_blank"
						color="secondary"
					>
						<?php esc_html_e( 'Learn more', 'uncanny-automator' ); ?>
					</uo-button>

				</div>

			</div>
		</div>

	</div>

</uo-tab-panel>