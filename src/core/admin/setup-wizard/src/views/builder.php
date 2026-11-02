<?php
/**
 * Step 3: Uncanny Page Builder offer.
 *
 * Shown with a connected AI, or with Uncanny Agent enabled (which then names
 * Uncanny Agent in place of "your AI"). Either answer goes on to Try it.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$is_agent   = $this->is_agent_path();
$app        = $this->get_selected_app();
// Yes is preselected until the site has made a choice; the setting itself is off by default.
$builder_on = ! $this->is_page_builder_setting_saved() || $this->is_page_builder_enabled();
$who        = $is_agent
	? esc_html_x( 'Uncanny Agent', 'Setup wizard builder subject', 'uncanny-automator' )
	: esc_html_x( 'your AI', 'Setup wizard builder subject', 'uncanny-automator' );
$who_cap    = $is_agent
	? $who
	: esc_html_x( 'Your AI', 'Setup wizard builder subject at sentence start', 'uncanny-automator' );
?>

<div class="automator-setup-wizard__head">
	<span class="automator-setup-wizard__pill automator-setup-wizard__pill--ok">
		<i aria-hidden="true"></i>
		<span>
			<?php if ( $is_agent ) : ?>
				<?php echo esc_html_x( 'Uncanny Agent is enabled', 'Setup wizard agent enabled pill', 'uncanny-automator' ); ?>
			<?php else : ?>
				<?php
				printf(
					/* translators: %s: Name of the AI app. */
					esc_html_x( '%s is connected to this site', 'Setup wizard connection pill', 'uncanny-automator' ),
					esc_html( $this->get_app_name( $app ) )
				);
				?>
			<?php endif; ?>
		</span>
	</span>
	<h2 class="automator-setup-wizard__title">
		<?php
		printf(
			/* translators: %s: "your AI" or "Uncanny Agent". */
			esc_html_x( 'Describe a page, and %s builds it', 'Setup wizard builder heading', 'uncanny-automator' ),
			esc_html( $who )
		);
		?>
	</h2>
	<p class="automator-setup-wizard__lead">
		<?php
		printf(
			/* translators: %s: "Your AI" or "Uncanny Agent". */
			esc_html_x( 'Already use Gutenberg, Spectra, Elementor, Divi or WPBakery? %s builds and updates pages in that builder too. No page builder? Uncanny Page Builder is built in: fast, light and AI-native.', 'Setup wizard builder lead', 'uncanny-automator' ),
			esc_html( $who_cap )
		);
		?>
	</p>
</div>

<form class="automator-setup-wizard__offer" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Setup_Wizard::PAGE_BUILDER_CHOICE_ACTION ); ?>" />
	<?php wp_nonce_field( Setup_Wizard::CHOICE_NONCE ); ?>
	<?php if ( $is_agent ) : ?>
		<input type="hidden" name="agent" value="1" />
	<?php elseif ( '' !== $app ) : ?>
		<input type="hidden" name="app" value="<?php echo esc_attr( $app ); ?>" />
	<?php endif; ?>

	<p class="automator-setup-wizard__forline">
		<?php
		printf(
			/* translators: %s: "your AI" or "Uncanny Agent". */
			esc_html_x( 'Describe the page you want, and %s designs the layout, writes the content and builds it in clean HTML and CSS that you own. Uncanny Page Builder is for sites without a page builder, or for a fast new landing page.', 'Setup wizard builder note', 'uncanny-automator' ),
			esc_html( $who )
		);
		?>
	</p>

	<ul class="automator-setup-wizard__ticks">
		<li>
			<?php
			printf(
				/* translators: %s: "Your AI's" or "Uncanny Agent's". */
				esc_html_x( "Nothing goes live until you save. %s changes land on a draft.", 'Setup wizard builder benefit', 'uncanny-automator' ),
				$is_agent
					? esc_html_x( "Uncanny Agent's", 'Setup wizard builder possessive', 'uncanny-automator' )
					: esc_html_x( "Your AI's", 'Setup wizard builder possessive', 'uncanny-automator' )
			);
			?>
		</li>
		<li><?php echo esc_html_x( 'Edit any text or image by hand in the Manual editor.', 'Setup wizard builder benefit', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Try it on an existing page. The original is saved, and you can switch back any time.', 'Setup wizard builder benefit', 'uncanny-automator' ); ?></li>
	</ul>

	<fieldset class="automator-setup-wizard__choice">
		<legend><?php echo esc_html_x( 'Enable Uncanny Page Builder?', 'Setup wizard builder question', 'uncanny-automator' ); ?></legend>
		<label class="automator-setup-wizard__option">
			<input type="radio" name="choice" value="yes" <?php checked( $builder_on ); ?> />
			<span class="automator-setup-wizard__option-label"><?php echo esc_html_x( 'Yes, enable Uncanny Page Builder', 'Setup wizard builder option', 'uncanny-automator' ); ?></span>
			<span class="automator-setup-wizard__option-sub">
				<?php
				printf(
					/* translators: %s: "Your AI" or "Uncanny Agent". */
					esc_html_x( '%s can start building pages right away.', 'Setup wizard builder option help', 'uncanny-automator' ),
					esc_html( $who_cap )
				);
				?>
			</span>
		</label>
		<label class="automator-setup-wizard__option">
			<input type="radio" name="choice" value="no" <?php checked( ! $builder_on ); ?> />
			<span class="automator-setup-wizard__option-label"><?php echo esc_html_x( 'No, not now', 'Setup wizard builder option', 'uncanny-automator' ); ?></span>
			<span class="automator-setup-wizard__option-sub">
				<?php
				printf(
					/* translators: %s: "Your AI" or "Uncanny Agent". */
					esc_html_x( '%s still builds and updates pages in the page builder you already use.', 'Setup wizard builder option help', 'uncanny-automator' ),
					esc_html( $who_cap )
				);
				?>
			</span>
		</label>
	</fieldset>

	<div class="automator-setup-wizard__go">
		<uas-button filled size="lg" type="submit"><?php echo esc_html_x( 'Continue', 'Setup wizard continue button', 'uncanny-automator' ); ?></uas-button>
	</div>
</form>

<div class="automator-setup-wizard__step-nav">
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( $is_agent ? Setup_Wizard::SCREEN_AGENT : Setup_Wizard::SCREEN_CHOOSE ) ); ?>">&larr; <?php echo esc_html_x( 'Back', 'Setup wizard back link', 'uncanny-automator' ); ?></a>
	<span></span>
</div>
