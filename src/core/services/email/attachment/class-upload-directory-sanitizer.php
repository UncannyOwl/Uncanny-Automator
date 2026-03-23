<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Upload_Directory_Sanitizer
 *
 * One-off cleanup run after the CVE-2026-2269 fix ships (plugin version 7.0.0.4+).
 *
 * - Static path (uploads/uncanny-automator/): hardens with .htaccess + index.php
 *   and deletes any files with disallowed extensions left over from before the fix.
 * - Legacy date-based paths (uploads/YYYY/MM/uncanny-automator/): removed entirely.
 *   Files stored there were transient email attachments that should have been cleaned
 *   up after sending; there is no reason to keep those directories around.
 *
 * Runs exactly once per site via a single scheduled event, gated by an option
 * flag. Subsequent page loads are a no-op.
 *
 * @package Uncanny_Automator
 */
class Upload_Directory_Sanitizer {

	const OPTION_FLAG = 'automator_upload_dir_sanitized_v1';
	const CRON_HOOK   = 'automator_upload_dir_sanitize';

	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'sanitize_upload_directories' ) );
		$this->maybe_schedule();
	}

	/**
	 * Schedules the one-off event if it hasn't run yet.
	 *
	 * @return void
	 */
	private function maybe_schedule() {

		if ( automator_get_option( self::OPTION_FLAG ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback. Hardens the static directory, removes all legacy date-based
	 * directories, then sets the flag so this never runs again.
	 *
	 * @return void
	 */
	public function sanitize_upload_directories() {

		$upload_basedir = wp_upload_dir()['basedir'] ?? '';

		if ( empty( $upload_basedir ) ) {
			return;
		}

		$basedir = trailingslashit( $upload_basedir );

		// Harden the static directory used by the fixed code.
		foreach ( glob( $basedir . 'uncanny-automator', GLOB_ONLYDIR ) ?: array() as $dir ) {
			\Uncanny_Automator\Services\Email\Attachment\Handler::write_directory_protection( $dir );
			$this->delete_unsafe_files( $dir );
		}

		// Remove legacy date-based directories entirely — files there are orphaned
		// transient attachments that should have been deleted after each send.
		foreach ( glob( $basedir . '/*/*/uncanny-automator', GLOB_ONLYDIR ) ?: array() as $dir ) {
			$this->remove_directory( $dir );
		}

		automator_update_option( self::OPTION_FLAG, true );
	}

	/**
	 * Deletes any file whose extension is not in the allowed attachment list.
	 *
	 * @param string $dir
	 *
	 * @return void
	 */
	private function delete_unsafe_files( $dir ) {

		$files     = glob( trailingslashit( $dir ) . '*' ) ?: array();
		$allowed   = apply_filters( 'automator_email_attachment_allowed_file_extensions', automator_get_allowed_attachment_ext() );
		$protected = array( '.htaccess', 'index.php' );

		foreach ( $files as $file ) {

			if ( ! is_file( $file ) ) {
				continue;
			}

			if ( in_array( basename( $file ), $protected, true ) ) {
				continue;
			}

			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

			if ( empty( $extension ) || ! in_array( $extension, $allowed, true ) ) {
				wp_delete_file( $file );
				automator_log( sprintf( 'Deleted unsafe file: %s', basename( $file ) ), 'Upload_Directory_Sanitizer' );
			}
		}
	}

	/**
	 * Deletes all files inside a directory (including dotfiles) then removes
	 * the directory itself.
	 *
	 * @param string $dir Absolute path to the directory to remove.
	 *
	 * @return void
	 */
	private function remove_directory( $dir ) {

		// GLOB_BRACE picks up dotfiles such as .htaccess.
		$entries = glob( trailingslashit( $dir ) . '{,.}*', GLOB_BRACE ) ?: array();

		foreach ( $entries as $entry ) {

			$basename = basename( $entry );

			if ( '.' === $basename || '..' === $basename ) {
				continue;
			}

			if ( is_dir( $entry ) ) {
				$this->remove_directory( $entry );
			} elseif ( is_file( $entry ) ) {
				wp_delete_file( $entry );
			}
		}

		if ( is_dir( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			rmdir( $dir );
			automator_log( sprintf( 'Removed legacy directory: %s', $dir ), 'Upload_Directory_Sanitizer' );
		}
	}
}
