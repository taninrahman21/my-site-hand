<?php
/**
 * Authentication manager for WP my-site-hand.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Auth Manager class.
 *
 * Handles token generation, validation, revocation, and extraction from requests.
 * Tokens are stored as SHA-256 hashes — the raw token is shown only once at generation.
 */
class Auth_Manager {

	/**
	 * Generate a new API token.
	 *
	 * @param int                   $user_id User ID who owns this token.
	 * @param string                $label   Human-readable label.
	 * @param array<string, mixed>  $options Optional: abilities (array), expires_at (string|null).
	 * @return array{token: string, token_id: int} Raw token (show ONCE) + DB id.
	 */
	public function generate_token( int $user_id, string $label, array $options = [] ): array {
		global $wpdb;

		// Generate cryptographically secure token.
		$raw_token  = bin2hex( random_bytes( 32 ) );
		$token_hash = hash( 'sha256', $raw_token );

		$abilities   = $options['abilities'] ?? [];
		$expires_at  = $options['expires_at'] ?? null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'mysitehand_tokens',
			[
				'token_hash' => $token_hash,
				'label'      => sanitize_text_field( $label ),
				'user_id'    => $user_id,
				'abilities'  => wp_json_encode( $abilities ),
				'expires_at' => $expires_at,
				'is_active'  => 1,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d', '%s' ]
		);

		$token_id = (int) $wpdb->insert_id;

		return [
			'token'    => $raw_token,
			'token_id' => $token_id,
		];
	}

	/**
	 * Validate a raw token from an incoming request.
	 *
	 * @param string $raw_token The raw bearer token.
	 * @return array<string, mixed>|\WP_Error Token record on success, WP_Error on failure.
	 */
	public function validate_token( string $raw_token ): array|\WP_Error {
		global $wpdb;

		if ( empty( $raw_token ) ) {
			return new \WP_Error( 'invalid_token', __( 'No token provided.', 'my-site-hand' ), [ 'status' => 401 ] );
		}

		$token_hash = hash( 'sha256', $raw_token );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mysitehand_tokens WHERE token_hash = %s AND is_active = 1",
				$token_hash
			),
			ARRAY_A
		);

		if ( ! $token ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid or revoked token.', 'my-site-hand' ), [ 'status' => 401 ] );
		}

		// Check expiry.
		if ( ! empty( $token['expires_at'] ) ) {
			$expires = strtotime( $token['expires_at'] );
			if ( $expires && $expires < time() ) {
				return new \WP_Error( 'token_expired', __( 'Token has expired.', 'my-site-hand' ), [ 'status' => 401 ] );
			}
		}

		// Update last_used timestamp.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'mysitehand_tokens',
			[ 'last_used' => current_time( 'mysql' ) ],
			[ 'id' => $token['id'] ],
			[ '%s' ],
			[ '%d' ]
		);

		// Decode abilities JSON.
		$token['abilities'] = json_decode( $token['abilities'], true ) ?? [];

		return $token;
	}

	/**
	 * Revoke a token by setting is_active = 0.
	 *
	 * @param int $token_id           DB token ID.
	 * @param int $requesting_user_id User ID requesting revocation.
	 * @return bool True on success.
	 */
	public function revoke_token( int $token_id, int $requesting_user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mysitehand_tokens WHERE id = %d",
				$token_id
			),
			ARRAY_A
		);

		if ( ! $token ) {
			return false;
		}

		// Only allow if user owns token or has manage_options.
		if ( (int) $token['user_id'] !== $requesting_user_id && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'mysitehand_tokens',
			[ 'is_active' => 0 ],
			[ 'id' => $token_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a token permanently.
	 *
	 * @param int $token_id           DB token ID.
	 * @param int $requesting_user_id User ID requesting deletion.
	 * @return bool True on success.
	 */
	public function delete_token( int $token_id, int $requesting_user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mysitehand_tokens WHERE id = %d",
				$token_id
			),
			ARRAY_A
		);

		if ( ! $token ) {
			return false;
		}

		if ( (int) $token['user_id'] !== $requesting_user_id && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			$wpdb->prefix . 'mysitehand_tokens',
			[ 'id' => $token_id ],
			[ '%d' ]
		);
	}

	/**
	 * List all tokens for a user (without exposing hashes).
	 *
	 * @param int $user_id User ID. Pass 0 to list all (requires manage_options).
	 * @return array<int, array<string, mixed>>
	 */
	public function list_tokens( int $user_id ): array {
		global $wpdb;

		if ( 0 === $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$tokens = $wpdb->get_results(
				"SELECT id, label, user_id, abilities, expires_at, last_used, is_active, created_at FROM {$wpdb->prefix}mysitehand_tokens ORDER BY created_at DESC",
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$tokens = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, label, user_id, abilities, expires_at, last_used, is_active, created_at FROM {$wpdb->prefix}mysitehand_tokens WHERE user_id = %d ORDER BY created_at DESC",
					$user_id
				),
				ARRAY_A
			);
		}

		if ( ! $tokens ) {
			return [];
		}

		// Decode abilities JSON for each token.
		foreach ( $tokens as &$token ) {
			$token['abilities'] = json_decode( $token['abilities'], true ) ?? [];
		}
		unset( $token );

		return $tokens;
	}

	/**
	 * Get the count of active tokens expiring within a specific number of days.
	 *
	 * @param int $days Number of days to check.
	 * @return int Count of expiring tokens.
	 */
	public function get_expiring_count( int $days = 30 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_tokens WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at BETWEEN %s AND %s",
				current_time( 'mysql', 1 ),
				gmdate( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days', current_time( 'timestamp', 1 ) ) )
			)
		);
	}

	/**
	 * Delete all expired tokens.
	 *
	 * @return int Number of tokens deleted.
	 */
	public function delete_expired_tokens(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			"DELETE FROM {$wpdb->prefix}mysitehand_tokens WHERE expires_at IS NOT NULL AND expires_at < NOW()"
		);

		return (int) $result;
	}

	/**
	 * Extract raw token from the current HTTP request.
	 *
	 * Checks Authorization: Bearer {token} header first,
	 * then falls back to ?token= query param.
	 *
	 * @return string|null Raw token or null if not found.
	 */
	public function extract_token_from_request(): ?string {
		// Try Authorization header first.
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) )
			: '';

		// Some servers use REDIRECT_HTTP_AUTHORIZATION.
		if ( empty( $auth_header ) && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth_header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( ! empty( $auth_header ) && str_starts_with( $auth_header, 'Bearer ' ) ) {
			return substr( $auth_header, 7 );
		}

		// Fall back to query param (for SSE clients).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['token'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET['token'] ) );
		}

		return null;
	}

	/**
	 * Authenticate the current request.
	 *
	 * Extracts and validates the bearer token. Can be used as a filter callback.
	 *
	 * @return array<string, mixed>|\WP_Error Token record or WP_Error.
	 */
	public function authenticate_request(): array|\WP_Error {
		$raw_token = $this->extract_token_from_request();

		if ( null === $raw_token ) {
			return new \WP_Error( 'missing_token', __( 'Authorization token is required.', 'my-site-hand' ), [ 'status' => 401 ] );
		}

		return $this->validate_token( $raw_token );
	}

	/**
	 * Get token record by ID (without hash).
	 *
	 * @param int $token_id Token ID.
	 * @return array<string, mixed>|null
	 */
	public function get_token( int $token_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, label, user_id, abilities, expires_at, last_used, is_active, created_at FROM {$wpdb->prefix}mysitehand_tokens WHERE id = %d",
				$token_id
			),
			ARRAY_A
		);

		if ( ! $token ) {
			return null;
		}

		$token['abilities'] = json_decode( $token['abilities'], true ) ?? [];

		return $token;
	}

	/**
	 * Delete all tokens (admin use for reset).
	 *
	 * @return int Number deleted.
	 */
	public function delete_all_tokens(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( "DELETE FROM {$wpdb->prefix}mysitehand_tokens" );
	}
}




