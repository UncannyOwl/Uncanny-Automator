<?php
// Delete credit data transient on dashboard
use Uncanny_Automator\Utilities;

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

$setup_wizard_link = add_query_arg(
	array(
		'post_type' => 'uo-recipe',
		'page'      => 'uncanny-automator-setup-wizard',
	),
	admin_url( 'edit.php' )
);

?>

<style>

	#wpfooter {
		position: relative;
	}

</style>

<div id="uap-dashboard" class="uap-dashboard <?php echo esc_attr( implode( ' ', $dashboard_css_classes ) ); ?>">

	<!-- Some plugins need an h1 to add their notices -->
	<h1 style="margin: 0; padding: 0;"></h1>

	<div class="uap-dashboard-header">
		<div class="uap-dashboard-header__title">
			<?php esc_attr_e( 'Dashboard', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-dashboard-header-user">

			<?php

			// If a user is connected, then add the submenu with links
			if ( $dashboard->has_site_connected ) {

				?>

				<div class="uap-dropdown uap-dropdown--caret-right">
					<div class="uap-dropdown-toggle">
						<div class="uap-dashboard-header-user__avatar">
							<img src="<?php echo esc_url_raw( $dashboard->connected_user->avatar ); ?>">
						</div>

						<div class="uap-dashboard-header-user__name">
							<?php echo esc_attr( $dashboard->connected_user->first_name ); ?>
						</div>
					</div>
					<div class="uap-dropdown-menu">
						<a
							class="uap-dropdown-item"
							href="<?php echo esc_url_raw( $dashboard->connected_user->url->edit_profile ); ?>"
							target="_blank"
						>
							<?php esc_attr_e( 'My account', 'uncanny-automator' ); ?>
						</a>
						<a
							class="uap-dropdown-item"
							href="<?php echo esc_url_raw( $dashboard->connected_user->url->connected_sites ); ?>"
							target="_blank"
						>
							<?php esc_attr_e( 'Manage sites', 'uncanny-automator' ); ?>

						</a>
						<?php if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) ) { ?>
							<a class="uap-dropdown-item"
							   href="<?php echo esc_url( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' ) ); ?>">
								<?php esc_html_e( 'Manage license', 'uncanny-automator' ); ?>
							</a>
						<?php } else { ?>
							<a class="uap-dropdown-item"
							   href="<?php echo esc_url_raw( $dashboard->connected_user->url->disconnect_account ); ?>">
								<?php esc_html_e( 'Disconnect account', 'uncanny-automator' ); ?>
							</a>
						<?php } ?>
					</div>
				</div>

			<?php } else { ?>
				<div class="uap-dropdown uap-dropdown--caret-right">
					<div class="uap-dropdown-toggle">
						<div class="uap-dashboard-header-user__avatar">
							<uo-icon id="user"></uo-icon>
						</div>

						<div class="uap-dashboard-header-user__name">
							<?php esc_attr_e( 'Guest', 'uncanny-automator' ); ?>
						</div>
					</div>
					<div class="uap-dropdown-menu">
						<a
							class="uap-dropdown-item"
							href="<?php echo esc_url( $setup_wizard_link ); ?>"
						>
							<?php esc_attr_e( 'Connect your site', 'uncanny-automator' ); ?>

						</a>
					</div>
				</div>


			<?php } ?>
		</div>
	</div>

	<?php do_action( 'automator_dashboard_header_after' ); ?>

	<?php

	// If a user is NOT connected, add the notice to connect the site
	if ( ! $dashboard->has_site_connected || ( $dashboard->has_site_connected && $dashboard->is_pro_installed && ! $dashboard->is_pro ) ) {

		?>

		<div class="uap-dashboard-connect-site-integration">
			<div class="uap-notice">
				<div class="uap-notice__title">
					<?php
					if ( $dashboard->is_pro_installed && ! $dashboard->is_pro ) {
						esc_attr_e( 'Activate your license to get the most out of Uncanny Automator Pro!', 'uncanny-automator' );
					} else {
						esc_attr_e( 'Connect your site and start using app integrations!', 'uncanny-automator' );
					}
					?>
				</div>
				<div class="uap-notice__content">
					<?php
					if ( ! $dashboard->is_pro_installed ) {
						vprintf(
						/* translators: 1. Number of credits; 2. Product; 3. Products; 4. Link */
							esc_attr__( 'The free version of Uncanny Automator includes %1$s to use with our %2$s like %3$s, and more. See the list of %4$s.', 'uncanny-automator' ),
							array(
								/* translators: 1. Number of credits */
								'<mark>' . sprintf( esc_attr__( '%1$s app credits', 'uncanny-automator' ), number_format( $dashboard->miscellaneous->free_credits ) ) . '</mark>',
								'<strong>' . esc_attr__( 'app integrations', 'uncanny-automator' ) . '</strong>',
								implode(
									', ',
									array(
										// Integration names are not translatable
										'<uo-icon integration="SLACK"></uo-icon> <strong>Slack</strong>',
										// Integration names are not translatable
										'<uo-icon integration="GOOGLESHEET"></uo-icon> <strong>Google Sheets</strong>',
										// Integration names are not translatable
										'<uo-icon integration="FACEBOOK"></uo-icon> <strong>Facebook</strong>',
									)
								),
								'<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=integrations_that_uses_credits#Integrations_that_use_credits" target="_blank">' . esc_attr__( 'integrations that use app credits', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>',
							)
						);
					}
					if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) {
						vprintf(
						/* translators: 1. Number of credits; 2. Product; 3. Products; 4. Link */
							esc_attr__( 'The pro version of Uncanny Automator includes %1$s to use with our %2$s like %3$s, and more. See the list of %4$s.', 'uncanny-automator' ),
							array(
								/* translators: 1. Number of credits */
								'<mark>' . sprintf( esc_attr__( '%1$s free credits', 'uncanny-automator' ), 'unlimited' ) . '</mark>',
								'<strong>' . esc_attr__( 'app integrations', 'uncanny-automator' ) . '</strong>',
								implode(
									', ',
									array(
										// Integration names are not translatable
										'<uo-icon integration="SLACK"></uo-icon> <strong>Slack</strong>',
										// Integration names are not translatable
										'<uo-icon integration="GOOGLESHEET"></uo-icon> <strong>Google Sheets</strong>',
										// Integration names are not translatable
										'<uo-icon integration="FACEBOOK"></uo-icon> <strong>Facebook</strong>',
									)
								),
								'<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=integrations_that_uses_credits#Integrations_that_use_credits" target="_blank">' . esc_attr__( 'integrations that use app credits', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>',
							)
						);
					}
					?>
				</div>
				<div class="uap-notice__actions">
					<?php if ( ! $dashboard->is_pro_installed ) { ?>

						<uo-button
							href="<?php echo esc_url( $setup_wizard_link ); ?>"
						>
							<?php esc_attr_e( 'Connect your site', 'uncanny-automator' ); ?>
						</uo-button>

					<?php } ?>
					<?php if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) { ?>

						<uo-button
							href="<?php echo esc_url_raw( $dashboard->pro_activate_link ); ?>"
						>
							<?php esc_attr_e( 'Activate your license', 'uncanny-automator' ); ?>
						</uo-button>

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
			<?php esc_attr_e( 'Learn', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-dashboard-section__content">
			<div id="uap-dashboard-learn-featured-integrations"
				 class="uap-dashboard-box uap-dashboard-learn-featured-integrations">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_attr_e( 'Videos', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--top">
					<div class="uap-video uap-video--16-9">
						<iframe src="https://www.youtube.com/embed/LMR5YIPu2Kk" title="YouTube video player"
								frameborder="0"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
								allowfullscreen></iframe>
					</div>
					<div class="uap-dashboard-videos-container">
						<div class="uap-dashboard-videos">
							<!-- Multiple triggers video -->
							<a href="https://www.youtube.com/watch?v=05-MjYDGk0Q&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt&index=2"
							target="_blank" class="uap-dashboard-video">
								<div class="uap-dashboard-video__thumbnail">
									<img
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'multiple-triggers-landscape-2@2x.png' ) ); ?>">
								</div>
								<div class="uap-dashboard-video__title">
									<?php esc_attr_e( 'Create an Uncanny Automator recipe with multiple triggers', 'uncanny-automator' ); ?>

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
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'multiple-actions-landscape-2@2x.png' ) ); ?>">
								</div>
								<div class="uap-dashboard-video__title">
									<?php esc_attr_e( 'Create an Uncanny Automator recipe with multiple actions', 'uncanny-automator' ); ?>

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
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'delay-or-schedule-actions-landscape-2@2x.png' ) ); ?>">
								</div>
								<div class="uap-dashboard-video__title">
									<?php esc_attr_e( 'Delay and schedule actions for your WordPress automations', 'uncanny-automator' ); ?>

									<div class="uap-dashboard-video__subtitle">
										<span class="uap-icon uap-icon--clock"></span> 2:10
									</div>
								</div>
							</a>
						</div>
					</div>
						
				</div>
				<div class="uap-dashboard-box-footer">
					<a href="https://www.youtube.com/@UncannyAutomator/videos"
					   target="_blank">
						<?php esc_attr_e( 'View all videos', 'uncanny-automator' ); ?>

					</a>
				</div>
			</div>
			<div id="uap-dashboard-learn-knowledge-base" class="uap-dashboard-box uap-dashboard-learn-knowledge-base">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_attr_e( 'Knowledge base', 'uncanny-automator' ); ?>
					</div>
				</div>

				<div class="uap-dashboard-box-content uap-dashboard-box-content--has-scroll">
					<div class="uap-dashboard-box-content-scroll">
						<div class="uap-accordion">
							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Getting started', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '12 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/what-is-uncanny-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'What is Uncanny Automator?', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/installing-uncanny-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Installing Uncanny Automator', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/creating-a-recipe/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Creating a Recipe', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/anonymous-recipes/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Recipes for Everyone', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Managing Triggers', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Managing Actions', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Managing Tokens', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/scheduled-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Scheduled Actions', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/action-filters-conditions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Action filters / conditions', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'What are App Credits?', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'License Keys', 'uncanny-automator' ); ?>
											</a>
										</li>

										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-redirects/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started"
											   target="_blank"><?php esc_attr_e( 'Working with Redirects', 'uncanny-automator' ); ?>
											</a>
										</li>

									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Key resources', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '9 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Uncanny Automator Changelog', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-pro-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Uncanny Automator Pro Changelog', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/important-notes-troubleshooting/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Having trouble? Read this', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/using-automator-logs/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Using Automator Logs', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/developer-resources/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Developer Resources', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/data-privacy-and-gdpr/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Data Privacy and GDPR', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/usage-tracking/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Usage Tracking', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/connecting-your-site-with-a-free-uncanny-automator-account/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'Connecting your site', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/php-version/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources"
											   target="_blank"><?php esc_attr_e( 'PHP version', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Webhooks', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '4 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/webhook-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Incoming Webhook Triggers', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/send-data-to-a-webhook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Webhook Actions', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/sending-a-json-array-with-automators-outgoing-webhook-action/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Sending a JSON Array', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/sending-a-json-object-with-outgoing-webhooks/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Sending a JSON Object', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Special triggers', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '13 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/magic-button/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Magic Buttons & Magic Links', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/schedule/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Schedule', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/google-sheets-webhook-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Google Sheets™ Webhook Addon', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/run-now/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Run Now', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/advanced-custom-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Advanced Custom Fields', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/activecampaign-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'ActiveCampaign', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/ifttt-to-wordpress/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'IFTTT', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/optinmonster-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'OptinMonster Triggers', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/mailchimp-wordpress-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Mailchimp', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/whatsapp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'WhatsApp', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/helpscout-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Help Scout', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/telegram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'Telegram', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/woocommerce-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers"
											   target="_blank"><?php esc_attr_e( 'WooCommerce', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Special actions', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '36 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/run-a-wordpress-hook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Run a WordPress hook', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/call-a-custom-function-method/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Call a custom function/method', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/the-formatter-action/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'The Formatter Action', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/google-sheets/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Google Sheets', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/google-calendar/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Google Calendar', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/open-ai/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'OpenAI', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/twitter/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'X / Twitter', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/mailchimp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Mailchimp', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/activecampaign/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'ActiveCampaign', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/facebook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Facebook Pages', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/optinmonster/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'OptinMonster', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-zapier-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Zapier Actions', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/whatsapp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'WhatsApp', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/integrately/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Integrately', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-popup-maker-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Popup Maker', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/slack/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Slack', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/zoom/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Zoom', 'uncanny-automator' ); ?> </a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/working-with-integromat-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Integromat', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/hubspot/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'HubSpot', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gototraining/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'GoToTraining', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gotowebinar/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'GoToWebinar', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/twilio/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Twilio', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/instagram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Instagram Business', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/generate-an-email-a-certificate-to-the-user/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Send a certificate', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-continuing-education-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Uncanny Continuing Education Credits', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/ifttt/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'IFTTT', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-woocommerce-orders/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Create WooCommerce orders', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/linkedin-pages/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'LinkedIn Pages', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/airtable/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Airtable', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/make/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Make', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/mailerlite/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'MailerLite', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/drip/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Drip', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/microsoft-teams/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Microsoft Teams (Beta)', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/telegram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Telegram', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/zoho-campaigns/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Zoho Campaigns', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/facebook-groups/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions"
											   target="_blank"><?php esc_attr_e( 'Facebook Groups (Deprecated)', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Special tokens', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '3 article', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/user-meta-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens"
											   target="_blank"><?php esc_attr_e( 'User meta tokens', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/post-meta-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens"
											   target="_blank"><?php esc_attr_e( 'Post meta tokens', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/calculations-math-equations/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens"
											   target="_blank"><?php esc_attr_e( 'Calculations (math equations)', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Custom User Fields Addon', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '5 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/installing-the-custom-user-fields-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Installing the Custom User Fields Addon', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-custom-user-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Managing Custom User Fields', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/updating-custom-user-field-data/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Updating Custom User Field Data', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/displaying-custom-user-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Displaying Custom User Fields', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-custom-user-fields-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Uncanny Automator Custom User Fields Addon Changelog', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Restrict Content Addon', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '7 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/installing-the-restrict-content-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Installing the Restrict Content Addon', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-access-levels/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Managing Access Levels', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/restrict-content-shortcodes/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Restrict Content Shortcodes', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/restrict-content-for-blocks/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Restrict Content for Blocks', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/restrict-wordpress-pages-posts/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Restrict WordPress Pages & Posts', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/restrict-content-integration-with-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Restrict Content Integration with Automator', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-restrict-content-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Uncanny Automator Restrict Content Addon Changelog', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'User Lists Addon', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '7 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/installing-the-user-lists-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Installing the User Lists Addon', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-user-lists/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Managing user lists', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/managing-user-list-subscriptions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Managing user list subscriptions', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/user-lists-integration-with-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'User Lists Integration with Automator', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/sending-bulk-emails-with-user-lists/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Sending bulk emails with user lists', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/the-unsubscribed-list-subscription-management/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'The Unsubscribed list and managing subscriptions', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/uncanny-automator-user-lists-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Uncanny Automator User Lists Addon Changelog', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Advanced Topics', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '3 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/user-loops/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'User Loops', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/post-loops/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Post Loops', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/how-to-use-custom-scheduling-in-wordpress-automations/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Custom Scheduling', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Registering users', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '6 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with Contact Form 7', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-caldera-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with Caldera Forms', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-ninja-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with Ninja Forms', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with Gravity Forms', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-formidable-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with Formidable Forms', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/create-a-registration-form-with-wpforms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users"
											   target="_blank"><?php esc_attr_e( 'Registration form with WPForms', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Integrations FAQ', 'uncanny-automator' ); ?>
									<span
										class="uap-dashboard-learn-knowledge-base__number-of-articles"> (<?php esc_attr_e( '3 articles', 'uncanny-automator' ); ?>)</span>
								</div>
								<div class="uap-accordion-item__content">

									<ul class="uap-dashboard-box-list">
										<li>
											<a href="https://automatorplugin.com/knowledge-base/contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Contact Form 7', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'Gravity Forms', 'uncanny-automator' ); ?>
											</a>
										</li>
										<li>
											<a href="https://automatorplugin.com/knowledge-base/woocommerce/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq"
											   target="_blank"><?php esc_attr_e( 'WooCommerce', 'uncanny-automator' ); ?>
											</a>
										</li>
									</ul>

								</div>
							</div>

						</div>
					</div>
				</div>

				<div class="uap-dashboard-box-footer">
					<a href="https://automatorplugin.com/knowledge-base/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=view_all_articles"
					   target="_blank">
						<?php esc_attr_e( 'View all articles', 'uncanny-automator' ); ?>

					</a>
				</div>
			</div>
			<div id="uap-dashboard-blog-posts" class="uap-dashboard-box uap-dashboard-learn-blog-posts">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_attr_e( 'Blog posts', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--top">

					<div class="uap-blog-posts">
						<?php require trailingslashit( UA_ABSPATH ) . 'src/core/views/admin-dashboard/blog-posts.php'; ?>
					</div><!--.uap-blog-posts-->

				</div>
				<div class="uap-dashboard-box-footer">
					<a href="https://automatorplugin.com/blog/"
					   target="_blank">
						<?php esc_attr_e( 'View all blog posts', 'uncanny-automator' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Credits section -->
	<div id="uap-dashboard-credits" class="uap-dashboard-section uap-dashboard-credits">
		<div class="uap-dashboard-section__title">
			<?php if ( ! $dashboard->is_pro ) { ?>
				<?php esc_attr_e( 'App credits left', 'uncanny-automator' ); ?>
			<?php } else { ?>
				<?php esc_attr_e( 'App credits used', 'uncanny-automator' ); ?>
			<?php } ?>
		</div>
		<div class="uap-dashboard-section__content">

			<?php

			// If the site is not connected
			if ( ! $dashboard->has_site_connected || ( $dashboard->is_pro_installed && ! $dashboard->is_pro ) ) {
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
							<?php esc_attr_e( 'App credits left', 'uncanny-automator' ); ?>
						</div>
						<div
							class="uap-dashboard-box-content-below-label uap-dashboard-box-content-below-label--warning">
							<span
								class="uap-icon uap-icon--exclamation-triangle"></span> <?php esc_attr_e( 'Site not connected', 'uncanny-automator' ); ?>
						</div>
					</div>
					<div class="uap-dashboard-box-footer">
						<?php if ( ! $dashboard->is_pro_installed ) { ?>

							<uo-button
								href="<?php echo esc_url( $setup_wizard_link ); ?>"
							>
								<?php esc_attr_e( 'Connect your site', 'uncanny-automator' ); ?>
							</uo-button>

						<?php } ?>
						<?php if ( ! $dashboard->is_pro && $dashboard->is_pro_installed ) { ?>

							<uo-button
								href="<?php echo esc_url_raw( $dashboard->pro_activate_link ); ?>"
							>
								<?php esc_attr_e( 'Activate your license', 'uncanny-automator' ); ?>
							</uo-button>

						<?php } ?>
					</div>
				</div>

				<?php
			} else {
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
								<?php echo esc_html( number_format( absint( $dashboard->paid_usage_count ) ) ); ?>
							</div>
							<div
								class="uap-dashboard-box-content-label uap-dashboard-box-content-label--reduced-margin">
								<?php
								/* translators: Credits used */
								echo esc_html__( 'App credits used', 'uncanny-automator' );
								?>
							</div>
							
							<div class="uap-dashboard-box-content-higlight" style="display: block; margin: 15px auto 0 auto; color: var(--uap-font-color-secondary);">
								<?php echo esc_html_e( 'You have', 'uncanny-automator' ); ?>
								<span style="color: #6bc45a">
									<strong><?php esc_html_e( 'unlimited', 'uncanny-automator' ); ?></strong>
								</span>
							</div>
							<div
								style="margin-top:0;"
								class="uap-dashboard-box-content-below-label uap-dashboard-box-content-below-label--secondary">
								<?php
								printf(
								/* translators: 1. Pro label */
									esc_attr__( 'App credits with %1$s', 'uncanny-automator' ),
									'<uo-pro-tag></uo-pro-tag>'
								);

								?>
							</div>
						</div>
						<div class="uap-dashboard-box-footer">
							<a href="https://automatorplugin.com/article-categories/specialized-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=connect_premium_integrations"
							   target="_blank">
								<?php esc_attr_e( 'Connect app integrations', 'uncanny-automator' ); ?>
							</a>
						</div>
					</div>

					<?php
				} else {
					?>

					<div id="uap-dashboard-credits-left" class="uap-dashboard-box">
						<div class="uap-dashboard-box-content">
							<div class="uap-dashboard-box-content">
								<div class="uap-dashboard-box-content-number">
									<?php echo esc_html( number_format( absint( $dashboard->miscellaneous->free_credits ) ) ); ?>
								</div>
								<div class="uap-dashboard-box-content-label uap-dashboard-box-content-label--reduced-margin">
									<?php esc_html_e( 'App credits left', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-dashboard-box-content-below-label uap-dashboard-box-content-below-label--secondary">
									<a href="<?php echo esc_url( $dashboard->upgrade_url ); ?>" title="<?php esc_attr_e( 'Get Pro', 'uncanny-automator' ); ?>">
										<?php esc_html_e( 'Get', 'uncanny-automator' ); ?> <uo-pro-tag></uo-pro-tag>
									</a>
								</div>
							</div>
						</div>
						<?php if ( $dashboard->is_pro_installed ) { ?>
							<div class="uap-dashboard-box-footer">

								<uo-button
									href="<?php echo esc_url_raw( $dashboard->pro_activate_link ); ?>"
								>
									<?php esc_attr_e( 'Activate Pro license', 'uncanny-automator' ); ?>
								</uo-button>

							</div>
						<?php } else { ?>
							<div class="uap-dashboard-box-footer">
								<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=how_do_i_get_more_credits"
								   target="_blank">
									<?php esc_attr_e( 'How do I get more credits?', 'uncanny-automator' ); ?>

								</a>
							</div>
						<?php } ?>
					</div>

					<?php
				}
			}

			?>

			<div id="uap-dashboard-credits-faq" class="uap-dashboard-box">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_attr_e( 'FAQ', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--has-scroll">
					<div class="uap-dashboard-box-content-scroll">
						<div class="uap-accordion">
							<div class="uap-accordion-item uap-accordion-item--open">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'What are app credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php esc_attr_e( "Some app integrations connect to other services using an API. Automator's app credit system allows free plugin users to try this out. Passing a record to one of these integrations uses one app credit.", 'uncanny-automator' ); ?>
								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Do I need app credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php esc_attr_e( 'App credits are only needed for app integrations that pass through an API. Everything else is unrestricted (and Pro users get unlimited app credits).', 'uncanny-automator' ); ?>
								</div>
							</div>

							<div class="uap-accordion-item">
								<div class="uap-accordion-item__toggle">
									<?php esc_attr_e( 'Can I get more app credits?', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-accordion-item__content">
									<?php esc_attr_e( 'If you use more than 250 app credits, you must either purchase the Pro version or disable your actions that use credits.', 'uncanny-automator' ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="uap-dashboard-box-footer">
					<a href="https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=learn_more_about_credits"
					   target="blank">
						<?php esc_attr_e( 'Learn more about app credits', 'uncanny-automator' ); ?>
					</a>
				</div>
			</div>

			<div id="uap-dashboard-credits-recipes" class="uap-dashboard-box">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_html_e( 'Recipes using app credits', 'uncanny-automator' ); ?>
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
									class="uap-icon uap-icon--info-circle"></span> <?php esc_attr_e( 'No recipes using app credits on this site', 'uncanny-automator' ); ?>
							</span>
						</div>

						<?php
					}
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Social media icons section -->
	<div id="uap-dashboard-social-media">
		<div class="uap-dashboard-social-media__text">
			<?php esc_attr_e( 'Connect with us:', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-dashboard-social-media__icons">
			<a href="https://www.facebook.com/uncannyautomator/" target="_blank" class="uap-dashboard-social-media__icon uap-dashboard-social-media__icon--facebook">
				<uo-icon id="facebook"></uo-icon>
			</a>
			<a href="https://twitter.com/automatorplugin" target="_blank" class="uap-dashboard-social-media__icon uap-dashboard-social-media__icon--x-twitter">
				<uo-icon id="x-twitter"></uo-icon>
			</a>
			<a href="https://www.youtube.com/channel/UChaGT08W7WslSNMy_F-iATA" target="_blank" class="uap-dashboard-social-media__icon uap-dashboard-social-media__icon--youtube">
				<uo-icon id="youtube"></uo-icon>
			</a>
		</div>
	</div>
</div>
