<?php

namespace Uncanny_Automator;

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
 */

?>

<uo-tab-panel active>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-placeholder" has-arrow>

			<uo-icon id="bolt"></uo-icon>

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

</uo-tab-panel>
