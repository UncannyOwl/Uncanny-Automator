<?php
/**
 * Step 4: Try it, for a connected AI or for Uncanny Agent.
 *
 * Copy a prompt into the connected AI, or send it to Uncanny Agent with Ask.
 *
 * @package Uncanny_Automator
 */

use Uncanny_Automator\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

$is_agent  = $this->is_agent_path();
$app       = $this->get_selected_app();
$app_name  = $this->get_app_name( $app );
$open_url  = $this->get_app_open_uri( $app );
$show_page = $this->has_page_builder_access();
$show_woo  = $this->is_woocommerce_active();

$groups = array(
	array(
		'title'   => esc_html_x( 'Get to know your site', 'Setup wizard prompt group', 'uncanny-automator' ),
		'prompts' => array(
			esc_html_x( "Review my site's setup and call out anything of interest.", 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Give me a summary of draft pages and posts.', 'Setup wizard prompt', 'uncanny-automator' ),
		),
	),
	array(
		'title'   => esc_html_x( 'Improve your site', 'Setup wizard prompt group', 'uncanny-automator' ),
		'prompts' => array_filter(
			array(
				esc_html_x( "Review my site's SEO.", 'Setup wizard prompt', 'uncanny-automator' ),
				esc_html_x( "Review my site's blog and suggest a couple of new posts that would complement the existing content.", 'Setup wizard prompt', 'uncanny-automator' ),
				esc_html_x( "Give me some suggestions to improve my site's performance.", 'Setup wizard prompt', 'uncanny-automator' ),
				$show_page ? esc_html_x( 'Build a new landing page for my site with Uncanny Page Builder.', 'Setup wizard prompt', 'uncanny-automator' ) : '',
			)
		),
	),
	array(
		'title'   => esc_html_x( 'Automate it', 'Setup wizard prompt group', 'uncanny-automator' ),
		'prompts' => array(
			esc_html_x( 'Build a recipe that posts new blog posts to my social media feeds.', 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Build a recipe that emails me whenever someone comments on one of my posts.', 'Setup wizard prompt', 'uncanny-automator' ),
		),
	),
);

if ( $show_woo ) {
	$groups[] = array(
		'title'   => esc_html_x( 'Your store', 'Setup wizard prompt group', 'uncanny-automator' ),
		'prompts' => array(
			esc_html_x( 'Summarize my sales this month and compare month over month.', 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Tell me which products get refunded the most and compare that with industry trends.', 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Tell me which products are most often sold together.', 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Which products are low on stock or out of stock?', 'Setup wizard prompt', 'uncanny-automator' ),
			esc_html_x( 'Review my product descriptions and suggest improvements that would help them sell.', 'Setup wizard prompt', 'uncanny-automator' ),
		),
	);
}
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
					esc_html( $app_name )
				);
				?>
			<?php endif; ?>
		</span>
	</span>
	<h2 class="automator-setup-wizard__title"><?php echo esc_html_x( 'Now ask it to do something', 'Setup wizard try heading', 'uncanny-automator' ); ?></h2>
	<p class="automator-setup-wizard__lead">
		<?php if ( $is_agent ) : ?>
			<?php echo esc_html_x( 'Click Ask to send a request to Uncanny Agent and watch it happen on this site.', 'Setup wizard try lead for Uncanny Agent', 'uncanny-automator' ); ?>
		<?php else : ?>
			<?php
			printf(
				/* translators: %s: Name of the AI app. */
				esc_html_x( 'Copy a request into %s and watch it happen on this site.', 'Setup wizard try lead', 'uncanny-automator' ),
				esc_html( $app_name )
			);
			?>
		<?php endif; ?>
	</p>
</div>

<div class="automator-setup-wizard__groups">
	<?php foreach ( $groups as $group ) : ?>
		<section class="automator-setup-wizard__group">
			<h3><?php echo esc_html( $group['title'] ); ?></h3>
			<?php foreach ( $group['prompts'] as $prompt ) : ?>
				<div class="automator-setup-wizard__prompt" data-prompt="<?php echo esc_attr( $prompt ); ?>">
					<span class="automator-setup-wizard__prompt-text"><?php echo esc_html( $prompt ); ?></span>
					<?php if ( $is_agent ) : ?>
						<uas-button size="sm" color="secondary" data-prompt-action="ask"><?php echo esc_html_x( 'Ask', 'Setup wizard prompt action', 'uncanny-automator' ); ?></uas-button>
					<?php else : ?>
						<uas-button size="sm" color="secondary" data-prompt-action="copy"><?php echo esc_html_x( 'Copy', 'Setup wizard prompt action', 'uncanny-automator' ); ?></uas-button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>
</div>

<p class="automator-setup-wizard__tooluse">
	<?php if ( $is_agent ) : ?>
		<?php echo esc_html_x( 'Uncanny Agent is free to start.', 'Setup wizard try usage note for Uncanny Agent', 'uncanny-automator' ); ?>
	<?php else : ?>
		<?php echo esc_html_x( 'Each request uses one or more tool uses.', 'Setup wizard try usage note', 'uncanny-automator' ); ?>
	<?php endif; ?>
</p>

<div class="automator-setup-wizard__ctas">
	<?php if ( $is_agent ) : ?>
		<uas-button filled href="<?php echo esc_url( $this->get_automator_dashboard_agent_uri() ); ?>"><?php echo esc_html_x( 'Open Uncanny Agent', 'Setup wizard try button', 'uncanny-automator' ); ?></uas-button>
	<?php elseif ( '' !== $open_url ) : ?>
		<uas-button filled href="<?php echo esc_url( $open_url ); ?>" target="_blank" unsafe-force-target>
			<?php
			printf(
				/* translators: %s: Name of the AI app. */
				esc_html_x( 'Open %s', 'Setup wizard try button', 'uncanny-automator' ),
				esc_html( $app_name )
			);
			?>
		</uas-button>
	<?php endif; ?>
	<uas-button color="secondary" href="<?php echo esc_url( $this->get_automator_dashboard_uri() ); ?>"><?php echo esc_html_x( 'Go to the Dashboard', 'Setup wizard try button', 'uncanny-automator' ); ?></uas-button>
</div>

<div class="automator-setup-wizard__linksrow">
	<a href="<?php echo esc_url( $this->get_new_recipe_uri() ); ?>"><?php echo esc_html_x( 'Build a recipe yourself', 'Setup wizard try link', 'uncanny-automator' ); ?></a>
	<a href="<?php echo esc_url( $this->get_screen_uri( Setup_Wizard::SCREEN_CHOOSE ) ); ?>">
		<?php if ( $is_agent ) : ?>
			<?php echo esc_html_x( 'Connect your own AI instead', 'Setup wizard try link', 'uncanny-automator' ); ?>
		<?php else : ?>
			<?php echo esc_html_x( 'Connect another AI app', 'Setup wizard try link', 'uncanny-automator' ); ?>
		<?php endif; ?>
	</a>
</div>
