<?php

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Automator_WP_Error;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

// Add other Automator functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helper-functions' . DIRECTORY_SEPARATOR . 'automator-helper-functions.php';

/**
 * @param $item_id
 *
 * @return int
 */
function automator_get_recipe_id( $item_id ) {
	return Automator()->get->maybe_get_recipe_id( $item_id );
}

/**
 * @param $directory
 *
 * @return array|false
 * @since 3.0
 * @throws Automator_Exception
 *
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_integration( $directory ) {
	return Set_Up_Automator::read_directory( $directory );
}

/**
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_integration_exists( $integration ) {
	$integration = strtolower( $integration );
	if ( ! isset( Set_Up_Automator::$all_integrations[ $integration ] ) ) {
		return false;
	}

	return true;
}

/**
 * @param $name
 *
 * @return string
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_get_integration_by_name( $name ) {
	$integration      = strtolower(
		str_replace(
			array( ' ', '_' ),
			'-',
			$name
		)
	);
	$integration_keys = array_keys( Set_Up_Automator::$all_integrations );
	if ( in_array( $integration, $integration_keys, true ) ) {
		return $integration;
	}
	$return = '';
	switch ( $integration ) {
		case 'wordpress': //phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
			$return = 'wp';
			break;
		case 'easy-digital-downloads':
			$return = 'edd';
			break;
		case 'automator':
			$return = 'uncanny-automator';
			break;
	}

	return apply_filters( 'automator_get_integration_key', $return, $integration, $name );
}

/**
 * @param $path
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_trigger( $path, $integration ) {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return false;
	}

	Set_Up_Automator::$all_integrations[ $integration ]['triggers'][] = $path;

	return true;
}

/**
 * @param $path
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_action( $path, $integration ) {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return false;
	}

	Set_Up_Automator::$all_integrations[ $integration ]['actions'][] = $path;

	return true;
}

/**
 * @param $integration_code
 * @param $directory
 *
 * @return bool
 *
 * @since 3.0
 * @throws Automator_Exception
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_add_integration_directory( $integration_code, $directory ) {
	$int_directory = automator_add_integration( $directory );
	if ( ! isset( $int_directory['main'] ) ) {
		return false;
	}
	Set_Up_Automator::$auto_loaded_directories[]             = dirname( $int_directory['main'] );
	Set_Up_Automator::$all_integrations[ $integration_code ] = $int_directory;

	return true;
}

/**
 * @param $icon_path
 *
 * @return string
 */
function automator_add_integration_icon( $icon_path, $plugin_path = AUTOMATOR_BASE_FILE ) {
	return Utilities::automator_get_integration_icon( $icon_path, $plugin_path );
}

/**
 * @param $type
 * @param null $variable
 * @param $flags
 *
 * @return mixed
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_filter_input( $variable = null, $type = INPUT_GET, $flags = FILTER_SANITIZE_STRING ) {
	/*
	 * View input types: https://www.php.net/manual/en/function.filter-input.php
	 * View flags at: https://www.php.net/manual/en/filter.filters.sanitize.php
	 */
	return filter_input( $type, $variable, $flags );
}


/**
 * @param $type
 * @param null $variable
 * @param $flags
 *
 * @return mixed
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_filter_has_var( $variable = null, $type = INPUT_GET ) {
	return filter_has_var( $type, $variable );
}

/**
 * @param $type
 * @param null $variable
 * @param $flags
 *
 * @return mixed
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_filter_input_array( $variable = null, $type = INPUT_GET, $flags = array() ) {
	if ( empty( $flags ) ) {
		$flags = array(
			'filter' => FILTER_VALIDATE_INT,
			'flags'  => FILTER_REQUIRE_ARRAY,
		);
	}
	/*
	 * View input types: https://www.php.net/manual/en/function.filter-input.php
	 * View flags at: https://www.php.net/manual/en/filter.filters.sanitize.php
	 */
	$args = array( $variable => $flags );
	$val  = filter_input_array( $type, $args );

	return isset( $val[ $variable ] ) ? $val[ $variable ] : array();
}

/**
 * @param mixed $message
 * @param $code
 *
 * @since 3.0
 * @throws Automator_Exception
 *
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_exception( $message, $code = 999 ) {
	throw new Automator_Exception( $message, $code );
}

/**
 * @param $message
 * @param mixed $error_code
 * @param mixed $data
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_wp_error( $message, $error_code = 'something_wrong', $data = '' ) {
	Automator()->error->add_error( $error_code, $message, $data );
}


/**
 * @param $error_code
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_wp_error_messages( $error_code = '' ) {
	Automator()->error->get_messages( $error_code );
}

/**
 * @param string|mixed $error_code
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_wp_error_get_message( $error_code = 'something_wrong' ) {
	Automator()->error->get_message( $error_code );

}

/**
 * @param mixed $thing
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function is_automator_error( $thing ) {
	return $thing instanceof Automator_WP_Error;
}

/**
 * @param $type
 *
 * @return bool
 */
function automator_db_view_exists( $type = 'recipe' ) {
	return \Uncanny_Automator\Automator_DB::is_view_exists( $type );
}

/**
 * global function to create and log custom messages.
 *
 * @param mixed $message
 * @param mixed $subject
 * @param false $force_log
 * @param mixed $log_file
 * @param false $backtrace
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_log( $message = '', $subject = '', $force_log = false, $log_file = 'debug', $backtrace = false ) {
	Utilities::log( $message, $subject, $force_log, $log_file, $backtrace );
}

/**
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_recipe_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->recipe->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_trigger_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->trigger->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_action_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->action->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_closure_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->closure->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * Add UTM parameters to a given URL
 *
 * @param String $url URL
 * @param array $medium The value for utm_medium
 * @param array $content The value for utm_content
 *
 * @return String           URL with the UTM parameters
 */
function automator_utm_parameters( $url, $medium = '', $content = '' ) {
	// utm_source=plugin-id
	// utm_medium=section-id
	// utm_content=element-id+unique-id

	$default_utm_parameters = array(
		'source' => 'uncanny_automator_pro',
	);

	try {
		// Parse the URL
		$url_parts = wp_parse_url( $url );

		// If URL doesn't have a query string.
		if ( isset( $url_parts['query'] ) ) {
			// Avoid 'Undefined index: query'
			parse_str( $url_parts['query'], $params );
		} else {
			$params = array();
		}

		// Add default parameters
		foreach ( $default_utm_parameters as $default_utm_parameter_key => $default_utm_parameter_value ) {
			$params[ 'utm_' . $default_utm_parameter_key ] = $default_utm_parameter_value;
		}

		// Add custom parameters
		if ( ! empty( $medium ) ) {
			$params['utm_medium'] = $medium;
		}

		if ( ! empty( $content ) ) {
			$params['utm_content'] = $content;
		}

		// Encode parameters
		$url_parts['query'] = http_build_query( $params );

		if ( function_exists( 'http_build_url' ) ) {
			// If the user has pecl_http
			$url = http_build_url( $url_parts );
		} else {
			$url_parts['path'] = ! empty( $url_parts['path'] ) ? $url_parts['path'] : '';

			$url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . '?' . $url_parts['query'];
		}
	} catch ( \Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	}

	return $url;
}

/**
 * Check if automator pro is active or not.
 *
 * @return boolean True if automator pro is active. Otherwise false.
 */
function is_automator_pro_active() {

	// Check if automator pro is in list of active plugins.
	if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
		return true;
	}

	return false;
}
