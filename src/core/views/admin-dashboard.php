<?php
// Delete credit data transient on dashboard
delete_transient( 'automator_api_credit_data' );
delete_transient( 'automator_api_credits' );

// Create an array where we could save CSS classes that
// we will later add to the dashboard main container
$dashboard_css_classes = array();

// Check if the user is pro
if ( $dashboard->is_pro ) {
	$dashboard_css_classes[] = 'uap-dashboard--is-pro';
}

// Check if a site is connected to an account
if ( $dashboard->has_site_connected ) {
	$dashboard_css_classes[] = 'uap-dashboard--has-site-connected';
}

?>

<style>

	#wpfooter {
		position: relative;
	}

</style>

<div id="uap-dashboard" class="uap-dashboard <?php echo implode( ' ', $dashboard_css_classes ); ?>">

	<div class="uap-dashboard-header">
		<div class="uap-dashboard-header__title">
			<?php _e( 'Dashboard', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-dashboard-header-user">

			<?php

			// If a user is connected, then add the submenu with links
			if ( $dashboard->has_site_connected ) {

				?>

				<div class="uap-dropdown uap-dropdown--caret-right">
					<div class="uap-dropdown-toggle">
						<div class="uap-dashboard-header-user__avatar">
							<img src="<?php echo $dashboard->connected_user->avatar; ?>">
						</div>

						<div class="uap-dashboard-header-user__name">
							<?php echo $dashboard->connected_user->first_name; ?>
						</div>
					</div>
					<div class="uap-dropdown-menu">
						<a
							class="uap-dropdown-item"
							href="<?php echo $dashboard->connected_user->url->edit_profile; ?>"
							target="_blank"
						>
							<?php _e( 'My account', 'uncanny-automator' ); ?> <span
								class="uap-icon uap-icon--external-link-alt"></span>
						</a>
						<a
							class="uap-dropdown-item"
							href="<?php echo $dashboard->connected_user->url->connected_sites; ?>"
							target="_blank"
						>
							<?php _e( 'Manage sites', 'uncanny-automator' ); ?> <span
								class="uap-icon uap-icon--external-link-alt"></span>
						</a>
						<a
							class="uap-dropdown-item"
							href="<?php echo $dashboard->connected_user->url->disconnect_account; ?>"
						>
							<?php _e( 'Disconnect account', 'uncanny-automator' ); ?>
						</a>
					</div>
				</div>

			<?php } else { ?>

				<div class="uap-dashboard-header-user__avatar">
					<span class="uap-icon uap-icon--user-robot"></span>
				</div>

				<div class="uap-dashboard-header-user__name">
					<?php _e( 'Guest', 'uncanny-automator' ); ?>
				</div>

			<?php } ?>
		</div>
	</div>

	<?php

	// If a user is NOT connected, add the notice to connect the site
	if ( ! $dashboard->has_site_connected || ( $dashboard->has_site_connected && $dashboard->is_pro_installed && ! $dashboard->is_pro ) ) {

		?>

		<div class="uap-dashboard-connect-site-integration">
			<div class="uap-notice">
				<div class="uap-notice__title">
					<?php if ( $dashboard->is_pro_installed && ! $dashboard->is_pro ) {
						_e( 'Activate your license to get the most out of Uncanny Automator Pro!', 'uncanny-automator' );
					} else {
						_e( 'Connect your site and start using 3rd-party integrations!', 'uncanny-automator' );
					} ?>
				</div>
				<div class="uap-notice__content">
					<?php
					if ( ! $dashboard->is_pro_installed ) {
						vprintf(
						/* translators: 1. Number of credits; 2. Product; 3. Products; 4. Link */
							__( 'The free version of Uncanny Automator includes %1$s to use with our %2$s like %3$s, and more. See the list of %4$s.', 'uncanny-automator' ),
							[
								/* translators: 1. Number of credits */
								'<mark>' . sprintf( __( '%1$s free credits', 'uncanny-automator' ), number_format( $dashboard->miscellaneous->free_credits ) ) . '</mark>',
								'<strong>' . __( 'third-party integrations', 'uncanny-automator' ) . '</strong>',
								implode( ', ', [
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/slack-icon.svg' ) . '"> Slack</span></strong>',
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/google-sheet-icon.svg' ) . '"> Google Sheets</span></strong>',
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/facebook-icon.svg' ) . '"> Facebook</span></strong>',
								] ),
								'<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=integrations_that_uses_credits#Integrations_that_use_credits" target="_blank">' . __( 'integrations that use credits', 'uncanny-automator' ) . ' <span class="uap-icon uap-icon--external-link-alt"></span></a>',
							]
						);
					}
					if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) {
						vprintf(
						/* translators: 1. Number of credits; 2. Product; 3. Products; 4. Link */
							__( 'The pro version of Uncanny Automator includes %1$s to use with our %2$s like %3$s, and more. See the list of %4$s.', 'uncanny-automator' ),
							[
								/* translators: 1. Number of credits */
								'<mark>' . sprintf( __( '%1$s free credits', 'uncanny-automator' ), 'unlimited' ) . '</mark>',
								'<strong>' . __( 'third-party integrations', 'uncanny-automator' ) . '</strong>',
								implode( ', ', [
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/slack-icon.svg' ) . '"> Slack</span></strong>',
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/google-sheet-icon.svg' ) . '"> Google Sheets</span></strong>',
									// Integration names are not translatable
									'<strong><span class="uap-integration-inline"><img src="' . \Uncanny_Automator\Utilities::automator_get_media( 'integrations/facebook-icon.svg' ) . '"> Facebook</span></strong>',
								] ),
								'<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=integrations_that_uses_credits#Integrations_that_use_credits" target="_blank">' . __( 'integrations that use credits', 'uncanny-automator' ) . ' <span class="uap-icon uap-icon--external-link-alt"></span></a>',
							]
						);
					}
					?>
				</div>
				<div class="uap-notice__actions">
					<?php if ( ! $dashboard->is_pro_installed ) { ?>
						<a href="<?php echo $dashboard->connect_url; ?>" class="uap-btn uap-btn--primary">
							<?php _e( 'Connect your site', 'uncanny-automator' ); ?>
						</a>
					<?php } ?>
					<?php if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) { ?>
						<a href="<?php echo $dashboard->pro_activate_link; ?>" class="uap-btn uap-btn--primary">
							<?php _e( 'Activate your license', 'uncanny-automator' ); ?>
						</a>
					<?php } ?>
				</div>
			</div>
		</div>

		<?php

	}

	?>

	<!-- Learn section -->
	<div id="uap-dashboard-learn" class="uap-dashboard-section uap-dashboard-learn">
		<div class="uap-dashboard-section__title">
			<?php _e( 'Learn', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-dashboard-section__content">
			<div id="uap-dashboard-learn-featured-integrations" class="uap-dashboard-box uap-dashboard-learn-featured-integrations">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php _e( 'Start here', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--top">
					<div class="uap-video uap-video--16-9">
						<iframe src="https://www.youtube.com/embed/NgSMwD1fjKk" title="YouTube video player"
								frameborder="0"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
								allowfullscreen></iframe>
					</div>
				</div>

			</div>
			<div id="uap-dashboard-learn-knowledge-base" class="uap-dashboard-box uap-dashboard-learn-knowledge-base">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php _e( 'Knowledge base', 'uncanny-automator' ); ?>
					</div>
				</div>

				<div class="uap-dashboard-box-content uap-dashboard-box-content--has-scroll">
					<div class="uap-dashboard-box-content-scroll">
						<div class="uap-accordion">
							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Getting started', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '9 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/what-is-uncanny-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'What is Uncanny Automator?', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/creating-a-recipe/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Creating a Recipe', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/anonymous-recipes/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Recipes for Everyone', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Managing Triggers', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Managing Actions', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/scheduled-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Scheduled Actions', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'What are credits?', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-redirects/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Working with Redirects', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php _e( 'Where can I find my license key?', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Key resources', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '6 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Uncanny Automator Changelog', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-pro-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Uncanny Automator Pro Changelog', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/important-notes-troubleshooting/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Having trouble? Read this', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/using-automator-logs/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Using Automator Logs', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/developer-resources/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Developer Resources', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/upgrading-to-uncanny-automator-3-0/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php _e( 'Upgrading to Uncanny Automator 3.0+', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Integrations FAQ', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '3 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php _e( 'Contact Form 7', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php _e( 'Gravity Forms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/woocommerce/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php _e( 'WooCommerce', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Special triggers', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '2 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/webhook-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php _e( 'Webhook Triggers', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/magic-button/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php _e( 'Magic Button', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Special actions', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '16 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/google-sheets/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Google Sheets', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/mailchimp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Mailchimp', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-zapier-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Zapier Actions', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/zoom/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Zoom', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/send-data-to-a-webhook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Webhook Actions', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/twilio/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Twilio', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/slack/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Slack', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/facebook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Facebook', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/instagram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Instagram', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gototraining/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'GoToTraining', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gotowebinar/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'GoToWebinar', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-popup-maker-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Popup Maker', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-integromat-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Integromat', 'uncanny-automator' ); ?> <span
													class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/generate-an-email-a-certificate-to-the-user/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Send a certificate', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-continuing-education-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Uncanny Continuing Education Credits', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/run-a-wordpress-hook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php _e( 'Run a WordPress hook', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Special tokens', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '1 article', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/user-meta-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens"
											   target="_blank"><?php _e( 'User meta tokens', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Registering users', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php _e( '6 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with Contact Form 7', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-caldera-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with Caldera Forms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-ninja-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with Ninja Forms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with Gravity Forms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-formidable-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with Formidable Forms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-wpforms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php _e( 'Create a registration form with WPForms', 'uncanny-automator' ); ?>
												<span class="uap-icon uap-icon--external-link-alt"></span></a>
										</li>
									</ul>

								</div>
							</div>

						</div>
					</div>
				</div>

				<div class="uap-dashboard-box-footer">
					<a href="https://automatorplugin.com/knowledge-base/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=view_all_articles" target="blank">
						<?php _e( 'View all articles', 'uncanny-automator' ); ?> <span
							class="uap-icon uap-icon--external-link-alt"></span>
					</a>
				</div>
			</div>
			<div id="uap-dashboard-learn-videos" class="uap-dashboard-box uap-dashboard-learn-videos">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php _e( 'Videos', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--top">

					<div class="uap-dashboard-videos">
						<!-- Multiple triggers video -->
						<a href="https://www.youtube.com/watch?v=05-MjYDGk0Q&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt&index=2"
						   target="_blank" class="uap-dashboard-video">
							<div class="uap-dashboard-video__thumbnail">
								<img
									src="<?php echo \Uncanny_Automator\Utilities::automator_get_media( 'dashboard/multiple-triggers-landscape@2x.png' ); ?>">
							</div>
							<div class="uap-dashboard-video__title">
								<?php _e( 'Create an Uncanny Automator recipe with multiple triggers', 'uncanny-automator' ); ?>

								<div class="uap-dashboard-video__subtitle">
									<span class="uap-icon uap-icon--clock"></span> 2:28
								</div>
							</div>
						</a>

						<!-- Multiple actions video -->
						<a href="https://www.youtube.com/watch?v=RhEHFGLipE4&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt&index=3"
						   target="_blank" class="uap-dashboard-video">
							<div class="uap-dashboard-video__thumbnail">
								<img
									src="<?php echo \Uncanny_Automator\Utilities::automator_get_media( 'dashboard/multiple-actions-landscape@2x.png' ); ?>">
							</div>
							<div class="uap-dashboard-video__title">
								<?php _e( 'Create an Uncanny Automator recipe with multiple actions', 'uncanny-automator' ); ?>

								<div class="uap-dashboard-video__subtitle">
									<span class="uap-icon uap-icon--clock"></span> 3:11
								</div>
							</div>
						</a>

						<!-- Delay and schedule actions video -->
						<a href="https://www.youtube.com/watch?v=VeJ9n7i2hPo&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt&index=4"
						   target="_blank" class="uap-dashboard-video">
							<div class="uap-dashboard-video__thumbnail">
								<img
									src="<?php echo \Uncanny_Automator\Utilities::automator_get_media( 'dashboard/delay-or-schedule-actions-landscape@2x.png' ); ?>">
							</div>
							<div class="uap-dashboard-video__title">
								<?php _e( 'Delay and schedule actions for your WordPress automations', 'uncanny-automator' ); ?>

								<div class="uap-dashboard-video__subtitle">
									<span class="uap-icon uap-icon--clock"></span> 2:10
								</div>
							</div>
						</a>
					</div>

				</div>
				<div class="uap-dashboard-box-footer">
					<a href="https://www.youtube.com/watch?v=NgSMwD1fjKk&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt"
					   target="blank">
						<?php _e( 'View all videos', 'uncanny-automator' ); ?> <span
							class="uap-icon uap-icon--external-link-alt"></span>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Credits section -->
	<div id="uap-dashboard-credits" class="uap-dashboard-section uap-dashboard-credits">
		<div class="uap-dashboard-section__title">
			<?php _e( 'Credits', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-dashboard-section__content">

			<?php

			// Add the "Credits left" box
			// First, check if the site is connected
			if ( $dashboard->has_site_connected ) {
				// Check if it's a pro user
				if ( $dashboard->is_pro ) {
					?>

					<div id="uap-dashboard-credits-left" class="uap-dashboard-box">
						<div class="uap-dashboard-box-header uap-dashboard-box-header--no-padding">
							<div class="uap-dashboard-box-progress uap-dashboard-box-progress--success">
								<div id="uap-dashboard-credits-left-progress-bar" class="uap-dashboard-box-progress-bar"
									 style="width: 100%"></div>
							</div>
						</div>
						<div class="uap-dashboard-box-content">
							<div class="uap-dashboard-box-content-number">
								<?php

								/* translators: Unlimited credits */
								_ex( 'Unlimited', 'Credits', 'uncanny-automator' );

								?>
							</div>
							<div
								class="uap-dashboard-box-content-label uap-dashboard-box-content-label--reduced-margin">
								<?php _e( 'Credits left', 'uncanny-automator' ); ?>
							</div>
							<div
								class="uap-dashboard-box-content-below-label uap-dashboard-box-content-below-label--secondary">
								<?php

								printf(
								/* translators: 1. Pro label */
									__( 'with %1$s', 'uncanny-automator' ),
									'<span class="uap-pro-label">' . __( 'Pro', 'uncanny-automator' ) . '</span>'
								);

								?>
							</div>
						</div>
						<div class="uap-dashboard-box-footer">
							<a href="https://automatorplugin.com/article-categories/specialized-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=connect_premium_integrations" target="blank">
								<?php _e( 'Connect premium integrations', 'uncanny-automator' ); ?> <span
									class="uap-icon uap-icon--external-link-alt"></span>
							</a>
						</div>
					</div>

					<?php
				} else {
					?>

					<div id="uap-dashboard-credits-left" class="uap-dashboard-box">
						<div class="uap-dashboard-box-header uap-dashboard-box-header--no-padding">
							<div class="uap-dashboard-box-progress uap-dashboard-box-progress--success">
								<div id="uap-dashboard-credits-left-progress-bar" class="uap-dashboard-box-progress-bar"
									 style="width: 100%"></div>
							</div>
						</div>
						<div class="uap-dashboard-box-content">
							<div id="uap-dashboard-credits-left-quantity" class="uap-dashboard-box-content-number">
								<span class="uap-placeholder-text" data-placeholder="000"></span>
							</div>
							<div class="uap-dashboard-box-content-below-number">
								<?php

								printf(
								/* translators: 1. Number of total credits */
									_x( 'of %1$s', 'Credits', 'uncanny-automator' ),
									'<span id="uap-dashboard-credits-left-total"><span class="uap-placeholder-text" data-placeholder="1000"></span></span>'
								);

								?>
							</div>
							<div class="uap-dashboard-box-content-label">
								<?php _e( 'Credits left', 'uncanny-automator' ); ?>
							</div>
						</div>
						<?php if ( $dashboard->is_pro_installed ) { ?>
							<div class="uap-dashboard-box-footer">
								<a href="<?php echo $dashboard->pro_activate_link; ?>" target="blank"
								   class="uap-btn uap-btn--primary">
									<?php _e( 'Activate Pro license', 'uncanny-automator' ); ?>
								</a>
							</div>
						<?php } else { ?>
							<div class="uap-dashboard-box-footer">
								<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=how_do_i_get_more_credits" target="blank">
									<?php _e( 'How do I get more credits?', 'uncanny-automator' ); ?> <span
										class="uap-icon uap-icon--external-link-alt"></span>
								</a>
							</div>
						<?php } ?>
					</div>

					<?php
				}
			} else {
				?>

				<div id="uap-dashboard-credits-left" class="uap-dashboard-box">
					<div class="uap-dashboard-box-header uap-dashboard-box-header--no-padding">
						<div class="uap-dashboard-box-progress uap-dashboard-box-progress--warning">
							<div class="uap-dashboard-box-progress-bar" style="width: 100%"></div>
						</div>
					</div>
					<div class="uap-dashboard-box-content">
						<div class="uap-dashboard-box-content-number">
							0
						</div>
						<div class="uap-dashboard-box-content-label uap-dashboard-box-content-label--reduced-margin">
							<?php _e( 'Credits left', 'uncanny-automator' ); ?>
						</div>
						<div
							class="uap-dashboard-box-content-below-label uap-dashboard-box-content-below-label--warning">
							<span
								class="uap-icon uap-icon--exclamation-triangle"></span> <?php _e( 'Site not connected', 'uncanny-automator' ); ?>
						</div>
					</div>
					<div class="uap-dashboard-box-footer">
						<?php if ( ! $dashboard->is_pro_installed ) { ?>
							<a href="<?php echo $dashboard->connect_url; ?>" class="uap-btn uap-btn--primary">
								<?php _e( 'Connect your site', 'uncanny-automator' ); ?>
							</a>
						<?php } ?>
						<?php if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) { ?>
							<a href="<?php echo $dashboard->pro_activate_link; ?>" class="uap-btn uap-btn--primary">
								<?php _e( 'Activate your license', 'uncanny-automator' ); ?>
							</a>
						<?php } ?>
					</div>
				</div>

				<?php
			}

			?>

			<div id="uap-dashboard-credits-faq" class="uap-dashboard-box">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php _e( 'FAQ', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--has-scroll">
					<div class="uap-dashboard-box-content-scroll">
						<div class="uap-accordion">
							<div class="uap-accordion-item uap-accordion-item--open">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'What are credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php _e( "Some premium non-WordPress integrations connect to other services using an API. Automator's credit system allows free plugin users to try this out. Passing a record to one of these integrations uses one credit.", 'uncanny-automator' ); ?>
								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Do I need credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php _e( "Credits are only needed for premium non-WordPress services that pass through an API. Everything else is unrestricted (and Pro users get unlimited credits).", 'uncanny-automator' ); ?>
								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php _e( 'Can I get more credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php _e( "If you use more than 1,000 credits, you must either purchase the Pro version or disable your actions that use credits.", 'uncanny-automator' ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="uap-dashboard-box-footer">
					<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=learn_more_about_credits" target="blank">
						<?php _e( 'Learn more about credits', 'uncanny-automator' ); ?> <span
							class="uap-icon uap-icon--external-link-alt"></span>
					</a>
				</div>
			</div>

			<div id="uap-dashboard-credits-recipes" class="uap-dashboard-box">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php

						printf(
						/* translators: 1. Site URL */
							__( 'Recipes using credits on %1$s', 'uncanny-automator' ),
							$dashboard->miscellaneous->site_url_without_protocol
						);

						?>
					</div>
				</div>
				<div id="uap-dashboard-credits-recipes-content"
					 class="uap-dashboard-box-content uap-dashboard-box-content--top uap-dashboard-box-content--has-scroll">

					<?php

					// Check if the site is connected
					if ( $dashboard->has_site_connected ) {
						?>

						<div class="uap-dashboard-box-content-scroll">
							<ul class="uap-dashboard-box-list">
								<li>
									<a>
										<span class="uap-placeholder-text" data-placeholder="From"></span> <span
											class="uap-placeholder-text" data-placeholder="fairest"></span> <span
											class="uap-placeholder-text" data-placeholder="creatures"></span> <span
											class="uap-placeholder-text" data-placeholder="we"></span> <span
											class="uap-placeholder-text" data-placeholder="desire"></span> <span
											class="uap-placeholder-text" data-placeholder="increase"></span>
									</a>
								</li>
								<li>
									<a>
										<span class="uap-placeholder-text" data-placeholder="That"></span> <span
											class="uap-placeholder-text" data-placeholder="thereby"></span> <span
											class="uap-placeholder-text" data-placeholder="beauty's"></span> <span
											class="uap-placeholder-text" data-placeholder="rose"></span> <span
											class="uap-placeholder-text" data-placeholder="might"></span> <span
											class="uap-placeholder-text" data-placeholder="never"></span> <span
											class="uap-placeholder-text" data-placeholder="die"></span>
									</a>
								</li>
								<li>
									<a>
										<span class="uap-placeholder-text" data-placeholder="But"></span> <span
											class="uap-placeholder-text" data-placeholder="as"></span> <span
											class="uap-placeholder-text" data-placeholder="the"></span> <span
											class="uap-placeholder-text" data-placeholder="riper"></span> <span
											class="uap-placeholder-text" data-placeholder="should"></span> <span
											class="uap-placeholder-text" data-placeholder="by"></span> <span
											class="uap-placeholder-text" data-placeholder="time"></span> <span
											class="uap-placeholder-text" data-placeholder="decease"></span>
									</a>
								</li>
								<li>
									<a>
										<span class="uap-placeholder-text" data-placeholder="His"></span> <span
											class="uap-placeholder-text" data-placeholder="tender"></span> <span
											class="uap-placeholder-text" data-placeholder="heir"></span> <span
											class="uap-placeholder-text" data-placeholder="might"></span> <span
											class="uap-placeholder-text" data-placeholder="bear"></span> <span
											class="uap-placeholder-text" data-placeholder="memory"></span>
									</a>
								</li>
							</ul>
						</div>
						<?php
					} else {
						?>
						<div class="uap-dashboard-credits-recipes__no-recipes">
							<span class="uap-text-secondary">
								<span
									class="uap-icon uap-icon--info-circle"></span> <?php _e( 'No recipes using credits on this site', 'uncanny-automator' ); ?>
							</span>
						</div>

						<?php
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>
