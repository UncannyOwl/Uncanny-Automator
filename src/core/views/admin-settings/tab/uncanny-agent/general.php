<?php
/**
 * Uncanny Agent - General
 * Settings > Uncanny Agent > General
 *
 * @since   7.1
 * @version 1.1
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Api\Application\Mcp\Handshake\Handshake_Handler;

// Check for handshake connect_token.
$handshake         = new Handshake_Handler();
$has_connect_token = $handshake->has_connect_token();
$handshake_data    = null;

if ( $has_connect_token ) {
	$handshake_data = $handshake->validate_token( $handshake->get_connect_token() );
}

?>

<form method="POST">

	<?php settings_fields( Admin_Settings_Uncanny_Agent_General::SETTINGS_GROUP ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<?php echo esc_html_x( 'Uncanny Agent', 'settings panel title', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-field uap-spacing-top--small">

					<?php echo esc_html_x( "Uncanny Agent is your made-for-WordPress AI assistant. It can analyze or answer questions about your users, posts, sales, courses and more. It can write blog posts, design pages and build and troubleshoot recipes. It's like having a dedicated WordPress helper at your fingertips.", 'agent feature description', 'uncanny-automator' ); ?>

					<uo-switch
						id="<?php echo esc_attr( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ); ?>"
						<?php echo $is_enabled ? 'checked' : ''; ?>

						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>"

						class="uap-spacing-top"
					></uo-switch>

					<div class="uap-field-description">
						<?php echo esc_html_x( "Keep Uncanny Agent enabled to help your team work faster. Uncanny Agent is available to Administrator users only.", 'agent feature description', 'uncanny-automator' ); ?>
					</div>

				</div>

				<!-- Handshake status -->
				<uo-alert style="display: none;" type="success" id="uoa-handshake-result-success" class="uap-spacing-top" heading="<?php echo esc_attr_x( 'Success. Redirecting...', 'Uncanny Agent', 'uncanny-automator' ); ?>"></uo-alert>
				<uo-alert style="display: none;" type="error" id="uoa-handshake-result-failure" class="uap-spacing-top" heading="<?php echo esc_attr_x( 'Permission denied.', 'Uncanny Agent', 'uncanny-automator' ); ?>"></uo-alert>

				 <?php if ( $has_connect_token && ! empty( $handshake_data['valid'] ) ) : ?>

					<uo-alert 
						class="uap-spacing-top"
						heading="
							<?php echo esc_html( $handshake_data['requester_email'] ); ?>
							<?php echo esc_html_x( 'wants to connect this site to', 'handshake description', 'uncanny-automator' ); ?>
							app.uncannyagent.com.
						" 
						type="info"
					>
						
						<div class="uap-spacing-top">
							<?php echo esc_html_x( 'This will allow the Uncanny Agent standalone app to manage automations on this site. The connection uses a secure application password that you can revoke at any time.', 'handshake description', 'uncanny-automator' ); ?>
						</div>

						<div class="uap-spacing-top">
							<?php echo esc_html_x( 'This request expires in 5 minutes.', 'handshake expiry', 'uncanny-automator' ); ?>
						</div>

						<div class="uap-spacing-top" id="uoa-handshake-actions">
							<uo-button size="small" type="button" id="uoa-handshake-allow">
								<?php echo esc_html_x( 'Allow Connection', 'handshake button', 'uncanny-automator' ); ?>
							</uo-button>
							<uo-button size="small" color="danger" type="button" id="uoa-handshake-deny">
								<?php echo esc_html_x( 'Deny', 'handshake button', 'uncanny-automator' ); ?>
							</uo-button>
						</div>

					</uo-alert>


					<script>
					(function() {
						var token   = <?php echo wp_json_encode( $handshake->get_connect_token() ); ?>;
						var nonce   = <?php echo wp_json_encode( wp_create_nonce( Handshake_Handler::AJAX_ACTION ) ); ?>;
						var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						var action  = <?php echo wp_json_encode( Handshake_Handler::AJAX_ACTION ); ?>;
						var callbackUrl = <?php echo wp_json_encode( Handshake_Handler::get_base_url() ); ?>;

						var resultElSuccess  = document.getElementById('uoa-handshake-result-success');
						var resultElFailure  = document.getElementById('uoa-handshake-result-failure');
						var actionsEl = document.getElementById('uoa-handshake-actions');

						function showResult(type, message) {
							actionsEl.style.display = 'none';
							if ( type === 'success' ) {
								resultElSuccess.style.display = 'block';
							} else {
								if ( message ) {
									resultElFailure.setAttribute( 'heading', message );
								}
								resultElFailure.style.display = 'block';
							}
						}

						function sendApproval(approvalAction) {
							var formData = new FormData();
							formData.append('action', action);
							formData.append('_nonce', nonce);
							formData.append('connect_token', token);
							formData.append('approval_action', approvalAction);

							fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
								.then(function(r) { return r.json(); })
								.then(function(data) {
									if (data.success) {
										if (approvalAction === 'deny') {
											showResult('error', '<?php echo esc_js( esc_html_x( 'Connection denied.', 'Uncanny Agent', 'uncanny-automator' ) ); ?>');
										} else {
											showResult('success');
											setTimeout(function() { window.location.href = callbackUrl; }, 1500);
										}
									} else {
										showResult('error', data.data && data.data.message ? data.data.message : '<?php echo esc_js( esc_html_x( 'Something went wrong.', 'Uncanny Agent', 'uncanny-automator' ) ); ?>');
									}
								})
								.catch(function() {
									showResult('error', '<?php echo esc_js( esc_html_x( 'Network error. Please try again.', 'Uncanny Agent', 'uncanny-automator' ) ); ?>');
								});
						}

						document.getElementById('uoa-handshake-allow').addEventListener('click', function() {
							this.disabled = true;
							this.textContent = '<?php echo esc_js( esc_html_x( 'Connecting...', 'Uncanny Agent', 'uncanny-automator' ) ); ?>';
							sendApproval('allow');
						});

						document.getElementById('uoa-handshake-deny').addEventListener('click', function() {
							sendApproval('deny');
						});
					})();
					</script>

				<?php elseif ( $has_connect_token && empty( $handshake_data['valid'] ) ) : ?>

					<uo-alert class="uap-spacing-top" heading="<?php echo esc_html_x( 'Invalid Connection Request', 'handshake error title', 'uncanny-automator' ); ?>" type="error">
						<?php echo esc_html_x( 'This connection request is invalid or has expired. Please try again from app.uncannyagent.com.', 'handshake error', 'uncanny-automator' ); ?>
					</uo-alert>

				<?php endif; ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">
				<uo-button type="submit">
					<?php echo esc_html_x( 'Save settings', 'settings save button', 'uncanny-automator' ); ?>
				</uo-button>
			</div>

		</div>

	</div><!--.uap-settings-panel-->

</form>
