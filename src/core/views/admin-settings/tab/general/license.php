<?php

namespace Uncanny_Automator;

/**
 * Improve Automator
 * Settings > General > License
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 */

?>

<div class="uap-settings-panel">
	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<?php esc_html_e( 'License', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

			<?php do_action( 'automator_settings_general_license_content' ); ?>

		</div>

	</div>

</div>
