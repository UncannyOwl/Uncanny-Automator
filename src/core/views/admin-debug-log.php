<?php
require_once UA_ABSPATH . 'src/core/views/admin-tools-header.php';
if ( ( filter_has_var( INPUT_GET, 'delete_log' ) && 'yes' === filter_input( INPUT_GET, 'delete_log' ) ) && ( filter_has_var( INPUT_GET, '_wpnonce' ) && wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), 'Aut0mAt0r' ) ) ) {
	$file = filter_input( INPUT_GET, 'delete' );
	if ( file_exists( UA_DEBUG_LOGS_DIR . $file ) ) {
		if ( unlink( UA_DEBUG_LOGS_DIR . $file ) ) {
			$url = add_query_arg(
				array(
					'post_type'    => filter_input( INPUT_GET, 'post_type' ),
					'page'         => filter_input( INPUT_GET, 'page' ),
					'file_removed' => 'yes',
				),
				admin_url( 'edit.php' )
			);
		} else {
			$url = add_query_arg(
				array(
					'post_type'    => filter_input( INPUT_GET, 'post_type' ),
					'page'         => filter_input( INPUT_GET, 'page' ),
					'file_removed' => 'no',
				),
				admin_url( 'edit.php' )
			);
		}
	}

	wp_safe_redirect( $url );
	exit;
}
if ( filter_has_var( INPUT_GET, 'file_removed' ) ) {
	if ( 'yes' === filter_input( INPUT_GET, 'file_removed' ) ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html__( 'Log deleted successfully.', 'uncanny-automator' ); ?></strong>
			</p>
		</div>
		<?php
	}
	if ( 'no' === filter_input( INPUT_GET, 'file_removed' ) ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p><strong><?php echo esc_html__( 'Log failed to delete.', 'uncanny-automator' ); ?></strong>
			</p>
		</div>
		<?php
	}
}
// Read logs directory
$log_directory = UA_DEBUG_LOGS_DIR;
$log_files     = array();
if ( ! file_exists( $log_directory ) ) {
	printf( '<h3>%s</h3>', esc_html__( 'No logs found.', 'uncanny-automator' ) );

	return;
}
$handle = opendir( $log_directory );
if ( $handle ) {
	$entry = readdir( $handle );
	while ( false !== ( $entry = readdir( $handle ) ) ) {
		if ( '.' !== $entry && '..' !== $entry && strpos( $entry, '.log' ) ) {
			$log_files[] = $entry;
		}
	}
	closedir( $handle );
}
if ( empty( $log_files ) ) {
	printf( '<h3>%s</h3>', esc_html__( 'No logs found.', 'uncanny-automator' ) );

	return;
}
?>
	<script>
		jQuery(function ($) {
			$("#tabs").tabs().addClass("ui-tabs-vertical ui-helper-clearfix");
			$("#tabs li").removeClass("ui-corner-top").addClass("ui-corner-left");
		});
	</script>
	<div class="wrap uap">
		<section id="tabs">
			<ul class="nav-tab-wrapper uap-nav-tab-wrapper">
				<?php if ( $log_files ) { ?>
					<?php foreach ( $log_files as $log ) { ?>
						<li><a class="nav-tab"
							   href="#<?php echo esc_attr( $log ); ?>"><?php echo esc_attr( $log ); ?></a>
						</li>
					<?php } ?>
				<?php } ?>
			</ul>
			<section class="uap-logs">
				<div class="uap-log-table-container">
					<?php if ( $log_files ) { ?>
						<?php foreach ( $log_files as $log ) { ?>
							<section class="uap-logs" id="<?php echo esc_attr( $log ); ?>">
								<h2>
									<?php
									echo esc_attr( $log );
									$url = add_query_arg(
										array(
											'post_type'  => filter_input( INPUT_GET, 'post_type' ),
											'page'       => filter_input( INPUT_GET, 'page' ),
											'delete_log' => 'yes',
											'delete'     => $log,
											'_wpnonce'   => wp_create_nonce( 'Aut0mAt0r' ),
										),
										admin_url( 'edit.php' )
									);
									?>
									<a style="float:right; display:inline-block"
									   class="button button-secondary button-small"
									   onclick="javascript: return confirm('<?php echo esc_html__( 'Are you sure you want to delete this log?', 'uncanny-automator' ); ?>');"
									   href="<?php echo esc_url_raw( $url ); ?>"><?php echo esc_html__( 'Delete log', 'uncanny-automator' ); ?></a>
								</h2>
								<textarea rows="50" style="width:100%;font-family: monospace; font-size:12px;">
								<?php echo esc_textarea( file_get_contents( $log_directory . $log ) ); ?>
									</textarea>
							</section>
						<?php } ?>
					<?php } ?>
				</div>
			</section>
		</section>
	</div>

<?php
