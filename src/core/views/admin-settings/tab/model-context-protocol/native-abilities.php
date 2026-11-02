<?php
/**
 * Native Automator abilities and their existing external-app permissions.
 *
 * @var array $group Automator provider with its operation groups and permission state.
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$ability_groups   = $group['operation_groups'];
$can_manage_tools = $group['can_manage_tools'];

require Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/operation-rows.php' );
?>
<div class="automator-mcp-native-abilities automator-mcp-tool-detail">
	<p><?php echo esc_html_x( 'Automator provides abilities to read site information, manage recipes and run actions. They are on by default.', 'MCP native abilities introduction', 'uncanny-automator' ); ?></p>
	<?php if ( ! empty( $group['runner_off'] ) ) { ?>
		<uo-alert type="warning" heading="<?php echo esc_attr_x( 'Run a WordPress operation is off.', 'MCP native abilities runner off heading', 'uncanny-automator' ); ?>">
			<p><?php echo esc_html_x( 'Connected AI apps cannot use Automator’s abilities while this tool is off, even when their switches are on. Your selections are saved. Uncanny Agent keeps its access.', 'MCP native abilities runner off explanation', 'uncanny-automator' ); ?></p>
			<?php
			printf(
				/* translators: %s Link to Site tools settings. */
				esc_html_x( 'To allow connected apps to use these abilities, turn on Run a WordPress operation under %s.', 'MCP native abilities runner off help', 'uncanny-automator' ),
				'<a href="' . esc_url( Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'tools' ) ) . '">' . esc_html_x( 'Site tools', 'MCP site tools link', 'uncanny-automator' ) . '</a>'
			);
			?>
		</uo-alert>
	<?php } ?>
	<?php if ( $group['load_error'] ) { ?>
		<uo-alert type="error"><?php echo esc_html_x( 'Automator’s abilities could not be loaded. Refresh the page to try again.', 'MCP native abilities load error', 'uncanny-automator' ); ?></uo-alert>
	<?php } else { ?>
		<div id="automator-mcp-tool-toggle-alert" class="uap-spacing-top" role="status" aria-live="polite"></div>
		<?php if ( ! $can_manage_tools ) { ?>
			<uo-alert type="info"><?php echo esc_html_x( 'You do not have permission to manage these settings. Contact a site administrator.', 'MCP native abilities administrator required', 'uncanny-automator' ); ?></uo-alert>
		<?php } ?>
		<?php if ( ! empty( $ability_groups['read'] ) ) { ?>
			<p role="heading" aria-level="3"><strong><?php echo esc_html_x( 'Read-only abilities', 'MCP read abilities heading', 'uncanny-automator' ); ?></strong></p>
			<?php $render_operations( $ability_groups['read'], true ); ?>
		<?php } ?>
		<?php if ( ! empty( $ability_groups['write'] ) ) { ?>
			<p role="heading" aria-level="3"><strong><?php echo esc_html_x( 'Abilities that make changes', 'MCP write abilities heading', 'uncanny-automator' ); ?></strong></p>
			<p><?php echo esc_html_x( 'These abilities can create, update, run or delete items. They require Read and make changes access.', 'MCP write abilities description', 'uncanny-automator' ); ?></p>
			<?php $render_operations( $ability_groups['write'], true ); ?>
		<?php } ?>
	<?php } ?>
</div>
