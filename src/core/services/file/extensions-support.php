<?php
namespace Uncanny_Automator\Services\File;

/**
 * Extension_Support.
 *
 * @package Uncanny_Automator\Services\File
 */
class Extension_Support {

	/**
	 * Common file extensions.
	 *
	 * @var string[]
	 */
	public static $common_file_extensions = array(
		'txt',   // Plain text file
		'pdf',   // Portable Document Format
		'doc',   // Microsoft Word document
		'docx',  // Microsoft Word document (XML-based)
		'xls',   // Microsoft Excel spreadsheet
		'xlsx',  // Microsoft Excel spreadsheet (XML-based)
		'ppt',   // Microsoft PowerPoint presentation
		'pptx',  // Microsoft PowerPoint presentation (XML-based)
		'odt',   // OpenDocument text document
		'ods',   // OpenDocument spreadsheet
		'jpg',   // JPEG image
		'jpeg',  // JPEG image
		'png',   // Portable Network Graphics
		'gif',   // Graphics Interchange Format
		'bmp',   // Bitmap image
		'tiff',  // Tagged Image File Format
		'zip',   // Compressed archive
		'rar',   // Compressed archive
		'7z',    // 7-Zip compressed archive
		'mp3',   // MP3 audio file
		'wav',   // WAV audio file
		'mp4',   // MP4 video file
		'avi',   // AVI video file
		'mov',   // QuickTime video file
		'csv',   // Comma-separated values
		'rtf',   // Rich Text Format
		'dmg',   // macOS Disk Image
		'app',   // macOS Application bundle (typically a directory, but sometimes zipped)
		'pkg',   // macOS Installer Package
		'pages', // Apple Pages document
		'numbers', // Apple Numbers spreadsheet
		'key',   // Apple Keynote presentation
	);
}
