<?php if ( ! defined( 'ABSPATH' ) ) {
	return;} ?>

<form method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon integration="ZOOM"></uo-icon> <?php esc_html_e( 'Zoom Meetings', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<?php

				// Check what button we have to add
				if ( ! $this->is_connected ) {

					?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Zoom Meetings', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Automatically register users for Zoom Meetings when they complete actions on your site, such as completing a course, filling out a form, or even simply clicking a button!', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add an attendee to a meeting', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add the user to a meeting', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove an attendee to a meeting', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove the user from a meeting', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>
					<?php
				}

				$this->display_alerts();

				if ( ! $this->is_connected ) {

					$this->alert_html(
						array(
							'heading' => esc_html__( 'Setup instructions', 'uncanny-automator' ),
							'content' => sprintf(
								esc_html__( "Connecting to Zoom requires setting up a Server-to-Server OAuth app and getting 3 values from inside your account. It's really easy, we promise! Visit our %1\$s for simple instructions.", 'uncanny-automator' ),
								'<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/zoom/', 'settings', 'zoom_meeting-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Knowledge Base article', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
							),
						)
					);

				}

				$hide_fields = $this->is_connected ? true : '';

				$this->text_input_html(
					array(
						'id'       => 'uap_automator_zoom_api_account_id',
						'value'    => $this->account_id,
						'label'    => esc_html__( 'Account ID', 'uncanny-automator' ),
						'required' => true,
						'class'    => 'uap-spacing-top',
						'hidden'   => $hide_fields,
						'disabled' => $hide_fields,
					)
				);

				$this->text_input_html(
					array(
						'id'       => 'uap_automator_zoom_api_client_id',
						'value'    => $this->api_key,
						'label'    => esc_html__( 'Client ID', 'uncanny-automator' ),
						'required' => true,
						'class'    => 'uap-spacing-top',
						'hidden'   => $hide_fields,
						'disabled' => $hide_fields,
					)
				);

				$this->text_input_html(
					array(
						'id'       => 'uap_automator_zoom_api_client_secret',
						'value'    => $this->api_secret,
						'label'    => esc_html__( 'Client secret', 'uncanny-automator' ),
						'required' => true,
						'class'    => 'uap-spacing-top',
						'hidden'   => $hide_fields,
						'disabled' => $hide_fields,
					)
				);

				$this->text_input_html(
					array(
						'id'       => 'uap_automator_zoom_api_settings_version',
						'value'    => '3',
						'hidden'   => true,
						'disabled' => true,
					)
				);

				if ( $this->is_connected ) {

					$this->alert_html(
						array(
							'heading' => esc_html__( 'Uncanny Automator only supports connecting to one Zoom Meetings account.', 'uncanny-automator' ),
						)
					);
				}
				?>
			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<div class="uap-settings-panel-bottom-left">

					<?php

					// Check what button we have to add
					if ( $this->is_connected ) {
						?>

							<div class="uap-settings-panel-user">

								<div class="uap-settings-panel-user__avatar">
									<?php echo esc_html( strtoupper( $this->user['first_name'][0] ) ); ?>
								</div>

								<div class="uap-settings-panel-user-info">
									<div class="uap-settings-panel-user-info__main">
										<?php echo esc_html( $this->user['first_name'] . ' ' . $this->user['last_name'] ); ?>
										<uo-icon integration="ZOOM"></uo-icon>
									</div>
									<div class="uap-settings-panel-user-info__additional">
										<?php echo esc_html( $this->user['email'] ); ?>
									</div>
								</div>
							</div>

							<?php


					} else {

						?>

						<uo-button
							type="submit"
						>
							<?php esc_html_e( 'Connect Zoom Meetings account', 'uncanny-automator' ); ?>
						</uo-button>

						<?php

					}

					?>

				</div>

				<div class="uap-settings-panel-bottom-right">

					<?php if ( $this->is_connected ) { ?>

						<uo-button
							href="<?php echo esc_url( $disconnect_url ); ?>"
							color="danger"
						>
							<uo-icon id="right-from-bracket"></uo-icon>

							<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
						</uo-button>

						<?php
					}
					?>
				</div>

		</div>

	</div>
</form>
