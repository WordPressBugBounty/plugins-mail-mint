<?php
/**
 * Parser class.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-07-09 09:03:17
 * @modify date 2024-07-09 09:03:17
 * @package Mint\MRM\Internal\Parser
 */

namespace Mint\MRM\Internal\Parser;

/**
 * Class Parser
 *
 * The Parser class dynamically handles method calls by creating an instance of MergeTagParser.
 * It supports both instance and static method calls, delegating the calls to MergeTagParser.
 *
 * @since 1.13.4
 */
class Parser {
	/**
	 * Handle dynamic instance method calls.
	 *
	 * This method intercepts calls to undefined instance methods and delegates them to an instance of MergeTagParser.
	 *
	 * @param string $method The name of the method being called.
	 * @param array  $params The parameters passed to the method.
	 * @return mixed The result of the delegated method call.
	 *
	 * @since 1.13.4
	 */
	public function __call( $method, $params ) {
		$instance = new MergeTagParser();
		return call_user_func_array( array( $instance, $method ), $params );
	}

	/**
	 * Handle dynamic static method calls.
	 *
	 * This method intercepts calls to undefined static methods and delegates them to an instance of the Parser class.
	 *
	 * @param string $method The name of the method being called.
	 * @param array  $params The parameters passed to the method.
	 * @return mixed The result of the delegated method call.
	 *
	 * @since 1.13.4
	 */
	public static function __callStatic( $method, $params ) {
		$instance = new static();
		return call_user_func_array( array( $instance, $method ), $params );
	}
}
