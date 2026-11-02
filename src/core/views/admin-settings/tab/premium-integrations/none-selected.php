<?php
/**
 * App integrations
 * Settings > App integrations > None selected
 *
 * Tab panel displayed when the user can access to the settings,
 * but haven't selected an integration yet
 *
 * @since   3.8
 * @version 3.8
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 *
 * Variables:
 * $first_integration_key  The value of the first tab, which the tabs component activates on the landing page
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<uas-tab-panel slot="panel" name="<?php echo esc_attr( $first_integration_key ); ?>" active>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-placeholder" has-arrow>

			<uas-icon code="bolt"></uas-icon>

			<div class="uap-settings-panel-title">
				<?php esc_html_e( 'Select an app integration', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-settings-panel-content-paragraph">

					<?php esc_html_e( 'Choose an app integration to connect an account or manage other settings.', 'uncanny-automator' ); ?>

				</div>

			</div>
		</div>

	</div>

</uas-tab-panel>
