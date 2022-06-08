<?php
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<?php if ( ! empty( $notifications ) ) { ?>

	<div id="uap-notifications-wrap" class="uap-notifications">
		<h3 class="uap-notifications__title uap-settings-panel-content-subtitle">
			<span class="dashicons dashicons-bell"></span>
			<span class="notifications-count">
				<?php echo esc_html( count( $notifications ) ); ?>
			</span>
			<?php esc_html_e( 'Notifications', 'uncanny-automator' ); ?>
		</h3>
		<ul class="uap-notifications-list">
			<?php $counter = 0; ?>
			<?php foreach ( $notifications as $notification ) { ?>
				<?php $counter++; ?>
				<li data-index="<?php echo esc_attr( $counter ); ?>" class="uap-notifications-list__item <?php echo 1 === $counter ? 'active' : ''; ?>">
					<div class="uap-notifications__wrap">
						<div class="uap-notifications-list__item-title">
							<h3 class="uap-settings-panel-content-subtitle"><?php echo esc_html( $notification['title'] ); ?></h3>
							<uo-button class="uap-notifications-action-dismiss" data-notification-id="<?php echo esc_attr( absint( $notification['id'] ) ); ?>" size="small" color="danger">
								<uo-icon id="times"></uo-icon>
								<?php esc_html_e( 'Dismiss', 'uncanny-automator' ); ?>
							</uo-button>
						</div>
						<div class="uap-notifications-list__item-content">
							<?php
								echo wp_kses(
									$notification['content'],
									array(
										'p'      => array(),
										'br'     => array(),
										'strong' => '',
										'em'     => '',
										'a'      => array(
											'href'  => array(),
											'title' => array(),
										),
									)
								);
							?>
						</div>

						<div class="uap-notifications-list__item-actions">

							<div class="uap-notifications-list__item-actions__links">

								<?php
								if ( isset( $notification['btns']['main']['url'] ) && isset( $notification['btns']['main']['text'] )
										&& ! empty( $notification['btns']['main']['url'] ) && ! empty( $notification['btns']['main']['text'] ) ) {
									?>

										<uo-button target="_blank" href="<?php echo esc_url( $this->url_add_utm( $notification['btns']['main']['url'], $notification['title'], $notification['btns']['main']['text'] ) ); ?>">
											<?php echo esc_html( $notification['btns']['main']['text'] ); ?>
										</uo-button>

								<?php } ?>

								<?php
								if ( isset( $notification['btns']['alt']['url'] ) && isset( $notification['btns']['alt']['text'] )
										&& ! empty( $notification['btns']['alt']['url'] ) && ! empty( $notification['btns']['alt']['text'] ) ) {
									?>
										<uo-button color="secondary" target="_blank" href="<?php echo esc_url( $this->url_add_utm( $notification['btns']['alt']['url'], $notification['title'], $notification['btns']['alt']['text'] ) ); ?>">
											<?php echo esc_html( $notification['btns']['alt']['text'] ); ?>
										</uo-button>

								<?php } ?>
							</div>
							<?php if ( count( $notifications ) >= 2 ) { ?>
								<div class="uap-notifications-list__item-actions__controls">
									<uo-button size="small" color="secondary" class="uap-notifications-controller-prev" href="#">
										&larr;
									</uo-button>
									<uo-button size="small" color="secondary" class="uap-notifications-controller-next" href="#">
										&rarr;
									</uo-button>
								</div>
							<?php } ?>
						</div>
						</uo-alert>
					</div>
				</li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>
