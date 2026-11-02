<?php
/**
 * Step 1: App credits, for a customer here to build recipes by hand.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$apps = array(
	array( 'Google Sheets', 'google-sheet/img/google-sheet-icon.svg' ),
	array( 'Slack', 'slack/img/slack-icon.svg' ),
	array( 'Mailchimp', 'mailchimp/img/mailchimp-icon.svg' ),
	array( 'Facebook Pages', 'facebook/img/facebook-icon.svg' ),
	array( 'X/Twitter', 'twitter/img/x-twitter-icon.svg' ),
	array( 'Zoom Meetings', 'zoom/img/zoom-icon.svg' ),
	array( 'Google Calendar', 'google-calendar/img/google-calendar-icon.svg' ),
	array( 'WhatsApp', 'whatsapp/img/whatsapp-icon.svg' ),
);
?>

<div class="automator-setup-wizard__head">
	<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Try app integrations with 250 free app credits', 'Setup wizard credits heading', 'uncanny-automator' ); ?></h2>
	<p class="automator-setup-wizard__lead"><?php echo esc_html_x( "Create a free Uncanny Automator account and your recipes can send data to external apps like Google Sheets, Slack and Mailchimp. Recipes between the plugins on your site don't use credits.", 'Setup wizard credits lead', 'uncanny-automator' ); ?></p>
</div>

<ul class="automator-setup-wizard__apps">
	<?php foreach ( $apps as $app ) : ?>
		<li class="automator-setup-wizard__apps-item">
			<img src="<?php echo esc_url( plugins_url( 'src/integrations/' . $app[1], AUTOMATOR_BASE_FILE ) ); ?>" alt="" width="32" height="32" />
			<span><?php echo esc_html( $app[0] ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>

<p class="automator-setup-wizard__more">
	<a href="<?php echo esc_url( $this->get_app_integrations_uri() ); ?>" target="_blank" rel="noopener"><?php echo esc_html_x( 'See all app integrations', 'Setup wizard credits link', 'uncanny-automator' ); ?></a>
</p>

<div class="automator-setup-wizard__card automator-setup-wizard__card--center">
	<uas-button
		filled
		size="lg"
		class="automator-setup-wizard__cta"
		href="<?php echo esc_url( $this->get_connect_button_uri( Setup_Wizard::SCREEN_SKIPPED ) ); ?>"
		unsafe-force-target
		target="_self"
	>
		<?php echo esc_html_x( 'Create free account or sign in', 'Setup wizard credits button', 'uncanny-automator' ); ?>
	</uas-button>
	<p class="automator-setup-wizard__small"><?php echo esc_html_x( 'Opens automatorplugin.com in this window. Sign up with Google, Facebook or email, then come straight back to finish setup.', 'Setup wizard credits note', 'uncanny-automator' ); ?></p>
</div>

<div class="automator-setup-wizard__step-nav">
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_ACCOUNT, array( 'review' => '1' ) ) ); ?>">&larr; <?php echo esc_html_x( 'Back', 'Setup wizard back link', 'uncanny-automator' ); ?></a>
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_SKIPPED ) ); ?>"><?php echo esc_html_x( 'Skip for now', 'Setup wizard skip link', 'uncanny-automator' ); ?></a>
</div>
