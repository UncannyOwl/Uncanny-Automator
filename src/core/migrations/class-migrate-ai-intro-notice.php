<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\App\Presentation\Admin\Ai_Intro_Notice;
use Uncanny_Automator\Automator_Load;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrate_Ai_Intro_Notice.
 *
 * 8.0 introduces connecting the customer's own AI to the site. A fresh
 * install learns about it in the setup wizard. A site that existed before
 * 8.0 never sees that wizard again, so this migration flags it for the
 * one-time intro notice instead.
 *
 * @since 8.0
 *
 * @package Uncanny_Automator
 */
class Migrate_Ai_Intro_Notice extends Migration {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '80_ai_intro_notice' );
	}

	/**
	 * migrate
	 *
	 * @return void
	 */
	public function migrate() {

		if ( ! Automator_Load::is_fresh_install() ) {
			Ai_Intro_Notice::mark_pending();
		}

		$this->complete();
	}
}

new Migrate_Ai_Intro_Notice();
