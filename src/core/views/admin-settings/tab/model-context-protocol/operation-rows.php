<?php
/**
 * Shared permission rows for root MCP tools and native WordPress abilities.
 *
 * @package Uncanny_Automator
 *
 * Variables:
 * $can_manage_tools Whether the current administrator may change these permissions.
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

/**
 * Render the declared behavior chips for a tool or ability.
 *
 * @param array<string,bool|null> $annotations Operation annotations.
 * @return void
 */
$render_behavior_chips = static function ( array $annotations ) {
	$chips = array(
		array(
			'value'   => $annotations['read_only'],
			'true'    => array( esc_html_x( 'Only reads information', 'MCP tool behavior label', 'uncanny-automator' ), 'info' ),
			'false'   => array( esc_html_x( 'Can make changes', 'MCP tool behavior label', 'uncanny-automator' ), 'warning' ),
			'missing' => esc_html_x( 'Read behavior not declared', 'MCP missing tool behavior label', 'uncanny-automator' ),
		),
		array(
			'value'   => $annotations['destructive'],
			'true'    => array( esc_html_x( 'May make permanent changes', 'MCP tool behavior label', 'uncanny-automator' ), 'error' ),
			'false'   => null,
			'missing' => esc_html_x( 'Permanent-change behavior not declared', 'MCP missing tool behavior label', 'uncanny-automator' ),
		),
		array(
			'value'   => $annotations['idempotent'],
			'true'    => array( esc_html_x( 'Same effect when repeated', 'MCP tool behavior label', 'uncanny-automator' ), 'success' ),
			'false'   => array( esc_html_x( 'May repeat a change', 'MCP tool behavior label', 'uncanny-automator' ), 'warning' ),
			'missing' => esc_html_x( 'Repeat behavior not declared', 'MCP missing tool behavior label', 'uncanny-automator' ),
		),
		array(
			'value'   => $annotations['open_world'],
			'true'    => array( esc_html_x( 'May connect outside this site', 'MCP tool behavior label', 'uncanny-automator' ), 'info' ),
			'false'   => array( esc_html_x( 'Uses a defined set of resources', 'MCP tool behavior label', 'uncanny-automator' ), 'neutral' ),
			'missing' => esc_html_x( 'External-access behavior not declared', 'MCP missing tool behavior label', 'uncanny-automator' ),
		),
	);

	foreach ( $chips as $chip ) {
		// A non-destructive hint does not establish that a change can be undone.
		if ( false === $chip['value'] && null === $chip['false'] ) {
			continue;
		}

		$label = null === $chip['value'] ? $chip['missing'] : $chip[ $chip['value'] ? 'true' : 'false' ][0];
		$color = null === $chip['value'] ? 'neutral' : $chip[ $chip['value'] ? 'true' : 'false' ][1];
		?>
		<uas-chip size="sm" color="<?php echo esc_attr( $color ); ?>" filled label="<?php echo esc_attr( $label ); ?>"></uas-chip>
		<?php
	}
};

/**
 * Render permission accordions using the public kind's labels.
 *
 * @param array<int,array<string,mixed>> $tools Tool presentation data.
 * @param bool $abilities Whether these rows represent native abilities.
 * @return void
 */
$render_operations = static function ( array $tools, bool $abilities = false ) use ( $render_behavior_chips, $can_manage_tools ) {
	if ( empty( $tools ) ) {
		?>
		<div class="uap-settings-panel-content-paragraph">
			<?php echo $abilities ? esc_html_x( 'No abilities in this group.', 'MCP abilities empty group', 'uncanny-automator' ) : esc_html_x( 'No tools in this group.', 'MCP tools empty group', 'uncanny-automator' ); ?>
		</div>
		<?php
		return;
	}
	?>
	<uas-accordion class="uap-spacing-top--small">
		<?php foreach ( $tools as $tool ) { ?>
			<uas-accordion-item>
				<div slot="summary" class="automator-mcp-tool-summary">
					<span class="automator-mcp-operation-title"><strong><?php echo $tool['title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared with escaped translations or esc_html(). ?></strong>
						<?php
						if ( 'site_manage' === $tool['name'] ) {
							?>
							<uas-chip class="automator-mcp-scope" size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Also affects Uncanny Agent', 'MCP operation scope', 'uncanny-automator' ); ?>"></uas-chip><?php } ?>
					</span>
					<span class="automator-mcp-tool-summary-chips">
						<?php if ( ! empty( $tool['variable'] ) ) { ?>
							<uas-chip size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Depends on the operation', 'MCP variable access tool label', 'uncanny-automator' ); ?>"></uas-chip>
						<?php } else { ?>
							<uas-chip size="sm" color="<?php echo Authenticated_Token_Context::SCOPE_WRITE === $tool['scope'] ? 'warning' : 'info'; ?>" filled label="<?php echo Authenticated_Token_Context::SCOPE_WRITE === $tool['scope'] ? esc_attr_x( 'Can make changes', 'MCP write tool label', 'uncanny-automator' ) : esc_attr_x( 'Read only', 'MCP read tool label', 'uncanny-automator' ); ?>"></uas-chip>
						<?php } ?>
						<?php if ( true === $tool['annotations']['destructive'] ) { ?>
							<uas-chip size="sm" color="error" filled label="<?php echo esc_attr_x( 'May make permanent changes', 'MCP destructive tool label', 'uncanny-automator' ); ?>"></uas-chip>
						<?php } ?>
					</span>
					<span class="automator-mcp-tool-summary-controls">
						<uo-switch
							class="automator-mcp-tool-toggle"
							data-tool="<?php echo esc_attr( $tool['name'] ); ?>"
							data-title="<?php echo esc_attr( wp_specialchars_decode( $tool['title'], ENT_QUOTES ) ); ?>"
							aria-label="<?php printf( /* translators: %s Tool name. */ esc_attr_x( 'Allow %s', 'MCP tool switch accessible name', 'uncanny-automator' ), esc_attr( wp_specialchars_decode( $tool['title'], ENT_QUOTES ) ) ); ?>"
							<?php echo ! $can_manage_tools || ! empty( $tool['consent_missing'] ) ? 'disabled' : ''; ?>
							<?php echo ! empty( $tool['enabled'] ) ? 'checked' : ''; ?>
							status-label="<?php echo esc_attr_x( 'On', 'MCP tool switch on', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Off', 'MCP tool switch off', 'uncanny-automator' ); ?>"
							status-label-position="left"
						></uo-switch>
					</span>
				</div>

				<div class="automator-mcp-tool-detail">
					<?php if ( ! $abilities ) { ?>
						<p class="automator-mcp-secondary"><?php echo esc_html_x( 'If this tool was off when you connected the site, reconnect it in Uncanny Automator after turning the tool on.', 'MCP reconnect help', 'uncanny-automator' ); ?></p>
					<?php } ?>
					<?php if ( ! empty( $tool['consent_missing'] ) ) { ?>
						<uo-alert type="info" class="uap-spacing-bottom">
							<?php echo esc_html_x( 'Enable Database access below to use this tool.', 'MCP database tool consent needed', 'uncanny-automator' ); ?>
						</uo-alert>
					<?php } ?>
					<div class="automator-mcp-tool-purpose uap-spacing-bottom">
						<?php echo $tool['purpose']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared with escaped translations. ?>
					</div>

					<?php if ( ! empty( $tool['capabilities'] ) ) { ?>
						<div class="automator-mcp-tool-capabilities uap-spacing-bottom">
							<div class="automator-mcp-tool-detail-heading uap-spacing-bottom--small">
								<?php echo esc_html_x( 'What it can do', 'MCP tool capabilities heading', 'uncanny-automator' ); ?>
							</div>
							<ul>
								<?php foreach ( $tool['capabilities'] as $capability ) { ?>
									<li><?php echo $capability; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared with escaped translations. ?></li>
								<?php } ?>
							</ul>
						</div>
					<?php } ?>

					<div class="automator-mcp-tool-access uap-spacing-bottom">
						<strong><?php echo esc_html_x( 'Required access', 'MCP tool enforced scope heading', 'uncanny-automator' ); ?>:</strong>
						<?php if ( ! empty( $tool['variable'] ) ) { ?>
							<uas-chip size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Depends on the operation', 'MCP variable access label', 'uncanny-automator' ); ?>"></uas-chip>
							<span class="automator-mcp-tool-access-note"><?php echo esc_html_x( 'Read-only operations run with Read only access. Operations that make changes require Read and make changes access.', 'MCP variable access explanation', 'uncanny-automator' ); ?></span>
						<?php } else { ?>
							<uas-chip size="sm" color="<?php echo Authenticated_Token_Context::SCOPE_WRITE === $tool['scope'] ? 'warning' : 'info'; ?>" filled label="<?php echo Authenticated_Token_Context::SCOPE_WRITE === $tool['scope'] ? esc_attr_x( 'Read and make changes', 'MCP write access label', 'uncanny-automator' ) : esc_attr_x( 'Read only', 'MCP read access label', 'uncanny-automator' ); ?>"></uas-chip>
						<?php } ?>
					</div>

					<div class="uap-spacing-bottom">
						<div class="automator-mcp-tool-detail-heading uap-spacing-bottom--small">
							<?php echo $abilities ? esc_html_x( 'Declared ability behavior', 'MCP ability annotations heading', 'uncanny-automator' ) : esc_html_x( 'Declared tool behavior', 'MCP tool annotations heading', 'uncanny-automator' ); ?>
						</div>
						<p><?php echo esc_html_x( 'These descriptions come from the provider. They do not grant access or prevent data from being sent to an AI app.', 'MCP declared behavior explanation', 'uncanny-automator' ); ?></p>
						<div class="automator-mcp-tool-behaviors">
							<?php $render_behavior_chips( $tool['annotations'] ); ?>
						</div>
					</div>

					<?php if ( '' !== $tool['warning'] ) { ?>
						<uo-alert class="uap-spacing-bottom" type="warning">
							<?php echo $tool['warning']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared with escaped translations. ?>
						</uo-alert>
					<?php } elseif ( true === $tool['annotations']['destructive'] ) { ?>
						<uo-alert class="uap-spacing-bottom" type="warning">
							<?php echo $abilities ? esc_html_x( 'This ability may permanently change or delete information. Review its purpose before allowing access.', 'MCP destructive ability notice', 'uncanny-automator' ) : esc_html_x( 'This tool may permanently change or delete information. Review its purpose before allowing access.', 'MCP destructive tool notice', 'uncanny-automator' ); ?>
						</uo-alert>
					<?php } ?>

					<div class="uap-spacing-bottom">
						<div class="automator-mcp-tool-detail-heading uap-spacing-bottom--small">
							<?php echo esc_html_x( 'Technical details', 'MCP technical details heading', 'uncanny-automator' ); ?>
						</div>
						<div class="automator-mcp-tool-technical-note uap-spacing-bottom--small">
							<?php echo esc_html_x( 'Names and descriptions used by AI apps.', 'MCP technical details explanation', 'uncanny-automator' ); ?>
						</div>
						<div class="automator-mcp-tool-name uap-spacing-bottom--small">
							<strong><?php echo $abilities ? esc_html_x( 'Ability name', 'MCP registered ability name label', 'uncanny-automator' ) : esc_html_x( 'MCP tool name', 'MCP registered tool name label', 'uncanny-automator' ); ?>:</strong>
							<span dir="ltr"><?php echo esc_html( $tool['public_name'] ); ?></span>
						</div>
						<div class="automator-mcp-tool-description">
							<strong><?php echo esc_html_x( 'Instructions for the AI app', 'MCP registered tool description label', 'uncanny-automator' ); ?>:</strong>
							<span><?php echo esc_html( $tool['description'] ); ?></span>
						</div>
					</div>

					<div class="automator-mcp-tool-detail-heading uap-spacing-bottom--small">
						<?php echo $abilities ? esc_html_x( 'Ability inputs', 'MCP ability inputs heading', 'uncanny-automator' ) : esc_html_x( 'Tool inputs', 'MCP tool inputs heading', 'uncanny-automator' ); ?>
					</div>

					<?php if ( empty( $tool['inputs'] ) ) { ?>
						<div class="automator-mcp-tool-empty-inputs">
							<?php echo esc_html_x( 'No inputs required.', 'MCP tool without inputs', 'uncanny-automator' ); ?>
						</div>
					<?php } else { ?>
						<div class="automator-mcp-table-wrap automator-mcp-table-wrap--list">
							<table class="automator-mcp-table automator-mcp-table--list">
								<caption class="screen-reader-text">
									<?php
									printf(
										/* translators: %s Tool name. */
										$abilities ? esc_html_x( 'Inputs accepted by the %s ability', 'MCP ability input table caption', 'uncanny-automator' ) : esc_html_x( 'Inputs accepted by the %s tool', 'MCP tool input table caption', 'uncanny-automator' ),
										esc_html( $tool['public_name'] )
									);
									?>
								</caption>
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html_x( 'Information', 'MCP tool input column', 'uncanny-automator' ); ?></th>
										<th scope="col"><?php echo esc_html_x( 'Format', 'MCP tool input column', 'uncanny-automator' ); ?></th>
										<th scope="col"><?php echo esc_html_x( 'Required', 'MCP tool input column', 'uncanny-automator' ); ?></th>
										<th scope="col"><?php echo esc_html_x( 'Description', 'MCP tool input column', 'uncanny-automator' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $tool['inputs'] as $input ) { ?>
										<tr>
											<td data-label="<?php echo esc_attr_x( 'Information', 'MCP tool input column', 'uncanny-automator' ); ?>"><strong dir="ltr"><?php echo esc_html( $input['name'] ); ?></strong></td>
											<td data-label="<?php echo esc_attr_x( 'Format', 'MCP tool input column', 'uncanny-automator' ); ?>"><?php echo $input['type']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by format_schema_type(). ?></td>
											<td data-label="<?php echo esc_attr_x( 'Required', 'MCP tool input column', 'uncanny-automator' ); ?>">
												<uas-chip size="sm" color="<?php echo $input['required'] ? 'warning' : 'neutral'; ?>" filled label="<?php echo $input['required'] ? esc_attr_x( 'Required', 'MCP required tool input', 'uncanny-automator' ) : esc_attr_x( 'Optional', 'MCP optional tool input', 'uncanny-automator' ); ?>"></uas-chip>
											</td>
											<td data-label="<?php echo esc_attr_x( 'Description', 'MCP tool input column', 'uncanny-automator' ); ?>">
												<?php echo esc_html( $input['description'] ); ?>
												<?php if ( ! empty( $input['enum'] ) ) { ?>
													<span class="automator-mcp-tool-allowed-values">
														<strong><?php echo esc_html_x( 'Choices', 'MCP enum values label', 'uncanny-automator' ); ?>:</strong>
														<bdi dir="ltr"><?php echo esc_html( implode( ', ', $input['enum'] ) ); ?></bdi>
													</span>
												<?php } ?>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					<?php } ?>
				</div>
			</uas-accordion-item>
		<?php } ?>
	</uas-accordion>
	<?php
};
