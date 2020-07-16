<?php


namespace Uncanny_Automator;


/**
 * Class Wp_Helpers
 * @package Uncanny_Automator
 */
class Wp_Helpers {
	/**
	 * Wp_Helpers constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_select_custom_post_by_type', array( $this, 'select_custom_post_func' ) );

		add_action( 'wp_ajax_nopriv_sendtest_wp_webhook', array( $this, 'sendtest_webhook' ) );
		add_action( 'wp_ajax_sendtest_wp_webhook', array( $this, 'sendtest_webhook' ) );
	}

	/**
	 * @var Wp_Helpers
	 */
	public $options;
	/**
	 * @var \Uncanny_Automator_Pro\Wp_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Wp_Helpers $options
	 */
	public function setOptions( Wp_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Wp_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Wp_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Return all the specific fields of post type in ajax call
	 */
	function select_custom_post_func() {
		global $uncanny_automator;

		$uncanny_automator->utilities->ajax_auth_check( $_POST );
		$fields = [];
		if ( isset( $_POST ) && key_exists( 'value', $_POST ) && ! empty( $_POST['value'] ) ) {
			$post_type = sanitize_text_field( $_POST['value'] );

			$args       = array(
				'posts_per_page'   => 999,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'suppress_filters' => true,
				'fields'           => array( 'ids', 'titles' ),
			);
			$posts_list = get_posts( $args );

			if ( ! empty( $posts_list ) ) {
				foreach ( $posts_list as $post ) {
					// Check if the post title is defined
					$post_title = ! empty( $post->post_title ) ? $post->post_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );

					$fields[] = array(
						'value' => $post->ID,
						'text'  => $post_title,
					);
				}
			}
		}
		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_posts( $label = null, $option_code = 'WPPOST', $any_option = true ) {

		if ( ! $label ) {
			/* translators: Noun */
			$label = __( 'Post', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'post',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$all_posts = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any post', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_posts,
			'relevant_tokens' => [
				$option_code          => __( 'Post title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Post ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Post URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_posts', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_pages( $label = null, $option_code = 'WPPAGE', $any_option = false ) {

		if ( ! $label ) {
			$label = __( 'Page', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'page',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$all_pages = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'All pages', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_pages,
			'relevant_tokens' => [
				$option_code          => __( 'Page title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Page ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Page URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_pages', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wp_user_roles( $label = null, $option_code = 'WPROLE' ) {

		if ( ! $label ) {
			/* translators: WordPress role */
			$label = __( 'Role', 'uncanny-automator' );
		}

		$roles = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			foreach ( wp_roles()->roles as $role_name => $role_info ) {
				$roles[ $role_name ] = $role_info['name'];
			}
		}
		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $roles,
		];

		return apply_filters( 'uap_option_wp_user_roles', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Post type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			$args = [
				'public'   => true,
				'_builtin' => false,
			];

			$output   = 'object';
			$operator = 'and';

			$post_types = get_post_types( $args, $output, $operator );
			if ( ! empty( $post_types ) ) {
				foreach ( $post_types as $post_type ) {
					$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];


		return apply_filters( 'uap_option_all_post_types', $option );
	}

	/**
	 * @param $_POST
	 */
	public function sendtest_webhook() {

		global $uncanny_automator;

		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$key_values   = [];
		$values       = (array) $uncanny_automator->uap_sanitize( $_POST['values'], 'mixed' );
		$request_type = 'POST';
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$webhook_url = esc_url_raw( $values['WEBHOOKURL'] );

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			for ( $i = 1; $i <= WP_SENDWEBHOOK::$number_of_keys; $i ++ ) {
				$key                = sanitize_text_field( $values[ 'KEY' . $i ] );
				$value              = sanitize_text_field( $values[ 'VALUE' . $i ] );
				$key_values[ $key ] = $value;
			}

		} elseif ( isset( $values['WEBHOOK_URL'] ) ) {
			$webhook_url = esc_url_raw( $values['WEBHOOK_URL'] );

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			if ( ! isset( $values['WEBHOOK_FIELDS'] ) || empty( $values['WEBHOOK_FIELDS'] ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter valid fields.', 'uncanny-automator' ),
				] );
			}

			$fields = $values['WEBHOOK_FIELDS'];

			for ( $i = 0; $i <= count( $fields ); $i ++ ) {
				$key   = isset( $fields[ $i ]['KEY'] ) ? sanitize_text_field( $fields[ $i ]['KEY'] ) : null;
				$value = isset( $fields[ $i ]['VALUE'] ) ? sanitize_text_field( $fields[ $i ]['VALUE'] ) : null;
				if ( ! is_null( $key ) && ! is_null( $value ) ) {
					$key_values[ $key ] = $value;
				}
			}

			if ( 'POST' === (string) $values['ACTION_EVENT'] || 'CUSTOM' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'POST';
			} elseif ( 'GET' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'GET';
			} elseif ( 'PUT' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'PUT';
			}
		}

		if ( $key_values && ! is_null( $webhook_url ) ) {

			$args = array(
				'method'   => $request_type,
				'body'     => $key_values,
				'timeout'  => '30',
				'blocking' => false,
			);

			$response = wp_remote_request( $webhook_url, $args );

			if ( $response instanceof \WP_Error ) {
				/* translators: 1. Webhook URL */
				$error_message = sprintf( __( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $webhook_url );
				wp_send_json( [
					'type'    => 'error',
					'message' => $error_message,
				] );
			}

			/* translators: 1. Webhook URL */
			$success_message = sprintf( __( 'Successfully sent data on %1$s.', 'uncanny-automator' ), $webhook_url );

			wp_send_json( array(
				'type'    => 'success',
				'message' => $success_message,
			) );
		}
	}
}