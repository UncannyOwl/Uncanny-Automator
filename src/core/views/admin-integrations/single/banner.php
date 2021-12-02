<?php

namespace Uncanny_Automator;

?>
<style>

	.uap-integration-banner {
		/* Change to use the main color for each integration */
		background-color: rgba( 155, 92, 143, 0.05 );
	}

	.uap-integration-banner__breadcrumbs {
		/* Change to use the main color for each integration */
		color: #9b5c8f !important;
	}

	.uap-integration-banner-menu-sections--selected a {
		/* Change to use the main color for each integration */
		color: #9b5c8f !important;
	}

</style>

<div class="uap-integration-banner" id="uap-integration-banner">
	<a href="#" class="uap-integration-banner__breadcrumbs">
		<span class="uap-icon uap-icon--angle-left"></span>
		<?php esc_html_e( 'Go to all integrations', 'uncanny-automator' ); ?>
	</a>
	<div class="uap-integration-banner-info-container">
		<div class="uap-integration-banner-info">
			<div class="uap-integration-banner-info__icon-container">
				<div class="uap-integration-banner-info__icon">
					<img src="https://automatorplugin.com/wp-content/uploads/2018/03/woocommerce.svg" alt="WooCommerce">
				</div>
			</div>
			<div class="uap-integration-banner-info__info-container">
				<div class="uap-integration-banner-info__name">
					WooCommerce
				</div>
				<div class="uap-integration-banner-info__description">
					<?php esc_html_e( 'WooCommerce is the most popular ecommerce platform on the internet and is a free addon for WordPress. With it, you can sell both physical and digital products globally and leverage hundreds of both free and paid WooCommerce extensions to add membership, subscription, recurring payment and other capabilities to your WordPress site.', 'uncanny-automator' ); ?>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="uap-integration-banner-menu" id="uap-integration-banner-menu">
	<ul class="uap-integration-banner-menu-sections" id="uap-integration-banner-menu-sections">
		<li class="uap-integration-banner-menu-sections--selected uap-integration-banner-menu-sections-triggers-and-actions" data-destionation="uap-integrations-triggers-and-actions">
			<a href="#"><?php esc_html_e( 'Triggers and Actions', 'uncanny-automator' ); ?></a>
		</li>
		<li class="uap-integration-banner-menu-sections-recipe-inspiration" data-destionation="uap-integrations-recipe-inspiration">
			<a href="#"><?php esc_html_e( 'Recipe Inspiration', 'uncanny-automator' ); ?></a>
		</li>
	</ul>
</div>
