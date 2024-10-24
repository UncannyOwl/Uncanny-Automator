<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

/**
 * Class Factory
 *
 * Factory class for creating and managing Hydrator and Entity instances.
 *
 * @package Uncanny_Automator\Services\Recipe\Action\Token
 */
class Factory {

	/**
	 * Hydrator instance.
	 *
	 * @var Hydrator
	 */
	protected $hydrator = null;

	/**
	 * Entity instance.
	 *
	 * @var Entity
	 */
	protected $entity = null;

	/**
	 * Get or create a Hydrator instance.
	 *
	 * @return Hydrator The Hydrator instance.
	 */
	public function hydrator() {

		if ( null === $this->hydrator ) {
			$this->hydrator = new Hydrator();
		}

		return $this->hydrator;

	}

	/**
	 * Get or create an Entity instance.
	 *
	 * @return Entity The Entity instance.
	 */
	public function entity() {

		if ( null === $this->entity ) {
			$this->entity = new Entity();
		}

		return $this->entity;

	}

	/**
	 * Set the Hydrator instance.
	 *
	 * @param Hydrator $hydrator The Hydrator instance to set.
	 */
	public function set_hydrator( Hydrator $hydrator ) {
		$this->hydrator = $hydrator;
	}

	/**
	 * Set the Entity instance.
	 *
	 * @param Entity $entity The Entity instance to set.
	 */
	public function set_entity( Entity $entity ) {
		$this->entity = $entity;
	}
}
