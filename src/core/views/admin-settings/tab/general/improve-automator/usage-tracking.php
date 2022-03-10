<?php

namespace Uncanny_Automator;

/**
 * Usage tracking
 * Settings > General > Improve Automator > Usage tracking
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $is_usage_tracking_enabled TRUE is usage tracking is enabled
 */

?>

<form method="POST" action="options.php">

	<?php

	// Add setting fields
	settings_fields( 'uncanny_automator_improve_automator_usage_tracking' );

	?>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Allow usage tracking', 'uncanny-automator' ); ?>
	</div>

	<div class="uap-field uap-spacing-top--small">

		<uo-switch
			id="uap_automator_allow_tracking"
			<?php echo $is_usage_tracking_enabled ? 'checked' : ''; ?>

			status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"

			class="uap-spacing-top"
		></uo-switch>

		<div class="uap-field-description">
			<?php esc_html_e( "By allowing us to anonymously track usage data, we'll have a better idea of which integrations are most popular and where we should focus our development effort, as well as which WordPress configurations, themes and PHP versions we should test against.", 'uncanny-automator' ); ?>
		</div>

	</div>

	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
	</uo-button>

</form>

<div class="uap-settings-panel-content-separator"></div>
