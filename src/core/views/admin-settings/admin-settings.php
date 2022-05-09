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

?>

<div  id="uap-settings" class="uap uap-settings" >

	<?php

	// Hide the header in the "Focus" UI version
	if ( $layout_version !== 'focus' ) {
		?>
		 
		<div id="uap-settings-header" class="uap-settings-header">
			<div class="uap-settings-header__title">
				<?php esc_html_e( 'Settings', 'uncanny-automator' ); ?>
			</div>
		</div>

		<?php
	}

	do_action( 'automator_settings_header_after' );

	?>

	<div id="uap-settings-content" class="uap-settings-content">

		<uo-tabs
			tab="settings"
			class="uap-settings-content-main-tabs"
		>

			<?php

			// Add navigation (tabs)
			// But don't add them in the "Focus" mode
			if ( $layout_version !== 'focus' ) {

				// Create tabs
				foreach ( $tabs as $tab_key => $setting_tab ) {

					?>

					<uo-tab
						id="<?php echo esc_attr( $tab_key ); ?>"

						<?php

						// IF the tab is selected, then add the "active" attribute
						echo $setting_tab->is_selected ? 'active' : '';

						?>

						<?php

						// IF the current tab is NOT selected,
						// and if the content DOESN'T have to be preloaded
						// THEN add the href attribute, which will redirect the user to another page
						if ( ! $setting_tab->is_selected && ! $setting_tab->preload ) {
							?>

							href="<?php echo esc_url( Admin_Settings::utility_get_settings_page_link( $tab_key ) ); ?>"

							<?php
						}

						?>
					>
						<?php echo esc_html( $setting_tab->name ); ?>
					</uo-tab>

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

					<uo-tab-panel
						id="<?php echo esc_attr( $tab_key ); ?>"

						<?php

						// IF the tab is selected, then add the "active" attribute
						echo $setting_tab->is_selected ? 'active' : '';

						?>
					>

						<?php do_action( 'automator_settings_' . $tab_key . '_tab' ); ?>

					</uo-tab-panel>

					<?php

				}
			}

			?>

		</uo-tabs>

	</div>

</div>
