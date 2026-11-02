<?php
/**
 * Model Context Protocol settings tabs.
 *
 * @since 7.6
 * @package Uncanny_Automator
 *
 * Variables:
 * $model_context_protocol_tabs Array with the list of tabs.
 * $layout_version              The UI version, either "default" or "focus".
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$focus_active_value = '';
if ( 'focus' === $layout_version ) {
	foreach ( $model_context_protocol_tabs as $tab_key => $model_context_protocol_tab ) {
		if ( $model_context_protocol_tab->is_selected ) {
			$focus_active_value = (string) $tab_key;
			break;
		}
	}
}

?>

<uas-tabs orientation="vertical"<?php echo '' !== $focus_active_value ? ' value="' . esc_attr( $focus_active_value ) . '"' : ''; ?>>

	<?php
	if ( 'focus' !== $layout_version ) {
		foreach ( $model_context_protocol_tabs as $tab_key => $model_context_protocol_tab ) {
			?>

			<uas-tab value="<?php echo esc_attr( $tab_key ); ?>" <?php echo $model_context_protocol_tab->is_selected ? 'active' : ''; ?>
				href="<?php echo esc_url( Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( $tab_key ) ); ?>"
			>
				<?php if ( isset( $model_context_protocol_tab->icon ) ) { ?>
					<uas-icon code="<?php echo esc_attr( $model_context_protocol_tab->icon ); ?>"></uas-icon>
				<?php } ?>

				<?php echo esc_html( $model_context_protocol_tab->name ); ?>
			</uas-tab>

			<?php
		}
	}

	foreach ( $model_context_protocol_tabs as $tab_key => $model_context_protocol_tab ) {
		if ( $model_context_protocol_tab->is_selected || $model_context_protocol_tab->preload ) {
			?>

			<uas-tab-panel slot="panel" name="<?php echo esc_attr( $tab_key ); ?>" <?php echo $model_context_protocol_tab->is_selected ? 'active' : ''; ?>>
				<?php do_action( 'automator_settings_model_context_protocol_' . $tab_key . '_tab' ); ?>
			</uas-tab-panel>

			<?php
		}
	}
	?>

</uas-tabs>
