<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Mailpoet_Pro_Helpers;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Tags\TagRepository;

/**
 * Class Mailpoet_Helpers
 *
 * @package Uncanny_Automator
 */
class Mailpoet_Helpers {

	/**
	 * @var Mailpoet_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * @var Mailpoet_Pro_Helpers
	 */
	public $pro;

	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Mailpoet_Helpers $options
	 */
	public function setOptions( Mailpoet_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Mailpoet_Pro_Helpers $pro
	 */
	public function setPro( Mailpoet_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|null
	 * @throws \Exception
	 */
	public function get_all_mailpoet_lists( $label = null, $option_code = 'MAILPOETLISTS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		$any_option  = key_exists( 'any_option', $args ) ? $args['any_option'] : false;
		$all_include = key_exists( 'all_include', $args ) ? $args['all_include'] : false;

		if ( ! $label ) {
			$label = esc_attr_x( 'List', 'Mailpoet', 'uncanny-automator' );
		}

		$options = array();
		if ( true === $any_option ) {
			$options['-1'] = esc_attr_x( 'Any list', 'Mailpoet', 'uncanny-automator' );
		}

		if ( true === $all_include ) {
			$options['all'] = esc_attr_x( 'All lists', 'Mailpoet', 'uncanny-automator' );
		}

		$mailpoet  = \MailPoet\API\API::MP( 'v1' );
		$all_lists = $mailpoet->getLists();

		foreach ( $all_lists as $list ) {
			$options[ $list['id'] ] = $list['name'];
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr_x( 'List', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr_x( 'List ID', 'Mailpoet', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_get_all_mailpoet_lists', $option );
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|null
	 */
	public function get_all_mailpoet_subscribers( $label = null, $option_code = 'MAILPOETSUBSCRIBERS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr_x( 'Subscriber', 'Mailpoet', 'uncanny-automator' );
		}

		$options = array();

		global $wpdb;

		$subscribers = $wpdb->get_results( "SELECT id,email FROM {$wpdb->prefix}mailpoet_subscribers  ORDER BY id DESC", ARRAY_A );

		foreach ( $subscribers as $subscriber ) {
			$options[ $subscriber['id'] ] = $subscriber['email'];
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr_x( 'Subscriber', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr_x( 'Subscriber ID', 'Mailpoet', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_get_all_mailpoet_subscribers', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_mailpoet_forms( $label = null, $option_code = 'MAILPOETFORMS', $args = array() ) {
		global $wpdb;

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr_x( 'Form', 'Mailpoet', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			$forms = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mailpoet_forms WHERE `deleted_at` IS NULL AND `status` = %s ORDER BY `id` LIMIT %d", 'enabled', 9999 ) );
			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->id ] = esc_html( $form->name );
				}
			}
		}
		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr_x( 'Form title', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr_x( 'Form ID', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_FIRSTNAME' => esc_attr_x( 'First name', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_LASTNAME'  => esc_attr_x( 'Last name', 'Mailpoet', 'uncanny-automator' ),
				$option_code . '_EMAIL'     => esc_attr_x( 'Email', 'Mailpoet', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_mailpoet_forms', $option );
	}

	/**
	 * Get all MailPoet tags.
	 *
	 * @param string|null $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return array|mixed|null
	 */
	public function get_mailpoet_tag_options() {
		$options = array();

		try {
			// get MailPoet TagRepository instance
			$tag_repository = ContainerWrapper::getInstance()->get( TagRepository::class );

			// get all tags
			$tags = $tag_repository->findAll();

			// build Automator options array
			foreach ( $tags as $tag ) {
				$options[] = array(
					'text'  => esc_html( $tag->getName() ),
					'value' => $tag->getId(),
				);
			}

			// if no tags, return fallback message
			if ( empty( $options ) ) {
				$options[] = array(
					'text'  => esc_html_x( 'No tags found', 'MailPoet', 'uncanny-automator' ),
					'value' => '',
				);
			}
		} catch ( \Throwable $e ) {
			$options[] = array(
				'text'  => esc_html_x( 'Unable to load tags', 'MailPoet', 'uncanny-automator' ),
				'value' => '',
			);
		}

		return $options;
	}

	/**
	 * Check if a tag exists for a subscriber
	 *
	 * @param array $subscriber Subscriber data from MailPoet API
	 * @param int $tag_id Tag ID to check
	 * @return bool True if tag exists, false otherwise
	 */
	public function check_tag_exists( $subscriber, $tag_id ) {
		if ( ! isset( $subscriber['tags'] ) || ! is_array( $subscriber['tags'] ) ) {
			// subscriber doesn't have tags or tags are not an array
			return false;
		}

		foreach ( $subscriber['tags'] as $tag ) {
			$actual_tag_id = isset( $tag['tag_id'] ) ? (int) $tag['tag_id'] : (int) $tag['id'];
			if ( $actual_tag_id === (int) $tag_id ) {
				// tag found
				return true;
			}
		}

		// subscriber tag not found
		return false;
	}
}
