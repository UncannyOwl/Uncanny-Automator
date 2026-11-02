<?php
/**
 * Step 2: Uncanny Agent offer, for a customer without an AI.
 *
 * Yes goes on to Page Builder; No ends the wizard on the Skipped screen.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

// Yes is preselected until the site has made a choice; the setting itself is off by default.
$agent_on = ! $this->is_agent_setting_saved() || $this->is_agent_enabled();
?>

<div class="automator-setup-wizard__head">
	<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Uncanny Agent is built in', 'Setup wizard agent heading', 'uncanny-automator' ); ?></h2>
	<p class="automator-setup-wizard__lead"><?php echo esc_html_x( 'No AI subscription, or nothing to set up? Uncanny Agent is the AI assistant built into Uncanny Automator. It works from your WordPress dashboard and checks with you before important actions.', 'Setup wizard agent lead', 'uncanny-automator' ); ?></p>
</div>

<form class="automator-setup-wizard__offer" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Setup_Wizard::AGENT_CHOICE_ACTION ); ?>" />
	<?php wp_nonce_field( Setup_Wizard::CHOICE_NONCE ); ?>

	<ul class="automator-setup-wizard__ticks">
		<li><?php echo esc_html_x( 'Answers questions about your site, and about WordPress', 'Setup wizard agent benefit', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Completes tasks and keeps your site maintained', 'Setup wizard agent benefit', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Builds pages and recipes', 'Setup wizard agent benefit', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Checks with you before anything important happens', 'Setup wizard agent benefit', 'uncanny-automator' ); ?></li>
	</ul>

	<fieldset class="automator-setup-wizard__choice">
		<legend><?php echo esc_html_x( 'Enable Uncanny Agent?', 'Setup wizard agent question', 'uncanny-automator' ); ?></legend>
		<label class="automator-setup-wizard__option">
			<input type="radio" name="choice" value="yes" <?php checked( $agent_on ); ?> />
			<span class="automator-setup-wizard__option-label"><?php echo esc_html_x( 'Yes, enable Uncanny Agent', 'Setup wizard agent option', 'uncanny-automator' ); ?></span>
			<span class="automator-setup-wizard__option-sub"><?php echo esc_html_x( 'Open it from your dashboard whenever you want it.', 'Setup wizard agent option help', 'uncanny-automator' ); ?></span>
		</label>
		<label class="automator-setup-wizard__option">
			<input type="radio" name="choice" value="no" <?php checked( ! $agent_on ); ?> />
			<span class="automator-setup-wizard__option-label"><?php echo esc_html_x( 'No, not now', 'Setup wizard agent option', 'uncanny-automator' ); ?></span>
			<span class="automator-setup-wizard__option-sub"><?php echo esc_html_x( 'You can enable it later.', 'Setup wizard agent option help', 'uncanny-automator' ); ?></span>
		</label>
	</fieldset>

	<div class="automator-setup-wizard__go">
		<uas-button filled size="lg" type="submit"><?php echo esc_html_x( 'Continue', 'Setup wizard continue button', 'uncanny-automator' ); ?></uas-button>
	</div>
</form>

<div class="automator-setup-wizard__step-nav">
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_CHOOSE ) ); ?>">&larr; <?php echo esc_html_x( 'Back', 'Setup wizard back link', 'uncanny-automator' ); ?></a>
	<span></span>
</div>
