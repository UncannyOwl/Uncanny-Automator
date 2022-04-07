<div class="notice" id="uap-review-banner" style="display: none">

	<div class="uap">
		<div class="uap-review-banner">
			<div class="uap-review-banner-left">
				<div class="uap-review-banner__robot">
					<img
						alt="Robot feedback icon"
						src="<?php echo esc_url_raw( \uncanny_automator\Utilities::automator_get_media( 'robot-feedback.svg' ) ); ?>">
				</div>
			</div>
			<div class="uap-review-banner-right">
				<div class="uap-review-banner__title">
					<?php echo esc_html_x( "Automator's robot would love to get your opinion", 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__description">
					<?php echo esc_html_x( "The robot's favorite food is 5-star reviews!", 'Reviews banner', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-review-banner__actions">
					<a href="<?php echo esc_url_raw( $url_send_review ); ?>" target="_blank"
					   class="uap-review-banner__action uap-review-banner__action--primary" data-action="hide-forever">
						<?php

						/* translators: Non-personal infinitive verb */
						echo esc_html_x( 'Add my review', 'Reviews banner', 'uncanny-automator' );

						?>
					</a>

					<a href="<?php echo esc_url_raw( $url_send_feedback ); ?>" target="_blank"
					   class="uap-review-banner__action uap-review-banner__action--secondary"
					   data-action="hide-forever">
						<?php

						/* translators: Non-personal infinitive verb */
						echo esc_html_x( 'Send feedback', 'Reviews banner', 'uncanny-automator' );

						?>
					</a>

					<a href="<?php echo esc_url_raw( $url_remind_later ); ?>"
					   class="uap-review-banner__action uap-review-banner__action--secondary" data-action="maybe-later">
						<?php echo esc_html_x( 'Maybe later', 'Reviews banner', 'uncanny-automator' ); ?>
					</a>

					<div
						class="uap-review-banner__action uap-review-banner__action--anchor uap-review-banner__action--no-margin-right"
						data-action="hide-forever">
						<a href="<?php echo esc_url_raw( $url_hide_forever ); ?>"
						   class="uap-review-banner__action--anchor-border"><?php echo esc_html_x( "Nah, a robot doesn't have feelings anyway", 'Reviews banner', 'uncanny-automator' ); ?></a>
						<span
							class="uap-review-banner__disclaimer"><?php echo esc_html_x( '(Plot twist, it does)', 'Reviews banner', 'uncanny-automator' ); ?></span>
					</div>
				</div>
				<div id="uap-review-banner__close">
					<div class="uap-review-banner__close-tooltip"
						 uap-tooltip-admin="<?php echo esc_html_x( 'Hide forever', 'Reviews banner', 'uncanny-automator' ); ?>"
						 uap-flow-admin="left"></div>
					<div class="uap-review-banner__close-icon"></div>
				</div>
			</div>
		</div>
	</div>

</div>
