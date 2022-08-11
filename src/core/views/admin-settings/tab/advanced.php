<?php

namespace Uncanny_Automator;

/**
 * Advanced
 * Settings > Advanced
 *
 * @since   4.3
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $advance_tabs         Array with the list of tabs
 * $current_general_tab  The ID of the current integration
 * $layout_version  The UI version, either "default" or "focus"
 */

?>

<uo-tabs direction="column" parameter="general">

	<?php
	if ( 'focus' !== $layout_version ) {

		foreach ( $advanced_tabs as $tab_key => $advanced_tab ) {
			?>

			<uo-tab id="<?php echo esc_attr( $tab_key ); ?>" <?php echo $advanced_tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $advanced_tab->status ) ? 'status="' . esc_attr( $advanced_tab->status ) . '"' : ''; ?>

				<?php if ( ! $advanced_tab->is_selected && ! $advanced_tab->preload ) { ?>

					href="<?php echo esc_url( Admin_Settings_Advanced::utility_get_general_page_link( $tab_key ) ); ?>"

				<?php } ?>

			><!--uo-tab /> -->

				<?php if ( isset( $advanced_tab->icon ) ) { ?>

					<uo-icon id="<?php echo esc_attr( $advanced_tab->icon ); ?>"></uo-icon>

					<?php } ?>

				<?php echo esc_html( $advanced_tab->name ); ?>

			</uo-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $advanced_tabs as $tab_key => $advanced_tab ) {

		if ( $advanced_tab->is_selected || $advanced_tab->preload ) {
			?>

			<uo-tab-panel id="<?php echo esc_attr( $tab_key ); ?>"

				<?php echo $advanced_tab->is_selected ? 'active' : ''; ?>
			><!--uo-tab-panel />-->

				<?php do_action( 'automator_settings_advanced_' . $tab_key . '_tab' ); ?>

			</uo-tab-panel>

			<?php

		}
	}

	?>

</uo-tabs>
