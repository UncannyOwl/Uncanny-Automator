<?php
/**
 * Status > Tools
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

?>

<uo-tabs direction="column" parameter="general">

	<?php if ( 'focus' !== $layout_version ) { ?>

		<?php foreach ( $tools_tabs as $tab_id => $tools_tab ) { ?>

			<uo-tab id="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $tools_tab->is_selected ? 'active' : '' ); ?>

					<?php echo ! empty( $tools_tab->status ) ? 'status="' . esc_attr( $tab->status ) . '"' : ''; ?>

					<?php if ( ! $tools_tab->is_selected && ! $tools_tab->preload ) { ?>

						href="<?php echo esc_url( Admin_Tools::utility_get_settings_page_link( $tab_id ) ); ?>"

					<?php } ?>

				><!-- uo-tab/> -->

				<?php if ( isset( $tools_tab->icon ) ) { ?>

					<uo-icon id="<?php echo esc_attr( $tools_tab->icon ); ?>"></uo-icon>

					<?php } ?>

				<?php echo esc_html( $tools_tab->name ); ?>

			</uo-tab>

		<?php } ?>

		<?php foreach ( $tools_tabs as $tab_id => $tools_tab ) { ?>

			<?php if ( $tools_tab->is_selected || $tools_tab->preload ) { ?>

				<uo-tab-panel id="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $tools_tab->is_selected ? 'active' : '' ); ?> >

					<?php do_action( 'automator_admin_tools_tools_' . $tab_id . '_tab' ); ?>

				</uo-tab-panel>

			<?php } ?>

		<?php } ?>

	<?php } ?>

</uo-tabs>
