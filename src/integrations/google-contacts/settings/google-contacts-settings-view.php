<?php $user_info = $vars['user_info']; ?>

<div class="uap-settings-panel">

	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-title">
			<uo-icon integration="GOOGLE_CONTACTS"></uo-icon>
			<?php echo esc_html_x( 'Google Contacts', 'Google Contacts', 'uncanny-automator' ); ?>
		</div>

		<?php if ( automator_filter_has_var( 'auth_error' ) ) { ?>
			<uo-alert heading="<?php echo esc_attr( _x( 'Authentication error', 'Google Contacts', 'uncanny-automator' ) ); ?>" type="error" class="uap-spacing-top">
				<?php esc_html_x( 'An error has occured while connecting your account to Google Contacts.', 'Google Contacts', 'uncanny-automator' ); ?>
				<?php echo esc_html( automator_filter_input( 'auth_error' ) ); ?>
			</uo-alert>
		<?php } ?>

		<?php if ( automator_filter_has_var( 'auth_success' ) && ! empty( $user_info['email'] ) ) { ?>
			<uo-alert 
				<?php /* translators: Google Contacts account connected message */ ?>
				heading="<?php echo esc_attr( sprintf( _x( 'Your account "%s" has been connected successfully!', 'Google Contacts', 'uncanny-automator' ), esc_html( $user_info['email'] ) ) ); ?>" 
				type="success" class="uap-spacing-top">
			</uo-alert>
		<?php } ?>

		<?php $this->display_alerts(); ?>

		<?php if ( false === $vars['is_connected'] ) { ?>

			<div class="uap-settings-panel-content">
				<div class="uap-settings-panel-content-subtitle">
					<?php echo esc_html_x( 'Connect Uncanny Automator to Google Contacts', 'Google Contacts', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
					<?php
						echo esc_html_x(
							'Connect Uncanny Automator to Google Contacts to automatically create contacts when users perform actions like submitting forms, joining groups and making purchases on your site. ',
							'Google Contacts',
							'uncanny-automator'
						);
					?>
				</div>

				<p>
					<strong>
					<?php
						echo esc_html_x(
							'Activating this integration will enable the following for use in your recipes:',
							'Google Contacts',
							'uncanny-automator'
						);
					?>
					</strong>
				</p>

				<ul>
					<li>
						<uo-icon id="bolt"></uo-icon> 
						<strong><?php echo esc_html_x( 'Action:', 'Google Contacts', 'uncanny-automator' ); ?></strong>
							<?php echo esc_html_x( 'Create or update a contact', 'Google Contacts', 'uncanny-automator' ); ?>
					</li>
				</ul>
			</div>

		<?php } ?>

		<?php if ( true === $vars['is_connected'] ) { ?>
			<div class="uap-settings-panel-content">
				<uo-alert heading="
				<?php
					echo esc_attr_x( 'Uncanny Automator only supports connecting to one Google Contacts account at a time.', 'Google Contacts', 'uncanny-automator' );
				?>
				"></uo-alert>	
			</div>
		<?php } ?>


	</div><!--.uap-settings-panel-top-->

	<?php if ( false === $vars['is_connected'] ) { ?>
		<div class="uap-settings-panel-bottom" has-arrow>
			<div class="uap-settings-panel-bottom-left">
				<uo-button
					class="uap-settings-button-google"
					href="<?php echo esc_url_raw( $vars['auth_url'] ); ?>"
				>
					<uo-icon id="google"></uo-icon>
					<?php echo esc_html_x( 'Sign in with Google', 'Google Contacts', 'uncanny-automator' ); ?>
				</uo-button>
			</div><!--.uap-settings-panel-bottom-left -->
		</div>
	<?php } ?>

	<?php if ( true === $vars['is_connected'] ) { ?>
		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">
				<div class="uap-settings-panel-user">

					<?php if ( ! empty( $user_info['avatar_uri'] ) ) { ?>
						<div class="uap-settings-panel-user__avatar">
							<img alt="<?php echo esc_attr( $user_info['name'] ); ?>" src="<?php echo esc_url( $user_info['avatar_uri'] ); ?>" />
						</div><!--.uap-settings-panel-user__avatar-->
					<?php } ?>

					<?php if ( ! empty( $user_info['name'] ) && ! empty( $user_info['email'] ) ) { ?>
						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">

								<?php echo esc_html( $user_info['name'] ); ?>

								<uo-icon id="google"></uo-icon>

							</div>

							<div class="uap-settings-panel-user-info__additional">

								<?php echo esc_html( $user_info['email'] ); ?>

							</div>

						</div> <!--uap-settings-panel-user-info-->
					<?php } ?>
				</div> <!--.uap-settings-panel-user-->
			</div> <!--.uap-settings-panel-bottom-left -->

			<div class="uap-settings-panel-bottom-right">
				<uo-button
					color="danger"
					href="<?php echo esc_url_raw( $vars['disconnect_url'] ); ?>"
				>
					<uo-icon id="sign-out"></uo-icon>
					<?php echo esc_html_x( 'Disconnect', 'Google Contacts', 'uncanny-automator' ); ?>
				</uo-button>
			</div><!--.uap-settings-panel-bottom-right -->

		</div><!--.uap-settings-panel-bottom-->
	<?php } ?>

</div><!--.uap-settings-panel-->
