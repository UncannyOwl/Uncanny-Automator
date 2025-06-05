<?php use Uncanny_Automator\Utilities; ?>
<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php echo esc_attr_x( 'Review your data management settings', 'Reviews banner', 'uncanny-automator' ); ?>"
		type="white"
		custom-icon
	>
		<uo-button
			href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=automator_dismiss_log_notification&nonce=' . wp_create_nonce( 'dismiss_log_notification' ) ) ); ?>"
			slot="top-right-icon"
			color="transparent"
			size="small"
		>
			<uo-icon id="xmark"></uo-icon>
		</uo-button>

		<img
			slot="icon"
			src="<?php echo esc_url( Utilities::automator_get_asset( 'build/img/credits-left-hundred.svg' ) ); ?>"
			width="90px"
		>

		<p>
			<?php
			$threshold_size = apply_filters( 'automator_display_log_size_notification_threshold_size_in_mb', 1024 );
			$threshold_size = \Uncanny_Automator\Prune_Logs::format_number_in_kb_mb_gb( $threshold_size );
			?>
			<?php
			printf(
			/* translators: %s: Size threshold (e.g., "1 GB") */
				esc_html_x( 'U-bot has been working hard on your automations and noticed that the size of your logs now exceeds %s. Consider removing records you no longer need by clicking the button below to set up data management options.', 'Reviews banner', 'uncanny-automator' ),
				esc_html( $threshold_size )
			);
			?>
		</p>

		<div class="uap-spacing-top">

			<uo-button
				href="<?php echo esc_url( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=logs' ) ); ?>"
				id="uap-review-banner-btn-negative"
				color="secondary"
				data-action="hide-notification-on-click"
				data-notification-type="0"
			>
				<?php echo esc_html_x( 'Data management', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

		</div>
	</uo-alert>

</div>
