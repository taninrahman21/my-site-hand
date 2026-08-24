<?php
/**
 * IP Utilities class.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * IP Utilities class.
 *
 * Provides shared logic for IP address resolution and validation against allowlists.
 */
class Ip_Utils {

	/**
	 * Get the client's real IP address.
	 *
	 * Checks proxy headers only if the site is configured to trust them.
	 *
	 * @return string IP address.
	 */
	public static function get_client_ip(): string {
		$trust_proxy = (bool) get_option( 'mysitehand_trust_proxy', false );

		if ( $trust_proxy ) {
			$ip_keys = [
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
			];

			foreach ( $ip_keys as $key ) {
				if ( isset( $_SERVER[ $key ] ) ) {
					$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
					// Handle comma-separated IPs (X-Forwarded-For).
					$ip = trim( explode( ',', $ip )[0] );
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Check if a given IP address is within the allowed IPs list.
	 *
	 * @param string      $request_ip  The IP address to check.
	 * @param string|null $allowed_ips Comma-separated list of allowed IPs or CIDR blocks.
	 * @return bool True if allowed, false otherwise.
	 */
	public static function is_ip_allowed( string $request_ip, ?string $allowed_ips ): bool {
		if ( empty( $allowed_ips ) ) {
			return true;
		}

		$ranges = array_filter( array_map( 'trim', explode( ',', $allowed_ips ) ) );

		if ( empty( $ranges ) ) {
			return true;
		}

		foreach ( $ranges as $range ) {
			if ( self::ip_in_range( $request_ip, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP is within a specific range (exact match or CIDR).
	 *
	 * @param string $ip    The IP address.
	 * @param string $range The range to check against.
	 * @return bool
	 */
	private static function ip_in_range( string $ip, string $range ): bool {
		// Exact match.
		if ( $ip === $range ) {
			return true;
		}

		// IPv4 CIDR match.
		if ( str_contains( $range, '/' ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			list( $subnet, $bits ) = explode( '/', $range, 2 );
			$subnet                = trim( $subnet );
			$bits                  = (int) trim( $bits );

			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );

			if ( false === $ip_long || false === $subnet_long || $bits < 0 || $bits > 32 ) {
				return false;
			}

			// Handle edge case of 0 bits.
			if ( 0 === $bits ) {
				return true;
			}

			$mask = -1 << ( 32 - $bits );
			$subnet_long &= $mask;

			return ( $ip_long & $mask ) === $subnet_long;
		}

		return false;
	}
}
