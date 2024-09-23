<?php
/**
 * WPRemoteRequestHandler class for handling remote requests.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-05-20 09:30:00
 * @modify date 2024-05-20 11:03:17
 * @package Mint\App\Classes
 */

namespace Mint\App\Classes;

/**
 * Class WPRemoteRequestHandler
 *
 * Handles remote requests using wp_remote_request.
 *
 * @package Mint\App\Classes
 * @since 1.12.0
 */
class WPRemoteRequestHandler {

    /**
     * The allowed responses for the request.
     *
     * @var array $allowed_responses The allowed responses for the request.
     * @since 1.12.0
     */
	private $allowed_responses;

    /**
     * Constructor method.
     * Initializes the allowed responses for the request.
     *
     * @param array $allowed_responses The allowed responses for the request.
     * @since 1.12.0
     */
	public function __construct($allowed_responses = [200]) {
		$this->allowed_responses = $allowed_responses;
	}

    /**
     * Makes a remote request using wp_remote_request.
     *
     * @param string $url The URL to make the request to.
     * @param array $params The parameters to send with the request.
     * @param array $headers The headers to send with the request.
     * @param int $req_method The request method to use.
     * @return array The response from the request.
     * @since 1.12.0
     */
	public function make_wp_requests($url, $params = array(), $headers = array(), $req_method = 1) {
		$body = array(
			'response' => 500,
			'body'     => __('Curl Error', 'wp-marketing-automations'),
		);

		$args = array(
			'timeout'     => 45,
			'httpversion' => '1.0',
			'blocking'    => true,
			'body'        => $params,
		);

		if (is_array($headers) && count($headers) > 0) {
			$args['headers'] = $headers;
		}

		switch ($req_method) {
			case 2:
				$args['method'] = 'POST';
				break;
			case 3:
				$args['method'] = 'DELETE';
				break;
			case 4:
				$args['method'] = 'PUT';
				break;
			case 5:
				$args['method'] = 'PATCH';
				break;
			default:
				$args['method'] = 'GET';
				break;
		}

		$response = wp_remote_request($url, $args);

		if (!is_wp_error($response)) {
			$body    = wp_remote_retrieve_body($response);
			$headers = wp_remote_retrieve_headers($response);
			if ($this->is_json($body)) {
				$body = json_decode($body, true);
			}
			$body = maybe_unserialize($body);
			if (in_array($response['response']['code'], $this->allowed_responses, true)) {
				$response_code = 200;
			} else {
				$response_code = $response['response']['code'];
			}

			$body = array(
				'response' => intval($response_code),
				'body'     => $body,
				'headers'  => $headers,
			);

			return $body;
		}

		$body['body'] = [$response->get_error_message()];

		return $body;
	}

    /**
     * Checks if a string is a valid JSON.
     *
     * @param string $string The string to check.
     * @return bool True if the string is a valid JSON, false otherwise.
     * @since 1.12.0
     */
	public function is_json($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}
}
