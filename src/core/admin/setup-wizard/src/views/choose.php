<?php
/**
 * Step 2: Choose your AI.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$email          = $this->get_account_email();
$hosted_address = $this->get_hosted_app_address();
?>

<div class="automator-setup-wizard__head">
	<span class="automator-setup-wizard__pill automator-setup-wizard__pill--ok">
		<i aria-hidden="true"></i>
		<span>
			<?php if ( '' !== $email ) : ?>
				<?php
				printf(
					/* translators: %s: Account e-mail. */
					esc_html_x( 'Signed in as %s.', 'Setup wizard signed-in pill', 'uncanny-automator' ),
					esc_html( $email )
				);
				?>
			<?php elseif ( $this->is_pro_active() ) : ?>
				<?php echo esc_html_x( 'Your Pro license is active on this site.', 'Setup wizard signed-in pill for Pro', 'uncanny-automator' ); ?>
			<?php else : ?>
				<?php echo esc_html_x( 'Your free account is linked to this site.', 'Setup wizard signed-in pill without e-mail', 'uncanny-automator' ); ?>
			<?php endif; ?>
		</span>
	</span>
	<?php if ( $this->is_pro_active() ) : ?>
		<?php // Pro's step 1 is the license, so the AI pitch moves here. ?>
		<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Connect your AI to this site', 'Setup wizard choose heading for Pro', 'uncanny-automator' ); ?></h2>
		<p class="automator-setup-wizard__lead">
			<?php
			printf(
				/* translators: %s: Address of the hosted app. */
				esc_html_x( 'Manage and automate this site by asking the AI you already use. Pick it below. %s opens in a new tab, where you can connect your AI in 60 seconds.', 'Setup wizard choose lead for Pro', 'uncanny-automator' ),
				esc_html( $hosted_address )
			);
			?>
		</p>
	<?php else : ?>
		<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Which AI do you use?', 'Setup wizard choose heading', 'uncanny-automator' ); ?></h2>
		<p class="automator-setup-wizard__lead">
			<?php
			printf(
				/* translators: %s: Address of the hosted app. */
				esc_html_x( 'Pick it below. %s opens in a new tab, where you can connect your AI in 60 seconds.', 'Setup wizard choose lead', 'uncanny-automator' ),
				esc_html( $hosted_address )
			);
			?>
		</p>
	<?php endif; ?>
</div>

<div class="automator-setup-wizard__picker">
	<?php foreach ( $this->get_ai_apps() as $app_key => $app ) : ?>
		<a
			class="automator-setup-wizard__app"
			href="<?php echo esc_url( $this->get_ai_connect_uri( $app_key ) ); ?>"
			target="_blank"
			rel="noopener"
			data-app="<?php echo esc_attr( $app_key ); ?>"
			data-waiting-url="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_WAITING, array( 'app' => $app_key ) ) ); ?>"
		>
			<?php if ( ! empty( $app['icon'] ) ) : ?>
				<img class="automator-setup-wizard__app-icon" src="<?php echo esc_url( $app['icon'] ); ?>" alt="" width="28" height="28" />
			<?php elseif ( ! empty( $app['brand'] ) ) : ?>
				<uap-d-ai-app-logo brand="<?php echo esc_attr( $app['brand'] ); ?>" size="lg"></uap-d-ai-app-logo>
			<?php else : ?>
				<uap-d-ai-app-logo size="lg"></uap-d-ai-app-logo>
			<?php endif; ?>
			<span class="automator-setup-wizard__app-label"><?php echo esc_html( $app['label'] ); ?></span>
			<span class="automator-setup-wizard__app-sub"><?php echo ! empty( $app['sub'] ) ? esc_html( $app['sub'] ) : esc_html( $hosted_address ) . ' &#8599;'; ?></span>
		</a>
	<?php endforeach; ?>
</div>

<div class="automator-setup-wizard__divider"><span><?php echo esc_html_x( 'or', 'Setup wizard divider', 'uncanny-automator' ); ?></span></div>

<div class="automator-setup-wizard__alt">
	<uas-button color="secondary" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_AGENT ) ); ?>"><?php echo esc_html_x( "I don't have an AI", 'Setup wizard choose alternative', 'uncanny-automator' ); ?></uas-button>
	<uas-button color="secondary" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_SKIPPED ) ); ?>"><?php echo esc_html_x( "I don't want to use AI", 'Setup wizard choose alternative', 'uncanny-automator' ); ?></uas-button>
</div>

<?php // App credits belong to the free account, so Pro sites have no manual-recipes path here. ?>
<?php if ( ! $this->is_pro_active() ) : ?>
	<p class="automator-setup-wizard__buildline">
		<strong><?php echo esc_html_x( 'Here to build recipes?', 'Setup wizard manual recipes lead', 'uncanny-automator' ); ?></strong>
		<?php echo esc_html_x( 'We recommend you start by connecting your AI, so your AI can configure recipes for you.', 'Setup wizard manual recipes body', 'uncanny-automator' ); ?>
		<a href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_CREDITS ) ); ?>"><?php echo esc_html_x( 'I would prefer to build recipes manually.', 'Setup wizard manual recipes link', 'uncanny-automator' ); ?></a>
	</p>
<?php endif; ?>

<div class="automator-setup-wizard__step-nav">
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_ACCOUNT, array( 'review' => '1' ) ) ); ?>">&larr; <?php echo esc_html_x( 'Back', 'Setup wizard back link', 'uncanny-automator' ); ?></a>
	<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_SKIPPED ) ); ?>"><?php echo esc_html_x( 'Skip for now', 'Setup wizard skip link', 'uncanny-automator' ); ?></a>
</div>
