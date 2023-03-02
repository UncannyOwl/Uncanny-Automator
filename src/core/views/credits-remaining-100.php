<?php
namespace Uncanny_Automator;

?>

<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php echo sprintf( esc_attr__( 'Hey %1$s! You have %2$d app credits remaining for app integrations in Uncanny Automator!', 'uncanny-automator' ), esc_attr( trim( $vars ['customer_name'] ) ), absint( $vars['credits_remaining'] ) ); ?>"
		type="white"
		custom-icon
	>
		<uo-button 
			href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"

			data-action="hide-notification-on-click"
			data-notification-type="100"

			slot="top-right-icon" 
			color="transparent" 
			size="small"
		>
			<uo-icon id="times"></uo-icon>
		</uo-button>

		<img 
			slot="icon"
			src="<?php echo esc_url( Utilities::automator_get_asset( 'backend/dist/img/credits-left-hundred.svg' ) ); ?>"
			width="90px"
		>

		<p>
			<?php esc_html_e( 'When credits run out, app integrations will stop working.', 'uncanny-automator' ); ?>
			<a target="_blank" href="https://automatorplugin.com/knowledge-base/what-are-credits/">
				<?php esc_html_e( 'Learn more', 'uncanny-automator' ); ?>
			</a>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				target="_blank"
				id="uap-review-banner-btn-positive"
				href="https://automatorplugin.com/pricing/?utm_source=Uncanny_Automator&utm_medium=credit_notification&utm_campaign=Get_unlimited_credits"
				class="uap-spacing-right uap-spacing-right--xsmall"
			>
				<?php echo esc_html_x( 'Get unlimited credits', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"
				id="uap-review-banner-btn-negative"
				color="secondary"
				data-action="hide-notification-on-click"
				data-notification-type="100"
			>
				<?php echo esc_html_x( 'Dismiss', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
