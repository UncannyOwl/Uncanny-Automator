<?php

namespace Uncanny_Automator;

use TINCANNYSNC\Database;
use TINCANNYSNC\Module_CRUD;

/**
 * Uncanny Tincanny Helpers.
 */
class Uncanny_Tincanny_Helpers {

    /**
     * Get the modules.
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
     * Get the item of the module.
     *
     * @param int $module_id
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
}