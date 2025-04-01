<?php
namespace Uncanny_Automator;

/**
 * Class Thrive_Apprentice_Helpers
 *
 * @package Uncanny_Automator
 */
class Thrive_Apprentice_Helpers {

	/**
	 * Course ID
	 *
	 * @var int
	 */
	private $course_id = 0;

	/**
	 * Is any needed
	 *
	 * @var bool
	 */
	private $is_any_needed = true;

	/**
	 * Constructor
	 *
	 * @param boolean $hooks_loaded
	 */
	public function __construct( $hooks_loaded = true ) {

		if ( $hooks_loaded ) {

			add_action( 'wp_ajax_automator_thrive_apprentice_content_type_handler', array( $this, 'get_dropdown_options_ajax_handler_content_type' ) );

			add_action( 'wp_ajax_automator_thrive_apprentice_content_handler', array( $this, 'get_dropdown_options_ajax_handler_content' ) );

			// Legacy Pro handler
			add_action( 'wp_ajax_automator_thrive_apprentice_lessons_handler', array( $this, 'get_dropdown_options_ajax_handler_lessons' ) );

			// (modern) ajax handler
			add_action( 'wp_ajax_automator_thrive_apprentice_updated_lessons_handler', array( $this, 'get_updated_dropdown_options_ajax_handler_lessons' ) );

			// Legacy Pro handler
			add_action( 'wp_ajax_automator_thrive_apprentice_modules_handler', array( $this, 'get_dropdown_options_ajax_handler_modules' ) );

			// (modern) ajax handler
			add_action( 'wp_ajax_automator_thrive_apprentice_updated_modules_handler', array( $this, 'get_updated_dropdown_options_ajax_handler_modules' ) );

			add_action( 'wp_ajax_automator_thrive_apprentice_assessments_handler', array( $this, 'get_dropdown_options_ajax_handler_assessments' ) );

		}
	}

	/**
	 * Retrieves course data by ID.
	 *
	 * @param int $course_id The course ID.
	 * @param bool $force_refresh Whether to force a refresh of the course data.
	 * @return array|WP_Error The course data or a WP_Error if the request fails.
	 */
	public function get_course_by_id( $course_id, $force_refresh = false ) {
		$transient = get_transient( 'automator_thrive_apprentice_course_' . $course_id );
		if ( false !== $transient && false === $force_refresh ) {
			return $transient;
		}
		if ( $course_id < 0 ) {
			return new \WP_Error( 'invalid_course', 'Invalid course ID' );
		}

		// Set up the request
		$url  = get_rest_url( get_current_blog_id(), 'tva-public/v1/course/' . $course_id );
		$args = array(
			'timeout'   => 10,
			'sslverify' => true,
			'blocking'  => true,
			'headers'   => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		);

		$response = wp_remote_get( $url, $args );

		set_transient( 'automator_thrive_apprentice_course_' . $course_id, $response, 10 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Retrieves all dropdown courses option values.
	 *
	 * @param bool $has_option_any Whether dropdown options has `any` or not.
	 *
	 * @return array The list of courses.
	 */
	public function get_dropdown_options_courses( $has_option_any = false, $latest = false ) {

		$tva_courses = (array) $this->get_courses( array( 'published' => true ) );
		$courses     = array();

		if ( ! empty( $tva_courses ) && ! is_wp_error( $tva_courses ) ) {
			if ( $has_option_any ) {
				if ( $latest ) {
					$courses[] = array(
						'value' => -1,
						'text'  => esc_html_x( 'Any course', 'Thrive Apprentice', 'uncanny-automator' ),
					);
				} else {
						$courses[-1] = esc_html_x( 'Any course', 'Thrive Apprentice', 'uncanny-automator' );
				}
			}

			foreach ( $tva_courses as $course ) {

				if ( isset( $course->term_id ) && isset( $course->name ) ) {
					if ( $latest ) {
						$courses[] = array(
							'value' => $course->term_id,
							'text'  => $course->name,
						);
					} else {
						$courses[ $course->term_id ] = $course->name;
					}
				}
			}
		}

		return $courses;
	}

	/**
	 * Retrieves Courses from TA.
	 *
	 * @return array The list of courses.
	 */
	private function get_courses( $filters = array() ) {
		if ( ! function_exists( 'tva_get_courses' ) ) {
			return array();
		}

		return tva_get_courses( $filters );
	}

	/**
	 * Retrieves the dropdown options for content types based on course.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_content_type() {
		Automator()->utilities->ajax_auth_check();

		$values = filter_input_array(
			INPUT_POST,
			array(
				'values'  => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'item_id' => array(
					'filter' => FILTER_SANITIZE_NUMBER_INT,
				),
			)
		);

		$course_id   = isset( $values['values']['COURSE'] ) ? absint( $values['values']['COURSE'] ) : 0;
		$item_id     = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;
		$include_any = 'uo-action' !== get_post_type( $item_id );

		$options = array();

		if ( $include_any ) {
			$options = array(
				array(
					'value' => -1,
					'text'  => esc_html_x( 'Any content type', 'Content type selection', 'uncanny-automator' ),
				),
			);
		}

		if ( intval( '-1' ) === intval( $course_id ) ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options,
				)
			);
			die();
		}

		$course_data = $this->get_course_by_id( $course_id );
		$course_data = json_decode( wp_remote_retrieve_body( $course_data ) );

		if ( is_wp_error( $course_data ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => $course_data->get_error_message(),
					'options' => $options,
				)
			);
			die();
		}

		$options = array_merge( $options, $this->get_content_types( $course_data->structure ) );

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	/**
	 * Retrieves the dropdown options for content based on content type.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_content() {
		try {
			Automator()->utilities->ajax_auth_check();

			$values = filter_input_array(
				INPUT_POST,
				array(
					'values'  => array(
						'filter' => FILTER_SANITIZE_STRING,
						'flags'  => FILTER_REQUIRE_ARRAY,
					),
					'item_id' => array(
						'filter' => FILTER_SANITIZE_NUMBER_INT,
					),
				)
			);

			$content_type = isset( $values['values']['CONTENT_TYPE'] ) ? sanitize_text_field( $values['values']['CONTENT_TYPE'] ) : '';
			$content_type = str_replace( 'tva_', '', $content_type );
			$course_id    = isset( $values['values']['COURSE'] ) ? $values['values']['COURSE'] : 0;
			$item_id      = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;

			if ( intval( '-1' ) === intval( $content_type ) ) {
				echo wp_json_encode(
					array(
						'success' => true,
						'options' => array(
							array(
								'value' => -1,
								'text'  => esc_html_x( 'Any content', 'Thrive Apprentice', 'uncanny-automator' ),
							),
						),
					)
				);
				die();
			}

			// Handle special cases for course_id
			if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
				echo wp_json_encode(
					array(
						'success' => true,
						'options' => array(
							array(
								'value' => -1,
								'text'  => esc_html_x( 'Any content', 'Thrive Apprentice', 'uncanny-automator' ),
							),
						),
					)
				);
				die();
			}

			// Only proceed if we have a valid positive course ID
			$course_id = absint( $course_id );
			if ( $course_id <= 0 ) {
				echo wp_json_encode(
					array(
						'success' => true,
						'options' => array(),
					)
				);
				die();
			}

			// For uo-action type, we don't want to include the "Any" option
			$include_any = 'uo-action' !== get_post_type( $item_id );
			$options     = $this->get_dropdown_options_by_type( $course_id, 'tva_' . $content_type, $include_any );

			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options,
				)
			);
			die();

		} catch ( \Exception $e ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
					'options' => array(),
				)
			);
			die();
		}
	}

	/**
	 * Retrieves the dropdown options for lessons.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_lessons() {
		Automator()->utilities->ajax_auth_check();

		$course_id = 0 !== $this->course_id ? $this->course_id : filter_input( INPUT_POST, 'value' );

		$options = array();

		if ( $this->is_any_needed ) {
			$options = array(
				array(
					'value' => -1,
					'text'  => esc_html_x( 'Any lesson', 'Thrive Apprentice lesson selection', 'uncanny-automator' ),
				),
			);
		}

		// Handle special cases: -1 or 'automator_custom_value'
		if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
			wp_send_json( $options );
			exit;
		}

		// Only proceed if we have a valid positive course ID
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			// For uo-action type, we don't want to include the "Any" option
			$include_any = 'uo-action' !== get_post_type( $item_id );
			$options     = $this->get_dropdown_options_by_type( $course_id, 'tva_lesson', $include_any );
		}

		wp_send_json( $options );
		exit;
	}

	/**
	 * Retrieves the dropdown options for lessons with the new ajax handler.
	 *
	 * @return void
	 */
	public function get_updated_dropdown_options_ajax_handler_lessons() {
		Automator()->utilities->ajax_auth_check();

		$values = filter_input_array(
			INPUT_POST,
			array(
				'values'  => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'item_id' => array(
					'filter' => FILTER_SANITIZE_NUMBER_INT,
				),
			)
		);

		$course_id = isset( $values['values']['COURSE'] ) ? $values['values']['COURSE'] : 0;
		$item_id   = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;

		$options = array();

		// For uo-action type, we don't want to include the "Any" option
		$include_any = 'uo-action' !== get_post_type( $item_id );

		if ( $include_any ) {
			$options = array(
				array(
					'value' => -1,
					'text'  => esc_html_x( 'Any lesson', 'Thrive Apprentice lesson selection', 'uncanny-automator' ),
				),
			);
		}

		// Handle special cases: -1 or 'automator_custom_value'
		if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options,
				)
			);
			die();
		}

		// Only proceed if we have a valid positive course ID
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			// For specific course, get lessons from that course
			$options = $this->get_dropdown_options_by_type( $course_id, 'tva_lesson', $include_any );
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	/**
	 * Retrieves the dropdown options for modules.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_modules() {
		try {
			Automator()->utilities->ajax_auth_check();

			$course_id = 0 !== $this->course_id ? $this->course_id : filter_input( INPUT_POST, 'value' );

			$options = array();

			if ( $this->is_any_needed ) {
				$options = array(
					array(
						'value' => -1,
						'text'  => esc_html_x( 'Any module', 'Module selection', 'uncanny-automator' ),
					),
				);
			}

			// Handle special cases: -1 or 'automator_custom_value'
			if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
				wp_send_json( $options );
				return;
			}

			// Only proceed if we have a valid positive course ID
			$course_id = absint( $course_id );
			if ( $course_id > 0 ) {
				if ( ! function_exists( 'tva_get_course_by_id' ) ) {
					wp_send_json( $options );
					return;
				}

				$course_data = tva_get_course_by_id( $course_id );

				if ( ! empty( $course_data ) && ! empty( $course_data->modules ) ) {
					foreach ( $course_data->modules as $module ) {
						if ( 'publish' === $module->post_status ) {
							$options[] = array(
								'value' => $module->ID,
								'text'  => $module->post_title,
							);
						}
					}
				}
			}

			wp_send_json( $options );

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					array(
						'value' => -1,
						'text'  => sprintf(
							// translators: %s is replaced with the error message
							esc_html_x( 'Error loading modules: %s', 'Error message', 'uncanny-automator' ),
							$e->getMessage()
						),
					),
				)
			);
		}
	}

	/**
	 * Retrieves the dropdown options for modules with the new ajax handler.
	 *
	 * @return void
	 */
	public function get_updated_dropdown_options_ajax_handler_modules() {
		try {
			Automator()->utilities->ajax_auth_check();

			$values = filter_input_array(
				INPUT_POST,
				array(
					'values'  => array(
						'filter' => FILTER_SANITIZE_STRING,
						'flags'  => FILTER_REQUIRE_ARRAY,
					),
					'item_id' => array(
						'filter' => FILTER_SANITIZE_NUMBER_INT,
					),
				)
			);

			$course_id = isset( $values['values']['COURSE'] ) ? $values['values']['COURSE'] : 0;
			$item_id   = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;

			$options = array();

			// For uo-action type, we don't want to include the "Any" option
			$include_any = 'uo-action' !== get_post_type( $item_id );

			if ( $include_any ) {
				$options = array(
					array(
						'value' => -1,
						'text'  => esc_html_x( 'Any module', 'Module selection', 'uncanny-automator' ),
					),
				);
			}

			// Handle special cases: -1 or 'automator_custom_value'
			if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
				echo wp_json_encode(
					array(
						'success' => true,
						'options' => $options,
					)
				);
				die();
			}

			// Only proceed if we have a valid positive course ID
			$course_id = absint( $course_id );
			if ( $course_id > 0 ) {
				if ( ! function_exists( 'tva_get_course_by_id' ) ) {
					echo wp_json_encode(
						array(
							'success' => true,
							'options' => $options,
						)
					);
					die();
				}

				$course_data = tva_get_course_by_id( $course_id );

				if ( ! empty( $course_data ) && ! empty( $course_data->modules ) ) {
					foreach ( $course_data->modules as $module ) {
						if ( 'publish' === $module->post_status ) {
							$options[] = array(
								'value' => $module->ID,
								'text'  => $module->post_title,
							);
						}
					}
				}
			}

			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options,
				)
			);
			die();

		} catch ( Exception $e ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
					'options' => array(),
				)
			);
			die();
		}
	}

	/**
	 * Retrieves the dropdown options for assessments.
	 *
	 * @return void
	 */
	public function get_dropdown_options_ajax_handler_assessments() {
		Automator()->utilities->ajax_auth_check();

		$values = filter_input_array(
			INPUT_POST,
			array(
				'values'  => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'item_id' => array(
					'filter' => FILTER_SANITIZE_NUMBER_INT,
				),
			)
		);

		$course_id = isset( $values['values']['COURSE'] ) ? $values['values']['COURSE'] : 0;
		$item_id   = isset( $values['item_id'] ) ? absint( $values['item_id'] ) : 0;

		$options = array();

		$include_any = 'uo-action' !== get_post_type( $item_id );

		if ( $include_any ) {
			$options = array(
				array(
					'value' => -1,
					'text'  => esc_html_x( 'Any assessment', 'Assessment selection', 'uncanny-automator' ),
				),
			);
		}

		// Return default "Any assessment" if no course is selected
		if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'options' => $options,
				)
			);
			die();
		}

		// Set up the request
		$response = $this->get_course_by_id( $course_id );

		// Check for valid response
		if ( is_wp_error( $response ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => $response->get_error_message(),
					'options' => $options,
				)
			);
			die();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => 'Invalid response code: ' . $response_code,
					'options' => $options,
				)
			);
			die();
		}

		$course_data = json_decode( wp_remote_retrieve_body( $response ) );

		// Validate course data and structure
		if ( empty( $course_data ) || ! isset( $course_data->structure ) || ! is_array( $course_data->structure ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'error'   => 'Invalid course data structure',
					'options' => $options,
				)
			);
			die();
		}

		// Loop through the structure array and find assessments
		$assessments = $this->find_assessments_in_structure( $course_data->structure );

		$options = array_merge( $options, $assessments );

		// Cache the results for 12 hours
		$cache_key = 'automator_thrive_assessments_' . $course_id;
		set_transient( $cache_key, $options, 1 * HOUR_IN_SECONDS );

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	/**
	 * Recursively finds all assessments in the course structure
	 *
	 * @param array|string $structure The course structure array or empty string
	 * @return array Array of assessment data with value (ID) and text (title)
	 */
	private function find_assessments_in_structure( $structure ) {
		$assessments = array();

		// If structure is empty or not an array, return empty array
		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return $assessments;
		}

		// Loop through each item in the structure
		foreach ( $structure as $item ) {
			// Skip if item is not an object
			if ( ! is_object( $item ) ) {
				continue;
			}

			// Check if current item is an assessment
			if ( 'tva_assessment' === $item->post_type && isset( $item->ID ) && isset( $item->post_title ) ) {
				$assessments[] = array(
					'value' => absint( $item->ID ),
					'text'  => sanitize_text_field( $item->post_title ),
				);
			}

			// If item has nested structure and it's not an empty string, recursively search it
			if ( isset( $item->structure ) && ! empty( $item->structure ) && is_array( $item->structure ) ) {
				$nested_assessments = $this->find_assessments_in_structure( $item->structure );
				if ( ! empty( $nested_assessments ) ) {
					$assessments = array_merge( $assessments, $nested_assessments );
				}
			}
		}

		return $assessments;
	}

	/**
	 * Retrieves all course modules.
	 *
	 * @param \WP_term $course The course wp term object.
	 *
	 * @return array The modules.
	 */
	private function get_course_modules( \WP_Term $course ) {

		if ( class_exists( '\TVA_Manager' ) && method_exists( '\TVA_Manager', 'get_course_lessons' ) ) {

			return \TVA_Manager::get_course_modules( $course );

		}

		return array();
	}

	/**
	 * Get all products.
	 *
	 * @return array The list of all products.
	 */
	public function get_products() {

		$products = get_terms(
			array(
				'taxonomy'   => 'tva_product',
				'hide_empty' => false,
			)
		);

		$options_dropdown = array();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				if ( $product instanceof \WP_Term ) {
					$options_dropdown[ $product->term_id ] = $product->name;
				}
			}
		}

		return $options_dropdown;
	}

	/**
	 * Issue certificate for a course to a user
	 *
	 * @param int $course_id The course ID
	 * @param int $user_id   The user ID
	 * @return array|WP_Error Array of certificate data on success, WP_Error on failure
	 */
	public function issue_certificate( $course_id, $user_id ) {
		try {
			// Validate inputs
			$course_id = absint( $course_id );
			$user_id   = absint( $user_id );

			if ( empty( $course_id ) || empty( $user_id ) ) {
				return new \WP_Error( 'invalid_input', 'Invalid course ID or user ID' );
			}

			$course   = new \TVA_Course_V2( $course_id );
			$customer = new \TVA_Customer( $user_id );
			$user     = $customer->get_user();

			if ( ! $course->has_certificate() ) {
				throw new \WP_Error( 'no_certificate', 'Course does not have a certificate configured' );
			}

			$certificate = $course->get_certificate();
			$response    = $certificate->download( $customer );

			if ( ! empty( $response['error'] ) ) {
				throw new \WP_Error( 'certificate_error', 'Error generating certificate. ' . $response['error'] );
			}

			// Get course data
			$course_data = $this->get_course_by_id( $course_id );

			// Return standardized course data with certificate info
			return array(
				'certificate_number' => isset( $certificate->number ) ? $certificate->number : '',
				'certificate_url'    => isset( $response['url'] ) ? $response['url'] : '',
				'course_data'        => ! empty( $course_data ) ? json_decode( wp_remote_retrieve_body( $course_data ) ) : array(),
			);

		} catch ( \Exception $e ) {
			return new \WP_Error( 'certificate_error', $e->getMessage() );
		}
	}

	/**
	 * Manage product access
	 *
	 * @param int $user_id User ID
	 * @param int $product_id Product ID
	 * @param string $action Action type: 'add' or 'remove'
	 * @param string|null $reason Action reason (optional)
	 * @return bool Operation success
	 */
	public function manage_product_access( $user_id, $product_id, $action, $reason = null ) {
		// Validate parameters
		if ( empty( $user_id ) || empty( $product_id ) || ! in_array( $action, array( 'add', 'remove' ), true ) ) {
			return false;
		}

		// Get product object
		$product = \TVA\Product::get_from_set( \TVD\Content_Sets\Set::get_for_object( null, $product_id ) );

		if ( ! $product ) {
			return false;
		}

		try {
			if ( 'add' === $action ) {
				// Add access permission
				\TVA_Customer::enrol_user_to_product( $user_id, array( $product_id ) );

				// Record access history
				$course_ids = $product->get_published_courses( true );
				$data       = array();

				foreach ( $course_ids as $course_id ) {
					$data[] = array(
						'user_id'    => (int) $user_id,
						'product_id' => (int) $product_id,
						'course_id'  => (int) $course_id,
						'status'     => \TVA\Access\Providers\Base::STATUS_ACCESS_ADDED,
						'source'     => 'manual',
						'reason'     => $reason,
					);
				}

				\TVA\Access\History_Table::get_instance()->insert( $data );

			} else {
				// Remove access permission
				\TVA_Customer::remove_user_from_product( $user_id, $product_id );

				// Record access history
				\TVA\Access\Main::remove_order_access( $product, $user_id, $reason );
			}

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get all content items from course structure
	 *
	 * @param array $structure The course structure array
	 * @return array Array of content items with their post types and titles
	 */
	public function get_content_types( $structure ) {
		$content_items = array();

		$allowed_post_types = array( 'tva_lesson', 'tva_module', 'tva_assessment' );
		$allowed_post_types = apply_filters( 'automator_thrive_apprentice_allowed_post_types', $allowed_post_types );

		// If structure is empty or not an array, return empty array
		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return $content_items;
		}

		// Loop through each item in the structure
		foreach ( $structure as $item ) {
			// Skip if item is not an object
			if ( ! is_object( $item ) ) {
				continue;
			}

			// Check if current item has required properties
			if ( isset( $item->post_type ) && isset( $item->post_title ) && in_array( $item->post_type, $allowed_post_types, true ) ) {
				$content_items[] = array(
					'value' => $item->post_type,
					'text'  => ucfirst( str_replace( 'tva_', '', $item->post_type ) ),
				);
			}

			// If item has nested structure and it's not empty, recursively search it
			if ( isset( $item->structure ) && ! empty( $item->structure ) && is_array( $item->structure ) ) {
				$nested_items = $this->get_content_types( $item->structure );
				if ( ! empty( $nested_items ) ) {
					$content_items = array_merge( $content_items, $nested_items );
				}
			}
		}

		// Remove duplicates based on value
		$content_items = array_unique( $content_items, SORT_REGULAR );

		return $content_items;
	}

	/**
	 * Get content items by post type from course structure
	 *
	 * @param array  $structure The course structure array
	 * @param string $post_type The post type to search for (e.g., 'tva_lesson', 'tva_module')
	 * @param bool   $include_any Whether to include "Any" option
	 * @return array Array of content items with their IDs and titles
	 */
	private function get_content_items_by_type( $structure, $post_type, $include_any = true ) {
		$items = array();

		// If structure is empty or not an array, return empty array
		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return $items;
		}

		// Add "Any" option if needed
		if ( $include_any ) {
			$items[] = array(
				'value' => -1,
				'text'  => sprintf(
					// translators: %s is replaced with the content type name
					esc_html_x( 'Any %s', 'Thrive Apprentice', 'uncanny-automator' ),
					str_replace( 'tva_', '', $post_type )
				),
			);
		}

		// Loop through each item in the structure
		foreach ( $structure as $item ) {
			// Skip if item is not an object
			if ( ! is_object( $item ) ) {
				continue;
			}

			// Check if current item matches the post type
			if ( isset( $item->post_type ) && $post_type === $item->post_type && isset( $item->post_status ) && 'publish' === $item->post_status ) {
				$items[] = array(
					'value' => absint( $item->ID ),
					'text'  => $item->post_title,
				);
			}

			// If item has nested structure and it's not empty, recursively search it
			if ( isset( $item->structure ) && ! empty( $item->structure ) && is_array( $item->structure ) ) {
				$nested_items = $this->get_content_items_by_type( $item->structure, $post_type, false );
				if ( ! empty( $nested_items ) ) {
					$items = array_merge( $items, $nested_items );
				}
			}
		}

		return $items;
	}

	/**
	 * Get dropdown options for a specific content type
	 *
	 * @param int    $course_id The course ID
	 * @param string $post_type The post type to get options for
	 * @return array Array of dropdown options
	 */
	public function get_dropdown_options_by_type( $course_id, $post_type, $include_any = true ) {
		// Handle special cases: -1 or 'automator_custom_value'
		if ( intval( '-1' ) === intval( $course_id ) || 'automator_custom_value' === $course_id ) {
			return array(
				array(
					'value' => -1,
					'text'  => sprintf(
						// translators: %s is replaced with the content type name
						esc_html_x( 'Any %s', 'Content type name', 'uncanny-automator' ),
						str_replace( 'tva_', '', $post_type )
					),
				),
			);
		}

		// Only proceed if we have a valid positive course ID
		$course_id = absint( $course_id );
		if ( $course_id <= 0 ) {
			return array();
		}

		if ( ! function_exists( 'tva_get_course_by_id' ) ) {
			return array(
				array(
					'value' => -1,
					'text'  => sprintf(
						// translators: %s is replaced with the content type name
						esc_html_x( 'Error loading %s', 'Error message', 'uncanny-automator' ),
						str_replace( 'tva_', '', $post_type )
					),
				),
			);
		}

		$course_data = $this->get_course_by_id( $course_id );
		if ( is_wp_error( $course_data ) ) {
			return array(
				array(
					'value' => -1,
					'text'  => sprintf(
						// translators: %s is replaced with the content type name
						esc_html_x( 'Error loading %s', 'Error message', 'uncanny-automator' ),
						str_replace( 'tva_', '', $post_type )
					),
				),
			);
		}

		$course_data = json_decode( wp_remote_retrieve_body( $course_data ) );
		if ( empty( $course_data ) || empty( $course_data->structure ) ) {
			return array(
				array(
					'value' => -1,
					'text'  => sprintf(
						// translators: %s is replaced with the content type name
						esc_html_x( 'No %s found', 'Content type name', 'uncanny-automator' ),
						str_replace( 'tva_', '', $post_type )
					),
				),
			);
		}

		return $this->get_content_items_by_type( $course_data->structure, $post_type, $include_any );
	}


	/**
	 * Returns all relevant tokens of the field `Course`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the course field.
	 * @deprecated 6.4.0
	 */
	public function get_relevant_tokens_courses() {

		return array(
			'COURSE_ID'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			'COURSE_URL'     => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
			'COURSE_AUTHOR'  => esc_html_x( 'Course author', 'Thrive Apprentice', 'uncanny-automator' ),
			'COURSE_SUMMARY' => esc_html_x( 'Course summary', 'Thrive Apprentice', 'uncanny-automator' ),
			'COURSE_TITLE'   => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
		);
	}

	/**
	 * Returns all relevant tokens of the field `Module`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the module field.
	 * @deprecated 6.4.0
	 */
	public function get_relevant_tokens_courses_modules() {

		return array(
			'MODULE_ID'    => esc_html_x( 'Module ID', 'Thrive Apprentice', 'uncanny-automator' ),
			'MODULE_URL'   => esc_html_x( 'Module URL', 'Thrive Apprentice', 'uncanny-automator' ),
			'MODULE_TITLE' => esc_html_x( 'Module title', 'Thrive Apprentice', 'uncanny-automator' ),
		);
	}

	/**
	 * Returns all relevant tokens of the field `Lesson`.
	 *
	 * @return array The list of the relevant tokens that should be attached to the lesson field.
	 * @deprecated 6.4.0
	 */
	public function get_relevant_tokens_courses_lessons() {

		return array(
			'LESSON_ID'    => esc_html_x( 'Lesson ID', 'Thrive Apprentice', 'uncanny-automator' ),
			'LESSON_URL'   => esc_html_x( 'Lesson URL', 'Thrive Apprentice', 'uncanny-automator' ),
			'LESSON_TITLE' => esc_html_x( 'Lesson title', 'Thrive Apprentice', 'uncanny-automator' ),
		);
	}
}
