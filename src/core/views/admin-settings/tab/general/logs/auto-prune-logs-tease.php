<?php

namespace Uncanny_Automator;

/**
 * Auto-prune logs (tease)
 * Settings > General > Logs > Auto-prune logs
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $upgrade_to_pro_url URL to upgrade to Automator Pro
 */

?>

<div class="uap-settings-panel-content-separator"></div>

<div class="uap-settings-panel-content-subtitle">
	<?php esc_html_e( 'Auto-prune activity logs', 'uncanny-automator' ); ?><uo-pro-tag></uo-pro-tag>
</div>

<div class="uap-field uap-spacing-top--small">

	<label class="uap-field-switch">
		<input type="checkbox" disabled>

		<span class="uap-field-switch__handle"></span>

		<span class="uap-field-switch__label uap-field-switch__label--on">
			<?php esc_html_e( 'Enabled', 'uncanny-automator' ); ?>
		</span>

		<span class="uap-field-switch__label uap-field-switch__label--off">
			<?php esc_html_e( 'Disabled', 'uncanny-automator' ); ?>
		</span>
	</label>

</div>

<p>
	<uo-icon id="lock"></uo-icon>  
	<?php

	printf(
		/* translators: 1. Trademarked term */
		esc_html__( 'This is a pro-only feature that requires %1$s', 'uncanny-automator' ),
		// "Uncanny Automator Pro" link
		sprintf(
			'<a href="%s" target="_blank">%s <uo-icon id="external-link"></uo-icon></a>',
			esc_url( $upgrade_to_pro_url ),
			'Uncanny Automator Pro'
		)
	);

	?>
</p>
