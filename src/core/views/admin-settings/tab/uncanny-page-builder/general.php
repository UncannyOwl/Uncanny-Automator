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
					<?php echo esc_html_x( 'Uncanny Page Builder is an AI page builder that runs on Uncanny Agent. Describe the page you need and Uncanny Agent builds it. Refine it through conversation, or fine-tune text and images right in the editor. Every page is clean HTML and CSS with no lock-in, so your pages stay yours.', 'page builder feature description', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-field uap-spacing-top">
					<?php echo esc_html_x( "If you don't want to use Uncanny Page Builder, you may disable it below. This removes the Page Builder menu from the sidebar and the Uncanny Page Builder launcher button from the WordPress post editor. Pages already built with Uncanny Page Builder are unaffected by this setting and remain editable with Uncanny Agent.", 'page builder setting description', 'uncanny-automator' ); ?>
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
