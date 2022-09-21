<?php
namespace Uncanny_Automator;

/**
 * Settings
 *
 * @since 4.5
 * @package Uncanny_Automator
 */
?>
<style>
#wpcontent { padding-left: 0 !important; }
.uo-recipe_page_uncanny-automator-admin-tools #wpfooter, 
.uo-recipe_page_uncanny-automator-admin-tools .notice { display: none!important;}
.uo-recipe_page_uncanny-automator-admin-tools #wpbody-content {
	float: none!important;
	padding-bottom: 0!important;
}
</style>

<div id="uap-settings" class="uap uap-settings" >

	<?php do_action( 'automator_tools_header_after' ); ?>

	<div id="uap-settings-content" class="uap-settings-content">

		<uo-tabs tab="settings" class="uap-settings-content-main-tabs">

			<?php

			if ( 'focus' !== $layout_version ) {

				// Create tabs
				foreach ( $tabs as $tab_key => $setting_tab ) {
					?>

					<uo-tab id="<?php echo esc_attr( $tab_key ); ?>"

						<?php echo $setting_tab->is_selected ? 'active' : ''; ?>

						<?php if ( ! $setting_tab->is_selected && ! $setting_tab->preload ) { ?> 
							href="<?php echo esc_url( Admin_Tools::utility_get_settings_page_link( $tab_key ) ); ?>"
						<?php } ?>

					><!--uo-tab/>-->

						<?php echo esc_html( $setting_tab->name ); ?>

					</uo-tab>

					<?php

				}
			}

			// Add tab panels
			foreach ( $tabs as $tab_key => $setting_tab ) {

				if ( $setting_tab->is_selected || $setting_tab->preload ) {
					?>

					<uo-tab-panel id="<?php echo esc_attr( $tab_key ); ?>" <?php echo $setting_tab->is_selected ? 'active' : ''; ?>>

						<?php do_action( 'automator_admin_tools_' . $tab_key . '_tab' ); ?>

					</uo-tab-panel>

					<?php

				}
			}

			?>

		</uo-tabs>

	</div>

</div>
