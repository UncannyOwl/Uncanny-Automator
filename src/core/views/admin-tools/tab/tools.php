<?php
/**
 * Status > Tools
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>

<uas-tabs orientation="vertical">

	<?php if ( 'focus' !== $layout_version ) { ?>

		<?php foreach ( $tools_tabs as $tab_id => $tools_tab ) { ?>

			<uas-tab value="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $tools_tab->is_selected ? 'active' : '' ); ?>

					<?php echo ! empty( $tools_tab->status ) ? 'status="' . esc_attr( $tools_tab->status ) . '"' : ''; ?>

					href="<?php echo esc_url( Admin_Tools::utility_get_settings_page_link( $tab_id ) ); ?>"

				>

				<?php if ( isset( $tools_tab->icon ) ) { ?>

					<uas-icon code="<?php echo esc_attr( $tools_tab->icon ); ?>"></uas-icon>

					<?php } ?>

				<?php echo esc_html( $tools_tab->name ); ?>

			</uas-tab>

		<?php } ?>

		<?php foreach ( $tools_tabs as $tab_id => $tools_tab ) { ?>

			<?php if ( $tools_tab->is_selected || $tools_tab->preload ) { ?>

				<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $tools_tab->is_selected ? 'active' : '' ); ?> >

					<?php do_action( 'automator_admin_tools_tools_' . $tab_id . '_tab' ); ?>

				</uas-tab-panel>

			<?php } ?>

		<?php } ?>

	<?php } ?>

</uas-tabs>
