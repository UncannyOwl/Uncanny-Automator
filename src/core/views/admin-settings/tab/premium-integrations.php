<?php

/**
 * App integrations
 * Settings > App integrations
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $user_can_use_premium_integrations  TRUE if the user can use the premium integrations
 * $integrations_tabs                  Array with the list of tabs
 * $current_integration                The ID of the current integration
 * $current_integration_tab            The current integration tab object
 * $upgrade_to_pro_url                 URL to upgrade to Automator Pro
 * $credits_article_url                URL to an article with information about the credits
 * $connect_site_url                   URL to connect the site to automatorplugin.com
 * $layout_version  The UI version, either "default" or "focus"
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// Sort integrations alphabetically by name (previously handled by the tabs' order="title").
uasort(
	$integrations_tabs,
	function ( $a, $b ) {
		$a_name = is_object( $a ) ? $a->name : $a['name'];
		$b_name = is_object( $b ) ? $b->name : $b['name'];
		return strcasecmp( (string) $a_name, (string) $b_name );
	}
);

// On the landing page (no integration selected) the tabs component activates the first tab; pair the "none selected" panel with it so the placeholder shows.
$first_integration_key = ! empty( $integrations_tabs ) ? (string) array_key_first( $integrations_tabs ) : '';

// In "Focus" mode the tab strip is hidden, so pass the active integration as the controlled value so its panel (or the credits message) still shows.
$focus_active_value = '';
if ( 'focus' === $layout_version ) {
	$focus_active_value = ! empty( $current_integration ) ? (string) $current_integration : $first_integration_key;
}

?>

<uas-tabs orientation="vertical"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>>

	<?php

	// Add navigation (tabs)
	// But don't add them in the "Focus" mode
	if ( 'focus' !== $layout_version ) {

		// Create tabs
		foreach ( $integrations_tabs as $tab_key => $integration_tab ) {

			$integration_tab = (object) $integration_tab;

			if ( empty( $integration_tab->preload ) ) {
				$integration_tab->preload = false;
			}

			?>

			<uas-tab
				value="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $integration_tab->is_selected ? 'active' : '';

				?>

				<?php

				// The href is the tab's canonical URL. It lets an in-place switch update the address bar, and lets a non-rendered tab navigate with a full page load.
				?>

				href="<?php echo esc_url( Admin_Settings_Premium_Integrations::utility_get_premium_integrations_page_link( $tab_key ) ); ?>"

				<?php

				// Check if there is an status
				if ( ! empty( $integration_tab->status ) ) {

					?>

					status="<?php echo esc_attr( $integration_tab->status ); ?>"

					<?php

				}

				?>
			>
				<?php

				// Check if it has an icon
				if ( isset( $integration_tab->icon ) ) {
					?>

					<uap-d-integrations-icon
						code="<?php echo esc_attr( $integration_tab->icon ); ?>"
						size="xs"
					></uap-d-integrations-icon>

					<?php
				}

				?>

				<?php echo esc_html( $integration_tab->name ); ?>

				<?php
				// Add a badge if the tab is a third party integration
				if ( $integration_tab->is_third_party ) :
					?>

					<uas-chip
						size="xs"
						label="<?php echo esc_attr_x( '3rd-party', 'Integration settings', 'uncanny-automator' ); ?>"
						color="info"
						filled
					></uas-chip>

				<?php endif; ?>
			</uas-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $integrations_tabs as $tab_key => $integration_tab ) {

		$integration_tab = (object) $integration_tab;

		// Check if we have to load the content
		// The content will load if one of these conditions are meet
		// 1. If the tab is selected
		// 2. If the content should be preloaded
		if ( $integration_tab->is_selected || $integration_tab->preload ) {

			?>

			<uas-tab-panel
				slot="panel"
				name="<?php echo esc_attr( $tab_key ); ?>"

				<?php

				// IF the tab is selected, then add the "active" attribute
				echo $integration_tab->is_selected ? 'active' : '';

				?>
			>

				<?php do_action( 'automator_settings_premium_integrations_' . $tab_key . '_tab' ); ?>

			</uas-tab-panel>

			<?php

		}
	}

	// Current integration is empty so we're on the landing page.
	if ( empty( $current_integration_tab ) || ! is_object( $current_integration_tab ) ) {
		// Load "None selected" panel
		include Utilities::automator_get_view( 'admin-settings/tab/premium-integrations/none-selected.php' );
	} else {
		// We are on a specific integration page.

		// Show message if not connected and integration requires credits.
		if ( ! $user_can_use_premium_integrations && $current_integration_tab->requires_credits ) {
			include Utilities::automator_get_view( 'admin-settings/tab/premium-integrations/not-connected.php' );
		}
	}
	?>

</uas-tabs>
