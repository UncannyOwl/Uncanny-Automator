<?php
namespace Uncanny_Automator\Api\Components\Token\Domain;

/**
 * Defines the valid data types for integration tokens.
 *
 * @package Uncanny_Automator\Api\Components\Token\Domain
 * @since 7.0.0
 */
interface Token_Data_Types {
	const TEXT     = 'text';
	const EMAIL    = 'email';
	const URL      = 'url';
	const INTEGER  = 'integer';
	const FLOAT    = 'float';
	const DATE     = 'date';
	const TIME     = 'time';
	const DATETIME = 'datetime';
	const BOOLEAN  = 'boolean';
	const ARRAY    = 'array';
}
