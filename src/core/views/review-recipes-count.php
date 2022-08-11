<?php namespace Uncanny_Automator; ?>

<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php printf( esc_attr_x( 'Uncanny Automator has successfully {{completed %1$s recipes}}. That\'s really cool!', 'Reviews banner', 'uncanny-automator' ), absint( $vars['total_recipe_completion_count'] ) ); ?>"
		type="white"
		custom-icon
	>
		<uo-button 
			slot="top-right-icon" 

			href="<?php echo esc_url( $vars['url_close_button'] ); ?>"

			data-action="hide-banner-on-click"

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
			<?php echo esc_html_x( 'Are you enjoying Uncanny Automator?', 'Reviews banner', 'uncanny-automator' ); ?>
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
