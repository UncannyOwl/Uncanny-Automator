<?php
/**
 * Step 1: Create account.
 *
 * Shown on a new install, and again from Back or "Not you?".
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// A customer who came back from automatorplugin.com without a linked account
// sees why. The transient is set by Api_Server when the licensing call fails.
$connection_error = '';

if ( 'connect' === automator_filter_input( 'method' ) ) {
	$connection_error = (string) get_transient( 'automator_setup_wizard_error' );
	delete_transient( 'automator_setup_wizard_error' );
}
?>

<?php if ( '' !== $connection_error ) : ?>
	<uas-alert class="automator-setup-wizard__alert" type="error" heading="<?php echo esc_attr( $connection_error ); ?>"></uas-alert>
<?php endif; ?>

<div class="automator-setup-wizard__head">
	<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Start with a free account', 'Setup wizard account heading', 'uncanny-automator' ); ?></h2>
	<p class="automator-setup-wizard__lead"><?php echo esc_html_x( 'Uncanny Automator is an MCP server for WordPress with a built-in automation engine. It connects your AI to this site.', 'Setup wizard account lead', 'uncanny-automator' ); ?></p>
</div>

<p class="automator-setup-wizard__ticks-intro"><?php echo esc_html_x( 'A free account gives you:', 'Setup wizard account benefits intro', 'uncanny-automator' ); ?></p>

<ul class="automator-setup-wizard__ticks">
	<li><?php echo esc_html_x( 'Manage and automate this site with your AI: up to 150 tool uses a day, 300 a day in your first week after connecting', 'Setup wizard account benefit', 'uncanny-automator' ); ?></li>
	<li><?php echo esc_html_x( '250 app credits for external apps like Slack, Google Sheets and Mailchimp', 'Setup wizard account benefit', 'uncanny-automator' ); ?></li>
	<li><?php echo esc_html_x( 'Unlimited recipes between the plugins on this site', 'Setup wizard account benefit', 'uncanny-automator' ); ?></li>
</ul>

<div class="automator-setup-wizard__card automator-setup-wizard__card--center">
	<uas-button
		filled
		size="lg"
		class="automator-setup-wizard__cta"
		href="<?php echo esc_url( $this->get_connect_button_uri( Setup_Wizard::SCREEN_CHOOSE ) ); ?>"
		unsafe-force-target
		target="_self"
	>
		<?php echo esc_html_x( 'Create free account or sign in', 'Setup wizard account button', 'uncanny-automator' ); ?>
	</uas-button>
	<p class="automator-setup-wizard__small"><?php echo esc_html_x( 'Opens automatorplugin.com in this window. Sign up with Google, Facebook or email.', 'Setup wizard account note', 'uncanny-automator' ); ?></p>
</div>

<div class="automator-setup-wizard__step-nav">
	<span></span>
	<uas-button
		color="transparent"
		size="sm"
		class="automator-setup-wizard__nav-button"
		href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_SKIPPED ) ); ?>"
		needs-confirmation
		confirmation-heading="<?php echo esc_attr_x( 'Are you sure?', 'Setup wizard skip confirmation heading', 'uncanny-automator' ); ?>"
		confirmation-content="<?php echo esc_attr_x( "Without a free account, your AI can't connect to this site, Uncanny Agent stays off, and app integrations won't work.", 'Setup wizard skip confirmation body', 'uncanny-automator' ); ?>"
		confirmation-button-label="<?php echo esc_attr_x( 'Skip for now', 'Setup wizard skip confirmation button', 'uncanny-automator' ); ?>"
	><?php echo esc_html_x( 'Later', 'Setup wizard skip link', 'uncanny-automator' ); ?></uas-button>
</div>
