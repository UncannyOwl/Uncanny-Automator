<?php

namespace Uncanny_Automator;

/**
 * Settings
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $tabs            Array with the list of tabs
 * $current_tab     The ID of the current tab
 * $layout_version  The UI version, either "default" or "focus"
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

<uap-d-site-auth
	site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
	site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
>
	<div  id="uap-settings" class="uap uap-settings" >

		<?php

		do_action( 'automator_settings_header_after' );

		?>

		<div id="uap-settings-content" class="uap-settings-content">

			<uas-tabs
				class="uap-settings-content-main-tabs"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>
			>

				<?php

				// Add navigation (tabs)
				// But don't add them in the "Focus" mode
				if ( $layout_version !== 'focus' ) {

					// Create tabs
					foreach ( $tabs as $tab_key => $setting_tab ) {

						?>

						<uas-tab
							value="<?php echo esc_attr( $tab_key ); ?>"

							<?php

							// IF the tab is selected, then add the "active" attribute
							echo $setting_tab->is_selected ? 'active' : '';

							?>

							<?php

							// The href is the tab's canonical URL. It lets an in-place switch update the address bar, and lets a non-rendered tab navigate with a full page load.
							?>

							href="<?php echo esc_url( Admin_Settings::utility_get_settings_page_link( $tab_key ) ); ?>"
						>
							<?php echo esc_html( $setting_tab->name ); ?>
						</uas-tab>

						<?php

					}
				}

				// Add tab panels
				foreach ( $tabs as $tab_key => $setting_tab ) {
					// Check if we have to load the content
					// The content will load if one of these conditions are meet
					// 1. If the tab is selected
					// 2. If the content should be preloaded
					if ( $setting_tab->is_selected || $setting_tab->preload ) {

						?>

						<uas-tab-panel
							slot="panel"
							name="<?php echo esc_attr( $tab_key ); ?>"

							<?php

							// IF the tab is selected, then add the "active" attribute
							echo $setting_tab->is_selected ? 'active' : '';

							?>
						>

							<?php do_action( 'automator_settings_' . $tab_key . '_tab' ); ?>

						</uas-tab-panel>

						<?php

					}
				}

				?>

			</uas-tabs>

		</div>

	</div>
</uap-d-site-auth>
