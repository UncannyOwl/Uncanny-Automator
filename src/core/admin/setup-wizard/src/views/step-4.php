<?php
/**
 * Final step template file.
 *
 * Rendered as step 4 for users who have not connected an account, and as step 3
 * for connected users (who skip the usage tracking step).
 *
 * @package Uncanny_Automator
 */

// Step 1 asks Lite users for a free account and Pro users for a license key.
$is_pro_active      = $this->is_pro_active();
$is_step_1_complete = $this->is_step_1_complete();

// These are independent Axis decisions. Agent follows AGENT_LAUNCHER_TAB;
// Page Builder follows PAGE_BUILDER_MENU plus saved-setting/create-hook readiness.
$has_agent_access        = $this->has_agent_access();
$has_page_builder_access = $this->has_page_builder_access();
$build_page_url          = $this->get_page_builder_create_uri();
$can_open_agent          = $has_agent_access && current_user_can( 'manage_options' );

// Pro customers upgrade an existing license from their account; Lite users have
// no license to manage yet, so they go to pricing.
$upgrade_plan_url = $is_pro_active
	? 'https://automatorplugin.com/my-account/licenses/'
	: 'https://automatorplugin.com/pricing/';
?>
<div class="center row-1 automator-setup-wizard__final-step">
	<div class="automator-setup-wizard__branding">
		<img width="380" src="<?php echo esc_url( Uncanny_Automator\Utilities::automator_get_asset( 'build/img/logo-horizontal.svg' ) ); ?>" alt="" />
	</div>
	<div class="automator-setup-wizard__steps">
		<div class="automator-setup-wizard__steps__inner-wrap">
			<ol>
				<?php foreach ( $this->get_steps() as $step ) : ?>
					<li class="<?php echo implode( ' ', $step['classes'] ); ?>"> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span>
							<?php
							// translators: %s: Step number.
							printf( esc_html__( 'Step %s', 'uncanny-automator' ), esc_html( $step['label'] ) );
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>

	<h2 class="title">
		<?php esc_html_e( "You're all set!", 'uncanny-automator' ); ?>
	</h2>

	<p>
		<?php esc_html_e( 'Uncanny Automator is ready to start automating your site. How would you like to begin?', 'uncanny-automator' ); ?>
	</p>

	<div class="automator-setup-wizard__options">

		<div class="automator-setup-wizard__option <?php echo $can_open_agent ? '' : 'automator-setup-wizard__option--unavailable'; ?>">
			<?php if ( $can_open_agent ) : ?>
				<uo-button href="<?php echo esc_url( $this->get_automator_dashboard_agent_uri() ); ?>">
					<?php esc_html_e( 'Chat with Uncanny Agent', 'uncanny-automator' ); ?>
				</uo-button>
			<?php else : ?>
				<uo-button disabled>
					<?php esc_html_e( 'Chat with Uncanny Agent', 'uncanny-automator' ); ?>
				</uo-button>
			<?php endif; ?>
			<p><?php esc_html_e( 'Chat with Uncanny Agent to create your first recipe, build your first page, complete your first task, or analyze your business.', 'uncanny-automator' ); ?></p>
		</div>

		<div class="automator-setup-wizard__option">
			<uo-button
				href="<?php echo esc_url( admin_url( 'post-new.php' ) . '?post_type=uo-recipe' ); ?>"
				<?php echo $can_open_agent ? 'color="secondary"' : ''; ?>
			>
				<?php esc_html_e( 'Create my first recipe', 'uncanny-automator' ); ?>
			</uo-button>
			<p><?php esc_html_e( 'Jump straight into creating a recipe in Uncanny Recipe Builder.', 'uncanny-automator' ); ?></p>
		</div>

		<div class="automator-setup-wizard__option <?php echo $has_page_builder_access ? '' : 'automator-setup-wizard__option--unavailable'; ?>">
			<?php if ( $has_page_builder_access ) : ?>
				<form class="automator-setup-wizard__create-page-form" action="<?php echo esc_url( $build_page_url ); ?>" method="post">
					<input type="hidden" name="uo_button_form_marker" value="1" /><!-- uo-button's submit handler requires a native form control. -->
					<uo-button
						type="submit"
						color="secondary"
					>
						<?php esc_html_e( 'Build my first page', 'uncanny-automator' ); ?>
					</uo-button>
				</form>
			<?php else : ?>
				<uo-button disabled>
					<?php esc_html_e( 'Build my first page', 'uncanny-automator' ); ?>
				</uo-button>
			<?php endif; ?>
			<p><?php esc_html_e( 'Create my first page with Uncanny Agent and Uncanny Page Builder.', 'uncanny-automator' ); ?></p>
		</div>

	</div>

	<?php if ( ! $has_agent_access ) : ?>

		<div class="automator-setup-wizard__connect-callout">

			<?php if ( ! $is_step_1_complete && $is_pro_active ) : ?>

				<?php // Pro is installed but its license was never activated. ?>
				<p><?php esc_html_e( 'Activate your Pro license to use Uncanny Agent and Uncanny Page Builder!', 'uncanny-automator' ); ?></p>
				<uo-button href="<?php echo esc_url( $this->get_dashboard_uri( 1 ) ); ?>">
					<?php esc_html_e( 'Enter license key', 'uncanny-automator' ); ?>
				</uo-button>

			<?php elseif ( ! $is_step_1_complete ) : ?>

				<?php
				// Second chance to connect for users who skipped step 1.
				// TODO: add a parameter so the registration flow returns the user to this
				// step (instead of step 2) when they connect from here — requires
				// coordination with the signup flow on automatorplugin.com.
				?>
				<p><?php esc_html_e( 'Connect a free account to use Uncanny Agent and Uncanny Page Builder!', 'uncanny-automator' ); ?></p>
				<uo-button
					href="<?php echo esc_url( $this->get_connect_button_uri() ); ?>"
					unsafe-force-target
					target="_self"
				>
					<?php esc_html_e( 'Connect your free account!', 'uncanny-automator' ); ?>
				</uo-button>

			<?php else : ?>

				<?php // A licensed Pro plan carries no Uncanny Agent usage. ?>
				<p><?php esc_html_e( 'Upgrade to an AI + Automation plan to use Uncanny Agent and Uncanny Page Builder!', 'uncanny-automator' ); ?></p>
				<uo-button href="<?php echo esc_url( $upgrade_plan_url ); ?>">
					<?php esc_html_e( 'View upgrade options', 'uncanny-automator' ); ?>
				</uo-button>

			<?php endif; ?>

		</div>

	<?php endif; ?>

	<div class="automator-setup-wizard__step-nav">
		<span class="automator-setup-wizard__step-nav-start">
			<?php if ( ! $is_pro_active || ! $is_step_1_complete ) : ?>
				<a class="automator-setup-wizard__nav-link" href="<?php echo esc_url( $is_pro_active ? $this->get_dashboard_uri( 1 ) : $this->get_previous_step_uri() ); ?>">
					&larr; <?php esc_html_e( 'Back', 'uncanny-automator' ); ?>
				</a>
			<?php endif; ?>
		</span>
	</div>

</div>
