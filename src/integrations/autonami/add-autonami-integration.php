<?php

namespace Uncanny_Automator;

/**
 * Class Add_Autonami_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Autonami_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'AUTONAMI' );
		$this->set_name( 'Autonami' );
		$this->set_icon( __DIR__ . '/img/autonami-icon.svg' );

		// By default, bwfan_contact_added_to_lists action passes a single list or an array of lists.
		// We need to create a custom hook that fires for each list separately to make sure our tokens work.
		add_action( 'bwfan_contact_added_to_lists', array( $this, 'contact_added_to_lists' ), 10, 2 );

		// By default, bwfan_tags_added_to_contact action passes a single tag or an array of tags.
		// We need to create a custom hook that fires for each tag separately to make sure our tokens work.
		add_action( 'bwfan_tags_added_to_contact', array( $this, 'tag_added_to_contact' ), 10, 2 );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'BWFCRM_Contact' );
	}


	/**
	 * Method contact_added_to_lists
	 *
	 * @param  mixed $lists
	 * @param  mixed $bwfcrm_contact_object
	 * @return void
	 */
	public function contact_added_to_lists( $lists, $bwfcrm_contact_object ) {

		if ( ! is_array( $lists ) ) {
			do_action( 'automator_bwfan_contact_added_to_list', $lists, $bwfcrm_contact_object );
			return;
		}

		foreach ( $lists as $list ) {
			do_action( 'automator_bwfan_contact_added_to_list', $list, $bwfcrm_contact_object );
		}

	}

	/**
	 * Method tag_added_to_user
	 *
	 * @param  mixed $lists
	 * @param  mixed $bwfcrm_contact_object
	 * @return void
	 */
	public function tag_added_to_contact( $tags, $bwfcrm_contact_object ) {

		if ( ! is_array( $tags ) ) {
			do_action( 'automator_bwfan_tag_added_to_contact', $tags, $bwfcrm_contact_object );
			return;
		}

		foreach ( $tags as $tag ) {
			do_action( 'automator_bwfan_tag_added_to_contact', $tag, $bwfcrm_contact_object );
		}

	}
}
