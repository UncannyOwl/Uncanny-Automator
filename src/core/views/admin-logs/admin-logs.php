<?php
namespace Uncanny_Automator;

/**
 * Admin Logs
 *
 * @since 4.5
 * @package Uncanny_Automator
 */
?>
<style>
#wpcontent { padding-left: 0 !important; }
.uo-recipe_page_uncanny-automator-admin-logs #wpfooter, 
.uo-recipe_page_uncanny-automator-admin-logs .notice { display: none!important;}
.uo-recipe_page_uncanny-automator-admin-logs #wpbody-content {
	float: none!important;
	padding-bottom: 0!important;
}
</style>

<div id="uap-settings" class="uap uap-settings" >

	<?php do_action( 'automator_tools_header_after' ); ?>

	<div id="uap-settings-content" class="uap-settings-content">

		<uo-tabs tab="settings" class="uap-settings-content-main-tabs">

			<?php if ( 'focus' !== $layout_version ) { ?>

				<?php foreach ( $tabs as $tab_key => $setting_tab ) { ?>

					<uo-tab id="<?php echo esc_attr( $tab_key ); ?>"

						<?php echo $setting_tab->is_selected ? 'active' : ''; ?>

						<?php if ( ! $setting_tab->is_selected && ! $setting_tab->preload ) { ?> 
							href="<?php echo esc_url( Admin_Logs::utility_get_settings_page_link( $tab_key ) ); ?>"
						<?php } ?>

					><!--uo-tab/>-->

						<?php echo esc_html( $setting_tab->name ); ?>

					</uo-tab>

					<?php

				}
			}

			// Add tab panels.
			foreach ( $tabs as $tab_key => $setting_tab ) {

				if ( $setting_tab->is_selected || $setting_tab->preload ) {
					?>

					<uo-tab-panel id="<?php echo esc_attr( $tab_key ); ?>" <?php echo $setting_tab->is_selected ? 'active' : ''; ?>>

						<?php do_action( 'automator_admin_logs_top_level_tabs_item_content_' . $tab_key ); ?>

					</uo-tab-panel>

					<?php

				}
			}

			?>

		</uo-tabs>

	</div>

</div>

<script>
<?php
/**
 * This is more like a temporary/hack`ish solution since the page value
 * is implemented in the Pro version. Updating free while leaving
 * Pro behind will cause the filter to redirect into the old recipe page.
 *
 * This script can be removed soon.
 */
?>
jQuery(document).ready(function($){
	'use strict';
	$('form.uap-report-filters').append("<input type='hidden' name='tab' value='<?php echo esc_html( $this->get_current_tab() ); ?>' />")
	$('form.uap-report-filters input[name=page]').val('uncanny-automator-admin-logs');
});
</script>
