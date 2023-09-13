<?php

namespace Uncanny_Automator;

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

?>

<uo-tabs direction="column" parameter="addons">

	<?php
	if ( 'focus' !== $layout_version ) {

		foreach ( $this->addons_tabs as $tab_key => $tab ) {
			?>

			<uo-tab id="<?php echo esc_attr( $tab_key ); ?>" <?php echo $tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $tab->status ) ? 'status="' . esc_attr( $tab->status ) . '"' : ''; ?>

				<?php if ( ! $tab->is_selected && ! $tab->preload ) { ?>

					href="<?php echo esc_url( Admin_Settings_Addons::utility_get_addons_page_link( $tab_key ) ); ?>"

				<?php } ?>

			><!--uo-tab /> -->

				<?php if ( isset( $tab->icon ) ) { ?>

					<uo-icon id="<?php echo esc_attr( $tab->icon ); ?>"></uo-icon>

					<?php } ?>

				<?php echo esc_html( $tab->name ); ?>

			</uo-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $this->addons_tabs as $tab_key => $tab ) {

		if ( $tab->is_selected || $tab->preload ) {
			?>

			<uo-tab-panel id="<?php echo esc_attr( $tab_key ); ?>"

				<?php echo $tab->is_selected ? 'active' : ''; ?>
			><!--uo-tab-panel />-->

				<?php do_action( 'automator_settings_addons_' . $tab_key . '_tab' ); ?>

			</uo-tab-panel>

			<?php

		}
	}

	?>

</uo-tabs>
