<?php
/**
 * Connect your site
 * Settings > General > License > Connect your site
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * $site_data             Object with data about the connected site to automatorplugin.com
 * $site_is_connected     TRUE if the user connected their site to automatorplugin.com
 * $connect_site_url      URL to connect the site
 * $disconnect_site_url   URL to disconnect the site
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>

<div class="uap-settings-panel-content-subtitle">
	<?php esc_html_e( 'Free account', 'uncanny-automator' ); ?>
</div>

<p>
	<?php esc_html_e( 'Connecting your AI to this site takes a free Uncanny Automator account. It also gives you 250 app credits to try external apps like Slack and Google Sheets.', 'uncanny-automator' ); ?>
</p>

<?php

$customer_email = isset( $site_data['customer_email'] ) ? $site_data['customer_email'] : '';

// Check if they have a Free account connected
if ( $site_is_connected ) {

	?>

	<uas-alert 
		type="success" 
		heading="<?php esc_attr_e( 'Site connected', 'uncanny-automator' ); ?>"
	>
		<p>
			<strong><?php esc_html_e( 'Account:', 'uncanny-automator' ); ?></strong> 
			<?php echo esc_html( $customer_email ); ?>
		</p>

		<uas-button size="sm" color="secondary" href="<?php echo esc_url( $disconnect_site_url ); ?>">
			<?php esc_html_e( 'Disconnect site', 'uncanny-automator' ); ?>
		</uas-button>

	</uas-alert>

	<?php

} else {

	?>

	<uas-button href="<?php echo esc_url( $connect_site_url ); ?>"><uas-icon code="badge-check"></uas-icon> <?php esc_html_e( 'Create a free account', 'uncanny-automator' ); ?></uas-button>

	<?php

}

?>
