<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( true === $vars['has_connection'] ) { ?>
	<uo-button color="danger" href="<?php echo esc_url( $vars['disconnect_url'] ); ?>">
		<uo-icon id="sign-out"></uo-icon>
		<?php echo esc_html_x( 'Disconnect', 'Facebook Lead Ads', 'uncanny-automator' ); ?>
	</uo-button>
	<?php
}
