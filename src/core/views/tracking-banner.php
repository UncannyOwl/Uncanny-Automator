<div class="notice" id="uap-review-banner" style="display: none">

	<div class="uap">
		<div class="uap-review-banner">
			<div class="uap-review-banner-left">
				<div class="uap-review-banner__robot">
					<img
						alt="Robot feedback"
						src="<?php echo esc_url_raw( \uncanny_automator\Utilities::automator_get_media( 'robot-feedback.svg' ) ); ?>">
				</div>
			</div>
			<div class="uap-review-banner-right">
				<div class="uap-review-banner__title">
					<?php echo esc_attr_x( 'Make Automator work for YOU!', 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__description">
					<?php echo esc_attr_x( 'We make decisions about which plugin integrations to develop based on how many sites are using them.  Make sure your site counts by providing anonymous usage information.  ', 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__actions">
					<a href="<?php echo esc_url_raw( $url_send_review ); ?>"
					   class="uap-track-banner__action uap-review-banner__action--primary" data-action="allow-tracking">
						<?php
						/* translators: Non-personal infinitive verb */
						echo esc_attr_x( "'I'm in!'", 'Reviews banner', 'uncanny-automator' );
						?>
					</a>

					<a href="<?php echo esc_url_raw( $url_remind_later ); ?>"
					   class="uap-track-banner__action uap-review-banner__action--secondary" data-action="maybe-later">
						<?php echo esc_attr_x( 'No thanks', 'Reviews banner', 'uncanny-automator' ); ?>
					</a>

				</div>
				<a href="<?php echo esc_url_raw( $url_remind_later ); ?>" id="uap-tracking-banner__close">
					<div class="uap-review-banner__close-tooltip"
						 uap-tooltip-admin="<?php echo esc_attr_x( 'Hide forever', 'Reviews banner', 'uncanny-automator' ); ?>"
						 uap-flow-admin="left"></div>
					<div class="uap-review-banner__close-icon"></div>
				</a>
			</div>
		</div>
	</div>

</div>
