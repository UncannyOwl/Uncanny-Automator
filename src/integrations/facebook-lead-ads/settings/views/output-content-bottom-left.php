<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( false === $vars['has_connection'] ) { ?>

	<uo-button type="button" href="<?php echo esc_url( $vars['connection_url'] ); ?>">
		<?php echo esc_html_x( 'Connect Facebook Lead Ads account', 'Facebook Lead Ads', 'uncanny-automator' ); ?>
	</uo-button>

<?php } else { ?>

	<div class="uap-settings-panel-user">

		<div class="uap-settings-panel-user__avatar">
			<uo-icon integration="FACEBOOK_LEAD_ADS"></uo-icon>
		</div>

		<div class="uap-settings-panel-user-info">

			<div class="uap-settings-panel-user-info__main">
				<?php if ( $vars['user']['name'] ) { ?>
					<?php echo esc_html( $vars['user']['name'] ); ?>
				<?php } ?>
			</div>

			<div class="uap-settings-panel-user-info__additional">
				<?php if ( isset( $vars['user']['id'] ) ) { ?>
					<?php echo esc_html( $vars['user']['id'] ); ?>
				<?php } ?>
			</div>

		</div>

	</div>

	<?php
}
