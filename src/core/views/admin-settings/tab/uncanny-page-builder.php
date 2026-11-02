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
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>

<uas-tabs orientation="vertical">

	<?php
	foreach ( $uncanny_page_builder_tabs as $tab_key => $uncanny_page_builder_tab ) {
		?>

		<uas-tab
			value="<?php echo esc_attr( $tab_key ); ?>"
			<?php echo $uncanny_page_builder_tab->is_selected ? 'active' : ''; ?>
			<?php echo ! empty( $uncanny_page_builder_tab->status ) ? 'status="' . esc_attr( $uncanny_page_builder_tab->status ) . '"' : ''; ?>
			href="<?php echo esc_url( Admin_Settings_Uncanny_Page_Builder::utility_get_uncanny_page_builder_page_link( $tab_key ) ); ?>"
		>
			<?php if ( isset( $uncanny_page_builder_tab->icon ) ) { ?>
				<uas-icon code="<?php echo esc_attr( $uncanny_page_builder_tab->icon ); ?>"></uas-icon>
			<?php } ?>

			<?php echo esc_html( $uncanny_page_builder_tab->name ); ?>
		</uas-tab>

		<?php
	}

	foreach ( $uncanny_page_builder_tabs as $tab_key => $uncanny_page_builder_tab ) {
		if ( $uncanny_page_builder_tab->is_selected || $uncanny_page_builder_tab->preload ) {
			?>

			<uas-tab-panel
				slot="panel"
				name="<?php echo esc_attr( $tab_key ); ?>"
				<?php echo $uncanny_page_builder_tab->is_selected ? 'active' : ''; ?>
			>
				<?php do_action( 'automator_settings_uncanny_page_builder_' . $tab_key . '_tab' ); ?>
			</uas-tab-panel>

			<?php
		}
	}
	?>

</uas-tabs>
