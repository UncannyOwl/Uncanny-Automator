<?php
/**
 * Setup-wizard main template file.
 */
?>

<script>
	if ( window.opener && window.opener !== window )
	{
		window.close();
	}
</script>

<div class="automator-setup-wizard-wrap">

	<div class="automator-setup-wizard <?php echo esc_attr( $this->get_step() ); ?>">

		<?php $step = sanitize_file_name( $this->get_step() ); ?>

		<?php $view = $this->get_view_path() . sprintf( '%s.php', $step ); ?>

		<?php if ( ! file_exists( $view ) ) : ?>
			<?php $step = 'step-1'; ?>
			<?php $view = $this->get_view_path() . sprintf( '%s.php', $step ); ?>
		<?php endif; ?>

		<?php require $view; ?>

	</div>

</div>

<?php $this->set_has_tried_connecting( false ); ?>

<div class="automator-setup-wizard__footer">
	<a href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>" title="<?php esc_attr_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>">
		&larr; <?php esc_html_e( 'Go back to the dashboard', 'uncanny-automator' ); ?>
	</a>
</div>

<script>
	jQuery(document).ready(function($){
		'use strict';
		function popupWindow(url, windowName, win, w, h) {
			const y = win.top.outerHeight / 2 + win.top.screenY - ( h / 2);
			const x = win.top.outerWidth / 2 + win.top.screenX - ( w / 2);
			var popupWindow = win.open(url, windowName, `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=${w}, height=${h}, top=${y}, left=${x}`);

			var popupTick = setInterval(function() {
					if (popupWindow.closed) {
						clearInterval(popupTick);
						setTriedConnectedTrue();

						$('#ua-connect-account-btn.uo-settings-btn').addClass('uo-settings-btn--disabled').addClass('loading');
					}
				}, 500);

			return popupWindow;
		}

		function setTriedConnectedTrue() {
			$.ajax({
				url: '<?php echo esc_html( admin_url( 'admin-ajax.php' ) ); ?>',
				data: {
					action: 'uo_setup_wizard_set_tried_connecting',
					nonce: '<?php echo esc_html( wp_create_nonce( 'uo_setup_wizard_set_tried_connecting' ) ); ?>'
				},
				success: function() {
					location.reload( true );
				},
				error: function( error, message ) {
					console.warn( message );
				}
			});
		}

		$('.ua-connect-account-btn-class').on('click', function(e){
			e.preventDefault();
			popupWindow(
				'<?php echo esc_url( $this->get_connect_button_uri() ); ?>',
				'<?php esc_html_e( 'Uncanny Automator', 'uncanny-automator' ); ?>',
				window,
				500,
				600
			);
		});

		$('#ua-checkout-btn').on('click', function(e){
			e.preventDefault();
			popupWindow(
				'<?php echo esc_url( $this->get_checkout_uri() ); ?>',
				'<?php esc_html_e( 'Uncanny Automator', 'uncanny-automator' ); ?>',
				window,
				500,
				600
			);
		});
	});
</script>
