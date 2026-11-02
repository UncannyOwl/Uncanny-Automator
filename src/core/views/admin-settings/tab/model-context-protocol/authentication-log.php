<?php
/**
 * Model Context Protocol - Authentication log.
 *
 * @since 7.6
 * @package Uncanny_Automator
 *
 * Variables:
 * $can_manage_log      Whether the current user may see the log.
 * $auth_log            Recent authentication events, newest first.
 * $log_status          Allowlisted redirect notice state.
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>

<div class="uap-settings-panel automator-mcp-settings automator-mcp-log">

	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<?php echo esc_html_x( 'Connection log', 'settings panel title', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

			<div class="uap-settings-panel-content-paragraph">
				<?php echo esc_html_x( 'Review connection and authentication events to troubleshoot AI app access. After a connection attempt, refresh the log to see the latest events.', 'MCP auth log description', 'uncanny-automator' ); ?>
			</div>
			<div class="uap-settings-panel-content-paragraph">
				<?php
				printf(
					/* translators: %1$s opens the Uncanny Automator link and %2$s closes it. */
					esc_html_x( 'Manage connections in %1$sUncanny Automator%2$s.', 'MCP auth log connection link', 'uncanny-automator' ),
					'<a href="' . esc_url( Hosted_App::connect_url() ) . '">',
					'</a>'
				);
				?>
			</div>

			<?php if ( $can_manage_log && empty( $auth_log ) ) : ?>
				<uo-button href="<?php echo esc_url( Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'authentication-log' ) ); ?>" color="secondary"><?php echo esc_html_x( 'Refresh log', 'MCP log refresh', 'uncanny-automator' ); ?></uo-button>
			<?php endif; ?>


			<?php if ( 'cleared' === $log_status ) : ?>
				<uo-alert class="uap-spacing-top" type="success" heading="<?php echo esc_attr_x( 'Log cleared', 'MCP auth log cleared heading', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'Stored entries were deleted. Future connection events will be recorded here.', 'MCP auth log cleared message', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php elseif ( 'session-expired' === $log_status ) : ?>
				<uo-alert class="uap-spacing-top" type="warning" heading="<?php echo esc_attr_x( 'Nothing changed', 'MCP auth log session expired heading', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'The security check failed. Refresh the page and try again.', 'MCP session expired message', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php elseif ( 'clear-failed' === $log_status ) : ?>
				<uo-alert class="uap-spacing-top" type="error" heading="<?php echo esc_attr_x( 'The log could not be cleared', 'MCP auth log clear error heading', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'Refresh the page and try again.', 'MCP auth log clear error message', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php endif; ?>

			<?php if ( ! $can_manage_log ) : ?>
				<uo-alert class="uap-spacing-top" type="info" heading="<?php echo esc_attr_x( 'Only administrators can see the log', 'MCP auth log permission heading', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'Ask an administrator on this site to download it for you.', 'MCP auth log permission message', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php elseif ( empty( $auth_log ) ) : ?>
				<uo-alert class="uap-spacing-top" type="info" heading="<?php echo esc_attr_x( 'Nothing recorded yet', 'MCP auth log empty heading', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'Start a connection from Uncanny Automator, then refresh this page. An empty log alone does not confirm whether a request reached this site.', 'MCP auth log empty message', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php else : ?>
				<?php
				// Captured once, printed above and below the table, so the log can be
				// cleared or downloaded without scrolling past 100 rows first.
				ob_start();
				?>
				<div class="automator-mcp-log-actions uap-spacing-top">
					<uo-button href="<?php echo esc_url( Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'authentication-log' ) ); ?>" size="small" color="secondary"><?php echo esc_html_x( 'Refresh log', 'MCP log refresh', 'uncanny-automator' ); ?></uo-button>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_Authentication_Log::DOWNLOAD_LOG_ACTION ); ?>">
						<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_Authentication_Log::DOWNLOAD_LOG_ACTION ); ?>
						<uo-button type="submit" size="small" color="secondary">
							<?php echo esc_html_x( 'Download log', 'MCP auth log download button', 'uncanny-automator' ); ?>
						</uo-button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_Authentication_Log::CLEAR_LOG_ACTION ); ?>">
						<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_Authentication_Log::CLEAR_LOG_ACTION ); ?>
						<uo-button type="submit" size="small" color="danger" needs-confirmation
							confirmation-heading="<?php echo esc_attr_x( 'Clear all log entries?', 'MCP log clear confirmation', 'uncanny-automator' ); ?>"
							confirmation-content="<?php echo esc_attr_x( 'This deletes all stored log entries, including entries not shown on this page. Connected apps will not be disconnected.', 'MCP log clear confirmation', 'uncanny-automator' ); ?>"
							confirmation-button-label="<?php echo esc_attr_x( 'Clear log', 'MCP log clear confirmation', 'uncanny-automator' ); ?>">
							<?php echo esc_html_x( 'Clear log', 'MCP auth log clear button', 'uncanny-automator' ); ?>
						</uo-button>
					</form>
				</div>
				<?php $log_actions = ob_get_clean(); ?>
				<?php echo $log_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where captured above. ?>
				<p class="automator-mcp-secondary"><?php echo esc_html_x( "Downloaded logs include this site's address, user IDs and connecting software. They do not include access tokens or sign-in codes. Share logs only with trusted support contacts.", 'MCP log sharing notice', 'uncanny-automator' ); ?></p>
				<p class="automator-mcp-secondary"><?php printf( /* translators: %s Site timezone name or UTC offset. */ esc_html_x( 'Times use this site\'s time zone: %s.', 'MCP log timezone', 'uncanny-automator' ), esc_html( wp_timezone_string() ) ); ?></p>

				<?php
				$event_labels  = array(
					'refresh_token_rotated'     => esc_html_x( 'Refresh token rotated', 'MCP auth log event', 'uncanny-automator' ),
					'handshake'                 => esc_html_x( 'Handshake', 'MCP auth log event', 'uncanny-automator' ),
					'bearer_auth'               => esc_html_x( 'Token check', 'MCP auth log event', 'uncanny-automator' ),
					'authorization'             => esc_html_x( 'Sign-in request', 'MCP auth log event', 'uncanny-automator' ),
					'authorization_consent'     => esc_html_x( 'Consent', 'MCP auth log event', 'uncanny-automator' ),
					'authorization_code_issued' => esc_html_x( 'Sign-in approved', 'MCP auth log event', 'uncanny-automator' ),
					'token_exchange'            => esc_html_x( 'Token exchange', 'MCP auth log event', 'uncanny-automator' ),
					'client_registration'       => esc_html_x( 'App registration', 'MCP auth log event', 'uncanny-automator' ),
					'client_registered'         => esc_html_x( 'App registration', 'MCP auth log event', 'uncanny-automator' ),
					'discovery_health'          => esc_html_x( 'Discovery check', 'MCP auth log event', 'uncanny-automator' ),
				);
				$good_outcomes = array( 'success', 'created', 'revoked', 'consent_shown', 'passed', 'passed_degraded', 'dcr', 'approved' );

				// One explanation per outcome class: what it means, what to do next.
				$explanations = array(
					'invalid_grant'                 => esc_html_x( 'The app sent a sign-in code or renewal token that this site could not accept. It may be invalid, expired, already used or tied to different connection details. Reconnect the site from Uncanny Automator.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_client'                => esc_html_x( 'The app’s identity or authentication method did not match this site’s requirements. Check the Details column and share the log with support if the connection keeps failing.', 'MCP auth log explanation', 'uncanny-automator' ),
					'unauthorized_client'           => esc_html_x( 'This site could not verify the app’s client ID. It may be unknown, revoked or unavailable. Reconnect the site from Uncanny Automator if the connection keeps failing.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_scope'                 => esc_html_x( 'The app asked for a permission this site does not offer.', 'MCP auth log explanation', 'uncanny-automator' ),
					'access_denied'                 => esc_html_x( 'Access was denied, or the request lacked administrator authorization. Check the Details column before starting the connection again.', 'MCP auth log explanation', 'uncanny-automator' ),
					'temporarily_unavailable'       => esc_html_x( 'This site could not complete the request right now. Check the Details column for the reason. If the connection keeps failing, download the log for support.', 'MCP auth log explanation', 'uncanny-automator' ),
					'server_error'                  => esc_html_x( 'This site could not complete the request. Check the Details column and download the log for support if the connection keeps failing.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_request'               => esc_html_x( 'The app sent a request this site could not accept. Check the Details column. Start the connection again from Uncanny Automator if sign-in failed.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_client_metadata'       => esc_html_x( 'The app tried to register itself with details this site could not accept.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_redirect_uri'          => esc_html_x( 'The app registered a return address this site could not accept. Return addresses must use HTTPS, or run on this device.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_missing_token'             => esc_html_x( 'The app connected without any token. Apps often probe once before signing in, which is normal on its own.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_invalid_token'             => esc_html_x( 'The token the app sent is not valid on this site. It may be expired, revoked, or made for a different site. Reconnect the WordPress site from Uncanny Automator.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_insufficient_scope'        => esc_html_x( 'The app requested more access than this token allows. Review the requested tool and the app’s access level. Grant Read and make changes access only if the task needs it.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_unsupported_version'       => esc_html_x( 'The app requested an MCP version this site does not support. Check for updates to the app and Uncanny Automator.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_protocol_version_mismatch' => esc_html_x( 'The app sent conflicting MCP versions in one request. Share the log with the app’s support team.', 'MCP auth log explanation', 'uncanny-automator' ),
					'mcp_missing_protocol_version'  => esc_html_x( 'The app omitted the MCP version required for this request. Check for an app update or share the log with its support team.', 'MCP auth log explanation', 'uncanny-automator' ),
					'failed'                        => esc_html_x( "An earlier discovery check failed. External apps now connect through Uncanny Automator. If a current connection fails, read its newest log entry and share it with support.", 'MCP auth log explanation', 'uncanny-automator' ),
					'administrator_required'        => esc_html_x( 'Someone signed in to WordPress without administrator access tried to approve an app. Sign in as an administrator, then start the connection again from the app.', 'MCP auth log explanation', 'uncanny-automator' ),
					'invalid_target'                => esc_html_x( 'The app omitted this site’s MCP endpoint or requested a token for a different endpoint. Reconnect the site from Uncanny Automator.', 'MCP auth log explanation', 'uncanny-automator' ),
				);
				// A few refs carry a more specific meaning than their outcome class.
				$ref_explanations       = array(
					'UA-MCP-320' => $explanations['administrator_required'],
					'UA-MCP-509' => $explanations['invalid_target'],
					'UA-MCP-525' => $explanations['invalid_target'],
					'UA-MCP-526' => esc_html_x( 'Another request was renewing this connection’s token. This request was refused without consuming its token. If the app does not recover, share the log with support.', 'MCP auth log explanation', 'uncanny-automator' ),
					'UA-MCP-611' => esc_html_x( 'The app tried to register too many or too long return addresses. Reconnect the WordPress site from Uncanny Automator. If it keeps happening, download this log and share it with support.', 'MCP auth log explanation', 'uncanny-automator' ),
				);
				$cimd_fetch_explanation = esc_html_x( "This site could not download the app's public description from the app's own address, so the sign-in was refused. Check that the site can make outbound HTTPS requests (hosting firewall, WP_HTTP_BLOCK_EXTERNAL) and try again.", 'MCP auth log explanation', 'uncanny-automator' );
				$generic_explanation    = esc_html_x( 'Check the result code and details. If the connection keeps failing, download the log and share it with support.', 'MCP auth log explanation', 'uncanny-automator' );
				?>
				<div class="automator-mcp-table-wrap automator-mcp-table-wrap--list uap-spacing-top--small">
					<table class="automator-mcp-table automator-mcp-table--list">
						<caption class="screen-reader-text">
							<?php echo esc_html_x( 'Recent MCP authentication events on this site', 'MCP auth log table caption', 'uncanny-automator' ); ?>
						</caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Time', 'uncanny-automator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Event', 'uncanny-automator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Result', 'uncanny-automator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'App / software', 'uncanny-automator' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Details', 'uncanny-automator' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $auth_log as $entry ) : ?>
								<?php
								$entry_outcome = (string) ( $entry['outcome'] ?? '' );
								$entry_good    = in_array( $entry_outcome, $good_outcomes, true );
								$event_key     = (string) ( $entry['event'] ?? '' );
								$entry_ref     = (string) ( $entry['ref'] ?? '' );
								// Ref and outcome travel as one greppable token, "UA-MCP-202:mcp_invalid_token".
								$entry_result      = '' === $entry_ref ? $entry_outcome : $entry_ref . ':' . $entry_outcome;
								$entry_detail      = (string) ( $entry['detail'] ?? '' );
								$entry_explanation = $ref_explanations[ $entry_ref ] ?? $explanations[ $entry_outcome ] ?? $generic_explanation;
								if ( in_array( $entry_ref, array( 'UA-MCP-313', 'UA-MCP-520' ), true ) && 1 === preg_match( '/^(?:The OAuth client could not be resolved: |Client authentication failed: )?cimd_fetch_failed:/', $entry_detail ) ) {
									$entry_explanation = $cimd_fetch_explanation;
								} elseif ( 'UA-MCP-520' === $entry_ref && 'invalid_client' === $entry_outcome && 'Client authentication failed: revoked.' === $entry_detail ) {
									$entry_explanation = esc_html_x( 'This app was disconnected and no longer has access to this site. To connect it again, start a new connection from Uncanny Automator.', 'MCP auth log explanation', 'uncanny-automator' );
								}
								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Time', 'uncanny-automator' ); ?>">
										<?php if ( ! empty( $entry['t'] ) ) : ?>
											<time datetime="<?php echo esc_attr( gmdate( 'c', (int) $entry['t'] ) ); ?>"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $entry['t'] ) ); ?></time>
											<small class="automator-mcp-secondary"><?php printf( /* translators: %s Human readable time difference. */ esc_html__( '%s ago', 'uncanny-automator' ), esc_html( human_time_diff( (int) $entry['t'] ) ) ); ?></small>
										<?php else : ?>
											<?php esc_html_e( 'Time unavailable', 'uncanny-automator' ); ?>
										<?php endif; ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Event', 'uncanny-automator' ); ?>">
										<strong><?php echo esc_html( $event_labels[ $event_key ] ?? ucwords( str_replace( '_', ' ', $event_key ) ) ); ?></strong>
									</td>
									<td data-label="<?php esc_attr_e( 'Result', 'uncanny-automator' ); ?>">
										<uas-chip size="sm" color="<?php echo $entry_good ? 'success' : 'error'; ?>" filled label="<?php echo $entry_good ? esc_attr_x( 'Success', 'MCP auth log result', 'uncanny-automator' ) : esc_attr_x( 'Failed', 'MCP auth log result', 'uncanny-automator' ); ?>"></uas-chip>
									</td>
									<td data-label="<?php esc_attr_e( 'App / software', 'uncanny-automator' ); ?>" class="automator-mcp-log-software">
										<span><?php echo esc_html( (string) ( $entry['ua'] ?? '' ) ); ?></span>
									</td>
									<td data-label="<?php esc_attr_e( 'Details', 'uncanny-automator' ); ?>">
										<details class="automator-mcp-details">
											<summary><?php echo esc_html_x( 'View details', 'MCP log entry details', 'uncanny-automator' ); ?></summary>
											<?php if ( ! $entry_good ) : ?>
												<p><strong><?php echo esc_html_x( 'Explanation', 'MCP log explanation', 'uncanny-automator' ); ?></strong><br><?php echo $entry_explanation; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped literals from the maps above. ?></p>
											<?php endif; ?>
											<p><code><?php echo esc_html( $event_key ); ?></code><br><code><?php echo esc_html( $entry_result ); ?></code></p>
											<?php foreach ( array( 'subject', 'detail', 'ua' ) as $detail_key ) : ?>
												<?php if ( ! empty( $entry[ $detail_key ] ) ) : ?>
													<p><?php echo esc_html( (string) $entry[ $detail_key ] ); ?></p>
												<?php endif; ?>
											<?php endforeach; ?>
											<?php if ( ! empty( $entry['rid'] ) ) : ?>
												<p><?php printf( /* translators: %s Request ID sent by Uncanny Automator. */ esc_html_x( 'Request ID: %s', 'MCP auth log request ID', 'uncanny-automator' ), esc_html( (string) $entry['rid'] ) ); ?></p>
											<?php endif; ?>
										</details>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php echo $log_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where captured above. ?>
			<?php endif; ?>
			<details class="automator-mcp-details uap-spacing-top">
				<summary><?php echo esc_html_x( 'About this log', 'MCP log help', 'uncanny-automator' ); ?></summary>
				<p><?php echo esc_html_x( 'This page shows up to 100 recent entries. Downloads include all stored entries, up to 200. New entries replace the oldest ones. Some unauthenticated failures are sampled, so not every failed attempt appears.', 'MCP log retention', 'uncanny-automator' ); ?></p>
			</details>

		</div>

	</div>

</div>
