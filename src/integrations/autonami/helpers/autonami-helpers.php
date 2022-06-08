<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Autonami_Helpers
 *
 * @package Uncanny_Automator
 */
class Autonami_Helpers {

	/**
	 * get_email_field
	 *
	 * @return array
	 */
	public function get_email_field() {
		return array(
			'option_code' => 'EMAIL',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);
	}

	/**
	 * get_list_dropdown
	 *
	 * @return void
	 */
	public function get_list_dropdown( $add_any = true ) {

		$list_options = array();

		if ( $add_any ) {
			$list_options[] = array(
				'value' => -1,
				'text'  => __( 'Any list', 'uncanny-automator' ),
			);
		}

		$list_options = array_merge( $list_options, $this->get_lists() );

		$dropdown = array(
			'input_type'            => 'select',
			'option_code'           => 'LIST',
			/* translators: HTTP request method */
			'label'                 => esc_attr__( 'List', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => $list_options,
		);

		return $dropdown;
	}

	/**
	 * Method get_lists
	 *
	 * @return void
	 */
	public function get_lists() {

		$bwfcrm_lists = \BWFCRM_Lists::get_lists();

		$lists = array();

		foreach ( $bwfcrm_lists as $list ) {
			$lists[] = array(
				'value' => $list['ID'],
				'text'  => $list['name'],
			);
		}

		usort( $lists, array( $this, 'sort_by_name' ) );

		return $lists;
	}

	/**
	 * get_tag_dropdown
	 *
	 * @return void
	 */
	public function get_tag_dropdown( $add_any = true ) {

		$tag_options = array();

		if ( $add_any ) {
			$tag_options[] = array(
				'value' => -1,
				'text'  => __( 'Any tag', 'uncanny-automator' ),
			);
		}

		$tag_options = array_merge( $tag_options, $this->get_tags() );

		$dropdown = array(
			'input_type'            => 'select',
			'option_code'           => 'TAG',
			/* translators: HTTP request method */
			'label'                 => esc_attr__( 'Tag', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => $tag_options,
		);

		return $dropdown;

	}

	/**
	 * Method get_tags
	 *
	 * @return void
	 */
	public function get_tags() {

		$bwfcrm_tags = \BWFCRM_Tag::get_tags();

		$tags = array();

		foreach ( $bwfcrm_tags as $tag ) {
			$tags[] = array(
				'value' => $tag['ID'],
				'text'  => $tag['name'],
			);
		}

		usort( $tags, array( $this, 'sort_by_name' ) );

		return $tags;
	}

	/**
	 * Method sortByName
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return void
	 */
	public function sort_by_name( $a, $b ) {
		return strcmp( $a['text'], $b['text'] );
	}

	/**
	 * extract_contact_from_args
	 *
	 * @param  mixed $args
	 * @return mixed
	 */
	public function extract_contact_from_args( $args ) {

		if ( empty( $args[0][1] ) ) {
			throw new \Exception( __( 'Contact not found', 'uncanny-automator' ) );
		}

		return $args[0][1];
	}

	/**
	 * Method get_wp_id
	 *
	 * @param  mixed $contact
	 * @return mixed
	 */
	public function get_wp_id( $contact ) {

		$email = $contact->contact->get_email();

		$user = get_user_by( 'email', $email );

		if ( false === $user ) {
			throw new \Exception( __( 'WP user not found', 'uncanny-automator' ) );
		}

		return $user->ID;

	}

	/**
	 * extract_list_id_from_args
	 *
	 * @param  mixed $args
	 * @return void
	 */
	public function extract_list_id_from_args( $args ) {

		if ( ! is_array( $args ) ) {
			throw new \Exception( __( 'List not found', 'uncanny-automator' ) );
		}

		$list = array_shift( $args );

		if ( empty( $list ) ) {
			throw new \Exception( __( 'List not found', 'uncanny-automator' ) );
		}

		if ( ! is_numeric( $list ) ) {
			return $list->get_id();
		}

		return $list;
	}

	/**
	 * extract_tag_id_from_args
	 *
	 * @return void
	 */
	public function extract_tag_id_from_args( $args ) {

		if ( ! is_array( $args ) ) {
			throw new \Exception( __( 'Tag not found', 'uncanny-automator' ) );
		}

		$tag = array_shift( $args );

		if ( ! is_numeric( $tag ) ) {
			return $tag->get_id();
		}

		return $tag;

	}

	/**
	 * Method add_tag_to_contact
	 *
	 * @param  mixed $email
	 * @param  mixed $tag_id
	 * @param  mixed $tag_readable
	 * @return void
	 */
	public function add_tag_to_contact( $email, $tag_id, $tag_readable ) {

		$tags_to_add = array(
			array(
				'id' => $tag_id,
			),
		);

		$autonami_contact = new \BWFCRM_Contact( $email );

		$result = $autonami_contact->add_tags( $tags_to_add );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		if ( empty( $result ) ) {
			/* translators: %s - the tag name. */
			throw new \Exception( sprintf( __( 'User already has the %s tag', 'uncanny-automator' ), $tag_readable ) );
		}

		$autonami_contact->save();

	}

	/**
	 * support_link
	 *
	 * @return string
	 */
	public function support_link( $code ) {
		return Automator()->get_author_support_link( $code, 'integration/autonami/' );
	}
}
