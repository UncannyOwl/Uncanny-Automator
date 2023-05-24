<?php namespace Uncanny_Automator; ?>

<div id="uap-review-banner-positive" class="uap notice" style="display: none">

	<uo-alert
		heading="<?php echo esc_attr_x( 'Fantastic!', 'Reviews banner', 'uncanny-automator' ); ?> 🎉"
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
			<?php echo esc_html_x( 'If you can spare 2 minutes, it would mean a lot to our team if you could leave us a review on WordPress.org. Please also let us know about any feature requests in your review; we always read them!', 'Reviews banner', 'uncanny-automator' ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				href="<?php echo esc_url( $vars['url_wordpress'] ); ?>"
				target="_blank"

				data-action="hide-banner-on-click"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'OK, you deserve it!', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['url_maybe_later'] ); ?>"

				color="secondary"
				data-action="hide-banner-on-click"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'Maybe later', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['url_already_did'] ); ?>"

				color="secondary"
				data-action="hide-banner-on-click"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'I already did', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
