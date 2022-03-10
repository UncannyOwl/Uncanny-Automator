<?php

namespace Uncanny_Automator;

/**
 * Feedback
 * Settings > General > Improve Automator > Feedback
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 */

?>

<div class="uap-settings-panel-content-subtitle">
	<?php esc_html_e( 'Have feedback or requests?', 'uncanny-automator' ); ?>
</div>

<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
	<?php

		printf(
			/* translators: 1. Trademarked term */
			esc_html__( 'We value your input and we use customer feedback to prioritize new integrations and features in %1$s releases. Let us know how we can make %1$s a better product!', 'uncanny-automator' ),
			'Uncanny Automator'
		);

		?>
</div>

<div class="uap-settings-panel-content-paragraph">

	<uo-button
		href="<?php echo esc_url( automator_utm_parameters( 'https://automatorplugin.com/feedback/', 'settings', 'improve_automator-send_feedback' ) ); ?>"
		target="_blank"
	>
		<?php esc_html_e( 'Send feedback', 'uncanny-automator' ); ?>
	</uo-button>

</div>

<div class="uap-settings-panel-content-separator"></div>
