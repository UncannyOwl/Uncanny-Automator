<?php

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Automator_Options;
use Uncanny_Automator\Automator_WP_Error;
use Uncanny_Automator\DB_Tables;
use Uncanny_Automator\Services\File\Extension_Support;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

// Add other Automator functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helper-functions' . DIRECTORY_SEPARATOR . 'automator-helper-functions.php';

/**
 * Get recipe ID by it's child item
 *
 * @param $item_id
 *
 * @return int
 */
function automator_get_recipe_id( $item_id ) {
	return Automator()->get->maybe_get_recipe_id( $item_id );
}

/**
 * Add an integration
 *
 * @param $directory
 *
 * @return array|false
 * @since 3.0
 * @version 3.0
 * @throws Automator_Exception
 *
 * @package Uncanny_Automator
 */
function automator_add_integration( $directory ) {
	return Set_Up_Automator::read_directory( $directory );
}

/**
 * Check if integration exists
 *
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_integration_exists( $integration ) {
	$integration = strtolower( $integration );
	if ( ! isset( Set_Up_Automator::$all_integrations[ $integration ] ) ) {
		return false;
	}

	return true;
}

/**
 * Get integration by name
 *
 * @param $name
 *
 * @return string
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
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
		case 'wordpress': //phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled,WordPress.WP.CapitalPDangit.MisspelledInText
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
 * Add a trigger
 *
 * @param $path
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
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
 * Add an action
 *
 * @param $path
 * @param $integration
 *
 * @return bool
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
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
 * Add integration directory
 *
 * @param $integration_code
 * @param $directory
 *
 * @return bool
 *
 * @since 3.0
 * @version 3.0
 * @throws Automator_Exception
 * @package Uncanny_Automator
 */
function automator_add_integration_directory( $integration_code, $directory, $plugin_namespace = '' ) {
	$int_directory = automator_add_integration( $directory );
	if ( ! isset( $int_directory['main'] ) ) {
		return false;
	}
	Set_Up_Automator::$auto_loaded_directories[]                            = dirname( $int_directory['main'] );
	Set_Up_Automator::$all_integrations[ $integration_code ]                = $int_directory;
	Set_Up_Automator::$external_integrations_namespace[ $integration_code ] = $plugin_namespace;

	return true;
}

/**
 * Add an integration
 *
 * @param $icon_path
 *
 * @return string
 */
function automator_add_integration_icon( $icon_path, $plugin_path = AUTOMATOR_BASE_FILE ) {
	return Utilities::automator_get_integration_icon( $icon_path, $plugin_path );
}

/**
 * Get the $_POST/$_GET/$_REQUEST variable
 *
 * @param $type
 * @param string $variable Defaults to null.
 * @param $flags
 *
 * @return string
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_filter_input( $variable = null, $type = INPUT_GET, $flags = FILTER_UNSAFE_RAW ) {
	/*
	 * View input types: https://www.php.net/manual/en/function.filter-input.php
	 * View flags at: https://www.php.net/manual/en/filter.filters.sanitize.php
	 */
	return sanitize_text_field( filter_input( $type, $variable, $flags ) );
}


/**
 * Automator filter has var - check if the $_POST/$_GET/$_REQUEST has the variable
 *
 * @param $type
 * @param null $variable
 * @param $flags
 *
 * @return mixed
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_filter_has_var( $variable = null, $type = INPUT_GET ) {
	return filter_has_var( $type, $variable );
}

/**
 * Automator filter input array - get the $_POST/$_GET/$_REQUEST array variable
 *
 * @param $type
 * @param null $variable
 * @param $flags
 *
 * @return mixed
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_filter_input_array( $variable = null, $type = INPUT_GET, $flags = array() ) {
	if ( empty( $flags ) ) {
		$flags = array(
			'filter' => FILTER_UNSAFE_RAW,
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
 * Automator exception
 *
 * @param mixed $message
 * @param $code
 *
 * @since 3.0
 * @version 3.0
 * @throws Automator_Exception
 *
 * @package Uncanny_Automator
 */
function automator_exception( $message, $code = 999 ) {
	throw new Automator_Exception( esc_html( $message ), esc_html( $code ) );
}

/**
 * Add Error
 *
 * @param $message
 * @param mixed $error_code
 * @param mixed $data
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_wp_error( $message, $error_code = 'something_wrong', $data = '' ) {
	Automator()->wp_error->add_error( $error_code, $message, $data );
}


/**
 * Show error messages
 *
 * @param $error_code
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_wp_error_messages( $error_code = '' ) {
	Automator()->wp_error->get_messages( $error_code );
}

/**
 * Show error message
 *
 * @param string|mixed $error_code
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_wp_error_get_message( $error_code = 'something_wrong' ) {
	Automator()->wp_error->get_message( $error_code );
}

/**
 * Check if thing is an error
 *
 * @param mixed $thing
 *
 * @return bool
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function is_automator_error( $thing ) {
	return $thing instanceof Automator_WP_Error;
}

/**
 * Check if VIEWS exits
 *
 * @param $type
 *
 * @return bool
 */
function automator_db_view_exists( $type = 'recipe' ) {
	return \Uncanny_Automator\Automator_DB::is_view_exists( $type );
}

/**
 * Global function to create and log custom messages.
 *
 * @param mixed $message
 * @param mixed $subject
 * @param bool $force_log
 * @param mixed $log_file
 * @param false $backtrace
 *
 * @since 3.0
 * @version 3.0
 * @package Uncanny_Automator
 */
function automator_log( $message = '', $subject = '', $force_log = false, $log_file = 'debug', $backtrace = false ) {
	Utilities::log( $message, $subject, $force_log, $log_file, $backtrace );
}

/**
 * Purge recipe logs
 *
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_recipe_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->recipe->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * Purge trigger logs
 *
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_trigger_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->trigger->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * Purge action logs
 *
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_action_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->action->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * Purge api logs
 *
 * @param $recipe_id
 * @param $automator_recipe_log_id
 */
function automator_purge_api_logs( $recipe_id, $automator_recipe_log_id ) {
	Automator()->db->api->delete_logs( $recipe_id, $automator_recipe_log_id );
}

/**
 * Purge closure logs
 *
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
		'source' => 'uncanny_automator',
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
	return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
}

/**
 * Check if pro license is valid
 *
 * @return bool
 */
function is_automator_pro_license_valid() {
	if ( 'pro' === \Uncanny_Automator\Api_Server::get_license_type() ) {
		return true;
	}

	return false;
}


/**
 * automator_pro_older_than
 *
 * Returns true if Automator Pro is enabled and older than the $version
 *
 * @param mixed $version
 *
 * @return bool
 */
function automator_pro_older_than( $version ) {

	if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
		return version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, $version, '<' );
	}

	return false;
}

/**
 * Clear all recipe activity
 *
 * @param $recipe_id
 *
 * @return void
 */
function clear_recipe_logs( $recipe_id ) {
	Automator()->db->recipe->clear_activity_log_by_recipe_id( $recipe_id );
}


/**
 * Only identify and add tokens IF it's edit recipe page
 * @return bool
 */
function automator_do_identify_tokens() {
	// If it's cron, do not identify tokens
	if ( defined( 'DOING_CRON' ) ) {
		return false;
	}

	if (
		isset( $_REQUEST['action'] ) && //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		(
			'heartbeat' === (string) sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) || //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'wp-remove-post-lock' === (string) sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )  //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		)
	) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// if it's heartbeat, post lock actions bail
		return false;
	}

	if ( ! Automator()->helpers->recipe->is_edit_page() && ! Automator()->helpers->recipe->is_valid_token_endpoint() ) {
		// If not automator edit page or rest call, bail
		return false;
	}

	return true;
}

/**
 * Duplicate a trigger or an action
 *
 * @param $part_id
 * @param $recipe_id
 * @param $status
 *
 * @return false|int|WP_Error
 */
function automator_duplicate_recipe_part( $part_id, $recipe_id, $status = 'draft' ) {
	if ( ! class_exists( '\Uncanny_Automator\Automator_Load' ) ) {
		return false;
	}
	/** @var \Uncanny_Automator\Copy_Recipe_Parts $copy_recipe_part */
	$copy_recipe_part = \Uncanny_Automator\Automator_Load::$core_class_inits['Copy_Recipe_Parts'];
	if ( ! $copy_recipe_part instanceof \Uncanny_Automator\Copy_Recipe_Parts ) {
		return false;
	}

	return $copy_recipe_part->copy( $part_id, $recipe_id, $status );
}

/**
 * Method automator_sort_options
 *
 * @param array $a
 * @param array $b
 *
 * @return int
 */
function automator_sort_options( $a, $b ) {
	return strcmp( $a['text'], $b['text'] );
}

/**
 * automator_array_as_options
 *
 * @param array $array
 *
 * @return array
 */
function automator_array_as_options( $array_as_options ) {

	$options = array();

	foreach ( $array_as_options as $value => $text ) {
		$options[] = array(
			'value' => $value,
			'text'  => $text,
		);
	}

	return $options;
}

// String oprtations fallback for old PHP versions.

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * @param $haystack
	 * @param $needle
	 *
	 * @return bool
	 */
	function str_starts_with( $haystack, $needle ) {
		return '' !== (string) $needle && strncmp( $haystack, $needle, strlen( $needle ) ) === 0;
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * @param $haystack
	 * @param $needle
	 *
	 * @return bool
	 */
	function str_ends_with( $haystack, $needle ) {
		return '' !== $needle && substr( $haystack, - strlen( $needle ) ) === (string) $needle;
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * @param $haystack
	 * @param $needle
	 *
	 * @return bool
	 */
	function str_contains( $haystack, $needle ) {
		return '' !== $needle && mb_strpos( $haystack, $needle ) !== false;
	}
}

/**
 * automator_get_option
 *
 * @param string $option
 * @param mixed $default_value
 *
 * @return mixed
 */
function automator_get_option( $option, $default_value = false, $force = false ) {
	return ( new Automator_Options() )->get_option( $option, $default_value, $force );
}

/**
 * Adds or updates an option in the uap_options table.
 *
 * @param string $option Name of the option.
 * @param mixed $value Value of the option.
 * @param bool $autoload Whether to autoload the option or not.
 * @param bool $run_actions Whether to run do_action hooks or not.
 *
 * @return void
 */
function automator_add_option( $option, $value, $autoload = true, $run_actions = true ) {
	return ( new Automator_Options() )->add_option( $option, $value, $autoload, $run_actions );
}

/**
 * @param $option
 *
 * @return bool
 */
function automator_delete_option( $option ) {
	return ( new Automator_Options() )->delete_option( $option );
}

/**
 * Updates or adds an option in the uap_options table using upsert.
 *
 * @param string $option Name of the option.
 * @param mixed $value Value of the option.
 * @param bool $autoload Whether to autoload the option or not.
 *
 * @return bool True if the operation was successful, false otherwise.
 */
function automator_update_option( $option, $value, $autoload = true ) {
	return ( new Automator_Options() )->update_option( $option, $value, $autoload );
}

/**
 * Wrapper function for add_settings_error.
 *
 * Bails if add_settings_error function is not yet loaded. Prevents fatal error when adding an option too early.
 *
 * @param string $setting Slug title of the setting to which this error applies.
 * @param string $code Slug-name to identify the error. Used as part of 'id' attribute in HTML output.
 * @param string $message The formatted message text to display to the user (will be shown inside styled <div> and <p> tags).
 * @param string $type Message type, controls HTML class. Possible values include 'error', 'success', 'warning', 'info'. Default 'error'.
 *
 */
function automator_add_settings_error( $setting = '', $code = '', $message = '', $type = 'error' ) {

	if ( ! function_exists( 'add_settings_error' ) ) {
		return;
	}

	add_settings_error( $setting, $code, $message, $type );
}

/**
 * Store a flash message.
 *
 * @param string $key    Unique key for the message (e.g. integration slug).
 * @param string $message The message to show.
 * @param string $type    Type of message: 'error', 'success', 'warning', 'info'.
 * @param int    $ttl     Optional. Time-to-live in seconds. Default: 30.
 */
function automator_flash_message( string $key, string $message, string $type = 'error', int $ttl = 30 ): void {

	set_transient(
		"automator_flash_{$key}",
		array(
			'message' => $message,
			'type'    => $type,
		),
		$ttl
	);
}

/**
 * Display and auto-clear a flash message if it exists.
 *
 * @param string $key Unique key used when storing the message.
 *
 * @return array|false False if no message is found. Otherwise, an array with the message type and message.
 */
function automator_get_flash_message( string $key ) {

	$transient = get_transient( "automator_flash_{$key}" );

	if ( ! $transient ) {
		return false;
	}

	delete_transient( "automator_flash_{$key}" );

	$type    = esc_attr( $transient['type'] ?? 'info' );
	$message = wp_kses_post( $transient['message'] ?? '' );

	return array(
		'type'    => $type,
		'message' => $message,
	);
}

/**
 * Deletes all cache that are member of a specific cache group.
 *
 * @param string $group
 *
 * @return void
 */
function automator_cache_delete_group( $group = 'automator' ) {

	$purge_group = true;

	// LiteSpeed Cache
	if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
		// Purge all LS Cache & Object cache
		\LiteSpeed\Purge::purge_all( 'Called by Automator' );
		$purge_group = false; // Since all cache is already cleared
	}

	// W3 Total Cache
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
		$purge_group = false; // Since all cache is already cleared
	}

	// WP Super Cache
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
		$purge_group = false; // Since all cache is already cleared
	}

	// WP Rocket
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
		$purge_group = false; // Since all cache is already cleared
	}

	// Nginx Cache (if applicable)
	if ( function_exists( 'nginx_cache_purge' ) ) {
		nginx_cache_purge();
		$purge_group = false; // Since all cache is already cleared
	}

	// Redis Cache
	if ( class_exists( 'RedisObjectCache' ) && method_exists( 'RedisObjectCache', 'flush' ) ) {
		RedisObjectCache::flush();
		$purge_group = false; // Since all cache is already cleared
	}

	if ( function_exists( 'wp_cache_flush_group' ) && $purge_group ) {
		wp_cache_flush_group( $group );
	}

	/** @type WP_Object_Cache $wp_object_cache */
	global $wp_object_cache;

	$cache = $wp_object_cache->cache ?? array();

	if ( isset( $cache[ $group ] ) ) {
		unset( $cache[ $group ] );
		$wp_object_cache->cache = $cache;
	}

	// Clear Cloudflare Cache
	clear_cloudflare_cache();

	// Clear Fastly Cache
	clear_fastly_cache();
}

/**
 * @return bool
 */
function clear_cloudflare_cache() {

	// Check if the Cloudflare plugin is active and the purge_cache method exists
	if ( defined( 'CLOUDFLARE_PLUGIN_DIR' ) && class_exists( 'CF\WordPress\Hooks' ) ) {
		( new \CF\WordPress\Hooks() )->purgeCacheEverything();

		return true;
	}

	$email   = AUTOMATOR_CLOUDFLARE_EMAIL;
	$api_key = AUTOMATOR_CLOUDFLARE_API_KEY;
	$zone_id = AUTOMATOR_CLOUDFLARE_ZONE_ID;

	if ( empty( $email ) || empty( $api_key ) || empty( $zone_id ) ) {
		return false;
	}

	$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/purge_cache';

	$body = wp_json_encode( array( 'purge_everything' => true ) );

	$response = wp_remote_post(
		$url,
		array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Auth-Email' => $email,
				'X-Auth-Key'   => $api_key,
			),
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	return true;
}

/**
 * @return bool
 */
function clear_fastly_cache() {
	$api_key    = AUTOMATOR_FASTLY_API_KEY;
	$service_id = AUTOMATOR_FASTLY_SERVICE_ID;

	if ( empty( $api_key ) || empty( $service_id ) ) {
		return false;
	}

	$url = 'https://api.fastly.com/service/' . $service_id . '/purge_all';

	$response = wp_remote_post(
		$url,
		array(
			'method'  => 'POST',
			'headers' => array(
				'Fastly-Key' => $api_key,
				'Accept'     => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	return true;
}

/**
 * Get allowed file attachments for email.
 *
 * @return string[]
 */
function automator_get_allowed_attachment_ext() {

	return apply_filters( 'automator_get_allowed_attachment_ext', Extension_Support::$common_file_extensions );
}

/**
 * @param $input
 *
 * @return array
 */
function automator_array_filter_recursive( $input ) {
	foreach ( $input as &$value ) {
		if ( is_array( $value ) ) {
			$value = automator_array_filter_recursive( $value );
		}
	}

	return array_filter(
		$input,
		function ( $value ) {
			return ! ( is_string( $value ) && empty( trim( $value ) ) );
		}
	);
}

/**
 * array_merge with recursion to merge sub-array keys and values
 *
 * @param array $array1
 * @param array $array2
 *
 * @return array
 * @since 5.10
 */
function automator_array_merge( array &$array1, array &$array2 ) {
	$merged = $array1;

	foreach ( $array2 as $key => &$value ) {
		if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
			$merged[ $key ] = automator_array_merge( $merged[ $key ], $value );
		} else {
			$merged[ $key ] = $value;
		}
	}

	return $merged;
}


if ( ! function_exists( 'is_iterable' ) ) {
	/**
	 * Add is_iterable function for PHP < 7.1
	 *
	 * @param $var
	 *
	 * @return bool
	 */
	function is_iterable( $iterable_var ) {
		return is_array( $iterable_var ) || $iterable_var instanceof Traversable;
	}
}

/**
 * Detects if we're running Automator's unit tests
 * This function uses multiple specific checks to ensure we're only detecting
 * Automator's own test environment and not conflicting with other plugins
 * 
 * @return bool
 */
function is_automator_running_unit_tests() {

	// Primary check - explicit environment variable (set in CI and local)
	if ( isset( $_ENV['DOING_AUTOMATOR_TEST'] ) || defined( 'DOING_AUTOMATOR_TEST' ) ) {
		return true;
	}

	// Only proceed if we can confirm we're in Automator's directory context
	if ( ! defined( 'UA_ABSPATH' ) ) {
		return false;
	}

	// Secondary check - Multiple Automator-specific indicators must be present
	$automator_indicators = 0;
	
	// Check 1: Automator-specific test class exists
	if ( class_exists( '\\AutomatorTestCase' ) ) {
		$automator_indicators++;
	}
	
	// Check 2: WpunitTester exists (less specific, but adds confidence)
	if ( class_exists( '\\WpunitTester' ) ) {
		$automator_indicators++;
	}
	
	// Check 3: Automator's specific test configuration exists
	$test_config = UA_ABSPATH . '/tests/wpunit.suite.yml';
	if ( file_exists( $test_config ) ) {
		$automator_indicators++;
		
		// Additional validation - ensure config is specifically for Automator
		$config_content = file_get_contents( $test_config );
		if ( false !== strpos( $config_content, 'uncanny-automator/uncanny-automator.php' ) ) {
			$automator_indicators++;
		}
	}
	
	// Check 4: We're running from within Automator's directory structure
	$current_file = __FILE__;
	if ( false !== strpos( $current_file, 'uncanny-automator' ) ) {
		$automator_indicators++;
	}
	
	// Check 5: PHPUnit with Automator's composer.json
	if ( defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
		$composer_file = UA_ABSPATH . '/composer.json';
		if ( file_exists( $composer_file ) ) {
			$composer_content = file_get_contents( $composer_file );
			if ( false !== strpos( $composer_content, 'uncanny-automator' ) ) {
				$automator_indicators++;
			}
		}
	}
	
	// Require at least 3 indicators to be confident this is Automator's test environment
	// This significantly reduces the chance of false positives with other plugins
	return 3 <= $automator_indicators;
}

/**
 * Returns the Automator tables.
 *
 * @return array
 */
function get_automator_tables() {
	return DB_Tables::get_automator_tables();
}
