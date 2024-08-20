<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="uap-review-banner" class="uap notice" style="padding:0">

	<uo-alert heading="<?php esc_html_e( 'Important Notice from Uncanny Automator', 'uncanny-automator' ); ?>" type="white" custom-icon no-radius>

		<p>
			<?php esc_html_e( 'Google has changed permission requirements for Google Drive apps. You must reconnect your Google account now or your Google Sheets actions will not work as expected.', 'uncanny-automator' ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				href="<?php echo esc_url( $args['url_settings'] ); ?>"
				class="uap-spacing-right uap-spacing-right--xsmall"
				>
				<?php echo esc_html_x( 'Go to settings', 'Google Sheets', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $args['url_learn_more'] ); ?>"
				target="_blank"
				color="secondary"
				data-action="hide-notification-on-click"
				data-notification-type="25"
				>
				<?php echo esc_html_x( 'Learn more', 'Google Sheets', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
