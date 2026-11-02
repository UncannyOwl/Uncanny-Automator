<?php
/**
 * MCP settings: Automator abilities sub-tab view.
 *
 * A shell plus markup templates. The page script requests the catalogue,
 * which is built inside a load-all admin-ajax request, and fills the shell
 * by cloning the templates below, so markup and translations stay here.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="uap-settings-panel automator-mcp-settings automator-mcp-abilities">
	<div class="uap-settings-panel-top">
		<div class="uap-settings-panel-title">
			<?php echo esc_html_x( 'Automator actions', 'settings panel title', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-settings-panel-content">
			<div class="uap-settings-panel-content-paragraph">
				<?php echo esc_html_x( 'Choose which Automator actions connected AI apps can find, add to recipes and run directly on this site.', 'MCP abilities introduction', 'uncanny-automator' ); ?>
			</div>
			<p><strong><?php echo esc_html_x( 'Applies to:', 'MCP settings label', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Connected AI apps only. These settings do not affect Uncanny Agent.', 'MCP settings help', 'uncanny-automator' ); ?></p>

			<p><?php echo esc_html_x( 'Available actions are on by default, including new actions in enabled integrations. Restricted actions cannot be turned on.', 'MCP action default access', 'uncanny-automator' ); ?></p>
			<uo-alert class="uap-spacing-top uap-spacing-bottom" type="info" heading="<?php echo esc_attr_x( 'Turning an action off does not stop existing recipes that use it.', 'MCP action execution boundary', 'uncanny-automator' ); ?>">
				<details class="automator-mcp-details">
					<summary><?php echo esc_html_x( 'What turning an action off does not block', 'MCP action execution details', 'uncanny-automator' ); ?></summary>
					<p><?php echo esc_html_x( 'Existing recipe actions can still be edited. Other allowed actions or WordPress abilities may use the same integration. To control recipe execution, review Run a recipe under WordPress abilities or Site tools.', 'MCP action execution details', 'uncanny-automator' ); ?></p>
				</details>
			</uo-alert>

			<div class="automator-mcp-tool-counts uap-spacing-bottom">
				<uas-chip size="sm" color="info" filled id="automator-mcp-abilities-available" hidden></uas-chip>
				<uas-chip size="sm" color="info" filled id="automator-mcp-abilities-total" hidden></uas-chip>
			</div>
			<div id="automator-mcp-abilities-status" class="automator-mcp-copy-status" role="status" aria-live="polite"></div>
			<div id="automator-mcp-abilities-alert"></div>

			<div class="automator-mcp-abilities-search uap-spacing-bottom">
				<label class="screen-reader-text" for="automator-mcp-abilities-search"><?php echo esc_html_x( 'Search integrations and actions', 'MCP abilities search label', 'uncanny-automator' ); ?></label>
				<div class="uap-field automator-mcp-search-control">
					<uas-icon code="magnifying-glass" class="uap-field-icon" aria-hidden="true"></uas-icon>
					<input type="text" class="uap-field-text uap-field--has-icon" id="automator-mcp-abilities-search" autocomplete="off" placeholder="<?php echo esc_attr_x( 'Search integrations and actions', 'MCP abilities search placeholder', 'uncanny-automator' ); ?>">
				</div>
			</div>
			<div class="uap-settings-panel-content-paragraph" id="automator-mcp-abilities-no-results" role="status" hidden>
				<span id="automator-mcp-abilities-no-results-text"></span>
				<span><?php echo esc_html_x( 'Try a different name or clear the search.', 'MCP abilities search empty help', 'uncanny-automator' ); ?></span>
				<uo-button type="button" size="small" color="secondary" id="automator-mcp-abilities-clear-search"><?php echo esc_html_x( 'Clear search', 'MCP abilities clear search', 'uncanny-automator' ); ?></uo-button>
			</div>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Available integrations', 'MCP abilities available heading', 'uncanny-automator' ); ?>
			</div>
			<div class="uap-settings-panel-content-paragraph uap-spacing-bottom">
				<?php echo esc_html_x( 'Expand an integration to choose individual actions. Turning it off blocks access through these controls and keeps your selections for when you turn it back on.', 'MCP abilities available explanation', 'uncanny-automator' ); ?>
			</div>
			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top uap-spacing-bottom"><uas-chip class="uap-spacing-right--small" size="sm" color="neutral" filled label="<?php echo esc_attr_x( 'Saves automatically', 'MCP saving status', 'uncanny-automator' ); ?>"></uas-chip> <span><?php echo esc_html_x( 'Applies to new requests.', 'MCP saving help', 'uncanny-automator' ); ?></span></div>
			<div id="automator-mcp-abilities-list">
				<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
					<?php echo esc_html_x( 'Loading integrations and actions…', 'MCP abilities loading', 'uncanny-automator' ); ?>
				</div>
			</div>

			<div id="automator-mcp-abilities-unavailable" class="uap-spacing-top" hidden>
				<uas-accordion>
					<uas-accordion-item>
						<div slot="summary" class="automator-mcp-tool-summary">
							<strong id="automator-mcp-abilities-unavailable-heading"></strong>
						</div>
						<div class="uap-settings-panel-content-paragraph uap-spacing-bottom">
							<?php echo esc_html_x( 'These integrations need additional setup before AI apps can use their actions. Check the requirement shown for each integration.', 'MCP abilities unavailable explanation', 'uncanny-automator' ); ?>
						</div>
						<div class="automator-mcp-ability-unavailable" id="automator-mcp-abilities-unavailable-list"></div>
					</uas-accordion-item>
				</uas-accordion>
			</div>

			<template id="automator-mcp-abilities-row-template">
				<uas-accordion-item>
					<div slot="summary" class="automator-mcp-ability-summary">
						<span class="automator-mcp-ability-title">
							<img class="automator-mcp-ability-icon" alt="" loading="lazy">
							<strong></strong>
						</span>
						<span class="automator-mcp-count-pair"><uas-chip size="sm" color="neutral" class="automator-mcp-ability-state" hidden></uas-chip><span class="automator-mcp-ability-count"></span></span>
						<span class="automator-mcp-ability-summary-controls">
							<uo-switch class="automator-mcp-integration-toggle" status-label="<?php echo esc_attr_x( 'On', 'MCP abilities switch on', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Off', 'MCP abilities switch off', 'uncanny-automator' ); ?>" status-label-position="left"></uo-switch>
						</span>
					</div>
					<div class="uap-settings-panel-content-paragraph automator-mcp-ability-parent-off uap-spacing-top--none uap-spacing-bottom--small" hidden></div>
					<div class="automator-mcp-ability-actions"></div>
				</uas-accordion-item>
			</template>

			<template id="automator-mcp-abilities-unavailable-template">
				<div class="automator-mcp-ability-unavailable-item">
					<img class="automator-mcp-ability-icon" alt="" loading="lazy">
					<span class="automator-mcp-ability-unavailable-name"></span>
					<uas-chip size="sm" filled class="automator-mcp-ability-status"></uas-chip>
				</div>
			</template>
		</div>
	</div>
</div>
