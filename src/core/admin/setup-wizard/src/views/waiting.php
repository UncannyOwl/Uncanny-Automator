<?php
/**
 * Step 2: Waiting for the chosen AI to connect.
 *
 * The script polls the connection status and moves on to Page Builder by itself.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$app      = $this->get_selected_app();
$app_name = $this->get_app_name( $app );

$hosted_address = $this->get_hosted_app_address();
?>

<div class="automator-setup-wizard__head">
	<span class="automator-setup-wizard__pill automator-setup-wizard__pill--wait" data-connection-pill role="status" aria-live="polite">
		<i aria-hidden="true"></i>
		<span data-connection-pill-text>
			<?php
			printf(
				/* translators: %s: Name of the AI app. */
				esc_html_x( 'Waiting for %s to connect…', 'Setup wizard waiting pill', 'uncanny-automator' ),
				esc_html( $app_name )
			);
			?>
		</span>
	</span>
	<h2 class="automator-setup-wizard__title">
		<?php
		printf(
			/* translators: 1: Name of the AI app. 2: Address of the hosted app. */
			esc_html_x( 'Connect %1$s on %2$s', 'Setup wizard waiting heading', 'uncanny-automator' ),
			esc_html( $app_name ),
			esc_html( $hosted_address )
		);
		?>
	</h2>
</div>

<ol class="automator-setup-wizard__wsteps">
	<li>
		<?php
		printf(
			/* translators: %s: Name of the AI app. */
			esc_html_x( 'In the new tab, follow the steps to add Uncanny Automator to %s.', 'Setup wizard waiting step', 'uncanny-automator' ),
			esc_html( $app_name )
		);
		?>
	</li>
	<li><?php echo esc_html_x( 'Approve the connection to this site.', 'Setup wizard waiting step', 'uncanny-automator' ); ?></li>
	<li><?php echo esc_html_x( "Come back to this tab when you're done.", 'Setup wizard waiting step', 'uncanny-automator' ); ?></li>
</ol>

<p class="automator-setup-wizard__lead automator-setup-wizard__lead--center" data-connection-wait-note>
	<?php
	printf(
		/* translators: %s: Name of the AI app. */
		esc_html_x( 'This page moves on to the next step by itself as soon as %s is connected.', 'Setup wizard waiting note', 'uncanny-automator' ),
		esc_html( $app_name )
	);
	?>
</p>

<div class="automator-setup-wizard__lead automator-setup-wizard__lead--center" data-connection-help-container hidden>
	<p data-connection-help role="status" aria-live="polite"></p>
	<button type="button" data-connection-retry hidden><?php echo esc_html_x( 'Try again', 'Setup wizard connection action', 'uncanny-automator' ); ?></button>
	<a href="" data-connection-reload hidden><?php echo esc_html_x( 'Reload this page', 'Setup wizard connection action', 'uncanny-automator' ); ?></a>
</div>

<div class="automator-setup-wizard__linksrow">
	<a href="<?php echo esc_url( $this->get_ai_connect_uri( $app ) ); ?>" target="_blank" rel="noopener">
		<?php
		printf(
			/* translators: %s: Address of the hosted app. */
			esc_html_x( 'Reopen %s', 'Setup wizard waiting link', 'uncanny-automator' ),
			esc_html( $hosted_address )
		);
		?>
	</a>
	<a href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_CHOOSE ) ); ?>"><?php echo esc_html_x( 'Choose a different AI', 'Setup wizard waiting link', 'uncanny-automator' ); ?></a>
	<a href="<?php echo esc_url( Setup_Wizard::KNOWLEDGE_BASE_URL ); ?>" target="_blank" rel="noopener"><?php echo esc_html_x( 'Having trouble connecting?', 'Setup wizard waiting link', 'uncanny-automator' ); ?></a>
</div>
