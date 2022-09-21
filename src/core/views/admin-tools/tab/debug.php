<?php
/**
 * Status > Debug
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

?>

<uo-tabs direction="column" parameter="debug">

	<?php if ( 'focus' !== $layout_version ) { ?>

		<?php foreach ( $debug_tabs as $tab_id => $debug_tab ) { ?>

			<uo-tab id="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $debug_tab->is_selected ? 'active' : '' ); ?>

					<?php echo ! empty( $debug_tab->status ) ? 'status="' . esc_attr( $tab->status ) . '"' : ''; ?>

					<?php if ( ! $debug_tab->is_selected && ! $debug_tab->preload ) { ?>

						href="<?php echo esc_url( Admin_Tools_Tab_Debug::utility_get_debug_page_link( $tab_id ) ); ?>"

					<?php } ?>

				><!-- uo-tab/> -->

				<?php if ( isset( $debug_tab->icon ) ) { ?>

					<uo-icon id="<?php echo esc_attr( $debug_tab->icon ); ?>"></uo-icon>

					<?php } ?>

				<?php echo esc_html( $debug_tab->name ); ?>

			</uo-tab>

		<?php } ?>

		<?php foreach ( $debug_tabs as $tab_id => $debug_tab ) { ?>

			<?php if ( $debug_tab->is_selected || $debug_tab->preload ) { ?>

				<uo-tab-panel id="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $debug_tab->is_selected ? 'active' : '' ); ?> >

					<?php do_action( 'automator_admin_tools_tools_' . $tab_id . '_tab' ); ?>

				</uo-tab-panel>

			<?php } ?>

		<?php } ?>

	<?php } ?>

</uo-tabs>
