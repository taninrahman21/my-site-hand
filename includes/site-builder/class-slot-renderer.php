<?php
/**
 * Fills {{slot}} tokens in starter site markup.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Slot_Renderer class.
 *
 * The one place where user, AI or MCP supplied values meet block markup.
 * Values are always plain data: they are sanitized by slot type, then escaped
 * for the exact spot the token sits in. Nobody supplies markup, so no value
 * can add a block, break a block comment or inject a script.
 *
 * Token forms:
 *   {{group.field}}   a text, richtext, url or color slot
 *   {{image:name}}    an image slot (its id includes the "image:" prefix)
 *   {{page:slug}}     the URL of a page in the same starter site (not a slot)
 *
 * Escaping contexts:
 *   json  inside a block comment's attribute JSON
 *   attr  inside an HTML tag (attribute value)
 *   html  HTML text content
 */
class Slot_Renderer {

	/**
	 * Matches one token; group 1 is the token body.
	 *
	 * @var string
	 */
	public const TOKEN_PATTERN = '/\{\{\s*([a-z0-9_.:-]+)\s*\}\}/i';

	/**
	 * Matches one block comment delimiter, opening, closing or void.
	 *
	 * @var string
	 */
	private const BLOCK_COMMENT_PATTERN = '/<!--\s+\/?wp:[a-z0-9\/_-]+.*?-->/s';

	/**
	 * Slot types and the contexts each may appear in.
	 *
	 * Richtext is HTML, so it can only ever stand in for text content.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const TYPE_CONTEXTS = [
		'text'     => [ 'html', 'attr', 'json' ],
		'richtext' => [ 'html' ],
		'url'      => [ 'html', 'attr', 'json' ],
		'image'    => [ 'attr', 'json' ],
		'color'    => [ 'attr', 'json' ],
	];

	/**
	 * Tags allowed in richtext slots: bold, italic, links and line breaks.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function richtext_allowed_html(): array {
		return [
			'strong' => [],
			'b'      => [],
			'em'     => [],
			'i'      => [],
			'br'     => [],
			'a'      => [ 'href' => true ],
		];
	}

	/**
	 * Sanitize one value for a slot.
	 *
	 * @param array<string, mixed> $slot  Slot definition from slots.json.
	 * @param mixed                $value Raw value from the editor, AI or MCP.
	 * @return string|int|null Clean value, or null when nothing usable is left
	 *                         (callers then fall back to the default).
	 */
	public static function sanitize_value( array $slot, $value ) {
		$type = (string) ( $slot['type'] ?? 'text' );
		$max  = isset( $slot['max'] ) ? max( 1, (int) $slot['max'] ) : 0;

		if ( is_array( $value ) || is_object( $value ) ) {
			return null;
		}

		switch ( $type ) {
			case 'richtext':
				$clean = trim( wp_kses( (string) $value, self::richtext_allowed_html() ) );
				// Never cut HTML mid-tag: an over-long value loses its
				// formatting before it loses any words.
				if ( $max && mb_strlen( wp_strip_all_tags( $clean ) ) > $max ) {
					$clean = self::truncate( wp_strip_all_tags( $clean ), $max );
				}
				break;

			case 'url':
				$clean = esc_url_raw( trim( (string) $value ) );
				break;

			case 'image':
				// An attachment id from the media library. Anything else means
				// "keep the bundled default".
				$id = absint( $value );
				return ( $id && wp_attachment_is_image( $id ) ) ? $id : null;

			case 'color':
				$clean = (string) sanitize_hex_color( (string) $value );
				break;

			case 'text':
			default:
				$clean = sanitize_text_field( (string) $value );
				if ( $max ) {
					$clean = self::truncate( $clean, $max );
				}
				break;
		}

		return '' === $clean ? null : $clean;
	}

	/**
	 * Sanitize a whole set of values against a schema.
	 *
	 * Unknown keys are dropped. Slots whose value sanitizes to nothing are
	 * left out, so they render their default.
	 *
	 * @param array<string, array<string, mixed>> $slots  Slots keyed by id.
	 * @param array<string, mixed>                $values Raw values keyed by slot id.
	 * @return array<string, string|int>
	 */
	public static function sanitize_values( array $slots, array $values ): array {
		$clean = [];

		foreach ( $values as $id => $value ) {
			$id = (string) $id;
			if ( ! isset( $slots[ $id ] ) ) {
				continue;
			}

			$sanitized = self::sanitize_value( $slots[ $id ], $value );
			if ( null !== $sanitized ) {
				$clean[ $id ] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * Replace every token in a piece of markup.
	 *
	 * @param string                              $markup  Block markup with tokens.
	 * @param array<string, array<string, mixed>> $slots   Slots keyed by id.
	 * @param array<string, string|int>           $values  Sanitized values keyed by slot id.
	 * @param array<string, mixed>                $context {
	 *     Rendering context.
	 *
	 *     @type callable              $asset_url Maps a package-relative path to a URL.
	 *     @type array<string, string> $page_urls Page slug => URL.
	 * }
	 * @return string
	 */
	public static function render( string $markup, array $slots, array $values, array $context ): string {
		$contexts = self::token_contexts( $markup );
		$offset   = 0;
		$output   = '';

		foreach ( $contexts as $token ) {
			$output .= substr( $markup, $offset, $token['offset'] - $offset );
			$output .= self::render_token( $token['name'], $token['context'], $slots, $values, $context );
			$offset  = $token['offset'] + strlen( $token['raw'] );
		}

		return $output . substr( $markup, $offset );
	}

	/**
	 * Find every token with its escaping context.
	 *
	 * @param string $markup Block markup.
	 * @return array<int, array{raw: string, name: string, offset: int, context: string}>
	 */
	public static function token_contexts( string $markup ): array {
		// Blank out block comments so their JSON braces and angle brackets
		// cannot confuse the tag scan below, but remember where they were.
		$comment_ranges = [];
		$masked         = $markup;

		if ( preg_match_all( self::BLOCK_COMMENT_PATTERN, $markup, $comments, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $comments[0] as $comment ) {
				$start            = (int) $comment[1];
				$length           = strlen( $comment[0] );
				$comment_ranges[] = [ $start, $start + $length ];
				$masked           = substr_replace( $masked, str_repeat( ' ', $length ), $start, $length );
			}
		}

		$tokens = [];

		if ( ! preg_match_all( self::TOKEN_PATTERN, $markup, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $tokens;
		}

		foreach ( $matches as $match ) {
			$offset  = (int) $match[0][1];
			$context = 'html';

			foreach ( $comment_ranges as $range ) {
				if ( $offset >= $range[0] && $offset < $range[1] ) {
					$context = 'json';
					break;
				}
			}

			if ( 'html' === $context ) {
				$before = substr( $masked, 0, $offset );
				$open   = strrpos( $before, '<' );
				$close  = strrpos( $before, '>' );

				if ( false !== $open && ( false === $close || $open > $close ) ) {
					$context = 'attr';
				}
			}

			$tokens[] = [
				'raw'     => $match[0][0],
				'name'    => strtolower( $match[1][0] ),
				'offset'  => $offset,
				'context' => $context,
			];
		}

		return $tokens;
	}

	/**
	 * Render one token.
	 *
	 * @param string                              $name    Token body.
	 * @param string                              $where   Escaping context.
	 * @param array<string, array<string, mixed>> $slots   Slots keyed by id.
	 * @param array<string, string|int>           $values  Sanitized values.
	 * @param array<string, mixed>                $context Rendering context.
	 * @return string
	 */
	private static function render_token( string $name, string $where, array $slots, array $values, array $context ): string {
		if ( str_starts_with( $name, 'page:' ) ) {
			$url = (string) ( $context['page_urls'][ substr( $name, 5 ) ] ?? home_url( '/' ) );
			return self::escape( $url, 'url', $where );
		}

		if ( ! isset( $slots[ $name ] ) ) {
			// The validator stops this from shipping; at runtime an unknown
			// token renders as nothing rather than as a raw {{token}}.
			return '';
		}

		$slot  = $slots[ $name ];
		$type  = (string) ( $slot['type'] ?? 'text' );
		$value = $values[ $name ] ?? null;

		if ( 'image' === $type ) {
			$url = $value ? (string) wp_get_attachment_image_url( (int) $value, 'large' ) : '';
			if ( '' === $url ) {
				$asset_url = $context['asset_url'] ?? null;
				$url       = is_callable( $asset_url ) ? (string) $asset_url( (string) ( $slot['default'] ?? '' ) ) : '';
			}
			return self::escape( $url, 'url', $where );
		}

		if ( null === $value || '' === $value ) {
			$value = self::sanitize_value( $slot, $slot['default'] ?? '' ) ?? '';
		}

		return self::escape( (string) $value, $type, $where );
	}

	/**
	 * Escape a clean value for its context.
	 *
	 * @param string $value Sanitized value.
	 * @param string $type  Slot type.
	 * @param string $where Escaping context.
	 * @return string
	 */
	private static function escape( string $value, string $type, string $where ): string {
		if ( 'url' === $type ) {
			$value = 'json' === $where ? esc_url_raw( $value ) : esc_url( $value );
		} elseif ( 'color' === $type ) {
			$value = (string) sanitize_hex_color( $value );
		} elseif ( 'richtext' === $type ) {
			// Only valid in text content (the validator enforces it). kses
			// again here so a value stored before sanitizing is still safe.
			return wp_kses( $value, self::richtext_allowed_html() );
		}

		if ( 'json' === $where ) {
			return self::escape_json_string( $value );
		}

		return 'attr' === $where ? esc_attr( $value ) : esc_html( $value );
	}

	/**
	 * Escape a string for use inside a JSON string in a block comment.
	 *
	 * Mirrors serialize_block_attributes(): the characters that could end the
	 * comment or confuse an HTML parser are written as unicode escapes.
	 *
	 * @param string $value Raw string.
	 * @return string JSON string contents, without the surrounding quotes.
	 */
	public static function escape_json_string( string $value ): string {
		$json = (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$json = substr( $json, 1, -1 );

		return str_replace(
			[ '--', '<', '>', '&', '\\"' ],
			[ '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ],
			$json
		);
	}

	/**
	 * Cut plain text to a length, on a word boundary when one is close.
	 *
	 * @param string $text Plain text.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	private static function truncate( string $text, int $max ): string {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > $max * 0.7 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t\n\r\0\x0B,;:-" );
	}
}
