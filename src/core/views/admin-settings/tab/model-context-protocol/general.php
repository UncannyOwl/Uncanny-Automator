<?php
/**
 * Model Context Protocol - Connection.
 *
 * @since 7.6
 * @package Uncanny_Automator
 *
 * Variables: $clients, $client_list_failed, $can_manage, $status, $bulk_counts,
 * $registration_count, $active_registrations, $tools_url,
 * $automator_abilities_url, $wordpress_abilities_url, $approved_users,
 * $http_address_location.
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\Site_Address_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$date_format         = get_option( 'date_format' );
$has_active_clients  = $active_registrations > 0;
$avatar_allowed_html = array(
	'img' => array(
		'src' => true,
		'srcset' => true,
		'alt' => true,
		'class' => true,
		'height' => true,
		'width' => true,
		'loading' => true,
		'decoding' => true,
		'aria-hidden' => true,
	),

);
?>

<div class="uap-settings-panel automator-mcp-settings automator-mcp-connections">

	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<?php echo esc_html_x( 'Connections', 'settings panel title', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

			<?php if ( '' !== $http_address_location ) : ?>
				<uo-alert type="warning" class="uap-spacing-bottom">
					<?php
					if ( Site_Address_Settings::CONFIGURATION === $http_address_location ) {
						echo esc_html_x( "AI apps can't connect while this site's address starts with http://. Ask your host or developer to update the site address in its configuration and confirm that HTTPS is working.", 'MCP http address warning', 'uncanny-automator' );
					} elseif ( Site_Address_Settings::NETWORK_EDIT_SITE === $http_address_location ) {
						echo esc_html_x( "AI apps can't connect while this site's address starts with http://. A network administrator can change Site Address (URL) to start with https:// in Network Admin › Sites › Edit site.", 'MCP http address warning', 'uncanny-automator' );
					} elseif ( Site_Address_Settings::HOST === $http_address_location ) {
						echo esc_html_x( "AI apps can't connect while this site's address starts with http://. This is the network's main site, so its address can't be changed in the dashboard. Ask your host to switch the network to https://.", 'MCP http address warning', 'uncanny-automator' );
					} else {
						echo esc_html_x( "AI apps can't connect while this site's address starts with http://. In Settings › General, change WordPress Address (URL) and Site Address (URL) to start with https://.", 'MCP http address warning', 'uncanny-automator' );
					}
					?>
				</uo-alert>
			<?php endif; ?>

			<uo-alert type="info">
				<div>
					<?php
					printf(
						/* translators: %1$s opens the Uncanny Automator link and %2$s closes it. */
						esc_html_x( 'Connect your AI to this WordPress site through %1$sUncanny Automator%2$s.', 'MCP connection introduction', 'uncanny-automator' ),
						'<a href="' . esc_url( Hosted_App::connect_url() ) . '">',
						'</a>'
					);
					?>
				</div>

				<uo-button href="<?php echo esc_url( Hosted_App::connect_url() ); ?>" target="_self" unsafe-force-target color="primary" size="small" class="uap-spacing-top--small"><?php echo esc_html_x( 'Connect an AI app', 'MCP connect action', 'uncanny-automator' ); ?></uo-button>

			</uo-alert>

			<section class="automator-mcp-access-review uap-spacing-top" aria-labelledby="automator-mcp-access-review-heading">
				<div role="heading" aria-level="2" id="automator-mcp-access-review-heading" class="uap-settings-panel-content-subtitle uap-spacing-top--none uap-spacing-bottom--small"><?php echo esc_html_x( 'Review permissions', 'MCP access reminder heading', 'uncanny-automator' ); ?></div>
				<p class="uap-spacing-top--none uap-spacing-bottom--none"><?php echo esc_html_x( 'Site management and database reading can give apps access to sensitive site data. Review these settings before approving an AI app.', 'MCP access reminder body', 'uncanny-automator' ); ?></p>
				<ul class="automator-mcp-access-links uap-spacing-top uap-spacing-bottom">
					<li>
						<a href="<?php echo esc_url( $tools_url ); ?>"><?php echo esc_html_x( 'Site tools', 'MCP access reminder link', 'uncanny-automator' ); ?></a>
						<span><?php echo esc_html_x( 'Control site access, including site management, WordPress requests and database reading.', 'MCP access reminder helper', 'uncanny-automator' ); ?></span>
					</li>
					<li>
						<a href="<?php echo esc_url( $automator_abilities_url ); ?>"><?php echo esc_html_x( 'Automator actions', 'MCP access reminder link', 'uncanny-automator' ); ?></a>
						<span><?php echo esc_html_x( 'Choose which actions AI apps can find, add to recipes and run directly.', 'MCP access reminder helper', 'uncanny-automator' ); ?></span>
					</li>
					<li>
						<a href="<?php echo esc_url( $wordpress_abilities_url ); ?>"><?php echo esc_html_x( 'WordPress abilities', 'MCP access reminder link', 'uncanny-automator' ); ?></a>
						<span><?php echo esc_html_x( 'Choose which abilities AI apps can access from Automator, WordPress core, and other plugins.', 'MCP access reminder helper', 'uncanny-automator' ); ?></span>
					</li>
				</ul>
			</section>

			<?php if ( 'revoked' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="success" heading="<?php echo esc_attr_x( 'Access removed.', 'MCP client revoked heading', 'uncanny-automator' ); ?>"></uo-alert>
			<?php elseif ( 'deleted' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="success" heading="<?php echo esc_attr_x( 'Registration deleted.', 'MCP client deleted heading', 'uncanny-automator' ); ?>"></uo-alert>
			<?php elseif ( 'all-revoked' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="success" heading="<?php echo esc_attr_x( 'All access removed.', 'MCP bulk revoked heading', 'uncanny-automator' ); ?>"></uo-alert>
			<?php elseif ( 'all-deleted' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="success" heading="<?php echo esc_attr_x( 'All registrations deleted.', 'MCP bulk deleted heading', 'uncanny-automator' ); ?>"></uo-alert>
			<?php elseif ( 'delete-failed' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="error" heading="<?php echo esc_attr_x( 'The request could not be completed. Review the current registrations and try again.', 'MCP save rejected', 'uncanny-automator' ); ?>">
					<?php echo esc_html_x( 'Its access must already be removed.', 'MCP delete registration requirement', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php elseif ( in_array( $status, array( 'bulk-revoke-failed', 'bulk-delete-failed' ), true ) ) : ?>
				<uo-alert class="uap-spacing-top" type="error" role="alert">
					<?php
					printf(
						/* translators: %1$d is the number of registrations updated, %2$d is the number that could not be updated. */
						esc_html( _n( '%1$d item updated. %2$d could not be updated. Review the remaining items and try again.', '%1$d items updated. %2$d could not be updated. Review the remaining items and try again.', $bulk_counts['updated'], 'uncanny-automator' ) ),
						(int) $bulk_counts['updated'],
						(int) $bulk_counts['failed']
					);
					?>
				</uo-alert>
			<?php elseif ( 'revoke-failed' === $status ) : ?>
				<uo-alert class="uap-spacing-top" type="error" heading="<?php echo esc_attr_x( 'The request could not be completed. Review the current registrations and try again.', 'MCP save rejected', 'uncanny-automator' ); ?>"></uo-alert>
			<?php endif; ?>

			<?php if ( $can_manage ) : ?>
				<div class="uap-settings-panel-content-separator"></div>

				<div class="uap-settings-panel-content-subtitle">
					<?php echo esc_html_x( 'AI app access to this site', 'MCP access heading', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-settings-panel-content-paragraph">
					<?php echo esc_html_x( 'Each connection lets Uncanny Automator and your connected AI apps use this site as the WordPress user who approved it. Remove access to stop a connection.', 'MCP access body', 'uncanny-automator' ); ?>
				</div>

				<?php if ( $unfinished_count > 0 ) : ?>
					<p class="description uap-spacing-top--small">
						<?php
						printf(
							/* translators: %d is the number of unfinished connection attempts. */
							esc_html( _n( '%d unfinished connection attempt is waiting for approval. Unfinished attempts are normally removed automatically after they expire.', '%d unfinished connection attempts are waiting for approval. Unfinished attempts are normally removed automatically after they expire.', $unfinished_count, 'uncanny-automator' ) ),
							(int) $unfinished_count
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $clients ) ) : ?>
					<p class="description uap-spacing-top--small">
						<?php
						printf(
							/* translators: %s is the site's timezone, for example Europe/Paris. */
							esc_html_x( 'Dates and times use the site timezone: %s.', 'MCP registration table timezone note', 'uncanny-automator' ),
							esc_html( wp_timezone_string() )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $client_list_failed ) ) : ?>
					<uo-alert type="error" role="alert"><?php esc_html_e( 'Connections could not be loaded. Refresh this page to try again. If it still does not work, contact your host or site administrator.', 'uncanny-automator' ); ?> <a href=""><?php esc_html_e( 'Try again', 'uncanny-automator' ); ?></a></uo-alert>
				<?php elseif ( empty( $clients ) ) : ?>
					<uo-alert
						class="uap-spacing-top"
						type="info"
						heading="<?php echo esc_attr_x( 'No connections yet', 'MCP access empty heading', 'uncanny-automator' ); ?>"
					>
						<?php
						printf(
							/* translators: %1$s opens the Uncanny Automator link and %2$s closes it. */
							esc_html_x( 'To get started, sign in to %1$sUncanny Automator%2$s and connect this WordPress site. Connections appear here after you approve them.', 'MCP access empty body', 'uncanny-automator' ),
							'<a href="' . esc_url( Hosted_App::connect_url() ) . '">',
							'</a>'
						);
						?>
					</uo-alert>
				<?php else : ?>
					<?php if ( in_array( null, array_column( $clients, 'approved_user_ids' ), true ) ) : ?>
						<uo-alert type="warning" role="alert"><?php esc_html_e( 'Access could not be checked. Refresh this page to try again. If it still does not work, contact your host or site administrator.', 'uncanny-automator' ); ?> <a href=""><?php esc_html_e( 'Try again', 'uncanny-automator' ); ?></a></uo-alert>
					<?php endif; ?>
					<div class="automator-mcp-table-wrap automator-mcp-table-wrap--list uap-spacing-top">
						<table class="automator-mcp-table automator-mcp-table--list">
							<caption class="screen-reader-text">
								<?php echo esc_html_x( 'Connections with access to this site', 'MCP client table caption', 'uncanny-automator' ); ?>
							</caption>
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html_x( 'Connection ID', 'MCP registrations column', 'uncanny-automator' ); ?></th>
									<th scope="col"><?php echo esc_html_x( 'Status', 'MCP registrations column', 'uncanny-automator' ); ?></th>
									<th scope="col"><?php echo esc_html_x( 'User', 'MCP registrations column', 'uncanny-automator' ); ?></th>
									<th scope="col"><?php echo esc_html_x( 'Registered on', 'MCP registrations column', 'uncanny-automator' ); ?></th>
									<th scope="col"><?php echo esc_html_x( 'Actions', 'MCP registrations column', 'uncanny-automator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $clients as $client ) :
									$client_id  = (string) ( $client['client_id'] ?? '' );
									$display_id = (string) preg_replace( '/^automator_(?:pkce|secret)_/', '', $client_id );
									$short_id   = substr( $client_id, -8 );
									$is_active  = ! empty( $client['active'] );
									?>
									<tr>
										<td data-label="<?php echo esc_attr_x( 'Connection ID', 'MCP registrations column', 'uncanny-automator' ); ?>">
											<strong dir="ltr"><?php echo esc_html( $display_id ); ?></strong>
										</td>
										<td data-label="<?php echo esc_attr_x( 'Status', 'MCP registrations column', 'uncanny-automator' ); ?>">
											<?php if ( null === $client['approved_user_ids'] ) : ?>
												<uas-chip size="sm" color="neutral" label="<?php echo esc_attr_x( 'Unknown', 'MCP registration approval state', 'uncanny-automator' ); ?>"></uas-chip>
											<?php elseif ( empty( $client['approved_user_ids'] ) ) : ?>
												<uas-chip size="sm" color="neutral" label="<?php echo esc_attr_x( 'Removed', 'MCP registration approval state', 'uncanny-automator' ); ?>"></uas-chip>
											<?php else : ?>
												<uas-chip size="sm" color="success" filled label="<?php echo esc_attr_x( 'Active', 'MCP registration approval state', 'uncanny-automator' ); ?>"></uas-chip>
											<?php endif; ?>
										</td>
										<td data-label="<?php echo esc_attr_x( 'User', 'MCP registrations column', 'uncanny-automator' ); ?>">
											<?php if ( null === $client['approved_user_ids'] ) : ?>
												<?php echo esc_html_x( 'Not available', 'MCP registrations missing value', 'uncanny-automator' ); ?>
											<?php elseif ( empty( $client['approved_user_ids'] ) ) : ?>
												<?php echo esc_html_x( 'None', 'MCP registration approver empty', 'uncanny-automator' ); ?>
											<?php else : ?>
												<ul class="automator-mcp-approvers">
													<?php foreach ( $client['approved_user_ids'] as $approved_user_id ) : ?>
														<?php $approved_user = $approved_users[ $approved_user_id ] ?? null; ?>
														<li>
															<?php if ( $approved_user ) : ?>
																<?php echo wp_kses( get_avatar( $approved_user, 32, '', '', array( 'extra_attr' => 'aria-hidden="true"' ) ), $avatar_allowed_html ); ?>
																<span><?php echo esc_html( $approved_user->user_login ); ?></span>
															<?php else : ?>
																<span><?php echo esc_html_x( 'Deleted user', 'MCP approval missing user', 'uncanny-automator' ); ?></span>
															<?php endif; ?>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
											<?php if ( ! empty( $client['contact'] ) ) : ?>
												<span class="automator-mcp-secondary">
													<?php
													/* translators: %s is the email address of the Uncanny Automator account that made the connection. */
													printf( esc_html_x( 'Automator account: %s', 'MCP connection account', 'uncanny-automator' ), esc_html( (string) $client['contact'] ) );
													?>
												</span>
											<?php endif; ?>
										</td>
										<td data-label="<?php echo esc_attr_x( 'Registered on', 'MCP registrations column', 'uncanny-automator' ); ?>">
											<?php echo empty( $client['created_at'] ) ? esc_html_x( 'Not available', 'MCP registrations missing value', 'uncanny-automator' ) : esc_html( wp_date( $date_format, (int) $client['created_at'] ) ); ?>
										</td>

										<td data-label="<?php echo esc_attr_x( 'Actions', 'MCP registrations column', 'uncanny-automator' ); ?>">
											<div class="automator-mcp-bulk-actions">
												<?php if ( $is_active ) : ?>
													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
														<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_General::REVOKE_CLIENT_ACTION ); ?>">
														<input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>">
														<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_General::REVOKE_CLIENT_ACTION . ':' . $client_id ); ?>
														<uo-button
															type="submit"
															size="small"
															color="danger"
															needs-confirmation
															confirmation-heading="<?php printf( /* translators: %s Last eight characters of the connection ID. */ esc_attr_x( 'Remove access for the connection ending %s?', 'MCP disconnect confirmation heading', 'uncanny-automator' ), esc_attr( $short_id ) ); ?>"
															confirmation-content="<?php echo esc_attr_x( 'Uncanny Automator and its AI apps can no longer use this site through this connection. To use them again, connect again from Uncanny Automator.', 'MCP disconnect confirmation body', 'uncanny-automator' ); ?> <?php echo esc_attr_x( 'Completed actions are not undone. Work already started may still finish.', 'MCP destructive confirmation note', 'uncanny-automator' ); ?>"
															confirmation-button-label="<?php echo esc_attr_x( 'Remove access', 'MCP disconnect confirmation button', 'uncanny-automator' ); ?>"
															aria-label="<?php printf( /* translators: %s Last eight characters of the connection ID. */ esc_attr_x( 'Remove access for the connection ending %s', 'MCP disconnect accessible name', 'uncanny-automator' ), esc_attr( $short_id ) ); ?>"
														>
															<?php echo esc_html_x( 'Remove access', 'MCP disconnect button', 'uncanny-automator' ); ?>
														</uo-button>
													</form>
												<?php else : ?>
													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
														<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_General::DELETE_CLIENT_ACTION ); ?>">
														<input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>">
														<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_General::DELETE_CLIENT_ACTION . ':' . $client_id ); ?>
														<uo-button
															type="submit"
															size="small"
															color="danger"
															needs-confirmation
															confirmation-heading="<?php printf( /* translators: %s Last eight characters of the connection ID. */ esc_attr_x( 'Delete the registration for the connection ending %s?', 'MCP delete registration confirmation heading', 'uncanny-automator' ), esc_attr( $short_id ) ); ?>"
															confirmation-content="<?php echo esc_attr_x( 'This permanently removes the registration record. Its access must already be removed.', 'MCP delete registration confirmation body', 'uncanny-automator' ); ?> <?php echo esc_attr_x( 'Completed actions are not undone. Work already started may still finish.', 'MCP destructive confirmation note', 'uncanny-automator' ); ?>"
															confirmation-button-label="<?php echo esc_attr_x( 'Delete registration', 'MCP delete registration confirmation button', 'uncanny-automator' ); ?>"
															aria-label="<?php printf( /* translators: %s Last eight characters of the connection ID. */ esc_attr_x( 'Delete the registration for the connection ending %s', 'MCP delete registration accessible name', 'uncanny-automator' ), esc_attr( $short_id ) ); ?>"
														>
															<?php echo esc_html_x( 'Delete registration', 'MCP delete registration button', 'uncanny-automator' ); ?>
														</uo-button>
													</form>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<p class="description uap-spacing-top--small">
						<?php
						printf(
							/* translators: %s is the prefix of every stored connection ID, automator_pkce_. */
							esc_html_x( 'For developers: each stored connection ID starts with %s, which is not shown above.', 'MCP connection ID prefix note', 'uncanny-automator' ),
							'<uas-chip size="sm" color="neutral" label="automator_pkce_"></uas-chip>'
						);
						?>
					</p>

					<uas-accordion class="uap-spacing-top">
						<uas-accordion-item id="automator-mcp-manage-registrations">
							<strong slot="summary"><?php echo esc_html_x( 'Manage registrations', 'MCP manage registrations disclosure', 'uncanny-automator' ); ?></strong>
							<div>
								<div class="uap-settings-panel-content-paragraph">
									<?php echo esc_html_x( 'Remove access before deleting the records.', 'MCP manage registrations help', 'uncanny-automator' ); ?>
								</div>
								<div class="automator-mcp-bulk-actions uap-spacing-top--small">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_General::REVOKE_ALL_CLIENTS_ACTION ); ?>">
										<input type="hidden" name="confirmed" value="1">
										<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_General::REVOKE_ALL_CLIENTS_ACTION ); ?>
										<uo-button type="submit" size="small" color="danger" <?php disabled( ! $has_active_clients ); ?>
											needs-confirmation
											confirmation-heading="<?php echo esc_attr_x( 'Remove all access?', 'MCP disconnect all confirmation heading', 'uncanny-automator' ); ?>"
											confirmation-content="<?php echo esc_attr_x( 'No connection can use this site any more. To use one again, connect again from Uncanny Automator.', 'MCP disconnect all confirmation body', 'uncanny-automator' ); ?> <?php echo esc_attr_x( 'Completed actions are not undone. Work already started may still finish.', 'MCP destructive confirmation note', 'uncanny-automator' ); ?>"
											confirmation-button-label="<?php echo esc_attr_x( 'Remove all access', 'MCP disconnect all confirmation button', 'uncanny-automator' ); ?>"
										>
											<?php echo esc_html_x( 'Remove all access', 'MCP disconnect all button', 'uncanny-automator' ); ?>
										</uo-button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_General::DELETE_ALL_CLIENTS_ACTION ); ?>">
										<input type="hidden" name="confirmed" value="1">
										<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_General::DELETE_ALL_CLIENTS_ACTION ); ?>
										<uo-button type="submit" size="small" color="danger" <?php disabled( $has_active_clients ); ?>
											needs-confirmation
											confirmation-heading="<?php echo esc_attr_x( 'Delete all registrations?', 'MCP delete all confirmation heading', 'uncanny-automator' ); ?>"
											confirmation-content="<?php printf( /* translators: %d Number of registration records. */ esc_attr( _n( 'This permanently removes %d registration record. All access must already be removed.', 'This permanently removes %d registration records. All access must already be removed.', $registration_count, 'uncanny-automator' ) ), (int) $registration_count ); ?> <?php echo esc_attr_x( 'Completed actions are not undone. Work already started may still finish.', 'MCP destructive confirmation note', 'uncanny-automator' ); ?>"
											confirmation-button-label="<?php echo esc_attr_x( 'Delete all registrations', 'MCP delete all confirmation button', 'uncanny-automator' ); ?>"
										>
											<?php echo esc_html_x( 'Delete all registrations', 'MCP delete all button', 'uncanny-automator' ); ?>
										</uo-button>
									</form>
								</div>
							</div>
						</uas-accordion-item>
					</uas-accordion>
				<?php endif; ?>
			<?php endif; ?>

		</div>

	</div>

</div>
