<div class="notice" id="uap-review-banner" style="display: none">

	<div class="uap">
		<div class="uap-review-banner">
			<div class="uap-review-banner-left">
				<div class="uap-review-banner__robot">
					<img
						src="<?php echo \uncanny_automator\Utilities::automator_get_media( 'robot-feedback.svg' ); ?>">
				</div>
			</div>
			<div class="uap-review-banner-right">
				<div class="uap-review-banner__title">
					<?php _ex( "Make Automator work for YOU!", 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__description">
					<?php _ex( "We make decisions about which plugin integrations to develop based on how many sites are using them.  Make sure your site counts by providing anonymous usage information.  ", 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__actions">
					<a href="<?php echo $url_send_review; ?>"
					   class="uap-track-banner__action uap-review-banner__action--primary" data-action="allow-tracking">
						<?php
						/* translators: Non-personal infinitive verb */
						_ex( 'I\'m in!', 'Reviews banner', 'uncanny-automator' );
						?>
					</a>

					<a href="<?php echo $url_remind_later; ?>"
					   class="uap-track-banner__action uap-review-banner__action--secondary" data-action="maybe-later">
						<?php _ex( 'No thanks', 'Reviews banner', 'uncanny-automator' ); ?>
					</a>

				</div>
				<a href="<?php echo $url_remind_later; ?>" id="uap-tracking-banner__close">
					<div class="uap-review-banner__close-tooltip"
						 uap-tooltip-admin="<?php _ex( 'Hide forever', 'Reviews banner', 'uncanny-automator' ); ?>"
						 uap-flow-admin="left"></div>
					<div class="uap-review-banner__close-icon"></div>
				</a>
			</div>
		</div>
	</div>

</div>
