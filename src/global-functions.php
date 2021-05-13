<?php

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Automator_WP_Error;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

// Add other Automator functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helper-functions' . DIRECTORY_SEPARATOR . 'automator-helper-functions.php';

/**
 * @param $directory
 *
 * @return array|false
 * @throws Exception
 */
function automator_add_integration( $directory ) {
	return Set_Up_Automator::read_directory( $directory );
}

/**
 * @param string $integration
 *
 * @return bool
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
 */
function automator_get_integration_by_name( string $name ): string {
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
 * @param array $integrations
 * @param string $path
 * @param string $integration
 *
 * @return array
 */
function automator_add_trigger( array $integrations, string $path, string $integration ): array {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return $integrations;
	}

	$integrations[ $integration ]['triggers'][] = $path;

	return $integrations;
}

/**
 * @param array $integrations
 * @param string $path
 * @param string $integration
 *
 * @return array
 */
function automator_add_action( array $integrations, string $path, string $integration ): array {
	$integration = strtolower( $integration );
	if ( ! automator_integration_exists( $integration ) ) {
		return $integrations;
	}

	$integrations[ $integration ]['actions'][] = $path;

	return $integrations;
}

/**
 * @param string $integration_code
 * @param array $directory
 * @param array $directories
 *
 * @return array
 */
function automator_add_integration_directory( string $integration_code, array $directory, array $directories ): array {
	$directories[ $integration_code ] = $directory;

	return $directories;
}

/**
 * @param string $type
 * @param null $variable
 * @param string $flags
 *
 * @return mixed
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
 * @throws Automator_Exception
 */
function automator_exception( $message, int $code = 999 ) {
	throw new Automator_Exception( $message, $code );
}

/**
 * @param $message
 * @param mixed $error_code
 * @param mixed $data
 */
function automator_wp_error( $message, $error_code = 'something_wrong', $data = '' ) {
	Automator()->error->add_error( $error_code, $message, $data );
}


/**
 * @param string $error_code
 */
function automator_wp_error_messages( $error_code = '' ) {
	Automator()->error->get_messages( $error_code );
}

/**
 * @param string|mixed $error_code
 */
function automator_wp_error_get_message( $error_code = 'something_wrong' ) {
	Automator()->error->get_message( $error_code );

}

/**
 * @param mixed $thing
 *
 * @return bool
 */
function is_automator_error( $thing ): bool {
	return $thing instanceof Automator_WP_Error;
}

/**
 * global function to create and log custom messages.
 *
 * @param mixed $message
 * @param mixed $subject
 * @param false bool $force_log
 * @param mixed $log_file
 * @param false bool $backtrace
 */
function automator_log( $message = '', $subject = '', $force_log = false, $log_file = 'debug', $backtrace = false ) {
	Utilities::log( $message, $subject, $force_log, $log_file, $backtrace );
}
