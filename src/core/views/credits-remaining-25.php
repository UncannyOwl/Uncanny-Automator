<?php
namespace Uncanny_Automator;

?>

<div id="uap-review-banner" class="uap notice">

	<uo-alert
		heading="<?php echo sprintf( esc_attr__( 'Warning! Only %d app credits left in your Uncanny Automator account!', 'uncanny-automator' ), absint( $vars['credits_remaining'] ) ); ?>"
		type="white"
		custom-icon
	>
		<uo-button 
			href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"
			data-action="hide-notification-on-click"
			data-notification-type="25"
			slot="top-right-icon" 
			color="transparent" 
			size="small"
		>
			<uo-icon id="times"></uo-icon>
		</uo-button>

		<img 
			slot="icon"
			src="<?php echo esc_url( Utilities::automator_get_asset( 'backend/dist/img/robot-feedback.svg' ) ); ?>"
			width="90px"
		>

		<p>
			<?php esc_html_e( 'When you reach 0 credits, recipes with app integrations will stop working.', 'uncanny-automator' ); ?>
			<a target="_blank" href="https://automatorplugin.com/knowledge-base/what-are-credits/">
				<?php esc_html_e( 'Learn more', 'uncanny-automator' ); ?>
			</a>
		</p>

		<div class="uap-spacing-top">
			<uo-button
				target="_blank"
				id="uap-review-banner-btn-positive"
				href="https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=upgrade&utm_campaign=25_credits_left&utm_term=Upgrade"
				class="uap-spacing-right uap-spacing-right--xsmall"
				>
				<?php echo esc_html_x( 'Upgrade', 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>

			<uo-button
				href="<?php echo esc_url( $vars['dismiss_link'] ); ?>"
				id="uap-review-banner-btn-negative"
				color="secondary"
				data-action="hide-notification-on-click"
				data-notification-type="25"
				>
				<?php echo esc_html_x( "I'm okay with my recipes not working", 'Reviews banner', 'uncanny-automator' ); ?>
			</uo-button>
		</div>
	</uo-alert>

</div>
