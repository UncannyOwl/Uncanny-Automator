<?php

namespace Uncanny_Automator;

/**
 * Class Incoming_Webhook.
 *
 * @package Uncanny_Automator
 */
class Incoming_Webhook
{

    public $rest_endpoint_base = 'automator/';
    public $endpoint;
    public $validation;
    public $action;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct( $endpoint, $validation, $action )
    {
        $this->endpoint = $endpoint;
        $this->validation = $validation;
        $this->action = $action;
        add_action('rest_api_init', array( $this, 'rest_api_endpoint' ));
    }

    public function get_rest_url()
    {
        $url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . '/' . $this->rest_endpoint_base . $this->endpoint;
        error_log('Rest URL: ' . $url);
        return $url;
    }

    /**
     * rest_api_endpoint
     * Create an endpoint so that the process can run at background
     * https://site_domain/wp-json/uap/v2/automator/
     *
     * @return void
     */
    public function rest_api_endpoint()
    {
        register_rest_route(
            AUTOMATOR_REST_API_END_POINT,
            '/automator/' . $this->endpoint,
            array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'process_rest_call' ),
            'permission_callback' => array( $this, 'validate_rest_call' )
            )
        );
    }

    /**
     * validate_rest_call
     *
     * @param  mixed $request
     * @return void
     */
    public function validate_rest_call( $request )
    {
        return call_user_func($this->validation, $request);
    }

    /**
     * process_rest_call
     *
     * @param  mixed $request
     * @return void
     */
    public function process_rest_call( $request )
    {
        return call_user_func($this->action, $request);
    }
}
