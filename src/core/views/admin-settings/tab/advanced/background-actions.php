<?php
/**
 * Advanced
 * Settings > Advanced > Background actions. Frozen since 8.0.0: nothing on
 * this panel saves, so it has no form.
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="uap-settings-panel">

	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<?php esc_html_e( 'Background actions', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content">

		<?php do_action( 'automator_settings_advanced_tab_view', self::SETTINGSGROUP ); ?>

		</div>
	</div>

</div><!--.uap-settings-panel-->
