<?php
/**
 * Advanced
 * Settings > Advanced
 *
 * @since   4.3
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $advanced_tabs        Array with the list of tabs; a tab's optional `chip`
 *                       (label, color) is drawn after its name
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
	foreach ( $advanced_tabs as $focus_tab_key => $focus_tab ) {
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

		foreach ( $advanced_tabs as $tab_key => $advanced_tab ) {
			?>

			<uas-tab value="<?php echo esc_attr( $tab_key ); ?>" <?php echo $advanced_tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $advanced_tab->status ) ? 'status="' . esc_attr( $advanced_tab->status ) . '"' : ''; ?>

				href="<?php echo esc_url( Admin_Settings_Advanced::utility_get_general_page_link( $tab_key ) ); ?>"

			>

				<?php if ( isset( $advanced_tab->icon ) ) { ?>

					<uas-icon code="<?php echo esc_attr( $advanced_tab->icon ); ?>"></uas-icon>

					<?php } ?>

				<?php echo esc_html( $advanced_tab->name ); ?>

				<?php if ( isset( $advanced_tab->chip->label ) ) { ?>

					<uas-chip size="sm" color="<?php echo esc_attr( $advanced_tab->chip->color ?? 'neutral' ); ?>" filled label="<?php echo esc_attr( $advanced_tab->chip->label ); ?>"></uas-chip>

				<?php } ?>

			</uas-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $advanced_tabs as $tab_key => $advanced_tab ) {

		if ( $advanced_tab->is_selected || $advanced_tab->preload ) {
			?>

			<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_key ); ?>"

				<?php echo $advanced_tab->is_selected ? 'active' : ''; ?>
			>

				<?php do_action( 'automator_settings_advanced_' . $tab_key . '_tab' ); ?>

			</uas-tab-panel>

			<?php

		}
	}

	?>

</uas-tabs>
