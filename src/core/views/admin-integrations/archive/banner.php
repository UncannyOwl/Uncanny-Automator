<?php

namespace Uncanny_Automator;

?>


<div class="uap-integrations-banner" id="uap-integrations-banner">
	<a href="<?php echo esc_url( $all_recipes_url ); ?>" class="uap-integrations-banner__breadcrumbs">
		<automator-icon id="angle-left"></automator-icon>
		<?php esc_html_e( 'Go to all recipes', 'uncanny-automator' ); ?>
	</a>
	<div class="uap-integrations-banner__title">
		<?php esc_html_e( 'Connect your plugins together', 'uncanny-automator' ); ?>
	</div>
	<div class="uap-integrations-banner-items">
		<div class="uap-integrations-banner-item">
			<span class="uap-integrations-banner-item__icon">
				<automator-icon id="tachometer"></automator-icon>
			</span>
			<div class="uap-integrations-banner-item__text">
				<?php esc_html_e( 'Improve your workflow efficiency', 'uncanny-automator' ); ?>
			</div>
		</div>
		<div class="uap-integrations-banner-item">
			<span class="uap-integrations-banner-item__icon">
				<automator-icon id="laugh-beam"></automator-icon>
			</span>
			<div class="uap-integrations-banner-item__text">
				<?php esc_html_e( 'Create a user experience that leads to conversions', 'uncanny-automator' ); ?>
			</div>
		</div>
		<div class="uap-integrations-banner-item">
			<span class="uap-integrations-banner-item__icon">
				<automator-icon id="usd-circle"></automator-icon>
			</span>
			<div class="uap-integrations-banner-item__text">
				<?php esc_html_e( 'Slash custom development costs', 'uncanny-automator' ); ?>
			</div>
		</div>
	</div>
</div>
