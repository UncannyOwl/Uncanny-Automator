<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Actions;

/**
 * This class handles the user selector object inside the recipe object.
 *
 * This object allows dynamic properties due to the nature of recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Actions
 *
 * @todo This class should live in Pro version that extends the Recipe UI Structure.
 *
 */
#[\AllowDynamicProperties]
class User_Selector {

	public $source             = '';
	public $unique_field       = '';
	public $unique_field_value = '';

	public function accept( $meta ) {

		$this->is_legacy   = true;
		$this->data_source = $meta;

		$this->hydrate();

	}

	/**
	 * Hydrates the object.
	 *
	 * @return void
	 */
	public function hydrate() {

		$fields = isset( $this->data_source['fields'] ) ? $this->data_source['fields'] : array();
		$source = isset( $this->data_source['source'] ) ? $this->data_source['source'] : array();

		$this->fields = (array) maybe_unserialize( $fields );

		$defaults = array(
			'uniqueField'      => null,
			'uniqueFieldValue' => null,
			'fallback'         => null,
			'prioritizedField' => null,
		);

		$fields_unserialized = wp_parse_args( $this->fields, $defaults );

		$this->source                         = $source;
		$this->unique_field                   = $fields_unserialized['uniqueField'];
		$this->unique_field_value             = $fields_unserialized['uniqueFieldValue'];
		$this->prioritized_field              = $fields_unserialized['prioritizedField'];
		$this->fallback_creates_user          = 'create-new-user' === $fields_unserialized['fallback'];
		$this->fallback_selects_existing_user = 'select-existing-user' === $fields_unserialized['fallback'];
	}

	/**
	 * Retrieves the existing or new user object.
	 *
	 * @return mixed[]|void
	 */
	public function retrieve() {

		$fields = wp_parse_args(
			$this->fields,
			array(
				'firstName'   => null,
				'lastName'    => null,
				'email'       => null,
				'username'    => null,
				'displayName' => null,
				'password'    => null,
				'role'        => null,
			)
		);

		if ( 'existingUser' === $this->source ) {
			return $this->existing_user_structure( $fields );
		}

		if ( 'newUser' === $this->source ) {
			return $this->new_user_structure( $fields );
		}

	}

	/**
	 * User selector existing user structure
	 *
	 * @param mixed[]
	 *
	 * @return mixed[]
	 */
	private function existing_user_structure( $fields ) {

		$log_user_in = isset( $fields['logUserIn'] ) ? $fields['logUserIn'] : '';

		return array(
			'source' => $this->source,
			'fields' => array(
				'UNIQUE_FIELD'             => array(
					'value'    => $this->unique_field,
					'readable' => ucfirst( $this->unique_field ),
				),
				'UNIQUE_FIELD_VALUE'       => array(
					'value' => $this->unique_field_value,
				),
				'FALLBACK_CREATES_USER'    => array(
					'value' => $this->fallback_creates_user,
				),
				'CREATE_USER_FIRST_NAME'   => array(
					'value' => $fields['firstName'],
				),
				'CREATE_USER_LAST_NAME'    => array(
					'value' => $fields['lastName'],
				),
				'CREATE_USER_EMAIL'        => array(
					'value' => $fields['email'],
				),
				'CREATE_USER_USERNAME'     => array(
					'value' => $fields['username'],
				),
				'CREATE_USER_DISPLAY_NAME' => array(
					'value' => $fields['displayName'],
				),
				'CREATE_USER_PASSWORD'     => array(
					'value' => $fields['password'],
				),
				'LOGIN_USER'               => array(
					'value' => 'no' !== $log_user_in,
				),
				'CREATE_USER_ROLE'         => array(
					'value' => $fields['role'],
				),
			),
		);
	}

	/**
	 * User selector new user structure
	 *
	 * @param mixed[]
	 *
	 * @return mixed[]
	 */
	private function new_user_structure( $fields ) {

		return array(
			'source' => $this->source,
			'fields' => array(
				'NEW_USER_FIRST_NAME'            => array(
					'value' => $fields['firstName'],
				),
				'NEW_USER_LAST_NAME'             => array(
					'value' => $fields['lastName'],
				),
				'NEW_USER_EMAIL'                 => array(
					'value' => $fields['email'],
				),
				'NEW_USER_USERNAME'              => array(
					'value' => $fields['username'],
				),
				'NEW_USER_DISPLAY_NAME'          => array(
					'value' => $fields['displayName'],
				),
				'NEW_USER_PASSWORD'              => array(
					'value' => $fields['password'],
				),
				'NEW_USER_ROLE'                  => array(
					'value' => $fields['role'],
				),
				'LOGIN_USER'                     => array(
					'value' => 'no' !== $fields['logUserIn'],
				),
				'FALLBACK_SELECTS_EXISTING_USER' => array(
					'value'             => $this->fallback_selects_existing_user,
					'prioritized_field' => $this->prioritized_field,
				),
			),
		);
	}

	public function __get( $prop ) {
		return $prop;
	}

	public function __set( $prop, $value ) {
		$this->{$prop} = $value;
	}
}
