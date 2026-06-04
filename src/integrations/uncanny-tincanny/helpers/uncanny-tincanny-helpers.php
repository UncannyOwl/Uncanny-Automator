<?php

namespace Uncanny_Automator\Integrations\Uncanny_Tincanny;

use TINCANNYSNC\Database;
use TINCANNYSNC\Module_CRUD;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Uotc_Helpers
 *
 * @package Uncanny_Automator
 */
class Uotc_Helpers extends Abstract_Helpers {

	/**
	 * Deprecated self-reference for backward compatibility.
	 * Pro condition may access `$this->options`.
	 *
	 * @deprecated
	 *
	 * @var \Uncanny_Automator\Integrations\Uncanny_Tincanny\Uotc_Helpers
	 */
	public $options;

	/**
	 * Uotc_Helpers constructor.
	 */
	public function __construct() {
		$this->options = $this;
	}

	/**
	 * Get all Tin Canny modules.
	 *
	 * Supports dual API: old Database class and new Module_CRUD class.
	 *
	 * @return array
	 */
	public static function get_modules() {

		if ( class_exists( '\TINCANNYSNC\Database' ) ) {
			return Database::get_modules();
		}

		if ( class_exists( '\TINCANNYSNC\Module_CRUD' ) ) {
			return Module_CRUD::get_modules();
		}

		return array();
	}

	/**
	 * Get a single module item by ID.
	 *
	 * Supports dual API: old Database class and new Module_CRUD class.
	 *
	 * @param int $module_id The module ID.
	 *
	 * @return array
	 */
	public static function get_item( $module_id ) {

		if ( class_exists( '\TINCANNYSNC\Database' ) ) {
			return Database::get_item( $module_id );
		}

		if ( class_exists( '\TINCANNYSNC\Module_CRUD' ) ) {
			return Module_CRUD::get_item( $module_id );
		}

		return array();
	}

	/**
	 * Remote-data handler: load Tin Can modules for dropdown options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_modules( $request ): array {

		$options = array();

		$options[] = array(
			'value' => '-1',
			'text'  => esc_html_x( 'Any module', 'Tin Canny Reporting', 'uncanny-automator' ),
		);

		$modules = self::get_modules();

		if ( ! empty( $modules ) ) {
			foreach ( $modules as $module ) {
				$options[] = array(
					'value' => $module->ID,
					'text'  => '(ID: ' . $module->ID . ') ' . $module->file_name,
				);
			}
		}

		return $this->remote_data_success( $options );
	}
}
