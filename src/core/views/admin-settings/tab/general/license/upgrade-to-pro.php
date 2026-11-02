<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Services\Admin_Post\Admin_Post_Routes;

/**
 * Prune Logs
 * Settings > General > License > Upgrade to Pro
 *
 * @since   6.3 - Added license activation form.
 * @since   3.7
 * @version 3.7
 *
 * @var string $upgrade_to_pro_url_button URL to upgrade to Automator Pro
 * @var string $upgrade_to_pro_url_link   URL to upgrade to Automator Pro
 * @var string $license_key_url           URL to license key page
 * @var string|null $error_message        Error message if any
 * @var string|null $success_message      Success message if any
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="uap-settings-panel-content-separator"></div>

<?php if ( ! empty( $error_message ) ) : ?>
	<!-- Error notice -->
	<uas-alert
		type="error"
		heading="<?php esc_attr_e( 'Installation Failed', 'uncanny-automator' ); ?>"
		role="alert"
		aria-label="<?php esc_attr_e( 'Installation error notice', 'uncanny-automator' ); ?>"
		class="uap-spacing-bottom"
	>
		<p><?php echo esc_html( $error_message ); ?></p>
		<p>
			<?php
			printf(
				// Translators: %1$s opens link, %2$s closes it, %3$s is the external‑link icon.
				esc_html_x(
					'If you continue to experience issues, you can %1$sdownload the Pro plugin manually%3$s%2$s from your account page.',
					'manual download link',
					'uncanny-automator'
				),
				'<a href="' . esc_url( $license_key_url ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>',
				'&nbsp;<uas-icon code="arrow-up-right-from-square" aria-hidden="true"></uas-icon>'
			);
			?>
		</p>
	</uas-alert>
<?php endif; ?>

<?php if ( ! empty( $success_message ) ) : ?>
	<!-- Success notice -->
	<uas-alert
		type="success"
		heading="<?php esc_attr_e( 'Installation Successful', 'uncanny-automator' ); ?>"
		role="alert"
		aria-label="<?php esc_attr_e( 'Installation success notice', 'uncanny-automator' ); ?>"
		class="uap-spacing-bottom"
	>
		<p><?php echo esc_html( $success_message ); ?></p>
		<p>
			<?php
			esc_html_e( 'You can now access all Pro features and unlimited app credits!', 'uncanny-automator' );
			?>
		</p>
	</uas-alert>
<?php endif; ?>

<!-- Notice prompting upgrade -->
<uas-alert
	type="info"
	heading="<?php esc_attr_e( 'Do more with Uncanny Automator Pro', 'uncanny-automator' ); ?>"
	role="region"
	aria-label="<?php esc_attr_e( 'Upgrade to Pro notice', 'uncanny-automator' ); ?>"
>
	<p>
		<?php
		printf(
			// Translators: %1$s opens link, %2$s closes it, %3$s is the external‑link icon.
			esc_html_x(
				'Pro removes the daily tool-use limit, adds WooCommerce actions, user management and hundreds more actions, and unlocks schedules, delays, conditions and loops in your recipes. %1$sSee plans%3$s%2$s',
				'Upgrade to Pro link',
				'uncanny-automator'
			),
			'<a href="' . esc_url( $upgrade_to_pro_url_link ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>',
			'&nbsp;<uas-icon code="arrow-up-right-from-square"></uas-icon>'
		);
		?>
	</p>

	<ul>
		<li><?php echo esc_html_x( 'Unlimited tool usage for your connected AI', 'license upgrade text', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Hundreds more triggers and actions, including WooCommerce actions and creating users', 'license upgrade text', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Schedules, delays, conditions and loops in your recipes', 'license upgrade text', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Unlimited app credits with no per-transaction fees', 'license upgrade text', 'uncanny-automator' ); ?></li>
		<li><?php echo esc_html_x( 'Priority support', 'license upgrade text', 'uncanny-automator' ); ?></li>
	</ul>

	<section class="uap-license-section">
		<!-- License activation form -->
		<form
			id="uap-license-form"
			class="uap-form"
			action="<?php echo esc_url( Admin_Post_Routes::get_url( 'uncanny_automator_pro_auto_install' ) ); ?>"
			method="post"
			aria-labelledby="uap-license-heading"
			data-error-required="<?php echo esc_attr_x( 'License key is required', 'form validation error', 'uncanny-automator' ); ?>"
		>
			<?php wp_nonce_field( 'uncanny_automator_pro_auto_install', 'uncanny_automator_pro_auto_install' ); ?>

			<uo-text-field
				id="automator_pro_license"
				name="automator_pro_license"
				required
				label="<?php echo esc_attr_x( 'License key', 'license key field label', 'uncanny-automator' ); ?>"
				helper="<?php echo esc_html_x( 'Enter your Pro license key to activate your site.', 'license helper text', 'uncanny-automator' ); ?>"
				placeholder="<?php echo esc_attr_x( 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'license key placeholder', 'uncanny-automator' ); ?>"
				autocomplete="off"
				class="uap-spacing-top"
			></uo-text-field>

			<p class="uap-field-helper">
				<a href="<?php echo esc_url( $license_key_url ); ?>" target="_blank">
					<?php echo esc_html_x( 'Where can I find my license key?', 'license key helper text', 'uncanny-automator' ); ?>
				</a>
			</p>

			<div class="uap-actions" style="display: flex; gap: 10px; margin-top: 1rem;">
				<uas-button id="btn-activate-license" type="submit" class="uap-btn-primary">
					<?php echo esc_html_x( 'Activate license', 'submit button text', 'uncanny-automator' ); ?>
				</uas-button>
				<uas-button
					color="secondary"
					href="<?php echo esc_url( $upgrade_to_pro_url_button ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					class="uap-btn-secondary"
				>
					<?php echo esc_html_x( 'Upgrade to Pro', 'secondary button text', 'uncanny-automator' ); ?>
				</uas-button>
			</div>
		</form>
	</section>
</uas-alert>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const licenseForm = document.getElementById('uap-license-form');
	const activateButton = document.getElementById('btn-activate-license');
	const licenseField = document.getElementById('automator_pro_license');
	
	// Only run if the form elements exist
	if (!licenseForm || !activateButton || !licenseField) {
		return;
	}

	// Function to get actual input value from custom component
	function getLicenseValue() {
		// Try to get the actual input element inside the custom component
		const actualInput = licenseField.querySelector('input') || licenseField.shadowRoot?.querySelector('input');
		if (actualInput) {
			return actualInput.value.trim();
		}
		// Fallback to checking the component's value property
		return licenseField.value?.trim() || '';
	}

	// Check validation on form submit
	licenseForm.addEventListener('submit', function(e) {
		const licenseValue = getLicenseValue();
		
		// Add class only if validation passes (license key is not empty)
		if (licenseValue.length > 0) {
			activateButton.classList.add('valid');
			activateButton.setAttribute('loading', true);
			// Allow form to submit normally
			return true;
		} else {
			e.preventDefault();
			activateButton.classList.remove('valid');
			
			// Show native browser validation or custom error
			const errorMessage = licenseForm.dataset.errorRequired;
			const actualInput = licenseField.querySelector('input') || licenseField.shadowRoot?.querySelector('input');
			if (actualInput) {
				actualInput.setCustomValidity(errorMessage);
				actualInput.reportValidity();
			} else {
				// Fallback for custom components - show validation error
				licenseField.setAttribute('invalid', 'true');
				licenseField.setAttribute('error', errorMessage);
			}
			return false;
		}
	});

});
</script>
