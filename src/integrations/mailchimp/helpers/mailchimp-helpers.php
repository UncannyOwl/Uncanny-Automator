<?php

namespace Uncanny_Automator;

global $mailchimp_meeting_token_renew;

use Uncanny_Automator_Pro\Mailchimp_Pro_Helpers;

/**
 * Class Mailchimp_Helpers
 * @package Uncanny_Automator
 */
class Mailchimp_Helpers {

	/**
	 * @var Mailchimp_Helpers
	 */
	public $options;
	/**
	 * @var Mailchimp_Helpers
	 */
	public $pro;
	/**
	 * @var Mailchimp_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @var string
	 */
	private $mailchimp_endpoint;

	/**
	 * @var string
	 */
	public static $hash_string = 'Uncanny Automator Pro Mailchimp Sheet Integration';

	/**
	 * Mailchimp_Helpers constructor.
	 */
	public function __construct() {

		$this->setting_tab   = 'mailchimp_api';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/mailchimp';

		add_filter( 'automator_settings_tabs', array( $this, 'add_mailchimp_api_settings' ), 15 );
		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );
		add_filter( 'automator_after_settings_extra_content', array( $this, 'mailchimp_connect_html' ), 10, 3 );
		add_action( 'wp_ajax_select_mcgroupslist_from_mclist', array( $this, 'select_mcgroupslist_from_mclist' ) );
		add_action( 'wp_ajax_select_mctagslist_from_mclist', array( $this, 'select_mctagslist_from_mclist' ) );

		add_action( 'wp_ajax_select_segments_from_list', array( $this, 'select_segments_from_list' ) );

		add_action( 'wp_ajax_get_mailchimp_audience_fields', array( $this, 'get_mailchimp_audience_fields' ) );
		add_action( 'wp_ajax_uo_mailchimp_disconnect', array( $this, 'uo_mailchimp_disconnect' ) );

	}

	/**
	 * @param Mailchimp_Helpers $options
	 */
	public function setOptions( Mailchimp_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Mailchimp_Helpers $pro
	 */
	public function setPro( Mailchimp_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * @param null $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_all_lists( $label = null, $option_code = 'MCLIST', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Audience', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'All', 'uncanny-automator' ),
			)
		);

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$options                  = array();

		$request_params = array(
			'action' => 'get_lists',
		);

		try {
			$response = $this->api_request( $request_params );
			// prepare lists
			if ( 200 === intval( $response->statusCode ) ) { // phpcs:ignore
				if ( ! empty( $response->data->lists ) ) {
					foreach ( $response->data->lists as $list ) {
						$options[] = array(
							'value' => $list->id,
							'text'  => $list->name,
						);
					}
				}
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'MAILCHIMP',
		);

		return apply_filters( 'uap_option_get_all_lists', $option );
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_list_groups( $label = null, $option_code = 'MCLISTGROUPS', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Groups', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any group', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => false,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'supports_multiple_values' => true,
			'options'                  => $options,
		);

		return apply_filters( 'uap_option_get_list_groups', $option );
	}

	/**
	 *
	 */
	public function select_mcgroupslist_from_mclist() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$flags = array(
			'filter' => 'FILTER_VALIDATE_STRING',
			'flags'  => FILTER_REQUIRE_ARRAY,
		);

		$values = automator_filter_input_array( 'values', INPUT_POST, $flags );

		if ( empty( $values['MCLIST'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$list_id = sanitize_text_field( $values['MCLIST'] );

		if ( empty( $list_id ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$request_params = array(
			'action'  => 'get_list_categories',
			'list_id' => $list_id,
		);

		try {
			$categories_response = $this->api_request( $request_params );

			if ( 200 !== intval( $categories_response->statusCode ) || empty( $categories_response->data->categories ) ) { // phpcs:ignore
				echo wp_json_encode( $fields );
				die();
			}

			foreach ( $categories_response->data->categories as $category ) {

				$request_params = array(
					'action'      => 'get_interests',
					'list_id'     => $list_id,
					'category_id' => $category->id,
				);

				$interests_response = $this->api_request( $request_params );

				if ( 200 === intval( $interests_response->statusCode ) ) { // phpcs:ignore

					if ( ! empty( $interests_response->data->interests ) ) {
						foreach ( $interests_response->data->interests as $interest ) {
							$fields[] = array(
								'value' => $interest->id,
								'text'  => $category->title . ' > ' . $interest->name,
							);
						}
					}
				}
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		echo wp_json_encode( $fields );
		die();
	}


	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_list_tags( $label = null, $option_code = 'MCLISTTAGS', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Tags', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any tag', 'uncanny-automator' ),
			)
		);

		$token                    = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$supports_multiple_values = key_exists( 'supports_multiple_values', $args ) ? $args['supports_multiple_values'] : false;
		$required                 = key_exists( 'required', $args ) ? $args['required'] : true;
		$options                  = array();

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => $required,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'supports_multiple_values' => $supports_multiple_values,
			'options'                  => $options,
			'hide_actions'             => isset( $args['hide_actions'] ) ? $args['hide_actions'] : false,
		);

		return apply_filters( 'uap_option_get_list_tags', $option );
	}

	/**
	 * Ajax callback for loading tags NAMES list.
	 */
	public function select_mctagslist_from_mclist() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$flags = array(
			'filter' => 'FILTER_VALIDATE_STRING',
			'flags'  => FILTER_REQUIRE_ARRAY,
		);

		$values = automator_filter_input_array( 'values', INPUT_POST, $flags );

		if ( empty( $values['MCLIST'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$list_id = sanitize_text_field( $values['MCLIST'] );

		$request_params = array(
			'action'  => 'get_segments',
			'list_id' => $list_id,
			'type'    => 'static',
			'fields'  => 'segments.name,segments.id',
			'count'   => 1000,
		);

		try {
			$response = $this->api_request( $request_params );

			if ( 200 !== intval( $response->statusCode ) || empty( $response->data->segments ) ) { // phpcs:ignore
				echo wp_json_encode( $fields );
				die();
			}

			foreach ( $response->data->segments as $segment ) {
				$fields[] = array(
					'value' => $segment->name,
					'text'  => $segment->name,
				);
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Ajax callback for loading segment IDS list.
	 */
	public function select_segments_from_list() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$fields[] = array(
			'value' => '-1',
			'text'  => __( 'Select a Segment or Tag', 'uncanny-automator' ),
		);

		$flags = array(
			'filter' => 'FILTER_VALIDATE_STRING',
			'flags'  => FILTER_REQUIRE_ARRAY,
		);

		$values = automator_filter_input_array( 'values', INPUT_POST, $flags );

		if ( empty( $values['MCLIST'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$list_id = sanitize_text_field( $values['MCLIST'] );

		$request_params = array(
			'action'  => 'get_segments',
			'list_id' => $list_id,
			'fields'  => 'segments.name,segments.id',
			'count'   => 1000,
		);

		try {
			$response = $this->api_request( $request_params );

			// prepare lists
			if ( 200 === intval( $response->statusCode ) ) { // phpcs:ignore

				if ( ! empty( $response->data->segments ) ) {
					foreach ( $response->data->segments as $segment ) {
						$fields[] = array(
							'value' => $segment->id,
							'text'  => $segment->name,
						);
					}
				}
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_double_opt_in( $label = null, $option_code = 'MCDOUBLEOPTIN', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Double opt-in', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any tag', 'uncanny-automator' ),
			)
		);

		$options   = array();
		$options[] = array(
			'value' => 'yes',
			'text'  => __( 'Yes', 'uncanny-automator' ),
		);

		$options[] = array(
			'value' => 'no',
			'text'  => __( 'No', 'uncanny-automator' ),
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description  = key_exists( 'description', $args ) ? $args['description'] : '';
		$options      = key_exists( 'options', $args ) ? $args['options'] : $options;

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'description'              => $description,
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'supports_multiple_values' => false,
			'options'                  => $options,
			'hide_actions'             => isset( $args['hide_actions'] ) ? $args['hide_actions'] : false,
		);

		return apply_filters( 'uap_option_get_double_opt_in', $option );
	}

	/**
	 * Ajax callback for loading audience list related merge fields.
	 */
	public function get_mailchimp_audience_fields() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$response = (object) array(
			'success' => false,
			'samples' => array(),
		);

		$list_id = sanitize_text_field( automator_filter_input( 'audience', INPUT_POST ) );

		$request_params = array(
			'action'  => 'get_list_fields',
			'list_id' => $list_id,
		);

		try {
			$response = $this->api_request( $request_params );

			// prepare meeting lists
			if ( 200 === intval( $response->statusCode ) ) { // phpcs:ignore

				if ( ! empty( $response->data->merge_fields ) ) {
					foreach ( $response->data->merge_fields as $field ) {
						$merge_order = $field->display_order * 10;
						if ( 'address' === $field->type ) {
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_addr1',
								'type' => 'text',
								'data' => $field->name,
							);
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_addr2',
								'type' => 'text',
								'data' => $field->name,
							);
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_city',
								'type' => 'text',
								'data' => $field->name,
							);
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_state',
								'type' => 'text',
								'data' => $field->name,
							);
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_zip',
								'type' => 'text',
								'data' => $field->name,
							);
							++$merge_order;
							$fields[ $merge_order ] = array(
								'key'  => $field->tag . '_country',
								'type' => 'text',
								'data' => $field->name,
							);
						} else {
							$fields[ $merge_order ] = array(
								'key'  => $field->tag,
								'type' => 'text',
								'data' => $field->name,
							);
						}
					}
					ksort( $fields );
					$ajax_response = (object) array(
						'success' => true,
						'samples' => array( $fields ),
					);
				}
			}
		} catch ( \Exception $e ) {
			$ajax_response = (object) array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		echo wp_json_encode( $ajax_response );
		die();
	}

	/**
	 * @param null $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_all_email_templates( $label = null, $option_code = 'MCEMAILTEMPLATE', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Template', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'All', 'uncanny-automator' ),
			)
		);

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$required                 = key_exists( 'required', $args ) ? $args['required'] : false;
		$options                  = array();

		// For default value, when user do not want to select a template.
		$options[] = array(
			'value' => '',
			'text'  => __( 'Select a template', 'uncanny-automator' ),
		);

		if ( Automator()->helpers->recipe->load_helpers ) {

			$request_params = array(
				'action' => 'get_email_templates',
			);

			try {
				$response = $this->api_request( $request_params );

				if ( 200 === intval( $response->statusCode ) ) { // phpcs:ignore

					if ( ! empty( $response->data->templates ) ) {
						foreach ( $response->data->templates as $template ) {
							$options[] = array(
								'value' => $template->id,
								'text'  => $template->name,
							);
						}
					}
				}
			} catch ( \Exception $e ) {
				automator_log( $e->getMessage() );
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => $required,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'MAILCHIMP',
		);

		return apply_filters( 'uap_option_get_all_lists', $option );
	}

	/**
	 * Check if the settings tab should display.
	 *
	 * @return boolean.
	 */
	public function display_settings_tab() {

		if ( Automator()->utilities->has_valid_license() ) {
			return true;
		}

		if ( Automator()->utilities->is_from_modal_action() ) {
			return true;
		}

		return ! empty( $this->get_mailchimp_client() );
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_mailchimp_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {

			$is_uncannyowl_mailchimp_settings_expired = get_option( '_uncannyowl_mailchimp_settings_expired', false );
			$tab_url                                  = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
			$tabs[ $this->setting_tab ]               = array(
				'name'           => __( 'Mailchimp', 'uncanny-automator' ),
				'title'          => __( 'Mailchimp account settings', 'uncanny-automator' ),
				'description'    => __( 'Connecting to Mailchimp requires signing into your account to link it to Automator. To get started, click the "Connect an account" button below or the "Change account" button if you need to connect a new account. Uncanny Automator can only connect to a single Mailchimp account at one time. (It is not possible to set some recipes up under one account and then switch accounts, all recipes are mapped to the account selected on this page and existing recipes may break if they were set up under another account).', 'uncanny-automator' ) . $this->get_user_info(),
				'is_pro'         => false,
				'is_expired'     => $is_uncannyowl_mailchimp_settings_expired,
				'settings_field' => 'uap_automator_mailchimp_api_settings',
				'wp_nonce_field' => 'uap_automator_mailchimp_api_nonce',
				'save_btn_name'  => 'uap_automator_mailchimp_api_save',
				'save_btn_title' => __( 'Connect mailchimp Sheets', 'uncanny-automator' ),
				'fields'         => array(),
			);

		}

		return $tabs;
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return mixed
	 */
	public function mailchimp_connect_html( $content, $active, $tab ) {

		if ( 'mailchimp_api' === $active ) {
			$action       = 'mailchimp_authorization_request';
			$redirect_url = rawurlencode( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab );
			$nonce        = wp_create_nonce( 'automator_api_mailchimp_authorize' );
			set_transient( 'automator_api_mailchimp_authorize_nonce', $nonce, 3600 );
			$scope             = '';
			$automator_version = InitializePlugin::PLUGIN_VERSION;
			$auth_url          = $this->automator_api . "?action={$action}&scope={$scope}&redirect_url={$redirect_url}&nonce={$nonce}&plugin_ver={$automator_version}&api_ver=1.0";

			$gs_client    = $this->get_mailchimp_client();
			$button_text  = __( 'Connect an account', 'uncanny-automator' );
			$button_class = '';

			if ( $gs_client ) {
				$button_text  = __( 'Change account', 'uncanny-automator' );
				$button_class = 'uo-connected-button';
			}
			ob_start();
			?>
			<div class="uo-settings-content-form">

				<a href="<?php echo esc_url( $auth_url ); ?>" class="uo-settings-btn uo-settings-btn--primary <?php echo esc_attr( $button_class ); ?>">
					<?php echo esc_attr( $button_text ); ?>
				</a>

				<?php if ( $gs_client ) : ?>
					<?php
					$disconnect_uri = add_query_arg(
						array(
							'action' => 'uo_mailchimp_disconnect',
							'nonce'  => wp_create_nonce( 'uo-mailchimp-disconnect' ),
						),
						admin_url( 'admin-ajax.php' )
					);
					?>
					<a href="<?php echo esc_url( $disconnect_uri ); ?>" class="uo-settings-btn uo-settings-btn--error">
						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</a>
				<?php endif; ?>

			</div>
			<style>
				.uo-mailchimp-user-info {
					display: flex;
					align-items: center;
					margin: 20px 0;
				}

				.uo-mailchimp-user-info__avatar {
					display: inline-flex;
					align-items: center;
					overflow: hidden;
					border-radius: 32px;
					margin-right: 10px;
				}

				.uo-mailchimp-user-info__name {
					margin-left: 5px;
					opacity: 0.75;
				}

				.uo-connected-button {
					color: #fff;
					background-color: #0790e8;
				}

				.uo-settings-content-footer {
					display: none !important;
				}
			</style>
			<?php
		}

		return $content;
	}

	/**
	 * Get Mailchimp Client object
	 *
	 * @return false|object
	 */
	public function get_mailchimp_client() {

		$client = get_option( '_uncannyowl_mailchimp_settings', array() );

		if ( empty( $client ) || ! isset( $client['access_token'] ) ) {
			return false;
		}

		return (object) $client;
	}

	/**
	 * Callback function for OAuth redirect verification.
	 */
	public function validate_oauth_tokens() {

		$api_message = automator_filter_input( 'automator_api_message' );
		$tab         = automator_filter_input( 'tab' );

		if ( ! empty( $api_message ) && ! empty( $tab ) && $tab === $this->setting_tab ) {
			try {
				$secret = get_transient( 'automator_api_mailchimp_authorize_nonce' );
				$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $api_message, $secret );

				if ( ! empty( $tokens['access_token'] ) ) {

					$user_info = array(
						'email'      => '',
						'avatar'     => '',
						'login_name' => '',
					);

					if ( isset( $tokens['login'] ) ) {
						$user_info['email']      = isset( $tokens['login']->email ) ? $tokens['login']->email : '';
						$user_info['avatar']     = isset( $tokens['login']->avatar ) ? $tokens['login']->avatar : '';
						$user_info['login_name'] = isset( $tokens['login']->login_name ) ? $tokens['login']->login_name : '';
					}

					// Update user info settings.
					update_option( '_uncannyowl_mailchimp_settings_user_info', $user_info );

					// On success
					update_option( '_uncannyowl_mailchimp_settings', $tokens );
					delete_option( '_uncannyowl_mailchimp_settings_expired' );
					//set the transient
					set_transient( '_uncannyowl_mailchimp_settings', $tokens['access_token'] . '|' . $tokens['dc'], 60 * 50 );
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=1' ) );
					die;

				} else {
					// On Error
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
					die;
				}
			} catch ( \Exception $e ) {
				// On Error
				wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
				die;
			}
		}
	}

	/**
	 * Get the user info of the user that is connected.
	 *
	 * @return string The html of the user info.
	 */
	public function get_user_info() {
		$user_info = get_option( '_uncannyowl_mailchimp_settings_user_info' );
		$mc_client = $this->get_mailchimp_client();

		ob_start();
		?>
		<?php if ( false !== $user_info && ! empty( $mc_client ) ) : ?>
			<div class="uo-mailchimp-user-info">
				<?php if ( ! empty( $user_info['avatar'] ) ) : ?>
					<div class="uo-mailchimp-user-info__avatar">
						<img width="32" src="<?php echo esc_url( $user_info['avatar'] ); ?>" alt="<?php echo esc_html( $user_info['login_name'] ); ?>"/>
					</div>
				<?php endif; ?>
				<div class="uo-mailchimp-user-info__email">
					<?php echo esc_html( $user_info['email'] ); ?>
				</div>
				<div class="uo-mailchimp-user-info__name">
					(<?php echo esc_html( $user_info['login_name'] ); ?>)
				</div>
			</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();

	}

	/**
	 * Disconnect the user. Remove access token from db, etc.
	 *
	 * @return void
	 */
	public function uo_mailchimp_disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_STRING ), 'uo-mailchimp-disconnect' ) ) {
			delete_option( '_uncannyowl_mailchimp_settings' );
			delete_option( '_uncannyowl_mailchimp_settings_expired' );
			delete_option( '_uncannyowl_mailchimp_settings_user_info' );
			delete_transient( 'automator_api_mailchimp_authorize_nonce' );
			delete_transient( '_uncannyowl_mailchimp_settings' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-settings',
					'tab'       => 'mailchimp_api',
				),
				admin_url( 'edit.php' )
			)
		);

		exit;

	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $params ) {

		$client = $this->get_mailchimp_client();

		if ( ! $client ) {
			throw new \Exception( __( 'Mailchimp account not found.', 'uncanny-automator' ) );
		}

		$body = array(
			'client'     => $client,
			'api_ver'    => '2.0',
			'plugin_ver' => InitializePlugin::PLUGIN_VERSION,
		);

		$body = array_merge( $body, $params );

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$json_data = json_decode( wp_remote_retrieve_body( $response ) );

		return $json_data;
	}

	/**
	 * Method log_action_error
	 *
	 * @param $response
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function log_action_error( $response, $user_id, $action_data, $recipe_id ) {

		// log error when no token found.
		$error_msg = __( 'API error: ', 'uncanny-automator' );

		if ( isset( $response->data->title ) ) {
			$error_msg .= ' ' . $response->data->title;
		}

		if ( isset( $response->data->detail ) ) {
			$error_msg .= ' ' . $response->data->detail;
		}

		if ( isset( $response->data->errors ) ) {
			foreach ( $response->data->errors as $error ) {
				$error_msg .= ' ' . $error->field;
				$error_msg .= ' ' . $error->message;
			}
		}

		if ( isset( $response->error->type ) ) {
			$error_msg .= ' ' . $response->error->type;
		}

		if ( isset( $response->error->description ) ) {
			$error_msg .= ' ' . $response->error->description;
		}

		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}
}
