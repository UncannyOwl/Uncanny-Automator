<?php

namespace Uncanny_Automator;

/**
 * Logs
 * Settings > General > Logs
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
			<?php esc_html_e( 'Logs', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

			<?php do_action( 'automator_settings_general_logs_content' ); ?>

		</div>

	</div>

</div>
