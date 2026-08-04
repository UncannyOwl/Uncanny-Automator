<?php

/**
 * Uncanny Page Builder
 * Settings > Uncanny Page Builder
 *
 * @since   7.5
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $uncanny_page_builder_tabs         Array with the list of tabs.
 * $current_uncanny_page_builder_tab  The ID of the current tab.
 * $layout_version                    The UI version, either "default" or "focus".
 */

namespace Uncanny_Automator;

?>

<uo-tabs direction="column" parameter="uncanny-page-builder">

	<?php
	if ( 'focus' !== $layout_version ) {
		foreach ( $uncanny_page_builder_tabs as $tab_key => $uncanny_page_builder_tab ) {
			?>

			<uo-tab
				id="<?php echo esc_attr( $tab_key ); ?>"
				<?php echo $uncanny_page_builder_tab->is_selected ? 'active' : ''; ?>
				<?php echo ! empty( $uncanny_page_builder_tab->status ) ? 'status="' . esc_attr( $uncanny_page_builder_tab->status ) . '"' : ''; ?>
				<?php if ( ! $uncanny_page_builder_tab->is_selected && ! $uncanny_page_builder_tab->preload ) { ?>
					href="<?php echo esc_url( Admin_Settings_Uncanny_Page_Builder::utility_get_uncanny_page_builder_page_link( $tab_key ) ); ?>"
				<?php } ?>
			>
				<?php if ( isset( $uncanny_page_builder_tab->icon ) ) { ?>
					<uo-icon id="<?php echo esc_attr( $uncanny_page_builder_tab->icon ); ?>"></uo-icon>
				<?php } ?>

				<?php echo esc_html( $uncanny_page_builder_tab->name ); ?>
			</uo-tab>

			<?php
		}
	}

	foreach ( $uncanny_page_builder_tabs as $tab_key => $uncanny_page_builder_tab ) {
		if ( $uncanny_page_builder_tab->is_selected || $uncanny_page_builder_tab->preload ) {
			?>

			<uo-tab-panel
				id="<?php echo esc_attr( $tab_key ); ?>"
				<?php echo $uncanny_page_builder_tab->is_selected ? 'active' : ''; ?>
			>
				<?php do_action( 'automator_settings_uncanny_page_builder_' . $tab_key . '_tab' ); ?>
			</uo-tab-panel>

			<?php
		}
	}
	?>

</uo-tabs>
