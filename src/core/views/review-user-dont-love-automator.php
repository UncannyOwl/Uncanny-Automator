<?php namespace Uncanny_Automator; ?>

<div id="uap-review-banner-negative" class="uap notice" style="display: none">

	<uo-alert
		heading="<?php echo esc_attr_x( "We're sorry to hear that you're not finding Uncanny Automator useful.", 'Reviews banner', 'uncanny-automator' ); ?>"
		type="white"
	>
		<uo-button 
			href="<?php echo esc_url( $vars['url_close_button'] ); ?>"

			data-action="hide-banner-on-click"

			slot="top-right-icon" 
			color="transparent" 
			size="small"
		>
			<uo-icon id="times"></uo-icon>
		</uo-button>

		<p>
			<?php echo esc_html_x( 'We would love a chance to make it a better fit for you. Could you take a minute and let us know what we can do better?', 'Reviews banner', 'uncanny-automator' ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				href="<?php echo esc_url( $vars['url_feedback'] ); ?>"
				target="_blank"

				data-action="hide-banner-on-click"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'Feedback', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['url_close_button'] ); ?>"

				color="secondary"
				data-action="hide-banner-on-click"
			>
				<?php echo esc_html_x( 'No, thanks', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
