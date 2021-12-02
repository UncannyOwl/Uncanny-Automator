<?php

use Uncanny_Automator\Automator_DB;

$report   = Automator()->system_report->get();
$database = $report['database'];
if ( ( filter_has_var( INPUT_GET, 'repair_db_tables' ) && 'yes' === filter_input( INPUT_GET, 'repair_db_tables' ) ) && ( filter_has_var( INPUT_GET, '_wpnonce' ) && wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), 'Aut0mAt0r' ) ) ) {
	Automator_DB::verify_base_tables( true );
	delete_option( 'automator_schema_missing_tables' );
	$url = add_query_arg(
		array(
			'post_type'         => filter_input( INPUT_GET, 'post_type' ),
			'page'              => filter_input( INPUT_GET, 'page' ),
			'database_repaired' => 'yes',
		),
		admin_url( 'edit.php' )
	);
	wp_safe_redirect( $url );
	exit;
}
if ( filter_has_var( INPUT_GET, 'database_repaired' ) && 'yes' === filter_input( INPUT_GET, 'database_repaired' ) ) {
	?>
	<div class="notice notice-success is-dismissible">
		<p><strong><?php echo esc_html__( 'Database repaired successfully', 'uncanny-automator' ); ?></strong>
		</p>
	</div>
	<?php
}
?>
<table id="status-database" class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Database">
			<h2>
				<?php
				esc_html_e( 'Database', 'uncanny-automator' );
				Automator()->system_report->output_tables_info();
				?>
			</h2>
		</th>
	</tr>
	</thead>
	<tbody>
	<?php if ( ! empty( $database['database_size'] ) && ! empty( $database['database_tables'] ) ) : ?>
		<?php foreach ( $database['database_tables']['automator'] as $table => $table_data ) { ?>
			<tr>
				<td><span class="dashicons dashicons-editor-table"></span> <?php echo esc_html( $table ); ?></td>
				<td class="help">&nbsp;</td>
				<td>
					<?php
					if ( ! $table_data ) {
						$msg = strpos( $table, '_view' ) ? esc_html__( 'View does not exist', 'uncanny-automator' ) : esc_html__( 'Table does not exist', 'uncanny-automator' );
						echo '<mark class="error"><span class="dashicons dashicons-database-remove"></span> ' . esc_attr( $msg ) . '</mark>';
					} else {
						echo '<mark class="yes">';
						echo '<span class="dashicons dashicons-database-view"></span>';
						echo '</mark>';
						/* Translators: %1$f: Table size, %2$f: Index size, %3$s Engine. */
						printf( esc_html__( 'Data: %1$.2fMB + Index: %2$.2fMB + Engine %3$s', 'uncanny-automator' ), esc_html( $table_data['data'] ), esc_html( $table_data['index'] ), esc_html( $table_data['engine'] ) );

					}
					?>
				</td>
			</tr>
		<?php } ?>
	<?php endif; ?>
	<script>
		/**
		 * Wrapping tables data to display tooltip.
		 */
		var wrapInner = function (parent, wrapper) {
			if (typeof wrapper === "string")
				wrapper = document.createElement(wrapper);

			var div = parent.appendChild(wrapper);

			while (parent.firstChild !== wrapper)
				wrapper.appendChild(parent.firstChild);
		}

		let td_help = document.querySelectorAll('table.automator_status_table td.help');

		if (td_help.length >= 1) {
			td_help.forEach(function (item) {
				if ('&nbsp;' === item.innerHTML.trim() || 0 === item.innerHTML.trim().length) {
					// Remove the '?' icon.
					item.classList.add('no-tooltip-text');
					return;
				}
				wrapInner(item, 'span');
			});
		}

	</script>
	</tbody>
</table>
<?php
$missing_tables = Automator_DB::verify_base_tables();
$missing_views  = Automator_DB::verify_base_views();

if ( 0 === count( $missing_tables ) && 0 === count( $missing_views ) ) {
	echo sprintf( '<h3 style="color:green"><span class="dashicons dashicons-yes"></span>%s</h3>', esc_html__( 'Everything Ok!', 'uncanny-automator' ) );

	return;
}
$url = add_query_arg(
	array(
		'post_type'        => filter_input( INPUT_GET, 'post_type' ),
		'page'             => filter_input( INPUT_GET, 'page' ),
		'repair_db_tables' => 'yes',
		'_wpnonce'         => wp_create_nonce( 'Aut0mAt0r' ),
	),
	admin_url( 'edit.php' )
);
?>
<p>
	<a href="<?php echo esc_url_raw( $url ); ?>"
	   class="button button-primary"><?php echo esc_html__( 'Repair Automator tables', 'uncanny-automator' ); ?></a>
</p>
