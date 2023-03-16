<?php
namespace Uncanny_Automator;

/**
 * This class handles the chat/completions endpoint
 *
 * @since 4.12
 */
class Chat_Completions_Client {

	/**
	 * Basic parameters we send to our API for processing.
	 *
	 * @var array The parameters.
	 */
	protected $parameters = array(
		'action'         => '',
		'prompt'         => '',
		'temperature'    => 1,
		'max_tokens'     => '',
		'model'          => 'gpt-3.5-turbo',
		'system_content' => '',
		'access_token'   => '',
	);

	/**
	 * Instance of automator client.
	 *
	 * @var Api_Server The api server.
	 */
	protected $client = null;

	/**
	 * Instance of this integration helper.
	 *
	 * @var Open_AI_Helpers The helper.
	 */
	protected $helper = null;

	/**
	 * Sets the client and helper.
	 *
	 * @param Api_Server $client The client.
	 * @param Open_AI_Helpers $helper The helper class.
	 *
	 * @todo Move specific methods that communicate to our API to its own concern.
	 *
	 * @return self
	 */
	public function __construct( Api_Server $client, Open_AI_Helpers $helper ) {

		$this->client = $client;

		$this->helper = $helper;

		return $this;

	}

	/**
	 * Sets the basic parameters. Uses wp_parse_args to apply default.
	 *
	 * @param array $parameters The parameters.
	 *
	 * @return self
	 */
	public function set_parameters( $parameters = array() ) {

		$this->parameters = wp_parse_args( $parameters, $this->parameters );

		return $this;

	}

	/**
	 * Retrieve the parameters.
	 *
	 * @return array The parameters.
	 */
	public function get_parameters() {

		return $this->parameters;

	}

	/**
	 * Sends the request.
	 *
	 * @param $action_data The action data.
	 *
	 * @todo Move the api_request to a Client class that handles HTTP req/res.
	 *
	 * @throws Exception If something is wrong.
	 *
	 * @return array The response from API.
	 */
	public function send_request( $action_data = array() ) {

		return $this->helper->api_request( $this->get_parameters(), $action_data );

	}

}
