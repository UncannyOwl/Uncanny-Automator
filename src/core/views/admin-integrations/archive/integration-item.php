<?php

/**
 * Variables:
 * $integration The integration data
 */

namespace Uncanny_Automator;

?>

<?php

// Check if the integration has tags
$has_tags = $integration->is_pro || $integration->is_built_in || $integration->is_installed;

// Check if the integration has description
$has_description = ! empty( $integration->short_description );

// Add UTM parameters to the URL
if ( isset( $integration->external_permalink ) && ! empty( $integration->external_permalink ) && isset( $integration->integration_id ) ) {
	$integration->external_permalink = add_query_arg(
		array(
			'utm_source'  => defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ? 'uncanny_automator_pro' : 'uncanny_automator',
			'utm_medium'  => 'integrations_page',
			'utm_content' => 'integration_item-' . $integration->integration_id
		),
		$integration->external_permalink
	);
}

?>

<a href="<?php echo esc_url( $integration->external_permalink ); ?>" target="_blank" class="uap-integrations-collections-integration" data-id="<?php echo esc_attr( $integration->id ); ?>">

	<div class="uap-integrations-collections-integration-content">
		<div class="uap-integrations-collections-integration__icon-container">
			<div class="uap-integrations-collections-integration__icon">
				<img src="<?php echo esc_url( $integration->icon_url ); ?>" alt="<?php echo esc_attr( $integration->name ); ?>">
			</div>
		</div>

		<div class="uap-integrations-collections-integration__info-container">
			<div class="uap-integrations-collections-integration__name">
				<?php echo esc_html( $integration->name ); ?>
			</div>

			<?php if ( $has_tags ) { ?>

				<div class="uap-integrations-collections-integration__tags uap-integrations-collections-integration--has-tags">

				<?php if ( $integration->is_pro ) { ?>

					<div class="uap-integrations-collections-integration__tag uap-integrations-collections-integration__tag-pro">
						Pro
					</div>

				<?php } ?>

				<?php if ( $integration->is_built_in ) { ?>

					<div class="uap-integrations-collections-integration__tag uap-integrations-collections-integration__tag-built-in">
						<?php esc_html_e( 'Built-in', 'uncanny-automator' ); ?>
					</div>

				<?php } ?>

				<?php if ( $integration->is_installed ) { ?>

					<div class="uap-integrations-collections-integration__tag uap-integrations-collections-integration__tag-installed">
						<?php esc_html_e( 'Installed', 'uncanny-automator' ); ?>
					</div>

				<?php } ?>

				</div>

			<?php } ?>

			<?php if ( $has_description ) { ?>

				<div class="uap-integrations-collections-integration__description">
					<?php echo esc_html( $integration->short_description ); ?>
				</div>

			<?php } ?>

			<?php if ( ! $user_has_automator_pro && $integration->is_pro ) { ?>

				<div class="uap-integrations-collections-integration--requieres-pro">
					<span class="uap-field-icon uap-icon uap-icon--lock-alt"></span><?php esc_html_e( 'Requires Uncanny Automator Pro', 'uncanny-automator' ); ?>
				</div>

			<?php } ?>

		</div>
	</div>

</a>
