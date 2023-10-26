<?php
/**
 * This content will be displayed for connected users.
 *
 * @since 5.2
 */
?>
<uo-alert heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Constant Contact account at a time.', 'Constant Contact', 'uncanny-automator' ); ?>" class = 'uap-spacing-bottom' >
</uo-alert>

<?php if ( true === $just_connected ) { ?>
	<uo-alert type="success" heading="<?php echo esc_attr_x( 'Your account has been successfully connected.', 'Constant Contact', 'uncanny-automator' ); ?>" class = 'uap-spacing-bottom' >
	</uo-alert>    
<?php } ?>
