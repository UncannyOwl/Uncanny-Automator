<?php

namespace Uncanny_Automator;

?>

<?php if ( ! empty( $collection->integrations ) ) { ?>

	<div class="uap-integrations-collection" data-id="<?php echo esc_attr( $collection->id ); ?>">

		<div class="uap-integrations-collections__title">
			<?php echo esc_html( $collection->name ); ?>
		</div>

		<div class="uap-integrations-collections__subtitle">
			<?php echo esc_html( $collection->description ); ?>
		</div>

		<div class="uap-integrations-collections-integrations">
			<?php

			foreach ( $collection->integrations as $integration_id ) {
				// Check if the integration exists
				if ( isset( $integrations[ $integration_id ] ) ) {
					// Get the integration
					$integration = $integrations[ $integration_id ];

					// Add the template
					include Utilities::automator_get_view( 'admin-integrations/archive/integration-item.php' );
				}
			}

			?>

			<?php if ( isset( $collection->add_no_results_item ) ) { ?>

				<div class="uap-integrations-collections-no-results">
					<?php esc_html_e( 'No integrations found.', 'uncanny-automator' ); ?>
				</div>

			<?php } ?>
		</div>

	</div>

<?php } ?>
