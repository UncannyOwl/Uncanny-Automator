<?php

use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security.
}

/**
 * Render the content for users without a Facebook Lead Ads connection.
 */
function automator_fbla_content_partial_no_connection() {
	?>
	<div class="uap-settings-panel-content-subtitle">
		<?php echo esc_html_x( 'Connect Uncanny Automator to Facebook Lead Ads', 'Facebook_Lead_Ads', 'uncanny-automator' ); ?>
	</div>

	<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
		<?php
		echo esc_html_x(
			'Connect Uncanny Automator with Facebook Lead Ads to automate workflows every time a new lead is created. Instantly trigger actions across your favorite apps, saving time and ensuring you never miss a chance to engage with your audience.',
			'Facebook_Lead_Ads',
			'uncanny-automator'
		);
		?>
	</div>

	<p>
		<strong>
			<?php
			echo esc_html_x(
				'Activating this integration will enable the following for use in your recipes:',
				'Facebook_Lead_Ads',
				'uncanny-automator'
			);
			?>
		</strong>
	</p>

	<ul>
		<li>
			<uo-icon id="bolt"></uo-icon>
			<strong><?php esc_html_x( 'Trigger:', 'Facebook Lead Ads', 'uncanny-automator' ); ?></strong>
			<?php echo esc_html_x( 'A lead is created', 'Facebook Lead Ads', 'uncanny-automator' ); ?>
		</li>
	</ul>
	<?php
}

/**
 * Render the table of connected Facebook pages.
 *
 * @param array $pages_access_tokens Pages and access tokens.
 */
function automator_fbla_content_partial_connected_pages_table( $pages_access_tokens ) {
	?>
	<table id="fblaPagesTable" class="uap-fbla-settings-page-table">
		<thead>
			<tr>
				<th style="width:15%;"><?php esc_html_x( 'Name', 'Facebook Lead Ads', 'uncanny-automator' ); ?></th>
				<th style="width:25%;"><?php esc_html_x( 'ID', 'Facebook Lead Ads', 'uncanny-automator' ); ?></th>
				<th style="width:35%;"><?php esc_html_x( 'Status', 'Facebook Lead Ads', 'uncanny-automator' ); ?></th>
				<th style="width:10%; text-align:center;"><?php esc_html_x( 'Actions', 'Facebook Lead Ads', 'uncanny-automator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pages_access_tokens as $page ) { ?>
				<tr>
					<td><?php echo esc_html( $page['name'] ); ?></td>
					<td><?php echo esc_html( $page['id'] ); ?></td>
					<td>
						<div id="status-<?php echo esc_attr( $page['id'] ); ?>">
							<span class="status loading"></span>
							<?php esc_html_x( 'Verifying...', 'Facebook Lead Ads', 'uncanny-automator' ); ?>
						</div>
					</td>
					<td style="text-align: center;">
						<uo-button data-page-id="<?php echo esc_attr( $page['id'] ); ?>" class="uap-fbla-settings-page-fbla-repair" color="secondary" size="small" href="#">
							<uo-icon id="rotate"></uo-icon>
						</uo-button>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
}

/**
 * Render the content for users with a Facebook Lead Ads connection.
 *
 * @param array $pages_access_tokens Pages and access tokens.
 */
function automator_fbla_content_partial_connected_content( $pages_access_tokens ) {
	?>
	<div class="uap-spacing-bottom">
		<p>
			<?php
			esc_html_x( 'The following pages were selected during the OAuth process. Disconnect and reconnect if there are any pages missing and make sure those pages are selected.', 'Facebook Lead Ads', 'uncanny-automator' );
			?>
		</p>
	</div>

	<?php
	if ( ! empty( $pages_access_tokens ) ) {
		automator_fbla_content_partial_connected_pages_table( $pages_access_tokens );
	}
}

/**
 * Render the Facebook Lead Ads content based on connection status.
 *
 * @param array $vars Array of variables including connection status and credentials.
 */
function automator_fbla_content_partial_render( $vars ) {
	if ( empty( $vars['has_connection'] ) ) {
		automator_fbla_content_partial_no_connection();
		return;
	}

	if ( ! empty( $vars['credentials']['pages_access_tokens'] ) ) {
		automator_fbla_content_partial_connected_content( $vars['credentials']['pages_access_tokens'] );
	}
}

automator_fbla_content_partial_render( $vars );

?>

<h5 class="uap-spacing-top"><?php esc_html_x( 'Test your webhook connection', 'Facebook Lead Ads', 'uncanny-automator' ); ?></h5>

<p>
	<?php
	esc_html_x( 'Click the button below to test your website’s accessibility and confirm API support for your WordPress site.', 'Facebook Lead Ads', 'uncanny-automator' );
	?>
</p>

<div id="automatorFblaConnectionResult"></div>

<uo-button 
	id="FBLAConnectionCheckBtn" 
	color="secondary" 
	size="small" 
	uap-tooltip="<?php echo esc_html_x( 'Sends a test payload from our server to your site to confirm it can receive webhooks for Facebook Lead Ads.', 'Facebook Lead Ads', 'uncanny-automator' ); ?>">
	<uo-icon id="rotate"></uo-icon> 
	<?php echo esc_html_x( 'Check webhook delivery', 'Facebook Lead Ads', 'uncanny-automator' ); ?>
</uo-button>

<script>
var automator_fbla_config;
document.addEventListener("DOMContentLoaded", function () {
	automator_fbla_config = {
		apiUrl: UncannyAutomatorBackend.ajax.url + '?action=facebook_lead_verify_page_connection&nonce=' + UncannyAutomatorBackend.rest.nonce,
		pages: <?php echo wp_json_encode( ( new Credentials_Manager() )->get_pages_ids() ?? array() ); ?>
	};
});
</script>
