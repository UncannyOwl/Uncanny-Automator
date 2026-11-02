<?php
/**
 * Advanced
 * Settings > Addons
 *
 * @since   5.0.1
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $addons_tabs         Array with the list of tabs
 * $current_addons_tab  The ID of the current integration
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
	foreach ( $this->addons_tabs as $focus_tab_key => $focus_tab ) {
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

		foreach ( $this->addons_tabs as $tab_key => $addons_tab ) {
			?>

			<uas-tab value="<?php echo esc_attr( $tab_key ); ?>" <?php echo $addons_tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $addons_tab->status ) ? 'status="' . esc_attr( $addons_tab->status ) . '"' : ''; ?>

				href="<?php echo esc_url( Admin_Settings_Addons::utility_get_addons_page_link( $tab_key ) ); ?>"

			>

				<?php if ( isset( $addons_tab->icon ) ) { ?>

					<uas-icon code="<?php echo esc_attr( $addons_tab->icon ); ?>"></uas-icon>

					<?php } ?>

				<?php echo esc_html( $addons_tab->name ); ?>

			</uas-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $this->addons_tabs as $tab_key => $addons_tab ) {

		if ( $addons_tab->is_selected || $addons_tab->preload ) {
			?>

		<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_key ); ?>"

			<?php echo $addons_tab->is_selected ? 'active' : ''; ?>
		>

				<?php do_action( 'automator_settings_addons_' . $tab_key . '_tab' ); ?>

			</uas-tab-panel>

			<?php

		}
	}

	?>

</uas-tabs>
