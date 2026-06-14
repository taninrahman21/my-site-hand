<?php
/**
 * Helper functions for the AI Assistant feature.
 *
 * Provides reversible encryption helpers used to store the external AI
 * provider API key at rest. The decrypted key is never logged or exposed
 * to the browser.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mysitehand_encrypt_api_key' ) ) {
	/**
	 * Encrypt an API key for storage.
	 *
	 * Uses AES-256-CBC with the WordPress 'auth' salt as the key. A random IV
	 * is generated for every call and prepended to the cipher text. The result
	 * is base64-encoded for safe storage in the options table.
	 *
	 * @param string $key Plain-text API key.
	 * @return string Base64-encoded "iv::ciphertext", or empty string on failure / empty input.
	 */
	function mysitehand_encrypt_api_key( string $key ): string {
		if ( '' === $key ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// OpenSSL unavailable — refuse to store the key in plain text.
			return '';
		}

		$cipher  = 'AES-256-CBC';
		$enc_key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv_len  = openssl_cipher_iv_length( $cipher );

		if ( false === $iv_len ) {
			return '';
		}

		$iv         = openssl_random_pseudo_bytes( $iv_len );
		$ciphertext = openssl_encrypt( $key, $cipher, $enc_key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		// Store IV prepended to the cipher text, base64-encoded.
		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}

if ( ! function_exists( 'mysitehand_decrypt_api_key' ) ) {
	/**
	 * Decrypt a stored API key.
	 *
	 * @param string $encrypted Base64-encoded value produced by mysitehand_encrypt_api_key().
	 * @return string Plain-text API key, or empty string on failure / empty input.
	 */
	function mysitehand_decrypt_api_key( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$cipher = 'AES-256-CBC';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( $encrypted, true );

		if ( false === $raw ) {
			return '';
		}

		$enc_key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv_len  = openssl_cipher_iv_length( $cipher );

		if ( false === $iv_len || strlen( $raw ) <= $iv_len ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_len );
		$ciphertext = substr( $raw, $iv_len );

		$plaintext = openssl_decrypt( $ciphertext, $cipher, $enc_key, OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? '' : $plaintext;
	}
}
