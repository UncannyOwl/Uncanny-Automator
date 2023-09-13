<?php
namespace Uncanny_Automator\Utilities;

/**
 * Automator_Http_Response_Code
 *
 * @package Uncanny_Automator\Utilities\Automator_Http_Response_Code
 * @since 5.0.1
 */
class Automator_Http_Response_Code {

	/**
	 * Accepts HTTP Response Code and return the corresponding HTTP Response Text.
	 *
	 * @param int $response_code The HTTP Response code
	 *
	 * @return string The HTTP Response Text
	 */
	public static function text( $response_code = 0 ) {

		// List of HTTP status codes.
		$status_list = array(
			'100' => 'Continue',
			'101' => 'Switching Protocols',
			'200' => 'OK',
			'201' => 'Created',
			'202' => 'Accepted',
			'203' => 'Non-Authoritative Information',
			'204' => 'No Content',
			'205' => 'Reset Content',
			'206' => 'Partial Content',
			'300' => 'Multiple Choices',
			'302' => 'Found',
			'303' => 'See Other',
			'304' => 'Not Modified',
			'305' => 'Use Proxy',
			'400' => 'Bad Request',
			'401' => 'Unauthorized',
			'402' => 'Payment Required',
			'403' => 'Forbidden',
			'404' => 'Not Found',
			'405' => 'Method Not Allowed',
			'406' => 'Not Acceptable',
			'407' => 'Proxy Authentication Required',
			'408' => 'Request Timeout',
			'409' => 'Conflict',
			'410' => 'Gone',
			'411' => 'Length Required',
			'412' => 'Precondition Failed',
			'413' => 'Request Entity Too Large',
			'414' => 'Request-URI Too Long',
			'415' => 'Unsupported Media Type',
			'416' => 'Requested Range Not Satisfiable',
			'417' => 'Expectation Failed',
			'418' => "I'm a teapot",
			'421' => 'Misdirected Request',
			'422' => 'Unprocessable Content',
			'423' => 'Locked',
			'424' => 'Failed Dependency',
			'425' => 'Too Early',
			'426' => 'Upgrade Required',
			'428' => 'Precondition Required',
			'429' => 'Too Many Requests',
			'431' => 'Request Header Fields Too Large',
			'451' => 'Unavailable For Legal Reasons',
			'500' => 'Internal Server Error',
			'501' => 'Not Implemented',
			'502' => 'Bad Gateway',
			'503' => 'Service Unavailable',
			'504' => 'Gateway Timeout',
			'505' => 'HTTP Version Not Supported',
		);

		// Caste the status code to a string.
		$code = (string) $response_code;

		// Determine if it exists in the array.
		if ( array_key_exists( $code, $status_list ) ) {
			// Return the status text
			return $status_list[ $response_code ];
		} else {
			// If it doesn't exists, degrade by returning the code.
			return $code;
		}

	}

}
