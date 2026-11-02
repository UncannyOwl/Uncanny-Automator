<?php

/**
 * App integrations
 * Settings > App integrations > Not connected
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
 * $upgrade_to_pro_url      URL to upgrade to Automator Pro
 * $credits_article_url     URL to an article with information about the credits
 * $connect_site_url        URL to connect the site to automatorplugin.com
 * $current_integration     The ID of the current integration (matches the selected tab)
 * $current_integration_tab The current integration tab object
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<uas-tab-panel slot="panel" name="<?php echo esc_attr( $current_integration ); ?>" active>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-placeholder">

			<uas-icon code="rotate"></uas-icon>

			<div class="uap-settings-panel-title">
				<?php
				printf(
					// translators: %s Integration name.
					esc_html__( 'Create a free account to connect %s', 'uncanny-automator' ),
					esc_html( $current_integration_tab->name )
				);
				?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-settings-panel-content-paragraph">

					<?php

						printf(
							/* translators: 1. Highlighted number of credits, 2. Integration name */
							esc_html__( 'A free Uncanny Automator account gives you %1$s to try external apps like %2$s. It is also how your AI connects to this site.', 'uncanny-automator' ),
							/* translators: 1. Integer. Number of credits */
							'<strong>' . sprintf( esc_html__( '%1$s app credits', 'uncanny-automator' ), '250' ) . '</strong>',
							esc_html( $current_integration_tab->name )
						);

						?>

					<a
						href="<?php echo esc_url( $upgrade_to_pro_url ); ?>"
						target="_blank"
					>
						<?php esc_html_e( 'Unlimited app credits on every paid plan.', 'uncanny-automator' ); ?> <uas-icon code="arrow-up-right-from-square"></uas-icon>
					</a>

				</div>

				<div class="uap-settings-panel-content-buttons">

					<uas-button
						href="<?php echo esc_url( $connect_site_url ); ?>"
					>
						<?php esc_html_e( 'Create a free account', 'uncanny-automator' ); ?>
					</uas-button>

					<uas-button
						href="<?php echo esc_url( $credits_article_url ); ?>"
						target="_blank"
						color="secondary"
					>
						<?php esc_html_e( 'Learn more', 'uncanny-automator' ); ?>
					</uas-button>

				</div>

			</div>
		</div>

	</div>

</uas-tab-panel>
