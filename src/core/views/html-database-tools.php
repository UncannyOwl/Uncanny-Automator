<?php

use Uncanny_Automator\Automator_DB;
use Uncanny_Automator\Automator_System_Report;

global $wpdb;

$report = Automator()->system_report->get();

$database = $report['database'];

$missing_tables = Automator_DB::verify_base_tables();

?>
<div class="uap-settings-panel">

	<div class="uap-settings-panel-top">

		<?php if ( 'yes' === automator_filter_input( 'database_repaired' ) ) { ?>

			<uo-alert class="uap-spacing-bottom" type="success"
						heading="<?php echo esc_attr__( 'Database repaired successfully', 'uncanny-automator' ); ?>"></uo-alert>

		<?php } ?>

		<?php if ( 'true' === automator_filter_input( 'status' ) ) { ?>

			<uo-alert class="uap-spacing-bottom" type="success"
						heading="<?php echo esc_attr__( 'Selected view has been successfully dropped.', 'uncanny-automator' ); ?>"></uo-alert>

		<?php } ?>

		<?php if ( 'true' === automator_filter_input( 'purged' ) ) { ?>

			<uo-alert class="uap-spacing-bottom" type="success"
						heading="<?php echo esc_attr__( 'Tables have been successfully purged.', 'uncanny-automator' ); ?>"></uo-alert>

		<?php } ?>

		<?php if ( 'false' === automator_filter_input( 'status' ) ) { ?>

			<uo-alert class="uap-spacing-bottom" type="error"
						heading="<?php echo esc_attr__( 'Database operation failed.', 'uncanny-automator' ); ?>"></uo-alert>

		<?php } ?>

		<?php // Display missing tables error. ?>
		<?php if ( ! empty( $missing_tables ) ) { ?>

			<uo-alert class="uap-spacing-bottom" type="error"
						heading="<?php esc_attr_e( 'Missing base tables. Some Automator functionality may not work as expected.', 'uncanny-automator' ); ?>">

				<?php echo esc_html( implode( ', ', $missing_tables ) ); ?>

			</uo-alert>

		<?php } ?>
		<?php

		$missing_views = Automator_DB::verify_base_views();

		if ( 0 === count( $missing_tables ) && 0 === count( $missing_views ) && AUTOMATOR_DATABASE_VIEWS_ENABLED ) {
			printf( '<uo-alert type="success" style="margin-bottom:5px;">%s</uo-alert>', esc_html__( 'No issues found with Automator DB.', 'uncanny-automator' ) );
		}

		if ( ! AUTOMATOR_DATABASE_VIEWS_ENABLED ) {
			printf( '<uo-alert type="warning" style="margin-bottom:5px;">%s</uo-alert>', esc_html__( 'Automator DB views are disabled by the site administrator.', 'uncanny-automator' ) );
		}
		?>
		<table id="status-database" class="automator_status_table widefat" cellspacing="0">

			<thead>

			<tr>

				<th colspan="3" data-export-label="Database">
					<h2><?php esc_html_e( 'Database tables', 'uncanny-automator' ); ?></h2>
				</th>

			</tr>

			</thead>

			<tbody>

			<?php if ( ! empty( $database['database_size'] ) && ! empty( $database['database_tables'] ) ) { ?>

				<?php foreach ( $database['database_tables']['automator'] as $table => $table_data ) { ?>

					<tr>

						<td>
							<span class="dashicons dashicons-editor-table"></span>
							<?php echo esc_html( $table ); ?>
						</td>

						<td>
							<?php // Display error message if table data is empty. ?>
							<?php if ( ! $table_data ) { ?>

								<?php
								$view_or_table_missing_message =
									strpos( $table, '_view' )
										? ( AUTOMATOR_DATABASE_VIEWS_ENABLED
										? esc_html__( 'View does not exist', 'uncanny-automator' )
										: esc_html__( 'DB view is disabled by site administrator', 'uncanny-automator' ) )
										: esc_html__( 'Table does not exist', 'uncanny-automator' );
								?>

								<mark class="error">
									<span class="dashicons dashicons-database-remove"></span>
									<?php echo esc_html( $view_or_table_missing_message ); ?>
								</mark>

							<?php } else { ?>

								<mark class="yes">
									<span class="dashicons dashicons-database-view"></span>
									<?php
									printf(
									/* Translators: %1$f: Table size, %2$f: Index size, %3$s Engine. */
										esc_html__( 'Data: %1$.2fMB + Index: %2$.2fMB + Engine %3$s', 'uncanny-automator' ),
										esc_html( $table_data['data'] ),
										esc_html( $table_data['index'] ),
										esc_html( $table_data['engine'] )
									);
									?>
								</mark>

							<?php } ?>
						</td>

						<td>
							<?php if ( ! empty( $table_data ) ) { ?>
								<?php
								// The delete view url.
								$delete_view_url = add_query_arg(
									array(
										'nonce'  => wp_create_nonce( 'automator_db_tools' ),
										'action' => 'automator_db_tools',
										'view'   => str_replace( $wpdb->prefix, '', $table ),
										'type'   => 'drop_view',
									),
									admin_url( 'admin-ajax.php' )
								);
								?>

								<?php
								// The delete api logs url.
								$purge_table_url = add_query_arg(
									array(
										'nonce'  => wp_create_nonce( 'automator_db_tools' ),
										'action' => 'automator_db_tools_empty_api_logs',
									),
									admin_url( 'admin-ajax.php' )
								);
								?>

								<?php // Determines whether the table is a view or not. ?>
								<?php $is_view = false !== strpos( $table, '_view' ); ?>

								<?php // Determines wether the table is api logs or not. ?>
								<?php $is_api_logs = false !== str_ends_with( $table, '_api_log' ); ?>

								<?php if ( $is_api_logs && ! $is_view ) { ?>
									<uo-tooltip>
										<span
											class="tooltip-nowrap"><?php esc_html_e( 'Empty API logs tables', 'uncanny-automator' ); ?></span>
										<uo-button
											slot="target"
											color="info"
											size="small"
											href="<?php echo esc_url( $purge_table_url ); ?>"
											needs-confirmation
											confirmation-heading="<?php esc_attr_e( 'This action is irreversible', 'uncanny-automator' ); ?>"
											confirmation-content="<?php printf( esc_attr__( 'Click "Proceed" to remove data related to the Resend feature in app integration logs. It will not be possible to resend data related to previous recipe runs.', 'uncanny-automator' ), esc_attr( $table ) ); ?>"
											confirmation-button-label="<?php esc_attr_e( 'Proceed', 'uncanny-automator' ); ?>"
										>
											<uo-icon id="broom"></uo-icon>
										</uo-button>
										</span>
									</uo-tooltip>
								<?php } ?>

								<?php if ( $is_view ) { ?>
									<uo-tooltip>
										<span
											class="tooltip-nowrap"><?php esc_html_e( 'Drop view', 'uncanny-automator' ); ?></span>
										<uo-button
											slot="target"
											color="danger"
											size="small"
											href="<?php echo esc_url( $delete_view_url ); ?>"
											needs-confirmation
											confirmation-heading="<?php esc_attr_e( 'This action is irreversible', 'uncanny-automator' ); ?>"
											confirmation-content="
											<?php
											printf(
												// translators: 1: View name
												esc_attr__( 'This will drop the existing view (%s) from the database.', 'uncanny-automator' ),
												esc_attr( $table )
											);
											?>
											"
											<?php
											echo sprintf(
												// translators: 1: View name
												esc_attr__( 'This will drop the existing view (%s) from the database.', 'uncanny-automator' ),
												esc_attr( $table )
											);
											?>
											"
											confirmation-button-label="<?php esc_attr_e( 'Proceed', 'uncanny-automator' ); ?>"
										>
											<uo-icon id="trash"></uo-icon>
										</uo-button>
										</span>
									</uo-tooltip>

								<?php } ?>

							<?php } ?>

						</td>

					</tr>

				<?php } ?>

			<?php } ?>

			</tbody>
		</table>
	</div>

	<div class="uap-settings-panel-bottom">

		<div class="uap-settings-panel-bottom-left">

			<?php if ( 0 !== count( $missing_tables ) || 0 !== count( $missing_views ) ) { ?>

				<?php
				$url_repair = add_query_arg(
					array(
						'nonce'  => wp_create_nonce( 'automator_db_tools' ),
						'action' => 'automator_db_tools',
						'type'   => 'repair_tables',
					),
					admin_url( 'admin-ajax.php' )
				);
				?>

				<uo-button class="uap-spacing-right--xsmall" href="<?php echo esc_url_raw( $url_repair ); ?>">

					<uo-icon id="wand-magic-sparkles"></uo-icon>

					<?php esc_html_e( 'Repair Automator tables', 'uncanny-automator' ); ?>

				</uo-button>

			<?php } ?>

			<?php
			$url_truncate = add_query_arg(
				array(
					'nonce'  => wp_create_nonce( 'automator_db_tools' ),
					'action' => 'automator_db_tools',
					'type'   => 'purge_tables',
				),
				admin_url( 'admin-ajax.php' )
			);
			?>

			<uo-button
				color="danger"
				href="<?php echo esc_url( $url_truncate ); ?>"
				needs-confirmation
				confirmation-heading="<?php esc_attr_e( 'This action is irreversible', 'uncanny-automator' ); ?>"
				confirmation-content="<?php esc_attr_e( 'This will delete ALL recipe, trigger and action log records from your site. All recipe runs will be reset to zero for all users. Recipes will not be affected.', 'uncanny-automator' ); ?>"
				confirmation-button-label="<?php esc_attr_e( 'Reset', 'uncanny-automator' ); ?>"
			>

				<uo-icon id="trash"></uo-icon>

				<?php esc_html_e( 'Reset Automator log tables', 'uncanny-automator' ); ?>

			</uo-button>

		</div>

		<div class="uap-settings-panel-bottom-right">
			<p>
				<?php
				$size = automator_get_option( 'automator_db_size', 0 );
				if ( $size > 0 ) {
					printf(
						/* translators: %s: Database size in MB */
						esc_html_x( 'Total tables size: %s MB', 'Database tables', 'uncanny-automator' ),
						esc_html( number_format_i18n( $size, 2 ) ) // Localized number formatting
					);
				}
				?>
			</p>
		</div>

	</div>

</div>
