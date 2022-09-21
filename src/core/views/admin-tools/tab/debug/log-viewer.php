<?php
/**
 * Status > Debug > (Single log view)
 *
 * @since   4.5
 */

?>
<style>
#log-editor {
	padding: 20px 40px;
	background: #282a36;
	color: #cdcdcd;
	width: 100%;
	font-family: 'verdana',monospace;
	font-size: 12px;
	height: 90%;
	border: 0 none;
	box-sizing: border-box;
	border-radius: 0;
}

#log-editor:focus,
#log-editor:active {
	outline: 0 none;
	box-shadow: none;
	border:0 none;
}
</style>

<div class="uap-settings-panel">

	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title uap-spacing-bottom">
			<?php echo esc_html( automator_filter_input( 'debug' ) ); ?>
		</div>

		<?php if ( empty( $this->get_requested_log() ) ) { ?>

			<uo-alert heading="<?php esc_html_e( 'Requested log is empty.', 'uncanny-automator' ); ?>" type="error" ></uo-alert>

		<?php } ?>

		<?php if ( ! empty( $this->get_requested_log() ) ) { ?>

			<textarea readonly cols="50" id="log-editor"><?php echo esc_textarea( $this->get_requested_log() ); ?></textarea>

		<?php } ?>

	</div>

	<div class="uap-settings-panel-bottom">

		<?php
			$url = add_query_arg(
				array(
					'action' => 'automator_log_delete',
					'log_id' => sanitize_file_name( automator_filter_input( 'debug' ) ),
					'nonce'  => wp_create_nonce( 'automator_log_delete' ),
				),
				admin_url( 'admin-ajax.php' )
			);
			?>

		<div class="uap-settings-panel-bottom-left">
		
			<uo-button 
				color="danger"
				href="<?php echo esc_url_raw( $url ); ?>"
				needs-confirmation
				confirmation-heading="<?php esc_attr_e( 'This action is irreversible', 'uncanny-automator' ); ?>"
				confirmation-content="<?php esc_attr_e( 'Are you sure you want to delete this log?', 'uncanny-automator' ); ?>"
				confirmation-button-label="<?php esc_attr_e( 'Yes', 'uncanny-automator' ); ?>"
				>
				<uo-icon id="trash"></uo-icon>
				<?php echo esc_html__( 'Delete log', 'uncanny-automator' ); ?>
			</uo-button>
		</div>

	</div>

</div>



