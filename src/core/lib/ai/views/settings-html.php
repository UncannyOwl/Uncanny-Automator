<?php
/**
 * AI Provider Settings Template
 *
 * @package Uncanny_Automator
 * @subpackage Integrations/AI
 * @version 1.0.0
 */

use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Get the presentation object from the variables.
 *
 * @var array<string, mixed> $vars
 * @var Settings|null $presentation
 */
$vars         = isset( $vars ) ? $vars : array();
$presentation = isset( $vars['presentation'] ) && $vars['presentation'] instanceof Settings
	? $vars['presentation']
	: null;

if ( null === $presentation ) {
	return;
}

/**
 * Get the settings array from the variables.
 *
 * @var array{
 *   id: string,
 *   icon: string,
 *   name: string,
 *   options: array<int, array{
 *     id: string,
 *     label: string,
 *     placeholder: string,
 *     value: string,
 *     description: string
 *   }>,
 *   connection_status: string
 * }|null $settings
 */
$settings = isset( $vars['settings'] ) && is_array( $vars['settings'] ) ? $vars['settings'] : null;

if ( null === $settings ) {
	return;
}

// Validate required settings keys.
$required_keys = array( 'id', 'icon', 'name', 'options', 'connection_status' );
foreach ( $required_keys as $key ) {
	if ( ! array_key_exists( $key, $settings ) ) {
		return;
	}
}

/**
 * Get flash message if any.
 *
 * @var array{type: string, message: string}|false $flash_message
 */
$flash_message = automator_get_flash_message( $settings['id'] );

// Check connection status.
$is_connected = 'success' === $settings['connection_status'];

/**
 * Get action sentences from the presentation.
 *
 * @var array<int, string> $action_sentences
 */
$action_sentences = $presentation->get_action_sentences();
?>

<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">
		<!-- Panel Header -->
		<div class="uap-settings-panel-top">
			<!-- Title Section -->
			<div class="uap-settings-panel-title">
				<uo-icon integration="<?php echo esc_attr( $settings['icon'] ); ?>"></uo-icon>
				<?php echo esc_html( $presentation->get_heading() ); ?>
			</div>

			<!-- Panel Content -->
			<div class="uap-settings-panel-content">
				
				<?php // Display flash messages if any. ?>
				<?php if ( false !== $flash_message ) : ?>
					<uo-alert class="uap-spacing-bottom uap-spacing-top" type="<?php echo esc_attr( $flash_message['type'] ); ?>">
						<?php echo wp_kses_post( $flash_message['message'] ); ?>
					</uo-alert>
				<?php endif; ?>

				<?php // Display connection form if not connected. ?>
				<?php if ( ! $is_connected ) : ?>
					
					<div class="uap-settings-panel-content-subtitle">
						<?php echo esc_html( $presentation->get_subheading() ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php echo esc_html( $presentation->get_description() ); ?>
					</div>

					<p>
						<strong>
							<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'AI', 'uncanny-automator' ); ?>
						</strong>
					</p>

					<?php // Display available actions. ?>
					<?php if ( ! empty( $action_sentences ) ) : ?>
						<ul>
							<?php foreach ( $action_sentences as $sentence ) : ?>
								<li>
									<uo-icon id="bolt"></uo-icon>
									<strong>
										<?php echo esc_html_x( 'Action:', 'AI', 'uncanny-automator' ); ?>
									</strong>
									<?php echo esc_html( $sentence ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php // Hidden fields for form submission. ?>
					<input 
						type="hidden" 
						name="action" 
						value="<?php echo esc_attr( AI_Settings::ADMIN_POST_ACTION ); ?>"
					/>

					<input 
						type="hidden" 
						name="provider" 
						value="<?php echo esc_attr( $settings['id'] ); ?>"
					/>

					<input
						type="hidden"
						name="stringified_options"
						value="<?php echo esc_attr( wp_json_encode( $settings['options'] ) ); ?>"
					/>
				<?php endif; ?>

				<?php // Display API key fields if not connected and options exist. ?>
				<?php if ( ! empty( $settings['options'] ) && ! $is_connected ) : ?>
					<?php
					/**
					 * Loop through the options.
					 *
					 * @var array{
					 *   id: string,
					 *   label: string,
					 *   placeholder: string,
					 *   value: string,
					 *   description: string
					 * } $option
					 */
					foreach ( $settings['options'] as $option ) :
						?>
						<uo-text-field
							id="<?php echo esc_attr( $option['id'] ); ?>"
							name="<?php echo esc_attr( $option['id'] ); ?>"
							value="<?php echo esc_attr( $option['value'] ); ?>"
							label="<?php echo esc_attr( $option['label'] ); ?>"
							placeholder="<?php echo esc_attr( $option['placeholder'] ); ?>"
							required
							class="uap-spacing-top"
						></uo-text-field>
						<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
							<?php echo wp_kses_post( $option['description'] ); ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php // Display connected account information. ?>
				<?php if ( $is_connected ) : ?>
					<uo-alert 
						type="info" 
						class="uap-spacing-bottom uap-spacing-bottom--big" 
						heading="
						<?php
							/* translators: %1$s: Name of the AI provider */
							printf(
								esc_html_x( 'Uncanny Automator only supports connecting to one %1$s account at a time.', 'AI', 'uncanny-automator' ),
								esc_html( $presentation->get_heading() )
							);
						?>
						"
					>
						<?php
							/* translators: %1$s: Name of the AI provider */
							printf(
								esc_html_x( 'If you create recipes and then change the connected %1$s account, your previous recipes might no longer work.', 'AI', 'uncanny-automator' ),
								esc_html( $presentation->get_heading() )
							);
						?>
					</uo-alert>
				<?php endif; ?>

			</div>
		</div>

		<!-- Panel Footer -->
		<div class="uap-settings-panel-bottom">
			<!-- Left Side - Connect Button or Account Info -->
			<div class="uap-settings-panel-bottom-left">
				<?php if ( ! $is_connected ) : ?>
					<uo-button type="submit">
						<?php
							/* translators: %1$s: Name of the AI provider */
							printf(
								esc_html_x( 'Connect %1$s account', 'AI', 'uncanny-automator' ),
								esc_html( $presentation->get_heading() )
							);
						?>
					</uo-button>
				<?php else : ?>
					<div class="uap-settings-panel-user">
						<!-- User Avatar -->
						<div class="uap-settings-panel-user__avatar">
							<?php echo esc_html( isset( $settings['name'][0] ) ? strtoupper( $settings['name'][0] ) : '' ); ?>
						</div>
						
						<!-- User Info -->
						<div class="uap-settings-panel-user-info">
							<div class="uap-settings-panel-user-info__main">
								<?php
									/* translators: %1$s: Name of the AI provider */
									printf(
										esc_html_x( '%1$s account', 'AI', 'uncanny-automator' ),
										esc_html( $presentation->get_heading() )
									);
								?>
								<uo-icon integration="<?php echo esc_attr( $settings['icon'] ); ?>"></uo-icon>
							</div>
							<div class="uap-settings-panel-user-info__additional">
								<?php /* translators: %1$s: The secret key */ ?>
								<?php printf( esc_html_x( 'API key connected: %1$s', 'AI', 'uncanny-automator' ), '**********' ); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Right Side - Disconnect Button -->
			<div class="uap-settings-panel-bottom-right">
				<?php if ( $is_connected ) : ?>
					<?php
					$disconnect_url = add_query_arg(
						array(
							'action'   => 'uncanny_automator_disconnect_ai_provider',
							'provider' => $settings['id'],
							'_wpnonce' => wp_create_nonce( 'uncanny_automator_disconnect_ai_provider_' . $settings['id'] ),
						),
						admin_url( 'admin-post.php' )
					);
					?>
					<uo-button 
						href="<?php echo esc_url( $disconnect_url ); ?>" 
						color="danger"
					>
						<uo-icon id="right-from-bracket"></uo-icon>
						<?php echo esc_html_x( 'Disconnect', 'AI', 'uncanny-automator' ); ?>
					</uo-button>
				<?php endif; ?>
			</div>
		</div>
	</div>
</form>
