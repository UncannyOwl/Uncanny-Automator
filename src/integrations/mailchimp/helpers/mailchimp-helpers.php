<?php
namespace Uncanny_Automator;

global $mailchimp_meeting_token_renew;

use Uncanny_Automator_Pro\Mailchimp_Pro_Helpers;

use Uncanny_Automator\Api_Server;

/**
 * Class Mailchimp_Helpers
 *
 * @package Uncanny_Automator
 */
class Mailchimp_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/mailchimp';

	/**
	 * Maichimp Helpers.
	 *
	 * @var Mailchimp_Helpers
	 */
	public $options;

	/**
	 * Pro Helpers.
	 *
	 * @var Pro_Helpers
	 */
	public $pro;


	/**
	 * Load Options.
	 *
	 * @var bool
	 */
	public $load_options;

	/**
	 * Mailchimp Endpoint.
	 *
	 * @var string
	 */
	private $mailchimp_endpoint;

	/**
	 * The webhook endpoint url.
	 *
	 * @var string
	 */
	public $webhook_endpoint = '';

	/**
	 * The Hash String.
	 *
	 * @var string
	 */
	public static $hash_string = 'Uncanny Automator Pro Mailchimp Sheet Integration';

	public function __construct() {

		$this->settings_tab = 'mailchimp_api';

		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );

		add_action( 'wp_ajax_select_mcgroupslist_from_mclist', array( $this, 'select_mcgroupslist_from_mclist' ) );

		add_action( 'wp_ajax_select_mctagslist_from_mclist', array( $this, 'select_mctagslist_from_mclist' ) );

		add_action( 'wp_ajax_select_segments_from_list', array( $this, 'select_segments_from_list' ) );

		add_action( 'wp_ajax_get_mailchimp_audience_fields', array( $this, 'get_mailchimp_audience_fields' ) );

		add_action( 'wp_ajax_uo_mailchimp_disconnect', array( $this, 'uo_mailchimp_disconnect' ) );

		add_action( 'wp_ajax_mailchimp-regenerate-webhook-key', array( $this, 'regenerate_webhook_key_ajax' ) );

		// Load webhook method once webhook option is enabled.
		if ( $this->is_webhook_enabled() ) {

			$this->webhook_endpoint = apply_filters( 'automator_mailchimp_webhook_endpoint', '/mailchimp', $this );

			$this->define_webhook_listener();

		}

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-mailchimp.php';

		new Mailchimp_Settings( $this );

	}

	/**
	 * Set Options.
	 *
	 * @param Mailchimp_Helpers $options
	 */
	public function setOptions( Mailchimp_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * Set Pro.
	 *
	 * @param Mailchimp_Helpers $pro
	 */
	public function setPro( Mailchimp_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * Get all list.
	 *
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

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$has_any                  = key_exists( 'has_any', $args ) ? $args['has_any'] : null;
		$options                  = array();

		try {

			$body = array(
				'action' => 'get_lists',
			);

			$response = $this->api_request( $body );

			if ( 200 === intval( $response['statusCode'] ) ) {
				if ( ! empty( $response['data']['lists'] ) ) {
					if ( ! empty( $has_any ) && true === $has_any ) {
						$options[] = array(
							'value' => '-1',
							'text'  => __( 'Any audience', 'uncanny-automator' ),
						);
					}
					foreach ( $response['data']['lists'] as $list ) {
						$options[] = array(
							'value' => $list['id'],
							'text'  => $list['name'],
						);
					}
				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
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
			'relevant_tokens'          => array(),
		);

		return apply_filters( 'uap_option_get_all_lists', $option );
	}

	/**
	 * Get all list groups.
	 *
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

	public function select_mcgroupslist_from_mclist() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		$values = automator_filter_input_array( 'values', INPUT_POST );

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
			$response = $this->api_request( $request_params );

			if ( 200 !== intval( $response['statusCode'] ) || empty( $response['data']['categories'] ) ) { // phpcs:ignore
				throw new \Exception( __( 'Could not fetch categories.', 'uncanny-automator' ) );
			}

			foreach ( $response['data']['categories'] as $category ) {

				$request_params = array(
					'action'      => 'get_interests',
					'list_id'     => $list_id,
					'category_id' => $category['id'],
				);

				$interests_response = $this->api_request( $request_params );

				if ( 200 === intval( $interests_response['statusCode'] ) ) {
					if ( ! empty( $interests_response['data']['interests'] ) ) {
						foreach ( $interests_response['data']['interests'] as $interest ) {
							$fields[] = array(
								'value' => $interest['id'],
								'text'  => $category['title'] . ' > ' . $interest['name'],
							);
						}
					}
				}
			}
		} catch ( \Exception $e ) {
			$fields[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		echo wp_json_encode( $fields );
		die();
	}


	/**
	 * Get list tags.
	 *
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
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : '';
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
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => true,
			'supports_multiple_values' => $supports_multiple_values,
			'options'                  => $options,
			'hide_actions'             => isset( $args['hide_actions'] ) ? $args['hide_actions'] : false,
		);

		return apply_filters( 'uap_option_get_list_tags', $option );
	}

	/**
	 * Ajax callback for loading tags NAMES list.
	 *
	 * @return void.
	 */
	public function select_mctagslist_from_mclist() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		$values = automator_filter_input_array( 'values', INPUT_POST );

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

			if ( 200 !== intval( $response['statusCode'] ) || empty( $response['data']['segments'] ) ) { // phpcs:ignore
				echo wp_json_encode( $fields );
				die();
			}

			foreach ( $response['data']['segments'] as $segment ) {
				$fields[] = array(
					'value' => $segment['name'],
					'text'  => $segment['name'],
				);
			}
		} catch ( \Exception $e ) {
			$fields[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * Ajax callback for loading segment IDS list.
	 *
	 * @return void.
	 */
	public function select_segments_from_list() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$fields[] = array(
			'value' => '-1',
			'text'  => __( 'Select a Segment or Tag', 'uncanny-automator' ),
		);

		$values = automator_filter_input_array( 'values', INPUT_POST );

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

			if ( 200 !== intval( $response['statusCode'] ) ) {
				throw new \Exception( __( 'Could not fetch segments.', 'uncanny-automator' ) );
			}

			if ( ! empty( $response['data']['segments'] ) ) {
				foreach ( $response['data']['segments'] as $segment ) {
					$fields[] = array(
						'value' => $segment['id'],
						'text'  => $segment['name'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$fields[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Get double opt in.
	 *
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
	 *
	 * @return void.
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

			if ( 200 !== intval( $response['statusCode'] ) ) { 
				throw new \Exception( __( 'Could not fetch fields', 'uncanny-automator' ) );
			}

			if ( ! empty( $response['data']['merge_fields'] ) ) {
				foreach ( $response['data']['merge_fields'] as $field ) {
					$merge_order = $field['display_order'] * 10;
					if ( 'address' === $field['type'] ) {
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_addr1',
							'type' => 'text',
							'data' => $field['name'],
						);
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_addr2',
							'type' => 'text',
							'data' => $field['name'],
						);
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_city',
							'type' => 'text',
							'data' => $field['name'],
						);
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_state',
							'type' => 'text',
							'data' => $field['name'],
						);
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_zip',
							'type' => 'text',
							'data' => $field['name'],
						);
						++ $merge_order;
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'] . '_country',
							'type' => 'text',
							'data' => $field['name'],
						);
					} else {
						$fields[ $merge_order ] = array(
							'key'  => $field['tag'],
							'type' => 'text',
							'data' => $field['name'],
						);
					}
				}
				ksort( $fields );
				$ajax_response = (object) array(
					'success' => true,
					'samples' => array( $fields ),
				);
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
	 * Get all email templates.
	 *
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

				if ( 200 !== intval( $response['statusCode'] ) ) {
					throw new \Exception( __( 'Could not fetch templates.', 'uncanny-automator' ) );
				}

				if ( ! empty( $response['data']['templates'] ) ) {
					foreach ( $response['data']['templates'] as $template ) {
						$options[] = array(
							'value' => $template['id'],
							'text'  => $template['name'],
						);
					}
				}
			} catch ( \Exception $e ) {
				$options[] = array(
					'value' => '',
					'text'  => $e->getMessage(),
				);
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
	 * Get Mailchimp Client object
	 *
	 * @return false|object
	 */
	public function get_mailchimp_client() {

		$client = get_option( '_uncannyowl_mailchimp_settings', array() );

		if ( empty( $client ) || ! isset( $client['access_token'] ) ) {
			throw new \Exception( __( 'Mailchimp account not found.', 'uncanny-automator' ) );
		}

		return (object) $client;
	}

	/**
	 * Callback function for OAuth redirect verification.
	 */
	public function validate_oauth_tokens() {

		$api_message = automator_filter_input( 'automator_api_message' );

		$integration = automator_filter_input( 'integration' );

		if ( ! empty( $api_message ) && 'mailchimp_api' === $integration ) {

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

					// On success.
					update_option( '_uncannyowl_mailchimp_settings', $tokens );
					delete_option( '_uncannyowl_mailchimp_settings_expired' );

					// Set the transient.
					set_transient( '_uncannyowl_mailchimp_settings', $tokens['access_token'] . '|' . $tokens['dc'], 60 * 50 );

					// Redirect back to settings page.
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=mailchimp_api&connect=1' ) );

					die;

				} else {

					// On Error.
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=mailchimp_api&connect=2' ) );

					die;

				}
			} catch ( \Exception $e ) {

				// On Error.
				wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=mailchimp_api&connect=2' ) );

				die;

			}
		}
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
					'post_type'   => 'uo-recipe',
					'page'        => 'uncanny-automator-config',
					'tab'         => 'premium-integrations',
					'integration' => 'mailchimp_api',
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
	public function api_request( $body, $action = null ) {

		$client = $this->get_mailchimp_client();

		$body['client'] = $client;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

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
	public function complete_with_error( $error_msg, $user_id, $action_data, $recipe_id ) {
		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * Get the Mailchimp OAuth URI.
	 *
	 * @return string The Mailchimp OAuth URI.
	 */
	public function get_connect_uri() {

		$action       = '';
		$redirect_url = rawurlencode( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->settings_tab );

		return add_query_arg(
			array(
				'action'       => 'mailchimp_authorization_request',
				'scope'        => '1',
				'redirect_url' => $redirect_url,
				'nonce'        => wp_create_nonce( 'automator_api_mailchimp_authorize' ),
				'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				'api_ver'      => '2.0',
			),
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);
	}

	public function get_disconnect_uri() {
		return add_query_arg(
			array(
				'action' => 'uo_mailchimp_disconnect',
				'nonce'  => wp_create_nonce( 'uo-mailchimp-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * check_for_errors
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function check_for_errors( $response ) {

		$expected_codes = array( 200, 204 );

		if ( in_array( $response['statusCode'], $expected_codes ) ) {
			return $response;
		}

		$error_msg = '';

		if ( isset( $response['data']['title'] ) ) {
			$error_msg .= $response['data']['title'];
		}

		if ( isset( $response['data']['detail'] ) ) {
			$error_msg .= ': ' . $response['data']['detail'];
		}

		if ( isset( $respons['data']['errors'] ) ) {
			foreach ( $response['data']['errors'] as $error ) {
				$error_msg .= ' ' . $error['field'];
				$error_msg .= ' ' . $error['message'];
			}
		}

		if ( ! empty( $error_msg ) ) {
			if ( isset( $response['status'] )) {
				$error_msg = '(' . $response['status'] . ') ' . $error_msg;
			}

			throw new \Exception( $error_msg, $response['status'] );
		}	
	}
	
	/**
	 * get_user
	 *
	 * @return void
	 */
	public function get_list_user( $list_id, $user_hash ) {

		$request_params = array(
			'action'    => 'get_subscriber',
			'list_id'   => $list_id,
			'user_hash' => $user_hash,
		);

		try {
			return $this->api_request( $request_params );
		} catch ( \Exception $e ) {
			return false;
		}
	}
	
	/**
	 * compile_user_interests
	 *
	 * @param  mixed $existing_user
	 * @param  mixed $change_groups
	 * @param  mixed $check_interests
	 * @return void
	 */
	public function compile_user_interests( $existing_user, $change_groups, $groups_list ) {

		// Only add new interests
		if ( 'add-only' === $change_groups ) {

			if ( empty( $groups_list ) ) {
				// No interests to add
				return array();
			}

			$add_interests = array();

			foreach ( $groups_list as $interest_id ) {
				$add_interests[$interest_id] = true;
			}

			return $add_interests;
		}

		// Remove any matching interests
		if ( 'replace-matching' === $change_groups ) {

			if ( empty( $groups_list ) ) {
				// No interests to remove
				return array();
			}
			
			$remove_interests = array();

			foreach ( $groups_list as $interest_id ) {
				$remove_interests[$interest_id] = false;
			}

			return $remove_interests;
		}

		// Replace All. All of the subscriber's existing groups will be cleared, and replaced with the groups selected below.
		if ( 'replace-all' === $change_groups ) {

			$new_interests = array();

			if ( ! empty( $existing_user['data']['interests'] ) ) {
				// First remove all interests
				foreach ( $existing_user['data']['interests'] as $interest_id => $status ) {
					$new_interests[ $interest_id ] = false;
				}
			}
			
			// Then add the new ones
			foreach ( $groups_list as $interest_id ) {
				$new_interests[$interest_id] = true;
			}

			return $new_interests;
		}

		return array();
	}
	
	/**
	 * Defines our webhook listener.
	 *
	 * @return void
	 */
	public function define_webhook_listener() {

		add_action( 'rest_api_init', array( $this, 'init_webhook' ) );

	}

	/**
	 * Checks if webhook is enabled or not.
	 *
	 * @return boolean True if webhook is enabled. Otherwise, false.
	 */
	public function is_webhook_enabled() {

		$webhook_enabled_option = get_option( 'uap_mailchimp_enable_webhook', false );

		// The get_option can return string or boolean sometimes.
		if ( 'on' === $webhook_enabled_option || 1 == $webhook_enabled_option ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

			return true;

		}

		return false;

	}

	/**
	 * Check if has connection data.
	 *
	 * @return boolean True if has connection data. Otherwise, false.
	 */
	public function has_connection_data() {

		$has_connection = true;

		try {

			$this->get_mailchimp_client();

		} catch ( \Exception $e ) {

			$has_connection = false;

		}

		return $has_connection;

	}

	/**
	 * Validates the webhook.
	 *
	 * @return boolean True if okay. Otherwise, false.
	 */
	public function validate_webhook( $request ) {

		// Don't process any GET request. Just return true if its a GET statement.
		if ( 'GET' === $request->get_method() ) {

			return true;

		}

		$query_params = $request->get_query_params();

		$headers = $request->get_headers();

		// Mailchimp sets user agent to MailChimp if its coming from their webhook.
		$user_agent = isset( $headers['user_agent'] ) ? $headers['user_agent'] : '';

		if ( empty( $user_agent ) ) {

			return false;

		}

		$user_agent_values = array_values( $user_agent );

		if ( 'MailChimp' !== array_shift( $user_agent_values ) ) {

			return false;

		}

		if ( ! isset( $query_params['key'] ) ) {

			return false;

		}

		$actual_key = $this->get_webhook_key();

		if ( $actual_key !== $query_params['key'] ) {

			return false;

		}

		return true;

	}

	/**
	 * Initialize the webhook rest api endpoint.
	 *
	 * @return void.
	 */
	public function init_webhook() {

		if ( $this->is_webhook_enabled() && $this->has_connection_data() ) {

			register_rest_route(
				AUTOMATOR_REST_API_END_POINT,
				$this->webhook_endpoint,
				array(
					'methods'             => array( 'POST', 'GET' ),
					'callback'            => array( $this, 'webhook_callback' ),
					'permission_callback' => array( $this, 'validate_webhook' ),
				)
			);

		}

	}

	/**
	 * This function will fire for valid incoming webhook calls
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function webhook_callback( $request ) {

		$body = $request->get_body_params();

		$type = isset( $body['type'] ) ? $body['type'] : '';

		$registered_trigger_types = array( 'unsubscribe', 'subscribe', 'upemail' );

		if ( empty( $type ) ) {

			$this->log( '[1/3. Event received - 400]. MailChimp has sent data but `type` is missing.' );

			return;

		}

		if ( ! in_array( $type, $registered_trigger_types, true ) ) {

			$this->log( '[1/3. Event received - 400]. Automator received webhook data from MailChimp of type: ' . $type . '. But no registered Triggers that will handle this event.' );

			return;

		}

		$this->log( '[1/3. Event received - 200]. Automator received webhook data from MailChimp of type: ' . $type );

		do_action( 'automator_mailchimp_webhook_received_' . $type, $body );

		exit;

	}

	/**
	 * Get the webhook uri.
	 *
	 * @return void
	 */
	public function get_webhook_url() {

		return $this->webhook_endpoint . '?key=' . $this->get_webhook_key();

	}

	/**
	 * Retrieve the webhook key.
	 *
	 * @return void
	 */
	public function get_webhook_key() {

		$webhook_key = get_option( 'uap_mailchimp_webhook_key', false );

		if ( false === $webhook_key ) {

			$webhook_key = $this->regenerate_webhook_key();

		}

		return $webhook_key;

	}

	/**
	 * Generate webhook key.
	 *
	 * @return void
	 */
	public function regenerate_webhook_key() {

		$new_key = md5( uniqid( wp_rand(), true ) );

		update_option( 'uap_mailchimp_webhook_key', $new_key );

		return $new_key;

	}

	/**
	 * Regenerate the webhook key via Ajax.
	 *
	 * @return void
	 */
	public function regenerate_webhook_key_ajax() {

		$this->regenerate_webhook_key();

		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=mailchimp_api';

		wp_safe_redirect( $uri );

		exit;

	}

	/**
	 * Log the MailChimp webhook and trigger events.
	 *
	 * @return mixed The automator_log.
	 */
	public function log( $message = '' ) {

		if ( empty( $message ) ) {
			return;
		}

		$force_debug = true;

		return automator_log( $message, 'MailChimp Webhook Trigger Entry', $force_debug, 'mailchimp-webhook' );

	}
	
	/**
	 * validate_trigger
	 *
	 * @return void
	 */
	public function validate_trigger() {

		$msg = 'true';

		try {
			Api_Server::charge_credit();
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->log( '[2/3. Validating found trigger]. Result: ' . $msg );

		return 'true' === $msg;
	}

}
