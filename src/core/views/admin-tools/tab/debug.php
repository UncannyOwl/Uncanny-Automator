<?php
/**
 * Status > Debug
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

		<?php foreach ( $debug_tabs as $tab_id => $debug_tab ) { ?>

			<uas-tab value="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $debug_tab->is_selected ? 'active' : '' ); ?>

					<?php echo ! empty( $debug_tab->status ) ? 'status="' . esc_attr( $debug_tab->status ) . '"' : ''; ?>

					href="<?php echo esc_url( Admin_Tools_Tab_Debug::utility_get_debug_page_link( $tab_id ) ); ?>"

				>

				<?php if ( isset( $debug_tab->icon ) ) { ?>

					<uas-icon code="<?php echo esc_attr( $debug_tab->icon ); ?>"></uas-icon>

					<?php } ?>

				<?php echo esc_html( $debug_tab->name ); ?>

			</uas-tab>

		<?php } ?>

		<?php foreach ( $debug_tabs as $tab_id => $debug_tab ) { ?>

			<?php if ( $debug_tab->is_selected || $debug_tab->preload ) { ?>

				<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_id ); ?>" <?php echo esc_attr( $debug_tab->is_selected ? 'active' : '' ); ?> >

					<?php do_action( 'automator_admin_tools_tools_' . $tab_id . '_tab' ); ?>

				</uas-tab-panel>

			<?php } ?>

		<?php } ?>

	<?php } ?>

</uas-tabs>
