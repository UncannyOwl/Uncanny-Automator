<?php
/**
 * Step 4: Skipped. The wizard ended without a connected AI or Uncanny Agent.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="automator-setup-wizard__head">
	<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( "You're set up. Connect your AI when you're ready.", 'Setup wizard skipped heading', 'uncanny-automator' ); ?></h2>
	<p class="automator-setup-wizard__lead"><?php echo esc_html_x( 'Uncanny Automator is installed and working. Connect your AI in 60 seconds, any time, from Connect your AI in the Automator menu.', 'Setup wizard skipped lead', 'uncanny-automator' ); ?></p>
</div>

<div class="automator-setup-wizard__tiles">
	<div class="automator-setup-wizard__tile automator-setup-wizard__tile--primary">
		<h3><?php echo esc_html_x( 'Connect your AI', 'Setup wizard skipped tile heading', 'uncanny-automator' ); ?></h3>
		<p><?php echo esc_html_x( 'Manage and automate this site by asking ChatGPT, Claude, Gemini or any AI app that supports remote MCP servers. Your AI can build recipes for you too.', 'Setup wizard skipped tile body', 'uncanny-automator' ); ?></p>
		<uas-button filled href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_ACCOUNT ) ); ?>"><?php echo esc_html_x( 'Connect your AI', 'Setup wizard skipped tile button', 'uncanny-automator' ); ?></uas-button>
	</div>
	<div class="automator-setup-wizard__tile">
		<h3><?php echo esc_html_x( 'Build a recipe yourself', 'Setup wizard skipped tile heading', 'uncanny-automator' ); ?></h3>
		<p><?php echo esc_html_x( 'Open the drag-and-drop recipe builder and start with a blank recipe.', 'Setup wizard skipped tile body', 'uncanny-automator' ); ?></p>
		<uas-button color="secondary" href="<?php echo esc_url( $this->get_new_recipe_uri() ); ?>"><?php echo esc_html_x( 'Open the recipe builder', 'Setup wizard skipped tile button', 'uncanny-automator' ); ?></uas-button>
	</div>
</div>
