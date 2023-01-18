<div class="uap">
	<div class="uap-upgrade-wrapper">
		<div class="uap-upgrade">

			<!-- Some plugins need an h1 to add their notices -->
			<h1 style="margin: 0; padding: 0;"></h1>

			<div class="uap-upgrade-header">
				<div class="uap-upgrade-header__logo">
					<img src="<?php echo esc_url( Uncanny_Automator\Utilities::automator_get_asset( 'backend/dist/img/logo-horizontal.svg' ) ); ?>" alt="Uncanny Automator logo">
				</div>
				<div class="uap-upgrade-header__title">
					<?php esc_html_e( 'Unlock everything that Uncanny Automator can do', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-upgrade-header__content">
					<?php esc_html_e( 'Uncanny Automator is the easiest and most powerful way to create automated workflows for your WordPress site. By upgrading to Pro, you unlock all of our integrations, all triggers, all actions, all features and all tokens, plus unlimited use of non-WordPress apps like Google Sheets, Slack, Zoom and more. You also get unlimited access to our premium support and new plugin features as soon as they\'re available.', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-upgrade__button">
					<a href="<?php echo esc_url( $pricing_link ); ?>" target="_blank">
						<?php esc_html_e( 'Upgrade to Pro', 'uncanny-automator' ); ?>
					</a>
				</div>
			</div>

			<table class="uap-upgrade-comparison-table">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Free', 'uncanny-automator' ); ?></th>
						<th><?php esc_html_e( 'Pro', 'uncanny-automator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( \Uncanny_Automator\Pro_Upsell::$feature as $group => $features ) { ?>

						<tr class="uap-upgrade-body-row--feature-category">
							<th colspan="3"><?php echo esc_html( $group ); ?></th>
						</tr>

						<?php foreach ( $features as $feature ) { ?>
							<tr class="uap-upgrade-body-row--feature">
								<th>

									<?php
									if ( ! empty( $feature['link'] ) ) {
										$link = add_query_arg(
										// UTM
											array(
												'utm_source'  => 'uncanny_automator',
												'utm_medium'  => 'upgrade_to_pro',
												'utm_content' => str_replace( '-', '_', sanitize_title( $feature['label'] ) ),
											),
											$feature['link']
										);

										?>

										<a href="<?php echo esc_url( $link ); ?>" target="_blank">
											<?php echo $feature['label']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<uo-icon id="external-link"></uo-icon>
										</a>

									<?php } else { ?>

										<?php echo $feature['label']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

									<?php } ?>

								</th>

								<td>
									<?php if ( $feature['free'] ) { ?>

										<uo-icon id="check"></uo-icon>

									<?php } else { ?>

										<uo-icon id="times"></uo-icon>

									<?php } ?>
								</td>

								<td>
									<uo-icon id="check"></uo-icon>
								</td>

							</tr>
						<?php } ?>
					<?php } ?>

				</tbody>
			</table>
			<div class="uap-upgrade-footer">
				<div class="uap-upgrade__button">
					<a href="<?php echo esc_url( $pricing_link ); ?>" target="_blank">
						<?php esc_html_e( 'Upgrade to Pro', 'uncanny-automator' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
