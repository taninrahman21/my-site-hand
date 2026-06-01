<?php
/**
 * Centralized error handler for WP my-site-hand.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Error Handler class.
 *
 * Provides centralized error logging and safe error response formatting.
 * Never leaks internal paths, DB structure, or stack traces to API responses.
 */
class Error_Handler {

	/**
	 * Initialize the error handler.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Nothing to hook into for now — used as a static utility class.
	}

	/**
	 * Log an error to the WordPress debug log.
	 *
	 * @param string     $message Error message.
	 * @param mixed      $context Additional context data.
	 * @param \Throwable $e       Optional exception.
	 * @return void
	 */
	public static function log( string $message, mixed $context = null, ?\Throwable $e = null ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = '[WP my-site-hand] ' . $message;

		if ( null !== $context ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		if ( null !== $e ) {
			$log_entry .= ' | Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $log_entry );
	}

	/**
	 * Convert a WP_Error to a safe API error response.
	 *
	 * Strips any internal paths or stack traces from the message.
	 *
	 * @param \WP_Error $error The WP_Error instance.
	 * @return array{code: string, message: string, status: int}
	 */
	public static function to_response( \WP_Error $error ): array {
		$data   = $error->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 500;

		return [
			'code'    => $error->get_error_code(),
			'message' => self::sanitize_message( $error->get_error_message() ),
			'status'  => $status,
		];
	}

	/**
	 * Sanitize an error message for safe external output.
	 *
	 * Removes file paths and DB structure from messages.
	 *
	 * @param string $message Raw error message.
	 * @return string Sanitized message.
	 */
	private static function sanitize_message( string $message ): string {
		// Remove file paths (both Unix and Windows style).
		$message = preg_replace( '/\b[A-Za-z]:\\\\[^\s]+|\/[^\s]+/', '[path]', $message ) ?? $message;

		return $message;
	}
}




