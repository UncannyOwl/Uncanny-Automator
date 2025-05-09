<?php

namespace Uncanny_Automator;

class Automator_Options {

    // Make sure all objects share the same cached options.
    public static $cached_options = null;

    private $wpdb;

    private $db_name;
    
    public function __construct() {

        global $wpdb;

        $this->wpdb = $wpdb;

        $this->db_name =  $this->wpdb->prefix . 'uap_options';

        if ( null === self::$cached_options ) {
            $this->autoload_options();
        }
    }

    /**
     * Retrieves all options from the uap_options table, with optional cache refresh.
     *
     * @param bool $force Whether to force a cache refresh.
     *
     * @return array All options from the uap_options table.
     */
    public function autoload_options() {

        $all_options_db = $this->db_get_results(
            "SELECT option_name, option_value FROM {$this->db_name} WHERE autoload = 'yes'"
        );

        $all_options = array();

        foreach ( (array) $all_options_db as $o ) {
            $all_options[ $o->option_name ] = $o->option_value;
        }

        // Store the result in the static cache.
        self::$cached_options = $all_options;
    }

   
    /**
     * automator_get_option
     *
     * @param string $option
     * @param mixed $default_value
     *
     * @return mixed
     */
    public function get_option( $option, $default_value = false, $force = false ) {

        // Trim the option.
        $option = trim( $option );

        // Bail if the option is not scalar or empty.
        if ( ! is_scalar( $option ) || empty( $option ) ) {
            return false;
        }

        // If there is a cached value, return it.
        if ( isset( self::$cached_options[ $option ] ) ) {
            $value = self::$cached_options[ $option ];
            return $this->output_option_value( $value, $option, $default_value );
        }

        // Check if there is a value in the automator options table.
        $automator_db_value = $this->get_automator_db_option( $option, $default_value );
        
        if ( null !== $automator_db_value ) {
            return $this->output_option_value( $automator_db_value, $option, $default_value );
        }
        
        // Check if there is a value in the WordPress options table.
        $wp_db_value = $this->get_wp_db_option( $option );
        
        if ( null !== $wp_db_value ) {
            $this->add_option( $option, $wp_db_value, true, false );
            return $this->output_option_value( $wp_db_value, $option, $default_value );
        }

        return $default_value;
    }

    public function get_automator_db_option( $option, $default_value ) {

        // Get the option from the database.
        $row = $this->db_get_row( $this->wpdb->prepare( "SELECT option_value FROM {$this->db_name} WHERE option_name = %s LIMIT 1", $option ) );

        if ( ! is_object( $row ) ) {
            return null;
        }

        $this->cache_value( $option, $row->option_value );

        // check if the option type is available in the database as well
        $type_row = $this->db_get_row( $this->wpdb->prepare( "SELECT option_value FROM {$this->db_name} WHERE option_name = %s LIMIT 1", $option . '_type' ) );
        
        if ( is_object( $type_row ) ) {
            $this->cache_value( "{$option}_type", $type_row->option_value );
        }

        return $row->option_value;
    }

    public function get_wp_db_option( $option ) {

        // Get the option from the database.
        $row = $this->db_get_row( $this->wpdb->prepare( "SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s LIMIT 1", $option ) );

        // If the value is found in the database, return it.
        if ( ! is_object( $row ) ) {
            return null;
        }

        return $row->option_value;
    }

    private function cache_value( $option, $value ) {
        self::$cached_options[ $option ] = $value;
    }

    public function output_option_value( $value, $option, $default_value ) {
        $formatted_value = $this->format_option_value( $option, $value, $default_value );
        return apply_filters( "automator_option_{$option}", $formatted_value, $option );
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
    public function add_option( $option, $value, $autoload = true, $run_actions = true ) {

        if ( ! is_scalar( $option ) || empty( trim( $option ) ) ) {
            return;
        }

        // Determine the original type.
        $type = gettype( $value );

        // Convert booleans to special strings for storage.
        if ( is_bool( $value ) ) {
            $value = $value ? '__true__' : '__false__';
        }

        if ( null === $value ) {
            $value = '__null__';
        }

        $option           = trim( $option );
        $serialized_value = is_scalar( $value ) ? $value : maybe_serialize( $value );
        $autoload_flag    = $autoload ? 'yes' : 'no';

        // Fire actions before adding or updating the option.
        if ( $run_actions ) {
            do_action( 'automator_add_option', $option, $value );
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE for a single upsert operation.
        $this->db_query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->db_name} (option_name, option_value, autoload)
    VALUES (%s, %s, %s)
    ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
                $option,
                $serialized_value,
                $autoload_flag
            )
        );

        // Store the type as metadata to track the original data type.
        $this->db_query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->db_name} (option_name, option_value, autoload)
                VALUES (%s, %s, 'no')
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                $option . '_type',
                $type
            )
        );

        self::$cached_options[ $option ] = $value;
        self::$cached_options[ "{$option}_type" ] = $type;

        // Fire post-add/update actions.
        if ( $run_actions ) {
            do_action( "automator_add_option_{$option}", $option, $value );
            do_action( 'automator_option_added', $option, $value );
        }
    }

    /**
     * @param $option
     *
     * @return bool
     */
    public function delete_option( $option ) {

        // Delete the option from the database
        $deleted = $this->wpdb->delete(
            $this->db_name,
            array( 'option_name' => $option ),
            array( '%s' )
        );

        $this->wpdb->delete(
            $this->db_name,
            array( 'option_name' => $option . '_type' ),
            array( '%s' )
        );

        // Fallback to deleting the option from the database
        delete_option( $option );

        unset( self::$cached_options[ $option ] );
        unset( self::$cached_options[ $option . '_type' ] );

        do_action( 'automator_option_deleted', $option );

        return ( false !== $deleted );
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
    public function update_option( $option, $value, $autoload = true ) {

        if ( ! is_scalar( $option ) || empty( trim( $option ) ) ) {
            return false;
        }

        // Determine the original type.
        $type = gettype( $value );

        $option           = trim( $option );
        $serialized_value = is_scalar( $value ) ? $value : maybe_serialize( $value );
        $autoload_flag    = $autoload ? 'yes' : 'no';

        // Perform the upsert operation.
        $result = $this->db_query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->db_name} (option_name, option_value, autoload)
                VALUES (%s, %s, %s)
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
                $option,
                $serialized_value,
                $autoload_flag
            )
        );

        // Store the type as metadata to track the original data type.
        $this->db_query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->db_name} (option_name, option_value, autoload)
                VALUES (%s, %s, %s)
                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                $option . '_type',
                $type,
                $autoload_flag
            )
        );

        self::$cached_options[ $option ] = $value;
        self::$cached_options[ "{$option}_type" ] = $type;

        // Fire post-update actions.
        do_action( "automator_update_option_{$option}", $option, $value );
        do_action( 'automator_updated_option', $option, $value );

        return ( false !== $result );
    }

    /**
     * Validates, sanitizes, and determines the correct value to return.
     *
     * @param string $option The option name.
     * @param mixed $value The value retrieved from cache or DB.
     * @param mixed $default_value The default value to use if needed.
     *
     * @return mixed The final sanitized value.
     */
    public function format_option_value( $option, $value, $default_value ) {

        // Unserialize the value if needed.
        $value = maybe_unserialize( $value );

        // Return false if the value is false.
        if ( '__false__' === $value || ( '' === $value && false === $default_value ) ) {
            return false;
        }

        // Return true if the value is true.
        if ( '__true__' === $value || ( '' === $value && true === $default_value ) ) {
            return true;
        }

        // Return null if the value is null.
        if ( '__null__' === $value || ( '' === $value && null === $default_value ) ) {
            return $default_value;
        }

        // Return '' if the value is truly empty.
        if ( '' === $value ) {
            return $value;
        }

        $original_type = null;

        if ( array_key_exists( "{$option}_type", self::$cached_options ) ) {
            $original_type = self::$cached_options[ "{$option}_type" ];
        }

        // Use the original type to restore the value's type.
        switch ( $original_type ) {
            case 'integer':
                return (int) $value;
            case 'double':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            case 'NULL':
                return null;
            default:
                return $value;  // Return as-is for strings and other types.
        }
    }

    /**
     * Retrieves the results of a query from the database.
     * 
     * We put this into a separate method so it's easier to unit test the class
     *
     * @param string $query The SQL query to execute.
     *
     * @return array The results of the query.
     */
    private function db_get_results( $query ) {

        $suppress = $this->wpdb->suppress_errors();

        $results = $this->wpdb->get_results( $query );

        $this->wpdb->suppress_errors( $suppress );

        return $results;
    }

    private function db_get_row( $query ) {

        $row = $this->wpdb->get_row( $query );

        return $row;
    }

    private function db_query( $query ) {

        $result = $this->wpdb->query( $query );

        return $result;
    }

}