<?php
/**
 * Auto-prune logs (tease)
 * Settings > General > Logs > Auto-prune logs
 *
 * Variables:
 * $upgrade_to_pro_url URL to upgrade to Automator Pro
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="uap-settings-panel-content-separator"></div>

<div class="uap-settings-panel-content-subtitle">
	<?php esc_html_e( 'Auto-prune activity logs', 'uncanny-automator' ); ?>
</div>

<uo-switch 
	disabled
	status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>" 
	class="uap-spacing-top"
></uo-switch>

<p>
	<uas-icon code="lock"></uas-icon>
	<?php

	printf(
		/* translators: 1. Trademarked term */
		esc_html__( 'This is a pro-only feature that requires %1$s', 'uncanny-automator' ),
		// "Uncanny Automator Pro" link
		sprintf(
			'<a href="%s" target="_blank">%s <uas-icon code="arrow-up-right-from-square"></uas-icon></a>',
			esc_url( $upgrade_to_pro_url ),
			'Uncanny Automator Pro'
		)
	);

	?>
</p>
