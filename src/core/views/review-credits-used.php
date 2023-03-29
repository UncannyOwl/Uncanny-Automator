<?php namespace Uncanny_Automator; ?>

<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php echo esc_attr_x( 'Great work connecting Uncanny Automator to other apps!', 'Reviews banner', 'uncanny-automator' ); ?>"
		type="white"
		custom-icon
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

		<img 
			slot="icon"
			src="<?php echo esc_url( Utilities::automator_get_asset( 'backend/dist/img/robot-feedback.svg' ) ); ?>"
			width="90px"
		>

		<p>
			<?php printf( esc_attr_x( 'You just used your first %1$s free app credits 🙌 with Uncanny Automator. Are you finding Automator useful?', 'Reviews banner', 'uncanny-automator' ), absint( $vars['credits_used'] ) ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				id="uap-review-banner-btn-positive"

				data-action="hide-banner-on-click"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'I love it', 'Reviews banner', 'uncanny-automator' ); ?> 😍
			</uo-button>

			<uo-button
				id="uap-review-banner-btn-negative"

				color="secondary"
				data-action="hide-banner-on-click"
			>
				<?php echo esc_html_x( 'Not really...', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
