<?php
namespace Uncanny_Automator;

/**
 * Admin Logs
 *
 * @since 4.5
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// In "Focus" mode the tab strip is hidden, so the tabs component has no tab to resolve the active panel from; pass the selected tab's key as the controlled value so its panel still shows.
$focus_active_value = '';
if ( 'focus' === $layout_version ) {
	foreach ( $tabs as $focus_tab_key => $focus_tab ) {
		if ( ! empty( $focus_tab->is_selected ) ) {
			$focus_active_value = (string) $focus_tab_key;
			break;
		}
	}
}

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

<uap-d-site-auth
	site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
	site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
>
	<div id="uap-settings" class="uap uap-settings" >

		<?php do_action( 'automator_tools_header_after' ); ?>

		<div id="uap-settings-content" class="uap-settings-content">

			<uas-tabs class="uap-settings-content-main-tabs"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>>

				<?php if ( 'focus' !== $layout_version ) { ?>

					<?php foreach ( $tabs as $tab_key => $setting_tab ) { ?>

						<uas-tab value="<?php echo esc_attr( $tab_key ); ?>"

							<?php echo $setting_tab->is_selected ? 'active' : ''; ?>

							href="<?php echo esc_url( Admin_Logs::utility_get_settings_page_link( $tab_key ) ); ?>"

						>

							<?php echo esc_html( $setting_tab->name ); ?>

						</uas-tab>

						<?php

					}
				}

				// Add tab panels.
				foreach ( $tabs as $tab_key => $setting_tab ) {

					if ( $setting_tab->is_selected || $setting_tab->preload ) {
						?>

						<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_key ); ?>" <?php echo $setting_tab->is_selected ? 'active' : ''; ?>>

							<?php do_action( 'automator_admin_logs_top_level_tabs_item_content_' . $tab_key ); ?>

						</uas-tab-panel>

						<?php

					}
				}

				?>

			</uas-tabs>

		</div>

	</div>

	<?php /* Single page-level dialog host. Per-row "Details" buttons just write the matching hash; this element listens for it and opens. */ ?>
	<uap-f-recipe-log></uap-f-recipe-log>

</uap-d-site-auth>

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
