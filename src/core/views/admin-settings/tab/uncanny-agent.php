<?php

namespace Uncanny_Automator;

/**
 * Uncanny Agent
 * Settings > Uncanny Agent
 *
 * @since   7.0
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $uncanny_agent_tabs         Array with the list of tabs
 * $current_uncanny_agent_tab  The ID of the current tab
 * $layout_version             The UI version, either "default" or "focus"
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// In "Focus" mode the tab strip is hidden, so the tabs component has no tab to resolve the active panel from; pass the selected tab's key as the controlled value so its panel still shows.
$focus_active_value = '';
if ( 'focus' === $layout_version ) {
	foreach ( $uncanny_agent_tabs as $focus_tab_key => $focus_tab ) {
		if ( ! empty( $focus_tab->is_selected ) ) {
			$focus_active_value = (string) $focus_tab_key;
			break;
		}
	}
}

?>

<uas-tabs orientation="vertical"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>>

	<?php
	if ( 'focus' !== $layout_version ) {

		foreach ( $uncanny_agent_tabs as $tab_key => $uncanny_agent_tab ) {
			?>

			<uas-tab value="<?php echo esc_attr( $tab_key ); ?>" <?php echo $uncanny_agent_tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $uncanny_agent_tab->status ) ? 'status="' . esc_attr( $uncanny_agent_tab->status ) . '"' : ''; ?>

				href="<?php echo esc_url( Admin_Settings_Uncanny_Agent::utility_get_uncanny_agent_page_link( $tab_key ) ); ?>"

			>

				<?php if ( isset( $uncanny_agent_tab->icon ) ) { ?>

					<uas-icon code="<?php echo esc_attr( $uncanny_agent_tab->icon ); ?>"></uas-icon>

					<?php } ?>

				<?php echo esc_html( $uncanny_agent_tab->name ); ?>

			</uas-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $uncanny_agent_tabs as $tab_key => $uncanny_agent_tab ) {

		if ( $uncanny_agent_tab->is_selected || $uncanny_agent_tab->preload ) {
			?>

			<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_key ); ?>"

				<?php echo $uncanny_agent_tab->is_selected ? 'active' : ''; ?>
			>

				<?php do_action( 'automator_settings_uncanny_agent_' . $tab_key . '_tab' ); ?>

			</uas-tab-panel>

			<?php

		}
	}

	?>

</uas-tabs>
