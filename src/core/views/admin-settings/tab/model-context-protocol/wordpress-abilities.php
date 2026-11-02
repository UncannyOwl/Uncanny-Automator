<?php
/**
 * MCP settings: WordPress abilities sub-tab view.
 *
 * Variables prepared by Admin_Settings_Model_Context_Protocol_WordPress_Abilities::tab_output():
 *
 * @var array<int,array{slug:string,name:string,abilities:array}> $groups         Abilities grouped by the plugin that registered them.
 * @var array{abilities:int,on:int}                               $summary        Counts for the page header.
 * @var bool                                                      $supported      Whether this WordPress has the Abilities API.
 * @var bool                                                      $load_error     Whether the list could not be built.
 * @var bool                                                      $dispatcher_off Whether the Run a WordPress ability tool is switched off.
 * @var string                                                    $tools_url      Site tools settings URL.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$core_descriptions = array(
	'core/get-environment-info' => esc_html_x( 'Read this site\'s WordPress version, PHP environment and database server information for troubleshooting.', 'MCP core ability purpose', 'uncanny-automator' ),
	'core/get-site-info'        => esc_html_x( 'Read the site information configured in WordPress.', 'MCP core ability purpose', 'uncanny-automator' ),
	'core/get-user-info'        => esc_html_x( 'Read profile details for the WordPress user authenticated for the request.', 'MCP core ability purpose', 'uncanny-automator' ),
);

// Behaviour badge from the provider's annotations. An ability that declares
// nothing is labelled as such rather than guessed either way.
$render_behavior = static function ( array $annotations ) {
	if ( true === $annotations['readonly'] ) {
		?>
		<uas-chip size="sm" color="info" filled label="<?php echo esc_attr_x( 'Read only', 'MCP ability behaviour', 'uncanny-automator' ); ?>"></uas-chip>
		<?php
		return;
	}

	if ( false === $annotations['readonly'] ) {
		?>
		<uas-chip size="sm" color="warning" filled label="<?php echo esc_attr_x( 'Can make changes', 'MCP ability behaviour', 'uncanny-automator' ); ?>"></uas-chip>
		<?php
	} else {
		?>
		<uas-chip size="sm" label="<?php echo esc_attr_x( 'Behavior not specified', 'MCP ability behaviour', 'uncanny-automator' ); ?>"></uas-chip>
		<?php
	}

	if ( true === $annotations['destructive'] ) {
		?>
		<uas-chip size="sm" color="error" filled label="<?php echo esc_attr_x( 'May make permanent changes', 'MCP ability behaviour', 'uncanny-automator' ); ?>"></uas-chip>
		<?php
	}
};
?>
<div class="uap-settings-panel automator-mcp-settings automator-mcp-wp-abilities">
	<div class="uap-settings-panel-top">
		<div class="uap-settings-panel-title" role="heading" aria-level="1">
			<?php echo esc_html_x( 'WordPress abilities', 'settings panel title', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-settings-panel-content">
			<div class="uap-settings-panel-content-paragraph">
				<?php echo esc_html_x( 'Choose which tasks AI apps can perform using abilities provided by WordPress, Automator and other plugins.', 'MCP WordPress abilities introduction', 'uncanny-automator' ); ?>
			</div>

			<p><strong><?php echo esc_html_x( 'Applies to:', 'MCP settings label', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Each provider shows whether these controls also affect Uncanny Agent.', 'MCP settings help', 'uncanny-automator' ); ?></p>


			<?php if ( ! $supported ) { ?>
				<uo-alert class="uap-spacing-top" type="info">
				<?php echo esc_html_x( 'The WordPress Abilities API is not available on this site.', 'MCP WordPress abilities unsupported', 'uncanny-automator' ); ?>
				</uo-alert>
			<?php } else { ?>
				<?php if ( $load_error ) { ?>
					<uo-alert class="uap-spacing-top" type="error"><?php echo esc_html_x( 'Some abilities could not be loaded. Refresh the page to try again.', 'MCP WordPress abilities load error', 'uncanny-automator' ); ?></uo-alert>
				<?php } ?>
				<div class="automator-mcp-tool-counts uap-spacing-bottom">
				<?php if ( $summary['abilities'] > 0 ) { ?>
						<?php
						$count_label = sprintf(
						/* translators: %d Number of abilities. */
							esc_attr( _n( '%d ability', '%d abilities', $summary['abilities'], 'uncanny-automator' ) ),
							absint( $summary['abilities'] )
						);
						?>
						<uas-chip size="sm" color="info" filled label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
				<?php } ?>
					<?php
					$count_label = sprintf(
					/* translators: %d Number of abilities switched on. */
						esc_attr_x( '%d on', 'MCP WordPress abilities on count', 'uncanny-automator' ),
						absint( $summary['on'] )
					);
					?>
					<uas-chip size="sm" color="success" filled id="automator-mcp-wp-abilities-on" <?php echo $summary['on'] > 0 ? '' : 'hidden'; ?> label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
				</div>


				<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top uap-spacing-bottom"><uas-chip class="uap-spacing-right--small" size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Saves automatically', 'MCP saving status', 'uncanny-automator' ); ?>"></uas-chip> <span><?php echo esc_html_x( 'Applies to new requests.', 'MCP saving help', 'uncanny-automator' ); ?></span></div>
				<div id="automator-mcp-wp-abilities-status" class="automator-mcp-copy-status" role="status" aria-live="polite"></div>
				<div id="automator-mcp-wp-abilities-alert"></div>

				<?php if ( empty( $groups ) ) { ?>
					<div class="uap-settings-panel-content-paragraph">
						<strong><?php echo esc_html_x( 'No WordPress abilities found.', 'MCP WordPress abilities empty', 'uncanny-automator' ); ?></strong>
					<?php echo esc_html_x( 'Abilities appear here when WordPress or an active plugin provides them.', 'MCP WordPress abilities empty help', 'uncanny-automator' ); ?>
					</div>
				<?php } else { ?>
					<uas-accordion>
					<?php foreach ( $groups as $group ) { ?>
						<?php
						$on_count      = 0;
						$managed_count = 0;
						foreach ( $group['abilities'] as $row ) {
							if ( $row['eligible'] && $row['enabled'] ) {
								++$on_count;
							}
							if ( ! $row['eligible'] ) {
								++$managed_count;
							}
						}
						?>
							<uas-accordion-item data-provider="<?php echo esc_attr( $group['slug'] ); ?>">
								<div slot="summary" class="automator-mcp-ability-summary automator-mcp-wp-ability-summary">
									<span class="automator-mcp-ability-title" role="heading" aria-level="2">
										<span><strong><?php echo esc_html( $group['name'] ); ?></strong><uas-chip class="automator-mcp-scope" size="sm" color="neutral" filled label="<?php echo isset( $group['operation_groups'] ) ? esc_attr_x( 'Connected AI apps only', 'MCP provider scope', 'uncanny-automator' ) : esc_attr_x( 'Connected AI apps and Uncanny Agent', 'MCP provider scope', 'uncanny-automator' ); ?>"></uas-chip></span>
									</span>
									<?php
									$count_label = sprintf(
									/* translators: %d Number of abilities switched on in the group. */
										esc_attr_x( '%d on', 'MCP WordPress abilities group state', 'uncanny-automator' ),
										absint( $on_count )
									);
									?>
									<uas-chip size="sm" color="success" class="automator-mcp-ability-state" <?php echo $on_count > 0 ? '' : 'hidden'; ?> label="<?php echo esc_attr( $count_label ); ?>"></uas-chip>
									<span class="automator-mcp-ability-count">
						<?php
						printf(
						/* translators: %d Number of abilities in the group. */
							esc_html( _n( '%d ability', '%d abilities', count( $group['abilities'] ), 'uncanny-automator' ) ),
							absint( count( $group['abilities'] ) )
						);
						?>
									</span>
								</div>
								<?php if ( isset( $group['operation_groups'] ) ) { ?>
									<?php require Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/native-abilities.php' ); ?>
								<?php } else { ?>
								<div>
									<p><?php echo esc_html_x( 'This provider’s abilities are on by default. Turn off any you do not want AI apps to use.', 'MCP WordPress abilities supporting text', 'uncanny-automator' ); ?></p>
									<?php if ( $managed_count > 0 ) { ?>
									<p class="automator-mcp-wp-provider-note">
										<?php
										printf(
										/* translators: %1$s: plugin name */
											esc_html_x( '%1$s keeps the abilities marked Unavailable away from AI apps, so you can’t turn them on here. They become available as soon as %1$s allows AI apps to use them, for example after a plugin update.', 'MCP WordPress ability managed by plugin explanation', 'uncanny-automator' ),
											esc_html( $group['name'] )
										);
										?>
									</p>
									<?php } ?>

									<?php if ( $dispatcher_off ) { ?>
					<uo-alert class="uap-spacing-bottom" type="warning" heading="<?php echo esc_attr_x( 'Run a WordPress ability is off.', 'MCP WordPress abilities dispatcher off heading', 'uncanny-automator' ); ?>">
										<?php
										printf(
										/* translators: %1$s opens the link to the Site tools tab and %2$s closes it. */
											esc_html_x( 'Turn on Run a WordPress ability in %1$sSite tools%2$s so connected AI apps can use this provider’s enabled abilities.', 'MCP WordPress abilities dispatcher off', 'uncanny-automator' ),
											'<a href="' . esc_url( $tools_url ) . '">',
											'</a>'
										);
										?>
					</uo-alert>
				<?php } ?>

								</div>
								<div class="automator-mcp-ability-actions">
									<?php foreach ( $group['abilities'] as $row ) { ?>
										<div class="automator-mcp-ability-row">
											<div class="automator-mcp-ability-row-label">
												<div class="automator-mcp-wp-ability-heading">
													<strong class="automator-mcp-ability-sentence"><?php echo esc_html( $row['label'] ); ?></strong>
													<div class="automator-mcp-wp-ability-behavior"><?php $render_behavior( $row['annotations'] ); ?></div>
												</div>
										<?php if ( '' !== $row['description'] && $row['description'] !== $row['label'] ) { ?>
													<span class="automator-mcp-ability-description"><?php echo esc_html( $core_descriptions[ $row['name'] ] ?? $row['description'] ); ?></span>
										<?php } ?>
												<details class="automator-mcp-details">
												<summary><?php echo esc_html_x( 'Technical details', 'MCP ability details', 'uncanny-automator' ); ?></summary>
												<p><code dir="ltr"><?php echo esc_html( $row['name'] ); ?></code></p>
													<?php if ( isset( $core_descriptions[ $row['name'] ] ) ) { ?>
													<p><?php echo esc_html( $row['description'] ); ?></p>
													<?php } ?>
											</details>
											</div>
											<div class="automator-mcp-ability-row-controls automator-mcp-wp-ability-access">
										<?php if ( $row['eligible'] ) { ?>
													<uo-switch
														class="automator-mcp-wp-ability-toggle"
														data-ability="<?php echo esc_attr( $row['name'] ); ?>"
														data-title="<?php echo esc_attr( $row['label'] ); ?>"
														data-provider="<?php echo esc_attr( $group['slug'] ); ?>"
														aria-label="<?php printf( /* translators: %s Ability name. */ esc_attr_x( 'Allow %s', 'MCP WordPress ability switch accessible name', 'uncanny-automator' ), esc_attr( $row['label'] ) ); ?>"
														status-label="<?php echo esc_attr_x( 'On', 'MCP WordPress ability switch on', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Off', 'MCP WordPress ability switch off', 'uncanny-automator' ); ?>"
														status-label-position="left"
											<?php echo $row['enabled'] ? 'checked' : ''; ?>
													></uo-switch>
							<?php } else { ?>
													<uas-chip size="sm" color="info" filled label="<?php echo esc_attr_x( 'Unavailable', 'MCP WordPress ability access state', 'uncanny-automator' ); ?>"></uas-chip>
							<?php } ?>
											</div>
										</div>
						<?php } ?>
								</div>
								<div class="automator-mcp-wp-provider-footer">
									<details class="automator-mcp-wp-ability-details">
										<summary><?php echo esc_html_x( 'Ability identifiers', 'MCP WordPress ability technical details label', 'uncanny-automator' ); ?></summary>
										<dl class="uap-spacing-top--small uap-spacing-bottom--none">
									<?php foreach ( $group['abilities'] as $row ) { ?>
											<dt><?php echo esc_html( $row['label'] ); ?></dt>
											<dd><code dir="ltr"><?php echo esc_html( $row['name'] ); ?></code></dd>
						<?php } ?>
										</dl>
									</details>
								</div>
								<?php } ?>
							</uas-accordion-item>
					<?php } ?>
					</uas-accordion>
					<details class="automator-mcp-wp-permissions-help uap-spacing-top--big">
						<summary><?php esc_html_e( 'How these permissions work', 'uncanny-automator' ); ?></summary>
						<p class="uap-spacing-top--small uap-spacing-bottom--none"><?php echo esc_html_x( 'Each switch saves automatically and applies to new requests. It does not edit existing recipes. Each provider’s note explains whether its switches also affect Uncanny Agent.', 'MCP WordPress abilities scope note', 'uncanny-automator' ); ?></p>
						<p class="uap-spacing-top--small uap-spacing-bottom--none"><?php echo esc_html_x( 'Descriptions and behavior labels come from the provider. Review abilities that can make changes before turning them on.', 'MCP WordPress abilities provider note', 'uncanny-automator' ); ?></p>
						<p class="uap-spacing-top--small uap-spacing-bottom--none"><?php echo esc_html_x( 'Turning an ability on does not grant an app extra access. The app’s approved access and the provider’s permission checks still apply.', 'MCP WordPress abilities read-access help', 'uncanny-automator' ); ?></p>
					</details>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
</div>
