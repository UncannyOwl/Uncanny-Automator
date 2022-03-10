<?php

namespace Uncanny_Automator;

/**
 * Prune Logs
 * Settings > General > License > Upgrade to Pro
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * $upgrade_to_pro_url    URL to upgrade to Automator Pro
 */

?>

<div class="uap-settings-panel-content-separator"></div>

<uo-alert 
	type="info" 
	heading="<?php esc_attr_e( 'Upgrade to Pro and unlock even more value for your site!', 'uncanny-automator' ); ?>"
>
	<p>
		<?php

		printf(
			/* translators: 1. Link (upgrading to Pro) */
			esc_html__( 'To unlock more than 3x the triggers and actions for your recipes and unlimited third-party actions, consider %1$s.', 'uncanny-automator' ),
			sprintf(
				'<a href="%1$s" target="_blank">%2$s <uo-icon id="external-link"></uo-icon></a>',
				esc_url(
					add_query_arg(
						array(
							'utm_source'  => 'uncanny_automator',
							'utm_medium'  => 'settings',
							'utm_content' => 'license_upgrade_link',
						),
						$upgrade_to_pro_url
					)
				),
				esc_html__( 'upgrading to Pro', 'uncanny-automator' )
			)
		);

		?>
	</p>

	<ul>
		<li>
			<?php esc_html_e( '3x the triggers and actions', 'uncanny-automator' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Unlimited third-party actions with no per-transaction fees', 'uncanny-automator' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Add schedules and delays to your actions', 'uncanny-automator' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Create users in recipes', 'uncanny-automator' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Premium help desk support', 'uncanny-automator' ); ?>
		</li>
	</ul>

	<uo-button

		<?php

		printf(
			'href="%s"',
			esc_url(
				add_query_arg(
					array(
						'utm_source'  => 'uncanny_automator',
						'utm_medium'  => 'settings',
						'utm_content' => 'license_upgrade_button',
					),
					$upgrade_to_pro_url
				)
			)
		);

		?>

		target="_blank"
	>
		<?php

		/* translators: 1. Trademarked term */
		printf( esc_html__( 'Get %1$s', 'uncanny-automator' ), 'Uncanny Automator Pro' );

		?>
	</uo-button>

</uo-alert>
