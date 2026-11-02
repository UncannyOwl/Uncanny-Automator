<?php
/**
 * Model Context Protocol - Tools.
 *
 * @since 7.6
 * @package Uncanny_Automator
 *
 * Variables:
 * $tool_groups Tool presentation data grouped by enforced scope.
 * $tool_counts Tool counts for the summary.
 * $load_error  Whether the registry could not be loaded.
 * $database_access_enabled Whether the owner consented to database access.
 * $database_extra_tables Tables outside the site prefix explicitly approved by the owner.
 * $database_table_prefix The current site's database table prefix.
 * $database_tables_notice Result of the last submitted table list.
 * $can_manage_tools Whether this administrator may change tool availability.
 * $database_consent_allowed Whether this administrator may grant database access (super admin on multisite).
 * $file_reading_enabled Whether connected AI apps may read the site's code and files.
 * $file_reading_grant_allowed Whether this administrator may turn file reading back on or remove an entry (super admin on multisite).
 * $file_reading_exclusions The files and folders the owner added, one entry each.
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

require Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/operation-rows.php' );

?>

<div class="uap-settings-panel automator-mcp-settings automator-mcp-tools">
	<div class="uap-settings-panel-top">
		<div class="uap-settings-panel-title">
			<?php echo esc_html_x( 'Site tools', 'settings panel title', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-settings-panel-content">
			<div>
				<p class="uap-spacing-top--none">
					<?php
					printf(
						/* translators: %1$s opens the Uncanny Automator website link and %2$s closes it. */
						esc_html_x( 'Choose which site tools connected AI apps can use through %1$sUncanny Automator%2$s.', 'MCP tools introduction', 'uncanny-automator' ),
						'<a href="' . esc_url( Hosted_App::connect_url() ) . '">',
						'</a>'
					);
					?>
				</p>
				<p><strong><?php echo esc_html_x( 'Applies to:', 'MCP settings label', 'uncanny-automator' ); ?></strong> <?php echo is_multisite() && ! is_main_site() ? esc_html_x( 'Connected AI apps. Site management, database table limits and this subsite’s database access also affect Uncanny Agent.', 'MCP subsite settings help', 'uncanny-automator' ) : esc_html_x( 'Connected AI apps. Site management and database table limits also affect Uncanny Agent.', 'MCP settings help', 'uncanny-automator' ); ?></p>


				<p>
				<?php
					printf(
						/* translators: %1$s opens the WordPress abilities settings link and %2$s closes it. */
						esc_html_x( 'Manage abilities from Automator, WordPress and other plugins under %1$sWordPress abilities%2$s.', 'MCP tools abilities link', 'uncanny-automator' ),
						'<a href="' . esc_url( Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'wordpress-abilities' ) ) . '">',
						'</a>'
					);
					?>
				</p>
				<?php if ( ! $load_error ) { ?>
					<div class="automator-mcp-tool-counts uap-spacing-top">
						<?php if ( $tool_counts['total'] > 0 ) { ?>
							<?php
							$count_label = sprintf(
							/* translators: %d Number of MCP tools. */
								esc_attr( _n( '%d tool', '%d tools', $tool_counts['total'], 'uncanny-automator' ) ),
								absint( $tool_counts['total'] )
							);
							?>
							<uas-chip size="sm" color="neutral" filled label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
						<?php } ?>
						<?php if ( $tool_counts['read'] > 0 ) { ?>
							<?php
							$count_label = sprintf(
							/* translators: %d Number of read-only MCP tools. */
								esc_attr_x( '%d read-only', 'MCP read tool count', 'uncanny-automator' ),
								absint( $tool_counts['read'] )
							);
							?>
							<uas-chip size="sm" color="info" filled label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
						<?php } ?>
						<?php if ( $tool_counts['write'] > 0 ) { ?>
							<?php
							$count_label = sprintf(
							/* translators: %d Number of MCP tools that can make changes. */
								esc_attr_x( '%d can make changes', 'MCP write tool count', 'uncanny-automator' ),
								absint( $tool_counts['write'] )
							);
							?>
							<uas-chip size="sm" color="warning" filled label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
						<?php } ?>
						<?php if ( $tool_counts['variable'] > 0 ) { ?>
							<?php
							$count_label = sprintf(
							/* translators: %d Number of MCP tools whose access depends on what they run. */
								esc_attr_x( '%d depend on the operation', 'MCP variable tool count', 'uncanny-automator' ),
								absint( $tool_counts['variable'] )
							);
							?>
							<uas-chip size="sm" color="neutral" filled label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
						<?php } ?>
					</div>
				<?php } ?>
				<details class="automator-mcp-tools-help uap-spacing-top">
					<summary><?php echo esc_html_x( 'How tool access works', 'MCP tools help summary', 'uncanny-automator' ); ?></summary>
					<p class="uap-spacing-top--small uap-spacing-bottom--none"><?php echo esc_html_x( 'An app can use a tool only when it is on and the app has the required access. Action permissions, ability settings and database consent still apply.', 'MCP tools help', 'uncanny-automator' ); ?></p>
				</details>
			</div>

			<?php if ( $load_error ) { ?>
				<uo-alert type="error" class="uap-spacing-top">
					<?php echo esc_html_x( 'Site tools could not be loaded. Refresh the page to try again.', 'MCP tools load error', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php } else { ?>
				<div id="automator-mcp-tool-toggle-alert" class="uap-spacing-top" role="status" aria-live="polite"></div>
				<?php if ( 'saved' === $database_tables_notice ) { ?>
					<uo-alert type="success" role="status"><?php echo esc_html_x( 'The table list was saved.', 'MCP database extra tables saved', 'uncanny-automator' ); ?></uo-alert>
				<?php } elseif ( 'mcp_database_tables_invalid' === $database_tables_notice ) { ?>
					<uo-alert type="error" role="alert"><?php echo esc_html_x( 'The list was not saved. Enter up to 100 exact table names, one per line. Each name can have up to 64 letters, numbers or underscores.', 'MCP database extra tables invalid', 'uncanny-automator' ); ?></uo-alert>
				<?php } elseif ( is_string( $database_tables_notice ) && '' !== $database_tables_notice ) { ?>
					<uo-alert type="error" role="alert"><?php echo esc_html_x( 'The list could not be saved. Refresh the page, check your permissions and try again.', 'MCP database extra tables error', 'uncanny-automator' ); ?></uo-alert>
				<?php } ?>
				<?php if ( ! $can_manage_tools ) { ?>
					<uo-alert type="info" class="uap-spacing-top"><?php echo esc_html_x( 'You do not have permission to manage these settings. Contact a site administrator.', 'MCP tools administrator required', 'uncanny-automator' ); ?></uo-alert>
				<?php } ?>

				<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top uap-spacing-bottom"><uas-chip class="uap-spacing-right--small" size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Saves automatically', 'MCP saving status', 'uncanny-automator' ); ?>"></uas-chip> <span><?php echo esc_html_x( 'Applies to new requests.', 'MCP saving help', 'uncanny-automator' ); ?></span></div>

				<section class="uap-spacing-top--big" aria-labelledby="automator-mcp-read-heading">
					<header>
						<div role="heading" aria-level="2" id="automator-mcp-read-heading" class="uap-settings-panel-content-subtitle uap-spacing-top--none uap-spacing-bottom--none"><?php echo esc_html_x( 'Read-only tools', 'MCP read tools heading', 'uncanny-automator' ); ?></div>
						<p><?php echo esc_html_x( 'Read site information without changing site data. Returned information may include sensitive data.', 'MCP read tools description', 'uncanny-automator' ); ?></p>
					</header>
					<?php $render_operations( $tool_groups['read'] ); ?>
					</section>
				<section class="uap-spacing-top--big" aria-labelledby="automator-mcp-access-heading">
					<div role="heading" aria-level="2" id="automator-mcp-access-heading" class="uap-settings-panel-content-subtitle"><?php echo esc_html_x( 'Additional access controls', 'MCP access controls heading', 'uncanny-automator' ); ?></div>
					<p><?php echo esc_html_x( 'These controls limit database tools and code or file reading. Individual tool switches still apply. Access buttons apply immediately; extra table names and file exclusions have their own save buttons.', 'MCP settings help', 'uncanny-automator' ); ?></p>
					<uas-accordion class="uap-spacing-top--small">
						<uas-accordion-item id="automator-mcp-database-settings">
							<div slot="summary" class="automator-mcp-database-summary" id="automator-mcp-database-summary">
								<strong><?php echo esc_html_x( 'Database access', 'MCP database access heading', 'uncanny-automator' ); ?></strong>
								<uas-chip size="sm" color="neutral" filled label="<?php echo $database_access_enabled ? esc_attr_x( 'On', 'MCP database access state', 'uncanny-automator' ) : esc_attr_x( 'Off', 'MCP database access state', 'uncanny-automator' ); ?>"></uas-chip>
							</div>
							<div id="automator-mcp-database-details">
								<div id="automator-mcp-database-alert" class="uap-spacing-bottom" role="alert"></div>
								<p class="uap-spacing-top--none"><?php echo esc_html_x( 'Lets connected AI apps look up your site’s tables and records to answer your questions. These tools only read; they never change records.', 'MCP database access body', 'uncanny-automator' ); ?></p>
								<?php if ( is_multisite() && ! is_main_site() ) { ?>
									<p><?php echo esc_html_x( 'Database access is off by default on a network subsite, including for Uncanny Agent. A super administrator can enable it.', 'MCP database subsite default', 'uncanny-automator' ); ?></p>
								<?php } else { ?>
									<p><?php echo esc_html_x( 'Database access is on by default.', 'MCP database default', 'uncanny-automator' ); ?></p>
								<?php } ?>
								<p><?php echo esc_html_x( 'By default, tools can read this site’s tables that start with its table prefix. Tables of another WordPress installation that shares this database are excluded, and so are database views. On a single site, you can approve exact tables or views below. On a multisite network, shared tables also require a super administrator.', 'MCP database access help', 'uncanny-automator' ); ?></p>
								<p><?php echo esc_html_x( 'If Automator cannot verify which tables are available, ask your hosting provider to check schema-wide SELECT access for the WordPress database user. On a single site, you can still approve exact table or view names below.', 'MCP database visibility help', 'uncanny-automator' ); ?></p>
								<p>
									<strong><?php echo esc_html_x( 'Good to know:', 'MCP database consent warning heading', 'uncanny-automator' ); ?></strong>
									<?php echo esc_html_x( 'Any AI app you connect with read access, such as Claude or ChatGPT, can use these tools. Results can include personal details, private content or settings that plugins store. Automator hides known secret fields, but it can’t recognize every private value. If you’d rather keep your data out of AI apps, turn database access off here.', 'MCP database consent warning', 'uncanny-automator' ); ?>
								</p>
								<?php if ( $can_manage_tools ) { ?>
									<div class="automator-mcp-database-actions uap-spacing-top">
										<?php if ( ! $database_access_enabled && ! $database_consent_allowed ) { ?>
											<p><?php echo esc_html_x( "On a multisite network, only a super administrator can enable database access.", 'MCP database consent multisite restriction', 'uncanny-automator' ); ?></p>
										<?php } elseif ( ! $database_access_enabled ) { ?>
											<label class="automator-mcp-database-consent" for="automator-mcp-database-consent">
												<input type="checkbox" id="automator-mcp-database-consent">
												<span><?php echo esc_html_x( 'I understand that connected AI apps will be able to read database information.', 'MCP database consent acknowledgement', 'uncanny-automator' ); ?></span>
											</label>
										<?php } else { ?>
											<p><?php echo esc_html_x( 'You can turn database access off here at any time. AI apps then can’t read the database, and the tool switches above still apply.', 'MCP database access enabled explanation', 'uncanny-automator' ); ?></p>
										<?php } ?>
										<?php if ( $database_access_enabled || $database_consent_allowed ) { ?>
										<uo-button
											id="automator-mcp-database-access"
											data-enabled="<?php echo $database_access_enabled ? '1' : '0'; ?>"
											<?php echo $database_access_enabled ? '' : 'disabled'; ?>
											type="button"
											size="small"
											color="secondary"
										>
											<?php echo $database_access_enabled ? esc_html_x( 'Disable database access', 'MCP database revoke button', 'uncanny-automator' ) : esc_html_x( 'Enable database access', 'MCP database consent button', 'uncanny-automator' ); ?>
										</uo-button>
										<?php } ?>
									</div>
								<?php } ?>
								<?php if ( ! is_multisite() ) { ?>
									<div class="uap-spacing-top--big">
										<label for="automator-mcp-database-extra-tables"><strong><?php echo esc_html_x( 'Additional database tables', 'MCP database extra tables heading', 'uncanny-automator' ); ?></strong></label>
										<p><?php echo esc_html_x( 'Add exact table or database view names below only if you want to share them. A database view is a saved query and may expose data from tables that are otherwise excluded. Leave the list empty to keep the default.', 'MCP database extra tables help', 'uncanny-automator' ); ?></p>
										<p><?php /* translators: %s is the site's database table prefix. */ printf( esc_html_x( 'This site’s table prefix is %s.', 'MCP database table prefix', 'uncanny-automator' ), '<uas-chip size="sm" color="neutral" label="' . esc_attr( $database_table_prefix ) . '"></uas-chip>' ); ?></p>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Settings_Model_Context_Protocol_Tools::DATABASE_TABLES_ACTION ); ?>">
											<?php wp_nonce_field( Admin_Settings_Model_Context_Protocol_Tools::DATABASE_TABLES_ACTION ); ?>
											<textarea id="automator-mcp-database-extra-tables" name="tables" class="large-text" rows="5" spellcheck="false" <?php disabled( ! $can_manage_tools ); ?>><?php echo esc_textarea( implode( "\n", $database_extra_tables ) ); ?></textarea>
											<p><?php echo esc_html_x( 'Enter up to 100 exact table names, one per line. Each name can have up to 64 letters, numbers or underscores.', 'MCP database extra tables instructions', 'uncanny-automator' ); ?></p>
											<?php
											if ( $can_manage_tools ) {
												?>
												<button type="submit" class="button button-secondary"><?php echo esc_html_x( 'Save table list', 'MCP database extra tables save', 'uncanny-automator' ); ?></button><?php } ?>
										</form>
									</div>
								<?php } ?>
							</div>
						</uas-accordion-item>
						<uas-accordion-item id="automator-mcp-file-reading-settings">
							<div slot="summary" class="automator-mcp-database-summary" id="automator-mcp-file-reading-summary">
								<strong><?php echo esc_html_x( 'Code and file reading', 'MCP file reading heading', 'uncanny-automator' ); ?></strong>
								<uas-chip size="sm" color="neutral" filled label="<?php echo $file_reading_enabled ? esc_attr_x( 'On', 'MCP file reading state', 'uncanny-automator' ) : esc_attr_x( 'Off', 'MCP file reading state', 'uncanny-automator' ); ?>"></uas-chip>
							</div>
							<div id="automator-mcp-file-reading-details">
								<div id="automator-mcp-file-reading-alert" class="uap-spacing-bottom" role="alert"></div>
								<p class="uap-spacing-top--none"><?php echo esc_html_x( 'Lets connected AI apps read your site’s code and files, so they can find the cause of errors and check that WordPress’s own files are unchanged.', 'MCP file reading body', 'uncanny-automator' ); ?></p>
								<p><?php echo esc_html_x( 'These tools can search and read files, but never change them. Code and file reading is on by default.', 'MCP file reading help', 'uncanny-automator' ); ?></p>
								<?php if ( is_multisite() ) { ?>
									<p><?php echo esc_html_x( 'The sites of this network share their files, so these choices apply to every site.', 'MCP file reading multisite scope', 'uncanny-automator' ); ?></p>
								<?php } ?>
								<?php if ( $can_manage_tools ) { ?>
									<div class="automator-mcp-database-actions uap-spacing-top">
										<?php if ( ! $file_reading_enabled && ! $file_reading_grant_allowed ) { ?>
											<p><?php echo esc_html_x( 'On a multisite network, only a super administrator can turn code and file reading back on.', 'MCP file reading multisite restriction', 'uncanny-automator' ); ?></p>
										<?php } elseif ( ! $file_reading_enabled ) { ?>
											<p><?php echo esc_html_x( 'AI apps can’t read your site’s code and files while this is off.', 'MCP file reading disabled explanation', 'uncanny-automator' ); ?></p>
										<?php } else { ?>
											<p><?php echo esc_html_x( 'You can turn code and file reading off here at any time.', 'MCP file reading enabled explanation', 'uncanny-automator' ); ?></p>
										<?php } ?>
										<?php if ( $file_reading_enabled || $file_reading_grant_allowed ) { ?>
										<uo-button
											id="automator-mcp-file-reading"
											data-enabled="<?php echo $file_reading_enabled ? '1' : '0'; ?>"
											type="button"
											size="small"
											color="secondary"
										>
											<?php echo $file_reading_enabled ? esc_html_x( 'Turn off code and file reading', 'MCP file reading revoke button', 'uncanny-automator' ) : esc_html_x( 'Turn on code and file reading', 'MCP file reading grant button', 'uncanny-automator' ); ?>
										</uo-button>
										<?php } ?>
									</div>
								<?php } ?>
								<div class="uap-spacing-top--big">
									<p><strong><?php echo esc_html_x( 'Protected by default', 'MCP file reading default protections heading', 'uncanny-automator' ); ?></strong></p>
									<p><?php echo esc_html_x( 'Built-in rules block access to the contents of known sensitive files and folders, including:', 'MCP file reading default protections introduction', 'uncanny-automator' ); ?></p>
									<ul class="automator-mcp-file-reading-defaults">
										<li><?php echo esc_html_x( 'Configuration and credentials: wp-config.php and its copies, .env files, .htpasswd and known cloud credential files.', 'MCP file reading protected configuration examples', 'uncanny-automator' ); ?></li>
										<li><?php echo esc_html_x( 'Private keys and password stores: .ssh folders, .pem and .key files, and .kdbx password databases.', 'MCP file reading protected key examples', 'uncanny-automator' ); ?></li>
										<li><?php echo esc_html_x( 'Database dumps, archives and known backup folders: .sql, .zip and .tar files, updraft and ai1wm-backups.', 'MCP file reading protected backup examples', 'uncanny-automator' ); ?></li>
										<li><?php echo esc_html_x( 'Other protected folders: .git, .svn, uploads/private, wflogs and wp-rocket-config.', 'MCP file reading protected folder examples', 'uncanny-automator' ); ?></li>
									</ul>
									<p><?php echo esc_html_x( 'PDFs and other files detected as binary cannot be displayed or searched as text. File names and basic details, such as size, can still be listed.', 'MCP file reading binary content and metadata explanation', 'uncanny-automator' ); ?></p>
									<p><?php echo esc_html_x( 'File contents are also checked for known credential patterns, but these checks do not detect every secret. Logs such as debug.log and other readable text files may contain private information. Add any other files or folders you want to protect below.', 'MCP file reading protection limits', 'uncanny-automator' ); ?></p>
								</div>
								<div class="automator-mcp-file-reading-exclusions uap-spacing-top--big">
									<label for="automator-mcp-file-reading-exclusions"><strong><?php echo esc_html_x( 'Additional files and folders to block', 'MCP file reading exclusions label', 'uncanny-automator' ); ?></strong></label>
									<p id="automator-mcp-file-reading-exclusions-defaults"><?php echo esc_html_x( 'Your list adds to the built-in protections; it cannot turn them off. Leaving this list empty keeps the default protections in place. AI apps can still see a blocked file’s name, but cannot read its contents.', 'MCP file reading exclusions default note', 'uncanny-automator' ); ?></p>
									<p id="automator-mcp-file-reading-exclusions-help" class="uap-spacing-top--small"><?php echo esc_html_x( 'Add one per line. A name, such as “invoices” or “*.csv”, matches every file or folder with that name. A path, such as “wp-content/uploads/invoices”, matches that folder and everything in it.', 'MCP file reading exclusions help', 'uncanny-automator' ); ?></p>
									<?php if ( $can_manage_tools && ! $file_reading_grant_allowed ) { ?>
										<p><?php echo esc_html_x( 'On a multisite network, only a super administrator can remove an entry from this list.', 'MCP file reading exclusions multisite restriction', 'uncanny-automator' ); ?></p>
									<?php } ?>
									<textarea
										id="automator-mcp-file-reading-exclusions"
										class="automator-mcp-file-reading-exclusions-field"
										rows="5"
										placeholder="<?php echo esc_attr( "invoices\n*.csv\nwp-content/uploads/invoices" ); ?>"
										spellcheck="false"
										autocomplete="off"
										aria-describedby="automator-mcp-file-reading-exclusions-defaults automator-mcp-file-reading-exclusions-help"
										<?php echo $can_manage_tools ? '' : 'disabled'; ?>
									><?php echo esc_textarea( implode( "\n", $file_reading_exclusions ) ); ?></textarea>
									<?php if ( $can_manage_tools ) { ?>
										<div class="automator-mcp-file-reading-exclusions-actions uap-spacing-top--small">
											<uo-button id="automator-mcp-file-reading-exclusions-save" type="button" size="small" color="secondary">
												<?php echo esc_html_x( 'Save list', 'MCP file reading exclusions save button', 'uncanny-automator' ); ?>
											</uo-button>
											<span id="automator-mcp-file-reading-exclusions-status" role="status" aria-live="polite"></span>
										</div>
									<?php } ?>
								</div>
							</div>
						</uas-accordion-item>
					</uas-accordion>
				</section>

				<?php if ( ! empty( $tool_groups['write'] ) ) { ?>
				<section class="uap-spacing-top--big" aria-labelledby="automator-mcp-write-heading">
					<header>
						<div role="heading" aria-level="2" id="automator-mcp-write-heading" class="uap-settings-panel-content-subtitle uap-spacing-top--none uap-spacing-bottom--none"><?php echo esc_html_x( 'Tools that make changes', 'MCP write tools heading', 'uncanny-automator' ); ?></div>
						<p><?php echo esc_html_x( 'Create, update, run or remove items. These tools require Read and make changes access.', 'MCP write tools description', 'uncanny-automator' ); ?></p>
					</header>
					<?php $render_operations( $tool_groups['write'] ); ?>
				</section>

				<?php } ?>

				<?php if ( ! empty( $tool_groups['variable'] ) ) { ?>
					<section class="uap-spacing-top--big" aria-labelledby="automator-mcp-variable-heading">
						<header>
							<div role="heading" aria-level="2" id="automator-mcp-variable-heading" class="uap-settings-panel-content-subtitle uap-spacing-top--none uap-spacing-bottom--none"><?php echo esc_html_x( 'Tools that can read or make changes', 'MCP variable tools heading', 'uncanny-automator' ); ?></div>
							<p><?php echo esc_html_x( 'These tools run other operations or abilities. Read-only operations need Read only access; operations that make changes need Read and make changes access. Each operation’s permissions still apply.', 'MCP variable tools description', 'uncanny-automator' ); ?></p>
						</header>
						<?php $render_operations( $tool_groups['variable'] ); ?>
					</section>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
</div>
