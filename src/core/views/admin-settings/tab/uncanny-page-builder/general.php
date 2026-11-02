<?php
/**
 * Uncanny Page Builder - General
 * Settings > Uncanny Page Builder > General
 *
 * @since   7.5
 * @version 1.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="POST">

	<?php settings_fields( Admin_Settings_Uncanny_Page_Builder_General::SETTINGS_GROUP ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<?php if ( $save_failed ) { ?>
				<uo-alert
					class="uap-spacing-bottom"
					type="error"
					heading="<?php echo esc_attr_x( 'Settings could not be saved. Please try again.', 'settings save error', 'uncanny-automator' ); ?>"
				></uo-alert>
			<?php } ?>

			<div class="uap-settings-panel-title">
				<?php echo esc_html_x( 'Uncanny Page Builder', 'settings panel title', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-field uap-spacing-top--small">
					<?php echo esc_html_x( 'Your AI builds and updates pages in the page builder you already use: Gutenberg, Spectra, Elementor, Divi or WPBakery. No page builder? Uncanny Page Builder is built in: fast, light and AI-native. Describe the page you want and your AI designs the layout, writes the content and builds it in clean HTML and CSS that you own.', 'page builder feature description', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-field uap-spacing-top">
					<?php echo esc_html_x( 'Switch Uncanny Page Builder on to add the Page Builder menu to the sidebar and its launcher button to the WordPress post editor. Pages already built with Uncanny Page Builder are unaffected by this setting and remain editable.', 'page builder setting description', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-field uap-spacing-top">
					<uo-switch
						id="<?php echo esc_attr( Admin_Settings_Uncanny_Page_Builder_General::ENABLED_KEY ); ?>"
						<?php checked( $is_enabled ); ?>
						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>"
					></uo-switch>
				</div>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">
				<uo-button type="submit">
					<?php echo esc_html_x( 'Save settings', 'settings save button', 'uncanny-automator' ); ?>
				</uo-button>
			</div>

		</div>

	</div><!--.uap-settings-panel-->

</form>
