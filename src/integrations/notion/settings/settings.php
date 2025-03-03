<?php
namespace Uncanny_Automator\Integrations\Notion;

/**
 * Notion settings class.
 *
 * @package Uncanny_Automator\Integrations\Notion
 */
class Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	protected $is_user_connected;
	protected $auth_url;
	protected $user;
	protected $disconnect_url;

	public function get_status() {
		$creds = $this->helpers->get_credentials();
		return ! empty( $creds ) ? 'success' : '';
	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'notion' );
		$this->set_icon( 'NOTION' );
		$this->set_name( 'Notion' );
	}

	/**
	 * output_panel_content
	 */
	public function output_panel_content() {

		$this->auth_url          = $this->helpers->get_auth_url();
		$this->user              = $this->helpers->get_user();
		$this->disconnect_url    = $this->helpers->get_disconnect_url();
		$this->is_user_connected = 'success' === $this->get_status();
		?>
		<?php if ( ! $this->is_user_connected ) { ?>

			<div class="uap-settings-panel-content-subtitle">
				<?php echo esc_html_x( 'Connect Uncanny Automator to Notion', 'Notion', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
				<?php echo esc_html_x( 'Automate Notion workflows with Uncanny Automator: Create and update database entries in Notion and generate new pages based on WordPress activity.', 'Notion', 'uncanny-automator' ); ?>
			</div>

			<p>
				<strong><?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Notion', 'uncanny-automator' ); ?></strong>
			</p>

			<ul>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Notion', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create a database item', 'Notion', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Notion', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Create a page', 'Notion', 'uncanny-automator' ); ?>
				</li>
				<li>
					<uo-icon id="bolt"></uo-icon> <strong><?php echo esc_html_x( 'Action:', 'Notion', 'uncanny-automator' ); ?></strong> <?php echo esc_html_x( 'Update a database item', 'Notion', 'uncanny-automator' ); ?>
				</li>
			</ul>

		<?php } else { ?>

			<uo-alert heading="<?php echo esc_attr( sprintf( _x( 'Uncanny Automator only supports connecting to one Notion account at a time.', 'Notion', 'uncanny-automator' ) ) ); ?>" class="uap-spacing-bottom">
				<?php echo esc_html_x( 'If you create recipes and then change the connected Notion account, your previous recipes may no longer work.', 'Notion', 'uncanny-automator' ); ?>
			</uo-alert>

			<?php
		}

	}

	/**
	 * output_panel_bottom_left
	 */
	public function output_panel_bottom_left() {

		if ( ! $this->is_user_connected ) {
			?>

			<uo-button class="uap-settings-button-notion" href="<?php echo esc_url( $this->auth_url ); ?>">
				<?php echo esc_html_x( 'Connect Notion account', 'Notion', 'uncanny-automator' ); ?>
			</uo-button>

		<?php } else { ?>

			<div class="uap-settings-panel-user">

				<div class="uap-settings-panel-user__avatar">
					<?php echo esc_html( substr( strtoupper( $this->user['owner']['user']['name'] ), 0, 1 ) ); ?>
				</div>

				<div class="uap-settings-panel-user-info">
					<div class="uap-settings-panel-user-info__main">
						<?php echo esc_html( $this->user['owner']['user']['person']['email'] ); ?>
						<uo-icon integration="NOTION"></uo-icon>
					</div>
					<div class="uap-settings-panel-user-info__additional">
						<?php
							printf(
								/* translators: 1. Email address */
								esc_html__( 'Workspace: %1$s', 'uncanny-automator' ),
								esc_html( $this->user['workspace_name'] )
							);
						?>
					</div>
				</div>
			</div><!-- uap-settings-panel-user -->
			<?php
		}
	}

	/**
	 * output_panel_bottom_right
	 */
	public function output_panel_bottom_right() {

		if ( ! $this->is_user_connected ) {
			return;
		}

		?>
		<uo-button color="danger" href="<?php echo esc_url( $this->disconnect_url ); ?>">
			<uo-icon id="right-from-bracket"></uo-icon>
			<?php echo esc_html_x( 'Disconnect', 'Notion', 'uncanny-automator' ); ?>
		</uo-button>
		<?php
	}

}
