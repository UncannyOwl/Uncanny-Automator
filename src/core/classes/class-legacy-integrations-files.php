<?php

namespace Uncanny_Automator;

/**
 * Class Legacy_Integrations
 *
 * @package Uncanny_Automator
 */
class Legacy_Integrations {

	/**
	 * @var array
	 */
	public $files;

	/**
	 * @var string
	 */
	public $automator_path;

	/**
	 * @var array
	 */
	public $class_map;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		$this->automator_path = $this->get_automator_path();
		$this->class_map      = $this->load_class_map_file();
	}

	/**
	 * generate_integrations_file_map
	 *
	 * @return array
	 */
	public function generate_integrations_file_map() {

		foreach ( $this->class_map as $class => $path ) {
			$this->process_path( $path, $class );
		}

		return $this->files;
	}

	/**
	 * get_automator_path
	 *
	 * @return string
	 */
	public function get_automator_path() {
		return rtrim( UA_ABSPATH, DIRECTORY_SEPARATOR ) . '/';
	}

	/**
	 * load_class_map_file
	 *
	 * @return array
	 */
	public function load_class_map_file() {
		return include UA_ABSPATH . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';
	}

	/**
	 * process_path
	 *
	 * @param string $path
	 * @param string $class
	 *
	 * @return void
	 */
	public function process_path( $path, $class ) {

		$relative_path = $this->get_relative_path( $path );

		if ( ! str_starts_with( $relative_path, 'src/integrations/' ) ) {
			return;
		}

		if ( 'index.php' === basename( $path ) ) {
			return;
		}

		$integration_folder = str_replace( 'src/integrations/', '', $relative_path );

		$integration_name = strtok( $integration_folder, '/' );

		$integration_path = $this->automator_path . 'src/integrations/' . $integration_name . '/';

		$folder = strtok( '/' );

		if ( str_starts_with( $folder, 'add' ) && str_ends_with( $folder, 'integration.php' ) ) {
			// It's not a folder, it's the add-integration file.
			$this->files[ $integration_name ]['main'] = $this->uniform_slashes( $integration_path . $folder );

			return;
		}

		if ( str_ends_with( $folder, '.php' ) ) {
			return;
		}

		$default_directories = apply_filters(
			'automator_integration_default_directories',
			array(
				'actions',
				'helpers',
				'tokens',
				'triggers',
				'closures',
			)
		);

		if ( ! in_array( $folder, $default_directories, true ) ) {
			return;
		}

		$file = strtok( '/' );

		$file_path = $integration_path . $folder . '/' . $file;

		$this->files[ $integration_name ][ $folder ][] = $this->uniform_slashes( $file_path );
	}

	/**
	 * uniform_slashes
	 *
	 * @param string $input
	 *
	 * @return string
	 */
	public function uniform_slashes( $input ) {
		return str_replace( '/', DIRECTORY_SEPARATOR, $input );
	}

	/**
	 * get_relative_path
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_relative_path( $path ) {
		return str_replace( $this->get_automator_path(), '', $path );
	}
}
