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

	<?php do_action( 'automator_settings_advanced_tab_view', self::SETTINGSGROUP ); ?>

	<div class="uap-settings-panel-content-separator"></div>

	<input type="hidden" name="<?php esc_attr_e( self::SETTINGSGROUP ); ?>'_settings_timestamp" value="<?php esc_attr_e( time() ); ?>" >

	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
	</uo-button>

</form>



