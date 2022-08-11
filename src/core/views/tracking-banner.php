<?php namespace Uncanny_Automator; ?>

<div class="uap notice">

	<uo-alert
		heading="<?php echo esc_attr_x( 'Make Automator work for YOU!', 'Reviews banner', 'uncanny-automator' ); ?>"
		type="white"
		custom-icon
	>
		<uo-button
			href="<?php echo esc_url_raw( $url_remind_later ); ?>"

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
			<?php printf( esc_attr_x( 'We make decisions about which plugin integrations to develop based on how many sites are using them. Make sure your site counts by providing anonymous usage information.', 'Reviews banner', 'uncanny-automator' ) ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				href="<?php echo esc_url_raw( $url_send_review ); ?>"
				data-action="allow-tracking"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php
				/* translators: Non-personal infinitive verb */
				echo esc_attr_x( "I'm in!", 'Reviews banner', 'uncanny-automator' );
				?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url_raw( $url_remind_later ); ?>"
				color="secondary"
				data-action="maybe-later"
			>
				<?php echo esc_html_x( 'No thanks', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
