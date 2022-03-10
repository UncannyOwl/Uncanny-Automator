<?php

namespace Uncanny_Automator;

/**
 * General
 * Settings > General
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $general_tabs         Array with the list of tabs
 * $current_general_tab  The ID of the current integration
 * $layout_version  The UI version, either "default" or "focus"
 */

?>

<uo-tabs direction="column" parameter="general">

	<?php

	// Add navigation (tabs)
	// But don't add them in the "Focus" mode
	if ( $layout_version !== 'focus' ) {

		// Create tabs
		foreach ( $general_tabs as $tab_key => $general_tab ) {

			?>

			<uo-tab
				id="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $general_tab->is_selected ? 'active' : '';

				?>

				<?php

				// IF this tab has a status
				echo ! empty( $general_tab->status ) ? 'status="' . esc_attr( $general_tab->status ) . '"' : '';

				?>

				<?php

				// IF the current tab is NOT selected,
				// and if the content DOESN'T have to be preloaded
				// THEN add the href attribute, which will redirect the user to another page
				if ( ! $general_tab->is_selected && ! $general_tab->preload ) {
					?>

					href="<?php echo esc_url( Admin_Settings_General::utility_get_general_page_link( $tab_key ) ); ?>"

					<?php
				}

				?>
			>
				<?php

				// Check if it has an icon
				if ( isset( $general_tab->icon ) ) {

					?>

					<uo-icon
						id="<?php echo esc_attr( $general_tab->icon ); ?>"
					></uo-icon>

					<?php
				}

				?>

				<?php echo esc_html( $general_tab->name ); ?>
			</uo-tab>

			<?php

		}

	}

	// Add tab panels
	foreach ( $general_tabs as $tab_key => $general_tab ) {
		// Check if we have to load the content
		// The content will load if one of these conditions are meet
		// 1. If the tab is selected
		// 2. If the content should be preloaded
		if ( $general_tab->is_selected || $general_tab->preload ) {

			?>

			<uo-tab-panel
				id="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $general_tab->is_selected ? 'active' : '';

				?>
			>

				<?php do_action( 'automator_settings_general_' . $tab_key . '_tab' ); ?>

			</uo-tab-panel>

			<?php

		}
	}

	?>

</uo-tabs>
