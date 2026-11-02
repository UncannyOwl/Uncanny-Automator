<?php
/**
 * General
 * Settings > General
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $general_tabs         Array with the list of tabs
 * $current_general_tab  The ID of the current integration
 * $layout_version  The UI version, either "default" or "focus"
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// In "Focus" mode the tab strip is hidden, so the tabs component has no tab to resolve the active panel from; pass the selected tab's key as the controlled value so its panel still shows.
$focus_active_value = '';
if ( 'focus' === $layout_version ) {
	foreach ( $general_tabs as $focus_tab_key => $focus_tab ) {
		if ( ! empty( $focus_tab->is_selected ) ) {
			$focus_active_value = (string) $focus_tab_key;
			break;
		}
	}
}

?>

<uas-tabs orientation="vertical"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>>

	<?php

	// Add navigation (tabs)
	// But don't add them in the "Focus" mode
	if ( 'focus' !== $layout_version ) {

		// Create tabs
		foreach ( $general_tabs as $tab_key => $general_tab ) {

			?>

			<uas-tab
				value="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $general_tab->is_selected ? 'active' : '';

				?>

				<?php

				// IF this tab has a status
				echo ! empty( $general_tab->status ) ? 'status="' . esc_attr( $general_tab->status ) . '"' : '';

				?>

				<?php

				// The href is the tab's canonical URL. It lets an in-place switch update the address bar, and lets a non-rendered tab navigate with a full page load.
				?>

				href="<?php echo esc_url( Admin_Settings_General::utility_get_general_page_link( $tab_key ) ); ?>"
			>
				<?php

				// Check if it has an icon
				if ( isset( $general_tab->icon ) ) {

					?>

					<uas-icon code="<?php echo esc_attr( $general_tab->icon ); ?>"></uas-icon>

					<?php
				}

				?>

				<?php echo esc_html( $general_tab->name ); ?>
			</uas-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $general_tabs as $tab_key => $general_tab ) {
		// Check if we have to load the content
		// The content will load if one of these conditions are meet
		// 1. If the tab is selected
		// 2. If the content should be preloaded
		if ( $general_tab->is_selected || $general_tab->preload ) {

			?>

			<uas-tab-panel
				slot="panel"
				name="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $general_tab->is_selected ? 'active' : '';

				?>
			>

				<?php do_action( 'automator_settings_general_' . $tab_key . '_tab' ); ?>

			</uas-tab-panel>

			<?php

		}
	}

	?>

</uas-tabs>
