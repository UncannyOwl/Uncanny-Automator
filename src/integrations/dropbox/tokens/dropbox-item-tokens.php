<?php
/**
 * Dropbox item tokens
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

/**
 * Token groups shared by the Dropbox file and folder triggers.
 *
 * Every trigger hook passes the item's Dropbox metadata (`$entry`), the folder
 * it is in, and — for a rename or move — the path it had before.
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Item_Tokens {

	/**
	 * The item a file or folder event is about.
	 *
	 * @return array
	 */
	public static function define_item_tokens() {
		return array(
			array(
				'tokenId'   => 'FILE_ID',
				'tokenName' => esc_html_x( 'File/Folder ID', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_NAME',
				'tokenName' => esc_html_x( 'File/Folder name', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_PATH',
				'tokenName' => esc_html_x( 'File/Folder path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_TYPE',
				'tokenName' => esc_html_x( 'Type (file or folder)', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_SIZE',
				'tokenName' => esc_html_x( 'File size (bytes)', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FILE_MODIFIED',
				'tokenName' => esc_html_x( 'Date modified', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PARENT_FOLDER',
				'tokenName' => esc_html_x( 'Parent folder path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Folders carry no size or modified date; those tokens resolve empty.
	 *
	 * @param array  $entry       The item's Dropbox metadata.
	 * @param string $folder_path The folder the item is in.
	 *
	 * @return array
	 */
	public static function hydrate_item_tokens( $entry, $folder_path ) {
		return array(
			'FILE_ID'       => (string) ( $entry['id'] ?? '' ),
			'FILE_NAME'     => (string) ( $entry['name'] ?? '' ),
			'FILE_PATH'     => (string) ( $entry['path_display'] ?? '' ),
			'FILE_TYPE'     => (string) ( $entry['.tag'] ?? '' ),
			'FILE_SIZE'     => (int) ( $entry['size'] ?? 0 ),
			'FILE_MODIFIED' => (string) ( $entry['server_modified'] ?? '' ),
			'PARENT_FOLDER' => (string) $folder_path,
		);
	}

	/**
	 * Where a renamed or moved item was before.
	 *
	 * @return array
	 */
	public static function define_previous_location_tokens() {
		return array(
			array(
				'tokenId'   => 'PREVIOUS_PATH',
				'tokenName' => esc_html_x( 'Previous path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PREVIOUS_NAME',
				'tokenName' => esc_html_x( 'Previous name', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PREVIOUS_FOLDER',
				'tokenName' => esc_html_x( 'Previous folder path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param string $previous_path The item's path before the rename or move.
	 *
	 * @return array
	 */
	public static function hydrate_previous_location_tokens( $previous_path ) {
		$previous_path = (string) $previous_path;

		if ( '' === $previous_path ) {
			return array(
				'PREVIOUS_PATH'   => '',
				'PREVIOUS_NAME'   => '',
				'PREVIOUS_FOLDER' => '',
			);
		}

		return array(
			'PREVIOUS_PATH'   => $previous_path,
			'PREVIOUS_NAME'   => basename( $previous_path ),
			'PREVIOUS_FOLDER' => dirname( $previous_path ),
		);
	}
}
