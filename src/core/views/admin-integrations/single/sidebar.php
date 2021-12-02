<?php

namespace Uncanny_Automator;

?>
<style>
	.uap-integration-sidebar-requires__button {
		/* Change to use the main color for each integration */
		border-color: #9b5c8f !important;
		color: #9b5c8f !important;
	}

	.uap-integration-sidebar-requires__button:hover {
		/* Change to use the main color for each integration */
		color: #fff !important;
		background-color: #9b5c8f !important;
	}

	.uap-integration-sidebar__link:hover {
	/* Change to use the main color for each integration */
		color: #9b5c8f !important;
	}
</style>

<div class="uap-integration-sidebar" id="uap-integration-sidebar">
	<div class="uap-integration-sidebar__title">
		<?php esc_html_e( 'Requires', 'uncanny-automator' ); ?>
	</div>
	<div class="uap-integration-sidebar-requires">				 
		<div class="uap-integration-sidebar-requires__row">

			<div class="uap-integration-sidebar-requires__icon">
				<img src="https://automatorplugin.com/wp-content/uploads/2018/03/woocommerce.svg" alt="WooCommerce">
			</div>

			<div class="uap-integration-sidebar-requires__name">
				WooCommerce
			</div>

			<a href="https://wordpress.org/plugins/woocommerce/" target="_blank" class="uap-integration-sidebar-requires__button">
				<?php esc_html_e( 'Learn more', 'uncanny-automator' ); ?>
			</a>
		</div>
	</div>
	<div class="uap-integration-sidebar__title">
		<?php esc_html_e( 'Other resources', 'uncanny-automator' ); ?>
	</div>
	<div class="uap-integration-sidebar__links">
		<a href="#" class="uap-integration-sidebar__link">
			<?php esc_html_e( 'Knowledge base', 'uncanny-automator' ); ?>
			<span class="uap-icon uap-icon--external-link-alt"></span>
		</a>
		<a href="#" class="uap-integration-sidebar__link">
			<?php esc_html_e( 'Support', 'uncanny-automator' ); ?>
			<span class="uap-icon uap-icon--external-link-alt"></span>
		</a>
		<a href="#" class="uap-integration-sidebar__link">
			<?php esc_html_e( 'WooCommerce’s developer site', 'uncanny-automator' ); ?>
			<span class="uap-icon uap-icon--external-link-alt"></span>
		</a>
	</div>
</div>
