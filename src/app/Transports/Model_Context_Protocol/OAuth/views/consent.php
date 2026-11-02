<?php
/**
 * MCP OAuth consent page.
 *
 * @var string   $authorization_endpoint Consent form endpoint.
 * @var string   $brand_mark            Trusted local SVG markup.
 * @var array    $capabilities          Requested capabilities.
 * @var bool     $is_hosted_app         Whether the callback matches the configured hosted app.
 * @var string   $client_name           Connecting application name.
 * @var string   $client_origin         Connecting application origin.
 * @var string   $consent_id            Server-side consent identifier.
 * @var bool     $is_local_redirect     Whether the redirect returns to this device.
 * @var bool     $remember_redirect     Whether this is the first approved redirect.
 * @var string   $site_url              WordPress site URL.
 * @var string   $script                Trusted local loading script.
 * @var string   $styles                Trusted local stylesheet contents.
 * @var string   $user_display_name     Administrator display name.
 * @var array    $record                Consent record (scopes, redirect_uri).
 *
 * @package Uncanny_Automator
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$requested_read  = isset( $record['scopes'] ) && in_array( 'mcp:read', (array) $record['scopes'], true );
$requested_write = isset( $record['scopes'] ) && in_array( 'mcp:write', (array) $record['scopes'], true );
$redirect_uri    = (string) ( $record['redirect_uri'] ?? '' );
$display_name    = $client_name;
$client_label    = '';

if ( 1 === preg_match( '/^(Claude Code) \((.+)\)$/u', $client_name, $client_parts ) ) {
	$display_name = $client_parts[1];
	$client_label = $client_parts[2];
}
$dialog_title = ! empty( $is_hosted_app )
	? esc_html_x( 'Connect this WordPress site?', 'MCP OAuth dialog', 'uncanny-automator' )
	: sprintf(
		/* translators: %s: Name of the app requesting access. */
		esc_html_x( 'Connect %s to this site?', 'MCP OAuth dialog', 'uncanny-automator' ),
		$display_name
	);
$connection_context  = ! empty( $is_hosted_app )
	? esc_html_x( 'Connecting Uncanny Automator MCP to WordPress', 'MCP OAuth dialog', 'uncanny-automator' )
	: esc_html_x( 'Connecting an app to WordPress', 'MCP OAuth dialog', 'uncanny-automator' );
$permission_headings = array(
	'read'  => esc_html_x( 'Read site information', 'MCP OAuth dialog', 'uncanny-automator' ),
	'write' => esc_html_x( 'Make changes', 'MCP OAuth dialog', 'uncanny-automator' ),
	'agent' => esc_html_x( 'Use Uncanny Agent', 'MCP OAuth dialog', 'uncanny-automator' ),
);
require __DIR__ . '/page-start.php';
?>
			<div class="consent-heading">
				<h1><?php echo esc_html( $dialog_title ); ?></h1>
				<p class="site"><?php echo esc_html( $site_url ); ?></p>
				<p class="signed-in"><?php esc_html_e( 'Your WordPress account', 'uncanny-automator' ); ?> <span class="chip"><?php echo esc_html( $user_display_name ); ?></span></p>
				<?php if ( '' !== $client_label ) : ?>
				<span class="client-label"><?php echo esc_html( $client_label ); ?></span>
				<?php endif; ?>
			</div>
			<div class="group">
				<p>
					<?php
					if ( ! empty( $is_hosted_app ) && ! empty( $is_agent_access ) ) {
						esc_html_e( 'This site can be used by AI apps you approve in Uncanny Automator, including apps already connected. Each app’s approved access and any site removals still apply. Continue only if you started this connection.', 'uncanny-automator' );
					} else {
						esc_html_e( 'Allow this app to work with this WordPress site. Continue only if you started this connection and recognize the app.', 'uncanny-automator' );
					}
					?>
				</p>
				<p class="acting"><strong><?php esc_html_e( 'Returns to', 'uncanny-automator' ); ?></strong>
					<?php
					if ( $is_local_redirect ) :
						?>
					<?php esc_html_e( 'This device', 'uncanny-automator' ); ?> · <?php endif; ?>
					<span><?php echo esc_html( $redirect_host ); ?></span>
				</p>
			</div>
			<div class="group">
				<h2><?php esc_html_e( 'Access you’re granting', 'uncanny-automator' ); ?></h2>
				<p>
					<?php
					if ( ! empty( $is_agent_access ) ) {
						esc_html_e( 'Uncanny Automator can use the access listed below. Other approved AI apps can use the site tools you allow. Your WordPress permissions still apply.', 'uncanny-automator' );
					} elseif ( $requested_write ) {
						esc_html_e( 'Read site information and make changes through the tools you allow.', 'uncanny-automator' );
					} elseif ( $requested_read ) {
						esc_html_e( 'Look up site information through the tools you allow. This connection is read-only.', 'uncanny-automator' );
					} else {
						esc_html_e( 'Stay connected. No access to read or change site data is requested.', 'uncanny-automator' );
					}
					?>
				</p>
			</div>
			<?php foreach ( $permission_headings as $permission_key => $permission_heading ) : ?>
				<?php if ( ! empty( $capabilities[ $permission_key ] ) ) : ?>
			<section class="group">
				<h2><?php echo esc_html( $permission_heading ); ?></h2>
				<ul><?php foreach ( $capabilities[ $permission_key ] as $capability ) : ?>
					<li><?php echo esc_html( $capability ); ?></li>
				<?php endforeach; ?></ul>
					<?php if ( 'write' === $permission_key ) : ?>
				<p class="fine"><strong><?php esc_html_e( 'Review your AI app’s approval settings:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Your AI app may ask you to approve actions before they run. This depends on the app, its mode, and your approval settings.', 'uncanny-automator' ); ?></p>
				<?php endif; ?>
			</section>
			<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $requested_read || $requested_write || ! empty( $is_agent_access ) ) : ?>
			<section class="group">
				<h2><?php esc_html_e( 'You control access', 'uncanny-automator' ); ?></h2>
				<p>
					<?php
					if ( empty( $is_agent_access ) ) {
						esc_html_e( 'Your WordPress permissions and the tools you allow on this site limit what this app can do.', 'uncanny-automator' );
					} else {
						esc_html_e( 'Your WordPress account permissions limit what this app can do. Site tool, database and Automator action and ability settings control other connected AI apps. Uncanny Agent follows your WordPress permissions, the Site management switch, code and file reading settings, database table limits and other plugins’ ability settings.', 'uncanny-automator' );
						if ( is_multisite() && ! is_main_site() ) {
							esc_html_e( ' On this subsite, the Database access switch also applies to Uncanny Agent.', 'uncanny-automator' );
						}
					}
					?>
				</p>
				<?php if ( $requested_read || $requested_write ) : ?>
				<p class="fine">
					<?php
					if ( is_multisite() && ! is_main_site() && $requested_write ) {
						esc_html_e( 'Site management and code and file reading are enabled by default unless you have turned them off. Database reading is off by default on this subsite and a super administrator can enable it.', 'uncanny-automator' );
					} elseif ( is_multisite() && ! is_main_site() ) {
						esc_html_e( 'Code and file reading are enabled by default unless you have turned them off. Database reading is off by default on this subsite and a super administrator can enable it.', 'uncanny-automator' );
					} elseif ( $requested_write ) {
						esc_html_e( 'Site management, database reading, and code and file reading are enabled by default unless you have turned them off.', 'uncanny-automator' );
					} else {
						esc_html_e( 'Database reading and code and file reading are enabled by default unless you have turned them off.', 'uncanny-automator' );
					}
					echo ' ';
					esc_html_e( 'Review these permissions before connecting, or change them later under Automator › Settings › MCP settings › Site tools. These choices control access for connected AI apps.', 'uncanny-automator' );
					?>
				</p>
				<p class="fine"><strong><?php esc_html_e( 'WordPress abilities:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'WordPress and plugins provide these features for connected apps. You choose which abilities are available.', 'uncanny-automator' ); ?></p>
				<?php endif; ?>
				<p class="fine"><strong><?php esc_html_e( 'How your data is used:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Information read through this connection is sent to this app and may be processed by the AI services it uses.', 'uncanny-automator' ); ?></p>
			</section>
			<?php endif; ?>
			<section class="group">
				<h2><?php esc_html_e( 'Connection duration', 'uncanny-automator' ); ?></h2>
				<?php if ( ! empty( $capabilities['offline'] ) ) : ?>
				<p><?php echo esc_html( $capabilities['offline'][0] ); ?></p>
				<?php else : ?>
				<p><?php esc_html_e( 'This request does not include renewal access.', 'uncanny-automator' ); ?></p>
				<?php endif; ?>
				<p><?php esc_html_e( 'This app stays connected until access expires or you disconnect.', 'uncanny-automator' ); ?></p>
				<p class="fine"><?php esc_html_e( 'Remove access under Automator › Settings › MCP settings › Connections to stop future access.', 'uncanny-automator' ); ?></p>
				<?php if ( $requested_write || ! empty( $is_agent_access ) ) : ?>
				<p class="fine"><strong><?php esc_html_e( 'Disconnecting does not undo completed changes.', 'uncanny-automator' ); ?></strong>
					<?php
					if ( $requested_write ) :
						?>
					<?php esc_html_e( 'Recipes created through this connection can keep running until you turn them off.', 'uncanny-automator' ); ?><?php endif; ?>
				</p>
				<?php endif; ?>
			</section>
			<details class="details">
				<summary><?php esc_html_e( 'Connection details', 'uncanny-automator' ); ?></summary>
				<div class="detail-values">
					<?php if ( '' !== $client_origin ) : ?>
					<p><strong><?php esc_html_e( 'App website', 'uncanny-automator' ); ?>:</strong> <span><?php echo esc_html( $client_origin ); ?></span></p>
					<?php endif; ?>
					<p><strong><?php esc_html_e( 'Return to', 'uncanny-automator' ); ?>:</strong> <span><?php echo esc_html( $redirect_uri ); ?></span></p>
					<?php if ( $remember_redirect ) : ?>
					<p><?php esc_html_e( 'Future connections from this app will use this return address.', 'uncanny-automator' ); ?></p>
					<?php endif; ?>
					<p><strong><?php esc_html_e( 'Requested permissions (scopes)', 'uncanny-automator' ); ?>:</strong> <span><?php echo esc_html( implode( ', ', (array) ( $record['scopes'] ?? array() ) ) ); ?></span></p>
					<?php if ( $requested_read ) : ?>
					<p><strong><?php esc_html_e( 'Database limits:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'By default, access excludes database views and tables of another WordPress installation, identified by their own options and posts tables. On a single site, the owner can approve exact tables or views in Site tools settings; views can expose data from other tables. On a network, site and super administrator restrictions apply. Automator blocks known sensitive fields by default, including password hashes and activation keys, but cannot guarantee that all sensitive information is filtered out.', 'uncanny-automator' ); ?></p>
					<?php endif; ?>
				</div>
			</details>
		</div>
		<form class="consent-footer" method="post" action="<?php echo esc_url( $authorization_endpoint ); ?>">
			<input type="hidden" name="consent_id" value="<?php echo esc_attr( $consent_id ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'automator_mcp_oauth_consent_' . $consent_id ) ); ?>">
			<div class="actions">
				<button class="deny" type="submit" name="decision" value="deny"><?php esc_html_e( 'Cancel', 'uncanny-automator' ); ?></button>
				<button class="approve" type="submit" name="decision" value="approve"><?php esc_html_e( 'Approve connection', 'uncanny-automator' ); ?></button>
			</div>
		</form>
	</main>
	<script><?php echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Only the bundled script is allowed by its CSP hash. ?></script>
</body>
</html>
