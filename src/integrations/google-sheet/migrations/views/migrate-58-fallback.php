<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice notice-warning">
	<p>
		<strong><?php esc_html_e( 'Important Notice from Uncanny Automator', 'uncanny-automator' ); ?></strong><br/>
		<?php esc_html_e( 'Google has changed permission requirements for Google Drive apps. You must reconnect your Google account now or your Google Sheets actions will not work as expected.', 'uncanny-automator' ); ?>
	</p>
	<p>
		<a
			href="<?php echo esc_url( $args['url_settings'] ); ?>"
			class="button button-primary"
		>
			<?php echo esc_html_x( 'Go to settings', 'Google Sheets', 'uncanny-automator' ); ?>
		</a>
		<a
			href="<?php echo esc_url( $args['url_learn_more'] ); ?>"
			target="_blank"
			class="button button-secondary"
		>
			<?php echo esc_html_x( 'Learn more', 'Google Sheets', 'uncanny-automator' ); ?>
		</a>
	</p>
</div>
