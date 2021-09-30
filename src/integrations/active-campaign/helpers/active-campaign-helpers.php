<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class ActiveCampaign Helpers
 * @package Uncanny_Automator
 */
class Active_Campaign_helpers {

	/**
	 * @var Active_Campaign
	 */
	public $options;

	/**
	 * @var Active_Campaign
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Active_Campaign_helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->prefix          = 'AC_ANNON_ADD';
		$this->ac_endpoint_uri = AUTOMATOR_API_URL . 'v2/active-campaign';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_AC_ENDPOINT_URL' ) ) {
			$this->ac_endpoint_uri = UO_AUTOMATOR_DEV_AC_ENDPOINT_URL;
		}

		$this->setting_tab   = 'active-campaign';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/active-campaign';

		add_filter( 'automator_settings_tabs', array( $this, 'add_active_campaign_api_settings' ), 15 );
		add_filter( 'automator_after_settings_extra_buttons', array( $this, 'ac_connect_html' ), 10, 3 );

		// Add the ajax endpoints.
		add_action( 'wp_ajax_active-campaign-list-tags', array( $this, 'list_tags' ) );
		add_action( 'wp_ajax_active-campaign-list-contacts', array( $this, 'list_contacts' ) );
		add_action( 'wp_ajax_active-campaign-list-retrieve', array( $this, 'list_retrieve' ) );
		add_action( 'wp_ajax_active-campaign-disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );

	}

	/**
	 * @param Active_Campaign_helpers $options
	 */
	public function setOptions( Active_Campaign_helpers $options ) {
		$this->options = $options;
	}

	/**
	 * Checks if the user has valid license in pro or free version.
	 *
	 * @return boolean.
	 */
	public function has_valid_license() {

		$has_pro_license  = false;
		$has_free_license = false;

		$free_license_status = get_option( 'uap_automator_free_license_status' );
		$pro_license_status  = get_option( 'uap_automator_pro_license_status' );

		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === $pro_license_status ) {
			$has_pro_license = true;
		}

		if ( 'valid' === $free_license_status ) {
			$has_free_license = true;
		}

		return $has_free_license || $has_pro_license;

	}

	/**
	 * Checks if screen is from the modal action popup or not.
	 *
	 * @return boolean.
	 */
	public function is_from_modal_action() {

		$minimal = filter_input( INPUT_GET, 'minimal', FILTER_DEFAULT );

		$hide_settings_tabs = filter_input( INPUT_GET, 'hide_settings_tabs', FILTER_DEFAULT );

		return ! empty( $minimal ) && ! empty( $hide_settings_tabs ) && ! empty( $hide_settings_tabs );
	}

	/**
	 * Check if the 3rd-party integration has any connection api stored.
	 *
	 * @return boolean.
	 */
	public function has_connection_data() {

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_key ) || empty( $settings_url ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_active_campaign_api_settings( $tabs ) {

		if ( $this->has_valid_license() || $this->has_connection_data() || $this->is_from_modal_action() ) {
			$tab_url                    = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'ActiveCampaign', 'uncanny-automator' ),
				'title'          => __( 'ActiveCampaign settings', 'uncanny-automator' ),
				'description'    => sprintf( '<p>%s</p>', __( 'Please enter your API Access URL and Key to get started with ActiveCampaign. Go to your ActiveCampaign account settings, and under developer tab to see your unique url and key.', 'uncanny-automator' ) ) . $this->get_user_name(),
				'settings_field' => 'uap_automator_active_campaign_api_settings',
				'wp_nonce_field' => 'uap_automator_active_campaign_nonce',
				'save_btn_name'  => 'uap_automator_active_campaign_save',
				'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
				'fields'         => array(
					'uap_active_campaign_api_url' => array(
						'title'       => __( 'Account url:', 'uncanny-automator' ),
						'type'        => 'text',
						'css_classes' => '',
						'placeholder' => 'https://<account-name>.api-us1.com',
						'default'     => '',
						'required'    => false,
					),
					'uap_active_campaign_api_key' => array(
						'title'       => __( 'API key:', 'uncanny-automator' ),
						'type'        => 'text',
						'css_classes' => '',
						'placeholder' => 'Your API key',
						'default'     => '',
						'required'    => false,
					),
				),
			);
		}

		return $tabs;
	}

	public function list_retrieve() {

		$form_data = array(
			'action' => 'list_retrieve',
			'url'    => get_option( 'uap_active_campaign_api_url', '' ),
			'token'  => get_option( 'uap_active_campaign_api_key', '' ),
		);

		$ua_ac_list_group = get_transient( 'ua_ac_list_group' );

		if ( false !== $ua_ac_list_group ) {
			wp_send_json( $ua_ac_list_group );
		}

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ) );
			$response_data = isset( $body->data ) ? $body->data : '';

			$lists = array();

			if ( ! empty( $response_data ) ) {
				$lists = isset( $response_data->lists ) ? $response_data->lists : '';
			}

			if ( ! empty( $lists ) ) {
				$lists_items = array();
				foreach ( $lists as $list ) {
					if ( ! empty( $list->name ) && ! empty( $list->id ) ) {
						$lists_items[] = array(
							'value' => $list->id,
							'text'  => $list->name,
						);
					}
				}

				set_transient( 'ua_ac_list_group', $lists_items, HOUR_IN_SECONDS );
				wp_send_json( $lists_items );
			}
		} else {
			wp_send_json(
				array(
					array(
						'text'  => $response->get_error_message(),
						'value' => 0,
					),
				)
			);
		}

	}
	public function list_tags() {

		$form_data = array(
			'action' => 'list_tags',
			'url'    => get_option( 'uap_active_campaign_api_url', '' ),
			'token'  => get_option( 'uap_active_campaign_api_key', '' ),
		);

		$ua_ac_tag_list = get_transient( 'ua_ac_tag_list' );

		if ( false !== $ua_ac_tag_list ) {
			wp_send_json( $ua_ac_tag_list );
		}

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body          = json_decode( wp_remote_retrieve_body( $response ) );
			$response_data = isset( $body->data ) ? $body->data : '';
			$tags          = array();

			if ( ! empty( $response_data ) ) {
				$tags = isset( $response_data->tags ) ? $response_data->tags : '';
			}

			if ( ! empty( $tags ) ) {
				$tag_items = array();
				foreach ( $tags as $tag ) {
					$tag_items[] = array(
						'value' => $tag->id,
						'text'  => $tag->tag,
					);
				}

				set_transient( 'ua_ac_tag_list', $tag_items, HOUR_IN_SECONDS );
				wp_send_json( $tag_items );
			}
		} else {
			wp_send_json(
				array(
					array(
						'text'  => $response->get_error_message(),
						'value' => 0,
					),
				)
			);
		}

		wp_send_json( array() );
	}

	public function list_contacts() {

		$form_data = array(
			'action' => 'list_contacts',
			'url'    => get_option( 'uap_active_campaign_api_url', '' ),
			'token'  => get_option( 'uap_active_campaign_api_key', '' ),
		);

		$saved_contact_list = get_transient( 'ua_ac_contact_list' );

		if ( false !== $saved_contact_list ) {
			wp_send_json( $saved_contact_list );
		}

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body          = json_decode( wp_remote_retrieve_body( $response ) );
			$response_data = isset( $body->data ) ? $body->data : '';
			$contacts      = array();

			if ( ! empty( $response_data ) ) {
				$contacts = isset( $response_data->contacts ) ? $response_data->contacts : '';
			}

			if ( ! empty( $contacts ) ) {
				$contact_items = array();
				foreach ( $contacts as $contact ) {
					$contact_items[] = array(
						'value' => $contact->id,
						'text'  => sprintf(
							'%s (%s)',
							implode( ' ', array( $contact->firstName, $contact->lastName ) ),
							$contact->email
						),
					);
				}
				set_transient( 'ua_ac_contact_list', $contact_items, HOUR_IN_SECONDS );
				wp_send_json( $contact_items );
			}
		} else {
			wp_send_json(
				array(
					array(
						'text'  => $response->get_error_message(),
						'value' => 0,
					),
				)
			);
		}

		wp_send_json( array() );
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return false|mixed|string
	 */
	public function ac_connect_html( $content, $active, $tab ) {
		return $content;
	}

	/**
	 * Displays the twitter handle of the user in settings description..
	 *
	 * @return string The twitter handle html.
	 */
	public function get_user_name() {

		ob_start();

		$account_users = array();
		$settings_url  = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key  = get_option( 'uap_active_campaign_api_key', '' );

		$users = $this->get_connected_users();
		?>

		<?php if ( empty( $users ) ) : ?>
			<p style="color: #656565;">
				<?php esc_html_e( 'Status: Not connected', 'uncanny-automator' ); ?>
			</p>
		<?php endif; ?>

		<?php
		if ( ! empty( $settings_key ) && ! empty( $settings_url ) ) {

			$url = sprintf( '%s/api/3/users', esc_url( $settings_url ) );

			if ( ! empty( $users ) ) :
				foreach ( $users as $user ) {
					$account_users[] = $user->firstName . ' ' . $user->lastName . ' (' . $user->email . ')';
				}
				?>
				<p style="color: #0dba19;">
					<?php echo 'Connected as: '; ?>
					<strong>
						<?php echo esc_html( implode( ' ', $account_users ) ); ?>
					</strong>
				</p>
				<style>
					.uo-settings-content-description a#ac-dc-btn { color: #e94b35; }
					.uo-settings-content-description a#ac-dc-btn:hover { color: #fff; }
				</style>
				<p>
					<?php
						$dc_uri = add_query_arg(
							array(
								'action' => 'active-campaign-disconnect',
							),
							admin_url( 'admin-ajax.php' )
						);
					?>
					<a id="ac-dc-btn"
						class="uo-settings-btn uo-settings-btn--error"
							href="<?php echo esc_url( $dc_uri ); ?>" title="<?php esc_attr_e( 'Disconnect', 'uncanny-automator' ); ?>">
								<?php esc_attr_e( 'Disconnect', 'uncanny-automator' ); ?>
						</a>
				</p>
				<?php
			endif;
		}
		return ob_get_clean();
	}

	/**
	 * Removes all option. Automatically disconnects the account.
	 */
	public function disconnect() {

		update_option( 'uap_active_campaign_api_url', '' );
		update_option( 'uap_active_campaign_api_key', '' );
		update_option( 'ua_active_campaign_connected_user', '' );

		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=active-campaign';
		wp_safe_redirect( $uri );

		exit;

	}

	/**
	 * Save the user connection data.
	 */
	public function save_settings() {

		$url   = filter_input( INPUT_POST, 'uap_active_campaign_api_url', FILTER_UNSAFE_RAW );
		$key   = filter_input( INPUT_POST, 'uap_active_campaign_api_key', FILTER_UNSAFE_RAW );
		$nonce = filter_input( INPUT_POST, 'uap_automator_active_campaign_nonce', FILTER_UNSAFE_RAW );

		if ( ! empty( $nonce ) ) :
			update_option( 'ua_active_campaign_connected_user', '' );
		endif;

		if ( ! empty( $url ) && ! empty( $key ) ) {

			$response = wp_remote_get(
				sprintf( '%s/api/3/users', esc_url( $url ) ),
				array(
					'headers' => array(
						'Api-token' => $key,
						'Accept'    => 'application/json',
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {

				$body  = wp_remote_retrieve_body( $response );
				$users = json_decode( $body );
				$users = isset( $users->users ) ? $users->users : '';

				update_option( 'ua_active_campaign_connected_user', $users );

			}
		}

	}

	/**
	 * Get the saved user info from wp_options.
	 *
	 * @return mixed the connection data.
	 */
	protected function get_connected_users() {

		return get_option( 'ua_active_campaign_connected_user' );

	}

	/**
	 * Get the user by email.
	 *
	 * @param string $email The email of the contact.
	 *
	 * @return array The contact data.
	 */
	public function get_user_by_email( $email = '' ) {

		$form_data = array(
			'action' => 'get_contact_by_email',
			'url'    => get_option( 'uap_active_campaign_api_url', '' ),
			'token'  => get_option( 'uap_active_campaign_api_key', '' ),
			'email'  => $email,
		);

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $body->data->contacts ) ) {
				return array(
					'error'   => true,
					/* translators: Email error message. */
					'message' => sprintf( __( 'The contact %s does not exist in ActiveCampaign.', 'uncanny-automator' ), $email ),
				);
			}

			return array(
				'error'   => false,
				'message' => $body->data->contacts[0],
			);

		} else {
			return array(
				'error'   => true,
				'message' => $response->get_error_message(),
			);
		}

		return array(
			'error'   => true,
			'message' => __( 'Unexpected error has occured.', 'uncanny-automator' ),
		);
	}

}

