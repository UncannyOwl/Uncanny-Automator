<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

use InvalidArgumentException;

/**
 * Class Entity
 *
 * Represents an entity for the Uncanny Automator recipe action token.
 *
 * @package Uncanny_Automator\Services\Recipe\Action\Token
 */
class Entity {

	/**
	 * The ID of the entity.
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * The name of the entity.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * The type of the entity.
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * The parent entity.
	 *
	 * @var string
	 */
	protected $parent = '';

	/**
	 * Set the ID of the entity.
	 *
	 * @param string $id The ID to set.
	 *
	 * @throws InvalidArgumentException If the ID is empty.
	 */
	public function set_id( $id ) {
		if ( empty( $id ) ) {
			throw new InvalidArgumentException( 'Action token id is required and must not be empty', 400 );
		}
		$this->id = $id;
	}

	/**
	 * Set the name of the entity.
	 *
	 * @param string $name The name to set.
	 */
	public function set_name( $name ) {
		if ( empty( $name ) ) {
			throw new InvalidArgumentException( 'Action token name is required and must not be empty', 400 );
		}
		$this->name = $name;
	}

	/**
	 * Set the type of the entity.
	 *
	 * @param string $type The type to set.
	 */
	public function set_type( $type = 'int' ) {
		$this->type = $type;
	}

	/**
	 * Set the parent of the entity.
	 *
	 * @param string $parent The parent to set.
	 */
	public function set_parent( $parent ) {
		if ( empty( $parent ) ) {
			throw new InvalidArgumentException( 'Action token parent is required and must not be empty', 400 );
		}
		$this->parent = $parent;
	}

	/**
	 * Get the ID of the entity.
	 *
	 * @return string The ID of the entity.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the name of the entity.
	 *
	 * @return string The name of the entity.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the type of the entity.
	 *
	 * @return string The type of the entity.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the parent of the entity.
	 *
	 * @return string The parent of the entity.
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Returns an array representation of the action token entity.
	 *
	 * @return string[]
	 */
	public function toArray() {
		return array(
			'tokenId'     => $this->get_id(),
			'tokenParent' => $this->get_parent(),
			'tokenName'   => $this->get_name(),
			'tokenType'   => $this->get_type(),
		);
	}

	/**
	 * Validate the entity's data.
	 *
	 * @return bool True if the entity is valid, false otherwise.
	 *
	 * @throws InvalidArgumentException If any required data is missing.
	 */
	public function validate() {
		if ( empty( $this->id ) ) {
			throw new InvalidArgumentException( 'ID is required', 400 );
		}

		if ( empty( $this->name ) ) {
			throw new InvalidArgumentException( 'Name is required', 400 );
		}

		if ( empty( $this->type ) ) {
			throw new InvalidArgumentException( 'Type is required', 400 );
		}

		return true;
	}
}
