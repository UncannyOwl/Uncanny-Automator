<?php

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Automator_WP_Error;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

// Add other Automator functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helper-functions' . DIRECTORY_SEPARATOR . 'automator-helper-functions.php';

/**
 * @param int $item_id
 *
 * @return int
 */
function automator_get_recipe_id( int $item_id ) {
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
 * @param string $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_integration_exists( string $integration ) {
	$integration = strtolower( $integration );
	if ( ! isset( Set_Up_Automator::$all_integrations[ $integration ] ) ) {
		return false;
	}

	return true;
}

/**
 * @param string $name
 *
 * @return string
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_get_integration_by_name( string $name ) {
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
 * @param string $path
 * @param string $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_trigger( string $path, string $integration ) {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return false;
	}

	Set_Up_Automator::$all_integrations[ $integration ]['triggers'][] = $path;

	return true;
}

/**
 * @param string $path
 * @param string $integration
 *
 * @return bool
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_add_action( string $path, string $integration ) {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return false;
	}

	Set_Up_Automator::$all_integrations[ $integration ]['actions'][] = $path;

	return true;
}

/**
 * @param string $integration_code
 * @param string $directory
 *
 * @return bool
 *
 * @since 3.0
 * @throws Automator_Exception
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_add_integration_directory( string $integration_code, string $directory ) {
	$int_directory = automator_add_integration( $directory );
	if ( ! isset( $int_directory['main'] ) ) {
		return false;
	}
	Set_Up_Automator::$auto_loaded_directories[]             = dirname( $int_directory['main'] );
	Set_Up_Automator::$all_integrations[ $integration_code ] = $int_directory;

	return true;
}

/**
 * @param string $icon_path
 *
 * @return string
 */
function automator_add_integration_icon( string $icon_path, string $plugin_path = AUTOMATOR_BASE_FILE ) {
	return Utilities::automator_get_integration_icon( $icon_path, $plugin_path );
}

/**
 * @param string $type
 * @param null $variable
 * @param string $flags
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
 * @param string $type
 * @param null $variable
 * @param string $flags
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
 * @param string $type
 * @param null $variable
 * @param string $flags
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
 * @param int $code
 *
 * @since 3.0
 * @throws Automator_Exception
 *
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_exception( $message, int $code = 999 ) {
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
 * @param string $error_code
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
 * @param string $type
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
 * @param false bool $force_log
 * @param mixed $log_file
 * @param false bool $backtrace
 *
 * @since 3.0
 * @package Uncanny_Automator
 * @version 3.0
 */
function automator_log( $message = '', $subject = '', $force_log = false, $log_file = 'debug', $backtrace = false ) {
	Utilities::log( $message, $subject, $force_log, $log_file, $backtrace );
}
