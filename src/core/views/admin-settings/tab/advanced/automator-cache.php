<?php

namespace Uncanny_Automator;

/**
 * Advanced
 * Settings > General > Advanced
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

?>

<form method="POST" action="options.php">

	<?php settings_fields( self::SETTINGSGROUP ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<?php esc_html_e( 'Automator cache', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php do_action( 'automator_settings_advanced_tab_view_automator_cache', self::SETTINGSGROUP ); ?>

			</div>
		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">
				<uo-button type="submit">
					<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>
				</uo-button>
			<div>

		</div>
	
	</div><!--.uap-settings-panel-->

</form>



