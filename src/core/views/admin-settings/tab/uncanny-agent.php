<?php

namespace Uncanny_Automator;

/**
 * Uncanny Agent
 * Settings > Uncanny Agent
 *
 * @since   7.0
 * @version 1.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $uncanny_agent_tabs         Array with the list of tabs
 * $current_uncanny_agent_tab  The ID of the current tab
 * $layout_version             The UI version, either "default" or "focus"
 */

?>

<uo-tabs direction="column" parameter="uncanny-agent">

	<?php
	if ( 'focus' !== $layout_version ) {

		foreach ( $uncanny_agent_tabs as $tab_key => $uncanny_agent_tab ) {
			?>

			<uo-tab id="<?php echo esc_attr( $tab_key ); ?>" <?php echo $uncanny_agent_tab->is_selected ? 'active' : ''; ?>

				<?php echo ! empty( $uncanny_agent_tab->status ) ? 'status="' . esc_attr( $uncanny_agent_tab->status ) . '"' : ''; ?>

				<?php if ( ! $uncanny_agent_tab->is_selected && ! $uncanny_agent_tab->preload ) { ?>

					href="<?php echo esc_url( Admin_Settings_Uncanny_Agent::utility_get_uncanny_agent_page_link( $tab_key ) ); ?>"

				<?php } ?>

			><!--uo-tab /> -->

				<?php if ( isset( $uncanny_agent_tab->icon ) ) { ?>

					<uo-icon id="<?php echo esc_attr( $uncanny_agent_tab->icon ); ?>"></uo-icon>

					<?php } ?>

				<?php echo esc_html( $uncanny_agent_tab->name ); ?>

			</uo-tab>

			<?php

		}
	}

	// Add tab panels
	foreach ( $uncanny_agent_tabs as $tab_key => $uncanny_agent_tab ) {

		if ( $uncanny_agent_tab->is_selected || $uncanny_agent_tab->preload ) {
			?>

			<uo-tab-panel id="<?php echo esc_attr( $tab_key ); ?>"

				<?php echo $uncanny_agent_tab->is_selected ? 'active' : ''; ?>
			><!--uo-tab-panel />-->

				<?php do_action( 'automator_settings_uncanny_agent_' . $tab_key . '_tab' ); ?>

			</uo-tab-panel>

			<?php

		}
	}

	?>

</uo-tabs>
