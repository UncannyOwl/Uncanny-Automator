<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<form method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon integration="OPEN_AI"></uo-icon>

				<?php echo esc_html_x( 'OpenAI', 'Open Ai', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<?php if ( ! empty( $vars['alerts'] ) ) { ?>

					<?php foreach ( $vars['alerts'] as $alert ) { ?>

						<uo-alert class="uap-spacing-bottom" type="<?php echo esc_attr( $alert['type'] ); ?>" heading="<?php echo esc_attr( $alert['code'] ); ?>">
							<?php echo esc_html( $alert['message'] ); ?>
						</uo-alert>

					<?php } ?>

				<?php } ?>

				<?php if ( false !== $vars['is_connected'] ) { ?>

					<uo-alert type="info" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one OpenAI account at a time.', 'Open Ai', 'uncanny-automator' ); ?>">
						<?php echo esc_html_x( 'If you create recipes and then change the connected OpenAI account, your previous recipes may no longer work.', 'Open Ai', 'uncanny-automator' ); ?>
					</uo-alert>

					<?php if ( true === $vars['can_access_gpt4'] ) { ?>
						<uo-alert type="success" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php echo esc_attr_x( 'The connected account has access to the GPT-4 API.', 'Open Ai', 'uncanny-automator' ); ?>">
						</uo-alert>
					<?php } else { ?>
						<uo-alert type="warning" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php echo esc_attr_x( 'GPT-4 API access', 'Open Ai', 'uncanny-automator' ); ?>">
							<?php
								echo esc_html_x(
									'The connected account does not currently have access to the GPT-4 API. Once you gain access to the GPT-4 API, additional OpenAI actions will become available. If you have recently been granted access to GPT-4, please create a new key, disconnect the current connection, and reconnect by entering your new key. You may also use the button below to recheck access to GPT-4.',
									'Open Ai',
									'uncanny-automator'
								);
							?>
							<br/></br>
							<uo-button href="<?php echo esc_url( $vars['recheck_gpt4_access_url'] ); ?>" size="small" color="secondary">
								<?php echo esc_html_x( 'Recheck GPT-4 access', 'Open Ai', 'uncanny-automator' ); ?>
							</uo-button>
						</uo-alert>
					<?php } ?>

					<?php } ?>

				<?php if ( false === $vars['is_connected'] ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php echo esc_html_x( 'Connect Uncanny Automator to OpenAI', 'Open Ai', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php echo esc_html_x( 'Use Uncanny Automator to feed prompts to OpenAI and use AI-generated content inside your actions. Choose from multiple models and settings to automate AI-generated content on your WordPress site.', 'Open Ai', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong>
							<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Open Ai', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Analyze sentiment with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Correct spelling and grammar with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Create a list of links that might help resolve a customer request with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Generate a meta description with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Generate an excerpt suitable for Instagram with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Generate an excerpt suitable for Twitter with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Generate an excerpt with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Generate an SEO title with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Translate text with GPT-4', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Use a prompt to generate an image', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Use a prompt to generate an image with DALL-E', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Use a prompt to generate text', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php echo esc_html_x( 'Action', 'Open Ai', 'uncanny-automator' ); ?>:
							<?php echo esc_html_x( 'Use a prompt to generate text with the GPT model', 'Open Ai', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<uo-alert heading="<?php echo esc_html_x( 'Setup instructions', 'Open Ai', 'uncanny-automator' ); ?>" class="uap-spacing-bottom uap-spacing-top">

						<?php echo esc_html_x( 'Connecting to OpenAI is a simple 1-step process of creating a secret API key in your OpenAI account.', 'Open Ai', 'uncanny-automator' ); ?>

						<hr/>

						<uo-button target="_blank" color="secondary" size="small" href="<?php echo esc_url( $vars['setup_url'] ); ?>">

							<?php echo esc_html_x( 'Setup instructions', 'Open Ai', 'uncanny-automator' ); ?>

						</uo-button>

					</uo-alert>

					<uo-text-field
						id="automator_open_ai_secret"
						value="<?php echo esc_attr( $vars['secret_key'] ); ?>"
						label="<?php echo esc_attr_x( 'Secret key', 'Open Ai', 'uncanny-automator' ); ?>"
						required
						<?php echo false !== $vars['is_connected'] ? 'hidden disabled' : ''; ?>
						class="uap-spacing-top"
					></uo-text-field>

				<?php } ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">

				<?php if ( false === $vars['is_connected'] ) { ?>
					<uo-button type="submit">
						<?php echo esc_html_x( 'Connect OpenAI account', 'Open Ai', 'uncanny-automator' ); ?>
					</uo-button>
				<?php } ?>

				<?php if ( ! empty( $vars['is_connected'] ) ) { ?>

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">
							O
						</div><!--.uap-settings-panel-user__avatar-->

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">
								<?php echo esc_html_x( 'OpenAI account', 'Open Ai', 'uncanny-automator' ); ?>
								<uo-icon integration="OPEN_AI"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php /* translators: %1$s The secret key. */ ?>
								<?php echo sprintf( esc_html_x( 'API key connected: %1$s', 'Open Ai', 'uncanny-automator' ), esc_html( $vars['redacted_token'] ) ); ?>
							</div>

						</div> <!--uap-settings-panel-user-info-->

					</div>
				<?php } ?>

			</div>

			<div class="uap-settings-panel-bottom-right">

				<?php if ( ! empty( $vars['is_connected'] ) ) { ?>

					<uo-button href="<?php echo esc_url( $vars['disconnect_url'] ); ?>" color="danger">
						<uo-icon id="right-from-bracket"></uo-icon>
						<?php echo esc_html_x( 'Disconnect', 'Open Ai', 'uncanny-automator' ); ?>
					</uo-button>

				<?php } ?>

			</div>

		</div>

	</div>
</form>
