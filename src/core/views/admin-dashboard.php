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
		'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
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

		<?php

		// If a user is connected, then add the submenu with links
		if ( $dashboard->has_site_connected ) {

			?>

			<uo-button-dropdown>
				<uo-button color="transparent" slot="target">
					<div class="uap-dashboard-header-user">
						<span class="uap-dashboard-header-user__avatar">
							<img src="<?php echo esc_url_raw( $dashboard->connected_user->avatar ); ?>">
						</span>

						<span class="uap-dashboard-header-user__name">
							<?php echo esc_attr( $dashboard->connected_user->first_name ); ?>
						</span>

						<uo-icon id="angle-down"></uo-icon>
					</div>
				</uo-button>

				<uo-button href="<?php echo esc_url_raw( $dashboard->connected_user->url->edit_profile ); ?>">
					<?php esc_attr_e( 'My account', 'uncanny-automator' ); ?>
				</uo-button>

				<uo-button href="<?php echo esc_url_raw( $dashboard->connected_user->url->connected_sites ); ?>">
					<?php esc_attr_e( 'Manage sites', 'uncanny-automator' ); ?>
				</uo-button>

				<?php if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) ) { ?>

					<uo-button href="<?php echo esc_url( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' ) ); ?>">
						<?php esc_html_e( 'Manage license', 'uncanny-automator' ); ?>
					</uo-button>

				<?php } else { ?>

					<uo-button href="<?php echo esc_url_raw( $dashboard->connected_user->url->disconnect_account ); ?>">
						<?php esc_html_e( 'Disconnect account', 'uncanny-automator' ); ?>
					</uo-button>

				<?php } ?>
				
			</uo-button-dropdown>

		<?php } else { ?>

			<uo-button-dropdown>
				<uo-button color="transparent" slot="target">
					<div class="uap-dashboard-header-user">
						<span class="uap-dashboard-header-user__avatar">
							<uo-icon id="user"></uo-icon>
						</span>

						<span class="uap-dashboard-header-user__name">
							<?php esc_attr_e( 'Guest', 'uncanny-automator' ); ?>
						</span>

						<uo-icon id="angle-down"></uo-icon>
					</div>
				</uo-button>

				<uo-button href="<?php echo esc_url( $setup_wizard_link ); ?>">
					<?php esc_attr_e( 'Connect your site', 'uncanny-automator' ); ?>
				</uo-button>
				
			</uo-button-dropdown>

		<?php } ?>
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
					<iframe
						src="https://www.youtube.com/embed/LMR5YIPu2Kk"
						referrerpolicy="strict-origin-when-cross-origin"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
						allowfullscreen
						style="width: 100%; aspect-ratio: 16 / 9;"
					></iframe>
					<div class="uap-dashboard-videos-container">
						<div class="uap-dashboard-videos">
							<!-- Multiple triggers video -->
							<a href="https://www.youtube.com/watch?v=05-MjYDGk0Q&list=PL1RknUTvSLClS5ggNPBZXK461vx6kNdTt&index=2"
							target="_blank" class="uap-dashboard-video">
								<div class="uap-dashboard-video__thumbnail">
									<img
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'multiple-triggers-landscape-2-2x.png' ) ); ?>">
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
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'multiple-actions-landscape-2-2x.png' ) ); ?>">
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
										src="<?php echo esc_url_raw( Utilities::automator_get_media( 'delay-or-schedule-actions-landscape-2-2x.png' ) ); ?>">
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
						<uo-accordion
							list-search
							
							.searchPlaceholder="<?php esc_attr_e( 'Search articles', 'uncanny-automator' ); ?>"
							.noResultsText="<?php esc_attr_e( 'No articles found', 'uncanny-automator' ); ?>"
						>

							<?php foreach ( $dashboard->kb_articles as $category ) { ?>

								<uo-accordion-item>

									<div slot="summary">
										<?php echo esc_html( $category['title'] ); ?>
										<em style="opacity: .7; font-size: .8em">
											<?php echo sprintf( esc_html__( '%s articles', 'uncanny-automator' ), count( $category['articles'] ) ); ?>
										</em>
									</div>

									<?php foreach ( $category['articles'] as $article ) { ?>
										<uo-accordion-item-li>
											<a href="<?php echo esc_url( $article['url'] ); ?>"
											   target="_blank"
											>
												<?php echo esc_html( $article['title'] ); ?>
											</a>
										</uo-accordion-item-li>
									<?php } ?>
									
								</uo-accordion-item>

							<?php } ?>

						</uo-accordion>
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

	<?php require trailingslashit( UA_ABSPATH ) . 'src/core/views/admin-dashboard/usage-panel.php'; ?>

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
