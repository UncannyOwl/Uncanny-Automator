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

				<?php esc_html_e( 'OpenAI', 'uncanny-automator' ); ?>

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

					<uo-alert type="info" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_attr_e( 'Uncanny Automator only supports connecting to one OpenAI account at a time.', 'uncanny-automator' ); ?>">
						<?php esc_html_e( 'If you create recipes and then change the connected OpenAI account, your previous recipes may no longer work.', 'uncanny-automator' ); ?>
					</uo-alert>

					<?php if ( true === $vars['can_access_gpt4'] ) { ?>
						<uo-alert type="success" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_attr_e( 'The connected account has access to the GPT-4 API.', 'uncanny-automator' ); ?>">
						</uo-alert>
					<?php } else { ?>
						<uo-alert type="warning" class="uap-spacing-bottom uap-spacing-bottom--big" heading="<?php esc_attr_e( 'GPT-4 API access', 'uncanny-automator' ); ?>">
							<?php
								esc_html_e(
									'The connected account does not currently have access to the GPT-4 API. Once you gain access to the GPT-4 API, additional OpenAI actions will become available. If you have recently been granted access to GPT-4, please create a new key, disconnect the current connection, and reconnect by entering your new key. You may also use the button below to recheck access to GPT-4.',
									'uncanny-automator'
								);
							?>
							<br/></br>
							<uo-button href="<?php echo esc_url( $vars['recheck_gpt4_access_url'] ); ?>" size="small" color="secondary">
								<?php esc_html_e( 'Recheck GPT-4 access', 'uncanny-automator' ); ?>
							</uo-button>
						</uo-alert>
					<?php } ?>

					<?php } ?>

				<?php if ( false === $vars['is_connected'] ) { ?>

					<div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to OpenAI', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Use Uncanny Automator to feed prompts to OpenAI and use AI-generated content inside your actions. Choose from multiple models and settings to automate AI-generated content on your WordPress site.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong>
							<?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon>
							<?php esc_html_e( 'Action' ); ?>:
							<?php esc_html_e( 'Use a prompt to generate text', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<uo-alert heading="<?php esc_html_e( 'Setup instructions', 'uncanny-automator' ); ?>" class="uap-spacing-bottom uap-spacing-top">

						<?php esc_html_e( 'Connecting to OpenAI is a simple 1-step process of creating a secret API key in your OpenAI account.', 'uncanny-automator' ); ?>

						<hr/>

						<uo-button target="_blank" color="secondary" size="small" href="<?php echo esc_url( $vars['setup_url'] ); ?>">

							<?php esc_html_e( 'Setup instructions', 'uncanny-automator' ); ?>
							<uo-icon id="external-link"></uo-icon>

						</uo-button>

					</uo-alert>

					<uo-text-field
						id="automator_open_ai_secret"
						value="<?php echo esc_attr( $vars['secret_key'] ); ?>"
						label="<?php esc_attr_e( 'Secret key', 'uncanny-automator' ); ?>"
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
						<?php esc_html_e( 'Connect OpenAI account', 'uncanny-automator' ); ?>
					</uo-button>
				<?php } ?>

				<?php if ( ! empty( $vars['is_connected'] ) ) { ?>

					<div class="uap-settings-panel-user">

						<div class="uap-settings-panel-user__avatar">
							O
						</div><!--.uap-settings-panel-user__avatar-->

						<div class="uap-settings-panel-user-info">

							<div class="uap-settings-panel-user-info__main">
								<?php esc_html_e( 'OpenAI account', 'uncanny-automator' ); ?>
								<uo-icon integration="OPEN_AI"></uo-icon>
							</div>

							<div class="uap-settings-panel-user-info__additional">
								<?php /* translators: %1$s The secret key. */ ?>
								<?php echo sprintf( esc_html__( 'API key connected: %1$s', 'uncanny-automator' ), esc_html( $vars['redacted_token'] ) ); ?>
							</div>

						</div> <!--uap-settings-panel-user-info-->

					</div>
				<?php } ?>

			</div>

			<div class="uap-settings-panel-bottom-right">

				<?php if ( ! empty( $vars['is_connected'] ) ) { ?>

					<uo-button href="<?php echo esc_url( $vars['disconnect_url'] ); ?>" color="danger">
						<uo-icon id="sign-out"></uo-icon>
						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>

				<?php } ?>

			</div>

		</div>

	</div>
</form>
