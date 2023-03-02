<?php
namespace Uncanny_Automator;

?>

<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php echo esc_attr_x( 'Critical issue: There are no credits left in your account!', 'Reviews banner', 'uncanny-automator' ); ?>"
		type="white"
		custom-icon
	>
		<uo-button
			href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"
			data-action="hide-notification-on-click"
			data-notification-type="0"
			slot="top-right-icon" 
			color="transparent" 
			size="small"
		>
			<uo-icon id="times"></uo-icon>
		</uo-button>

		<img 
			slot="icon"
			src="<?php echo esc_url( Utilities::automator_get_asset( 'backend/dist/img/credits-left-zero.svg' ) ); ?>"
			width="90px"
		>

		<p>
			<?php echo esc_attr_x( 'Recipes with app integrations are not working because your Uncanny Automator account has run out of credits. Upgrade to Pro to get unlimited credits and continue using app integrations.', 'Reviews banner', 'uncanny-automator' ); ?>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				target="_blank"
				id="uap-review-banner-btn-positive"
				href="https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=upgrade&utm_campaign=0_credits_left&utm_term=Upgrade"
				class="uap-spacing-right uap-spacing-right--xsmall"
				>
				<?php echo esc_html_x( 'Upgrade now', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"
				id="uap-review-banner-btn-negative"
				color="secondary"
				data-action="hide-notification-on-click"
				data-notification-type="0"
				>
				<?php echo esc_html_x( "I'm okay with my recipes not working", 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
