<?php

namespace Uncanny_Automator;

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

?>

<div class="uap-settings-panel-content-subtitle">
	<?php esc_html_e( 'Access third-party integrations', 'uncanny-automator' ); ?>
</div>

<p>
	<?php esc_html_e( 'Creating a free account gives you access to third-party integrations including Google Sheets, Slack, Facebook, MailChimp and more.', 'uncanny-automator' ); ?>
</p>

<?php

// Check if they have a Free account connected
if ( $site_is_connected ) {

	?>

	<uo-alert 
		type="success" 
		heading="<?php esc_attr_e( 'Site connected', 'uncanny-automator' ); ?>"
	>
		<p>
			<strong><?php esc_html_e( 'Account:', 'uncanny-automator' ); ?></strong> 
			<?php echo esc_html( $site_data['customer_email'] ); ?>
		</p>

		<uo-button size="small" color="secondary" href="<?php echo esc_url( $disconnect_site_url ); ?>">
			<?php esc_html_e( 'Disconnect site', 'uncanny-automator' ); ?>
		</uo-button>

	</uo-alert>

	<?php

} else {

	?>

	<uo-button href="<?php echo esc_url( $connect_site_url ); ?>"><uo-icon id="badge-check"></uo-icon> <?php esc_html_e( 'Connect your site', 'uncanny-automator' ); ?></uo-button>

	<?php

}

?>
