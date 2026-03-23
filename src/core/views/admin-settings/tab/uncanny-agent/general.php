<?php
/**
 * Uncanny Agent - General
 * Settings > Uncanny Agent > General
 *
 * @since   7.1
 * @version 1.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

?>

<form method="POST">

	<?php settings_fields( Admin_Settings_Uncanny_Agent_General::SETTINGS_GROUP ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<?php echo esc_html_x( 'Uncanny Agent', 'settings panel title', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-field uap-spacing-top--small">

					<?php echo esc_html_x( "Uncanny Agent is your made-for-WordPress AI assistant. It can analyze or answer questions about your users, posts, sales, courses and more. It can write blog posts, design pages and build and troubleshoot recipes. It's like having a dedicated WordPress helper at your fingertips.", 'agent feature description', 'uncanny-automator' ); ?>

					<uo-switch
						id="<?php echo esc_attr( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ); ?>"
						<?php echo $is_enabled ? 'checked' : ''; ?>

						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>"

						class="uap-spacing-top"
					></uo-switch>

					<div class="uap-field-description">
						<?php echo esc_html_x( "Keep Uncanny Agent enabled to help your team work faster. Uncanny Agent is available to Administrator users only.", 'agent feature description', 'uncanny-automator' ); ?>
					</div>

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
