<?php
/**
 * Admin View: Page - Status Report.
 *
 * @package Automator
 */

$report             = Automator()->system_report->get();
$environment        = $report['environment'];
$database           = $report['database'];
$active_plugins     = $report['active_plugins'];
$inactive_plugins   = $report['inactive_plugins'];
$dropins_mu_plugins = $report['dropins_mu_plugins'];
$theme              = $report['theme'];
?>
<script>
	jQuery(function ($) {

		/**
		 * Users country and state fields
		 */
		var uoSystemStatus = {
			init: function () {
				$(document.body)
					.on('click', 'a.help_tip, a.automator-help-tip', this.preventTipTipClick)
					.on('click', 'a.debug-report', this.generateReport)
					.on('click', '#copy-for-support', this.copyReport)
					.on('aftercopy', '#copy-for-support', this.copySuccess)
					.on('aftercopyfailure', '#copy-for-support', this.copyFail);
			},

			/**
			 * Prevent anchor behavior when click on TipTip.
			 *
			 * @return {Bool}
			 */
			preventTipTipClick: function () {
				return false;
			},

			/**
			 * Generate system status report.
			 *
			 * @return {Bool}
			 */
			generateReport: function () {
				var report = '';

				$('.automator_status_table thead, .automator_status_table tbody').each(function () {
					if ($(this).is('thead')) {
						var label = $(this).find('th:eq(0)').data('export-label') || $(this).text();
						report = report + '\n### ' + label.trim() + ' ###\n\n';
					} else {
						$('tr', $(this)).each(function () {
							var label = $(this).find('td:eq(0)').data('export-label') || $(this).find('td:eq(0)').text();
							var the_name = label.trim().replace(/(<([^>]+)>)/ig, ''); // Remove HTML.

							// Find value
							var $value_html = $(this).find('td:eq(2)').clone();
							$value_html.find('.private').remove();
							$value_html.find('.dashicons-yes').replaceWith('&#10004;');
							$value_html.find('.dashicons-no-alt, .dashicons-warning').replaceWith('&#10060;');

							// Format value
							var the_value = $value_html.text().trim();
							var value_array = the_value.split(', ');

							if (value_array.length > 1) {
								// If value have a list of plugins ','.
								// Split to add new line.
								var temp_line = '';
								$.each(value_array, function (key, line) {
									temp_line = temp_line + line + '\n';
								});

								the_value = temp_line;
							}

							report = report + '' + the_name + ': ' + the_value + '\n';
						});
					}
				});

				try {
					$('#debug-report').slideDown();
					$('#debug-report').find('textarea').val('`' + report + '`').focus().select();
					$(this).fadeOut();
					return false;
				} catch (e) {
					/* jshint devel: true */
					console.log(e);
				}

				return false;
			},

			/**
			 * Copy for report.
			 *
			 * @param {Object} evt Copy event.
			 */
			copyReport: function (evt) {
				evt.preventDefault();
				// Focus TextArea.
				$("#debug-report > textarea").select();
				// Copy the TextArea contents.
				document.execCommand('copy');
				// Toggle to status.
				$('span#copy-for-support-status').toggle();
				// Automatically fades out after 750ms.
				setTimeout(function () {
					$('span#copy-for-support-status').fadeOut();
				}, 750);
			},

			/**
			 * Display a "Copied!" tip when success copying
			 */
			copySuccess: function () {
				$('#copy-for-support').tipTip({
					'attribute': 'data-tip',
					'activation': 'focus',
					'fadeIn': 50,
					'fadeOut': 50,
					'delay': 0
				}).focus();
			},

			/**
			 * Displays the copy error message when failure copying.
			 */
			copyFail: function () {
				$('.copy-error').removeClass('hidden');
				$('#debug-report').find('textarea').focus().select();
			}
		};

		uoSystemStatus.init();

		$('.automator_status_table').on('click', '.run-tool .button', function (evt) {
			evt.stopImmediatePropagation();
			return window.confirm(automator_admin_system_status.run_tool_confirmation);
		});

		$('#log-viewer-select').on('click', 'h2 a.page-title-action', function (evt) {
			evt.stopImmediatePropagation();
			return window.confirm(automator_admin_system_status.delete_log_confirmation);
		});
	});

</script>
<div class="updated automator-message inline">
	<p>
		<?php esc_html_e( 'Please copy and paste this information in your ticket when contacting support:', 'uncanny-automator' ); ?>
	</p>
	<p class="submit">
		<a href="#"
		   class="button-primary debug-report"><?php esc_html_e( 'Get system report', 'uncanny-automator' ); ?></a>
		<!--		<a class="button-secondary docs"-->
		<!--		   href=""-->
		<!--		   target="_blank">-->
		<!--			--><?php //esc_html_e( 'Understanding the status report', 'uncanny-automator' ); ?>
		<!--		</a>-->
	</p>
	<div id="debug-report">
		<textarea readonly="readonly"></textarea>
		<p class="submit">
			<button id="copy-for-support" class="button-primary" href="#"
					data-tip="<?php esc_attr_e( 'Copied!', 'uncanny-automator' ); ?>">
				<?php esc_html_e( 'Copy for support', 'uncanny-automator' ); ?>
			</button>
			<span id="copy-for-support-status" class="automator-tooltip-help-text">
				<?php esc_html_e( 'Copied to clipboard!', 'uncanny-automator' ); ?>
			</span>
		</p>
		<p class="copy-error hidden">
			<?php esc_html_e( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'uncanny-automator' ); ?>
		</p>
	</div>
</div>
<table class="automator_status_table widefat" cellspacing="0" id="status">
	<thead>
	<tr>
		<th colspan="3" data-export-label="WordPress Environment">
			<h2><?php esc_html_e( 'WordPress environment', 'uncanny-automator' ); ?></h2></th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td data-export-label="WordPress address (URL)"><?php esc_html_e( 'WordPress address (URL)', 'uncanny-automator' ); ?>
			:
		</td>
		<td class="help"><?php echo esc_html__( 'The root URL of your site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $environment['site_url'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="Site address (URL)"><?php esc_html_e( 'Site address (URL)', 'uncanny-automator' ); ?>:
		</td>
		<td class="help"><?php echo esc_html__( 'The homepage URL of your site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $environment['home_url'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="UA Version"><?php esc_html_e( 'Automator version', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The version of Automator installed on your site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $environment['version'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="REST API Path"><?php esc_html_e( 'Automator REST API path', 'uncanny-automator' ); ?>:
		</td>
		<td class="help"><?php echo esc_html__( 'The Automator REST API path on your site.', 'uncanny-automator' ); ?></td>
		<td>
			<mark class="yes"><span class="dashicons dashicons-yes"></span><code
					class="private"><?php echo esc_url_raw( site_url() . '/wp-json/' . esc_html( AUTOMATOR_REST_API_END_POINT ) ) ?></code>
			</mark>
		</td>
	</tr>
	<?php if ( is_automator_pro_active() ) { ?>
		<tr>
			<td data-export-label="Action Scheduler Version"><?php esc_html_e( 'Action Scheduler package', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'Action Scheduler package running on your site.', 'uncanny-automator' ); ?></td>
			<td>
				<?php
				if ( class_exists( 'ActionScheduler_Versions' ) && class_exists( 'ActionScheduler' ) ) {
					$version = ActionScheduler_Versions::instance()->latest_version();
					$path    = ActionScheduler::plugin_path( '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				} else {
					$version = null;
				}

				if ( ! is_null( $version ) ) {
					echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html( $version ) . ' <code class="private">' . esc_html( $path ) . '</code></mark> ';
				} else {
					echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Unable to detect the Action Scheduler package.', 'uncanny-automator' ) . '</mark>';
				}
				?>
			</td>
		</tr>
	<?php } ?>
	<?php if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) { ?>
		<tr>
			<td data-export-label="Log Directory Writable"><?php esc_html_e( 'Log directory writable', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'Several Automator extensions can write logs which makes debugging problems easier. The directory must be writable for this to happen.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td>
				<?php
				if ( $environment['log_directory_writable'] ) {
					echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> <code class="private">' . esc_html( $environment['log_directory'] ) . '</code></mark> ';
				} else {
					/* Translators: %1$s: Log directory, %2$s: Log directory constant */
					echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( 'To allow logging, make %1$s writable or define a custom %2$s.', 'uncanny-automator' ), '<code>' . esc_html( $environment['log_directory'] ) . '</code>', '<code>UA_DEBUG_LOGS_DIR</code>' ) . '</mark>';
				}
				?>
			</td>
		</tr>
	<?php } ?>
	<tr>
		<td data-export-label="WP Version"><?php esc_html_e( 'WordPress version', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The version of WordPress installed on your site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			echo '<mark class="yes">' . esc_html( $environment['wp_version'] ) . '</mark>';
			?>
		</td>
	</tr>
	<tr>
		<td data-export-label="WP Multisite"><?php esc_html_e( 'WordPress multisite', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Whether or not you have WordPress Multisite enabled.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo ( $environment['wp_multisite'] ) ? '<span class="dashicons dashicons-yes"></span>' : '&ndash;'; ?></td>
	</tr>
	<tr>
		<td data-export-label="WP Memory Limit"><?php esc_html_e( 'WordPress memory limit', 'uncanny-automator' ); ?>:
		</td>
		<td class="help"><?php echo esc_html__( 'The maximum amount of memory (RAM) that your site can use at one time.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			if ( $environment['wp_memory_limit'] < 67108864 ) {
				/* Translators: %1$s: Memory limit, %2$s: Docs link. */
				echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( '%1$s - We recommend setting memory to at least 64MB. See: %2$s', 'uncanny-automator' ), esc_html( size_format( $environment['wp_memory_limit'] ) ), '<a href="https://wordpress.org/support/article/editing-wp-config-php/#increasing-memory-allocated-to-php" target="_blank">' . esc_html__( 'Increasing memory allocated to PHP', 'uncanny-automator' ) . '</a>' ) . '</mark>';
			} else {
				echo '<mark class="yes">' . esc_html( size_format( $environment['wp_memory_limit'] ) ) . '</mark>';
			}
			?>
		</td>
	</tr>
	<tr>
		<td data-export-label="WP Debug Mode"><?php esc_html_e( 'WordPress debug mode', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Displays whether or not WordPress is in Debug Mode.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php if ( $environment['wp_debug_mode'] ) : ?>
				<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
			<?php else : ?>
				<mark class="no">&ndash;</mark>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td data-export-label="WP Cron"><?php esc_html_e( 'WordPress cron', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Displays whether or not WP Cron Jobs are enabled.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php if ( $environment['wp_cron'] ) : ?>
				<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
			<?php else : ?>
				<mark class="no">&ndash;</mark>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td data-export-label="Language"><?php esc_html_e( 'Language', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The current language used by WordPress. Default = English', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $environment['language'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="External object cache"><?php esc_html_e( 'External object cache', 'uncanny-automator' ); ?>
			:
		</td>
		<td class="help"><?php echo esc_html__( 'Displays whether or not WordPress is using an external object cache.', 'uncanny-automator' ); ?></td>
		<td>
			<?php if ( $environment['external_object_cache'] ) : ?>
				<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
			<?php else : ?>
				<mark class="no">&ndash;</mark>
			<?php endif; ?>
		</td>
	</tr>
	</tbody>
</table>
<table class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Server Environment">
			<h2><?php esc_html_e( 'Server environment', 'uncanny-automator' ); ?></h2></th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td data-export-label="Server Info"><?php esc_html_e( 'Server info', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Information about the web server that is currently hosting your site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $environment['server_info'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="PHP Version"><?php esc_html_e( 'PHP version', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The version of PHP installed on your hosting server.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			if ( version_compare( $environment['php_version'], '7.2', '>=' ) ) {
				echo '<mark class="yes">' . esc_html( $environment['php_version'] ) . '</mark>';
			} else {
				$update_link = ' <a href="https://automatorplugin.com/knowledge-base/php-version/?utm_source=uncanny_automator&utm_medium=tools_status&utm_content=update_php_version" target="_blank">' . esc_html__( 'How to update your PHP version', 'uncanny-automator' ) . '</a>';
				$class       = 'error';

				if ( version_compare( $environment['php_version'], '5.6', '<' ) ) {
					$notice = '<span class="dashicons dashicons-warning"></span> ' . __( 'Automator will not run under this version of PHP, however, it has reached end of life. We recommend using PHP version 5.6 or above for greater performance and security.', 'uncanny-automator' ) . $update_link;
				} elseif ( version_compare( $environment['php_version'], '7.2', '<' ) ) {
					$notice = __( 'We recommend using PHP version 7.2 or above for greater performance and security.', 'uncanny-automator' ) . $update_link;
					$class  = 'recommendation';
				}

				echo '<mark class="' . esc_attr( $class ) . '">' . esc_html( $environment['php_version'] ) . ' - ' . wp_kses_post( $notice ) . '</mark>';
			}
			?>
		</td>
	</tr>
	<?php if ( function_exists( 'ini_get' ) ) : ?>
		<tr>
			<td data-export-label="PHP Post Max Size"><?php esc_html_e( 'PHP post max size', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'The largest filesize that can be contained in one post.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( size_format( $environment['php_post_max_size'] ) ); ?></td>
		</tr>
		<tr>
			<td data-export-label="PHP Time Limit"><?php esc_html_e( 'PHP time limit', 'uncanny-automator' ); ?>:</td>
			<td class="help"><?php echo esc_html__( 'The amount of time (in seconds) that your site will spend on a single operation before timing out (to avoid server lockups)', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( $environment['php_max_execution_time'] ); ?></td>
		</tr>
		<tr>
			<td data-export-label="PHP Max Input Vars"><?php esc_html_e( 'PHP max input vars', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'The maximum number of variables your server can use for a single function to avoid overloads.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( $environment['php_max_input_vars'] ); ?></td>
		</tr>
		<tr>
			<td data-export-label="cURL Version"><?php esc_html_e( 'cURL version', 'uncanny-automator' ); ?>:</td>
			<td class="help"><?php echo esc_html__( 'The version of cURL installed on your server.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( $environment['curl_version'] ); ?></td>
		</tr>
	<?php endif; ?>

	<?php

	if ( $environment['mysql_version'] ) :
		?>
		<tr>
			<td data-export-label="MySQL Version"><?php esc_html_e( 'MySQL version', 'uncanny-automator' ); ?>:</td>
			<td class="help"><?php echo esc_html__( 'The version of MySQL installed on your hosting server.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td>
				<?php
				if ( version_compare( $environment['mysql_version'], '5.6', '<' ) && ! strstr( $environment['mysql_version_string'], 'MariaDB' ) ) {
					/* Translators: %1$s: MySQL version, %2$s: Recommended MySQL version. */
					echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( '%1$s - We recommend a minimum MySQL version of 5.6. See: %2$s', 'uncanny-automator' ), esc_html( $environment['mysql_version_string'] ), '<a href="https://wordpress.org/about/requirements/" target="_blank">' . esc_html__( 'WordPress requirements', 'uncanny-automator' ) . '</a>' ) . '</mark>';
				} else {
					echo '<mark class="yes">' . esc_html( $environment['mysql_version_string'] ) . '</mark>';
				}
				?>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<td data-export-label="Default Timezone is UTC"><?php esc_html_e( 'Default timezone is UTC', 'uncanny-automator' ); ?>
			:
		</td>
		<td class="help"><?php echo esc_html__( 'The default timezone for your server.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			if ( 'UTC' !== $environment['default_timezone'] ) {
				/* Translators: %s: default timezone.. */
				echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( 'Default timezone is %s - it should be UTC', 'uncanny-automator' ), esc_html( $environment['default_timezone'] ) ) . '</mark>';
			} else {
				echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
			}
			?>
		</td>
	</tr>
	<tr>
		<td data-export-label="Remote Post"><?php esc_html_e( 'Webhook post', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Automator uses this method of communication when sending webhook information to another site.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			if ( $environment['remote_post_successful'] ) {
				echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
			} else {
				/* Translators: %s: function name. */
				echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( '%s failed. Contact your hosting provider.', 'uncanny-automator' ), 'wp_remote_post()' ) . ' ' . esc_html( $environment['remote_post_response'] ) . '</mark>';
			}
			?>
		</td>
	</tr>
	<tr>
		<td data-export-label="Remote Get"><?php esc_html_e( 'Webhook get', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'Automator uses this method of communication when receiving webhook information from other sites.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			if ( $environment['remote_get_successful'] ) {
				echo '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>';
			} else {
				/* Translators: %s: function name. */
				echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( '%s failed. Contact your hosting provider.', 'uncanny-automator' ), 'wp_remote_get()' ) . ' ' . esc_html( $environment['remote_get_response'] ) . '</mark>';
			}
			?>
		</td>
	</tr>
	</tbody>
</table>
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
	<tr>
		<td data-export-label="UA Database Version"><?php esc_html_e( 'Automator database version', 'uncanny-automator' ); ?>
			:
		</td>
		<td class="help"><?php echo esc_html__( 'The database version of Automator.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $database['automator_database_version'] ); ?>
			/<?php echo esc_html( $database['automator_database_available_version'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="UA Database Views Version"><?php esc_html_e( 'Automator database VIEWS version', 'uncanny-automator' ); ?>
			:
		</td>
		<td class="help"><?php echo esc_html__( 'The database VIEWS version of Automator.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $database['automator_database_views_version'] ); ?>
			/<?php echo esc_html( $database['automator_database_available_view_version'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="UA Database Prefix"><?php esc_html_e( 'Database prefix', 'uncanny-automator' ); ?></td>
		<td class="help">&nbsp;</td>
		<td>
			<?php
			if ( strlen( $database['database_prefix'] ) > 20 ) {
				/* Translators: %1$s: Database prefix, %2$s: Docs link. */
				echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( '%1$s - We recommend using a prefix with less than 20 characters. See: %2$s', 'uncanny-automator' ), esc_html( $database['database_prefix'] ), '<a href="#" target="_blank">' . esc_html__( 'How to update your database table prefix', 'uncanny-automator' ) . '</a>' ) . '</mark>';
			} else {
				echo '<mark class="yes">' . esc_html( $database['database_prefix'] ) . '</mark>';
			}
			?>
		</td>
	</tr>

	<?php if ( ! empty( $database['database_size'] ) && ! empty( $database['database_tables'] ) ) : ?>
		<tr>
			<td><?php esc_html_e( 'Total Database Size', 'uncanny-automator' ); ?></td>
			<td class="help">&nbsp;</td>
			<td><?php printf( '%.2fMB', esc_html( $database['database_size']['data'] + $database['database_size']['index'] ) ); ?></td>
		</tr>

		<tr>
			<td><?php esc_html_e( 'Database Data Size', 'uncanny-automator' ); ?></td>
			<td class="help">&nbsp;</td>
			<td><?php printf( '%.2fMB', esc_html( $database['database_size']['data'] ) ); ?></td>
		</tr>

		<tr>
			<td><?php esc_html_e( 'Database Index Size', 'uncanny-automator' ); ?></td>
			<td class="help">&nbsp;</td>
			<td><?php printf( '%.2fMB', esc_html( $database['database_size']['index'] ) ); ?></td>
		</tr>

		<?php foreach ( $database['database_tables']['automator'] as $table => $table_data ) { ?>
			<tr>
				<td><?php echo esc_html( $table ); ?></td>
				<td class="help">&nbsp;</td>
				<td>
					<?php
					if ( ! $table_data ) {
						echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Table does not exist', 'uncanny-automator' ) . '</mark>';
					} else {
						/* Translators: %1$f: Table size, %2$f: Index size, %3$s Engine. */
						printf( esc_html__( 'Data: %1$.2fMB + Index: %2$.2fMB + Engine %3$s', 'uncanny-automator' ), esc_html( $table_data['data'] ), esc_html( $table_data['index'] ), esc_html( $table_data['engine'] ) );
					}
					?>
				</td>
			</tr>
		<?php } ?>

		<?php foreach ( $database['database_tables']['other'] as $table => $table_data ) { ?>
			<tr>
				<td><?php echo esc_html( $table ); ?></td>
				<td class="help">&nbsp;</td>
				<td>
					<?php
					/* Translators: %1$f: Table size, %2$f: Index size, %3$s Engine. */
					printf( esc_html__( 'Data: %1$.2fMB + Index: %2$.2fMB + Engine %3$s', 'uncanny-automator' ), esc_html( $table_data['data'] ), esc_html( $table_data['index'] ), esc_html( $table_data['engine'] ) );
					?>
				</td>
			</tr>
		<?php } ?>
	<?php else : ?>
		<tr>
			<td><?php esc_html_e( 'Database information:', 'uncanny-automator' ); ?></td>
			<td class="help">&nbsp;</td>
			<td>
				<?php
				esc_html_e(
					'Unable to retrieve database information. Usually, this is not a problem, and it only means that your install is using a class that replaces the WordPress database class (e.g., HyperDB) and Automator is unable to get database information.',
					'uncanny-automator'
				);
				?>
			</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>
<table class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Active Plugins (<?php echo count( $active_plugins ); ?>)">
			<h2>
				<?php
				esc_html_e( 'Active plugins', 'uncanny-automator' );
				echo ' (' . count( $active_plugins ) . ')';
				?>
			</h2></th>
	</tr>
	</thead>
	<tbody>
	<?php Automator()->system_report->output_plugins_info( $active_plugins ); ?>
	</tbody>
</table>
<table class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Inactive Plugins (<?php echo count( $inactive_plugins ); ?>)">
			<h2><?php esc_html_e( 'Inactive plugins', 'uncanny-automator' ); ?>
				(<?php echo count( $inactive_plugins ); ?>)</h2></th>
	</tr>
	</thead>
	<tbody>
	<?php Automator()->system_report->output_plugins_info( $inactive_plugins ); ?>
	</tbody>
</table>
<?php
if ( 0 < count( $dropins_mu_plugins['dropins'] ) ) :
	?>
	<table class="automator_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="3" data-export-label="Dropin Plugins (<?php echo count( $dropins_mu_plugins['dropins'] ); ?>)">
				<h2><?php esc_html_e( 'Dropin Plugins', 'uncanny-automator' ); ?>
					(<?php echo count( $dropins_mu_plugins['dropins'] ); ?>)</h2></th>
		</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $dropins_mu_plugins['dropins'] as $dropin ) {
			?>
			<tr>
				<td><?php echo wp_kses_post( $dropin['plugin'] ); ?></td>
				<td class="help">&nbsp;</td>
				<td><?php echo wp_kses_post( $dropin['name'] ); ?>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>
<?php
endif;
if ( 0 < count( $dropins_mu_plugins['mu_plugins'] ) ) :
	?>
	<table class="automator_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="3" data-export-label="Must Use Plugins (
			<?php
			echo count( $dropins_mu_plugins['mu_plugins'] );
			?>
			)"><h2>
					<?php
					esc_html_e( 'Must Use Plugins', 'uncanny-automator' );
					?>
					(
					<?php
					echo count( $dropins_mu_plugins['mu_plugins'] );
					?>
					)</h2></th>
		</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $dropins_mu_plugins['mu_plugins'] as $mu_plugin ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$plugin_name = esc_html( $mu_plugin['name'] );
			if ( ! empty( $mu_plugin['url'] ) ) {
				$plugin_name = '<a href="' . esc_url( $mu_plugin['url'] ) . '" aria-label="' . esc_attr__( 'Visit plugin homepage', 'uncanny-automator' ) . '" target="_blank">' . $plugin_name . '</a>';
			}

			?>
			<tr>
				<td>
					<?php
					echo wp_kses_post( $plugin_name );
					?>
				</td>
				<td class="help">&nbsp;</td>
				<td>
					<?php
					/* translators: %s: plugin author */
					printf( esc_html__( 'by %s', 'uncanny-automator' ), esc_html( $mu_plugin['author_name'] ) );
					echo ' &ndash; ' . esc_html( $mu_plugin['version'] );

					?>
			</tr>
			<?php
		}

		?>
		</tbody>
	</table>
<?php endif; ?>
<table class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Theme"><h2><?php esc_html_e( 'Theme', 'uncanny-automator' ); ?></h2></th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td data-export-label="Name"><?php esc_html_e( 'Name', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The name of the current active theme.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $theme['name'] ); ?></td>
	</tr>
	<tr>
		<td data-export-label="Version"><?php esc_html_e( 'Version', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The installed version of the current active theme.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td>
			<?php
			echo esc_html( $theme['version'] );
			?>
		</td>
	</tr>
	<tr>
		<td data-export-label="Author URL"><?php esc_html_e( 'Author URL', 'uncanny-automator' ); ?>:</td>
		<td class="help"><?php echo esc_html__( 'The theme developers URL.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
		<td><?php echo esc_html( $theme['author_url'] ); ?></td>
	</tr>
	<?php
	if ( $theme['is_child_theme'] ) {
		?>
		<tr>
			<td data-export-label="Child Theme"><?php esc_html_e( 'Child theme', 'uncanny-automator' ); ?>:</td>
			<td class="help"><?php echo esc_html__( 'Displays whether or not the current theme is a child theme.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td>
				<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
			</td>
		</tr>
	<?php } ?>
	<?php if ( $theme['is_child_theme'] ) : ?>
		<tr>
			<td data-export-label="Parent Theme Name"><?php esc_html_e( 'Parent theme name', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'The name of the parent theme.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( $theme['parent_name'] ); ?></td>
		</tr>
		<tr>
			<td data-export-label="Parent Theme Author URL"><?php esc_html_e( 'Parent theme author URL', 'uncanny-automator' ); ?>
				:
			</td>
			<td class="help"><?php echo esc_html__( 'The parent theme developers URL.', 'uncanny-automator' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></td>
			<td><?php echo esc_html( $theme['parent_author_url'] ); ?></td>
		</tr>
	<?php endif ?>
	</tbody>
</table>
<table class="automator_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="3" data-export-label="Status report information">
			<h2>
				<?php esc_html_e( 'Status report information', 'uncanny-automator' ); ?>

				<!--help text-->
				<span class="automator-tooltip-help-wrap">
					<span class="dashicons dashicons-editor-help"></span>
					<span class="automator-tooltip-help-text-wrap">
						<span class="automator-tooltip-help-text">
							<?php echo esc_html__( 'This section shows information about this status report.', 'uncanny-automator' ); ?>
						</span>
					</span>
				</span>
				<!--help text end-->

			</h2>
		</th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td data-export-label="Generated at"><?php esc_html_e( 'Generated at', 'uncanny-automator' ); ?>:</td>
		<td class="help">&nbsp;</td>
		<td><?php echo esc_html( current_time( 'Y-m-d H:i:s P' ) ); ?></td>

	</tr>
	</tbody>
</table>
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
