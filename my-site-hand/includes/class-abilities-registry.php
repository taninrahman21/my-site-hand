<?php
/**
 * Abilities Registry — central registry for all my-site-hand abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Abilities Registry class.
 *
 * Manages registration, validation, and execution of all my-site-hand abilities.
 * An "ability" is a named, typed, permission-gated RPC-style operation that
 * can be executed locally or via MCP.
 */
class Abilities_Registry {

	/**
	 * Registered abilities.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $abilities = [];

	/**
	 * Known annotation keys.
	 *
	 * @var array<string>
	 */
	private const KNOWN_ANNOTATION_KEYS = [
		'readonly',
		'destructive',
		'idempotent',
		'meta',
	];

	/**
	 * Register an ability.
	 *
	 * @param string                $name The unique ability name (e.g. 'my-site-hand/list-posts').
	 * @param array<string, mixed>  $args Ability definition:
	 *   - label           (string, required) Human-friendly name.
	 *   - description     (string, required)
	 *   - execute_callback (callable, required)
	 *   - input_schema    (array, optional) JSON Schema-style array
	 *   - permission_callback (callable, optional)
	 *   - annotations     (array, optional)
	 * @return bool True on success, false if already registered.
	 */
	public function register( string $name, array $args ): bool {
		// Validate required fields.
		if ( empty( $args['label'] ) || empty( $args['description'] ) || ! is_callable( $args['execute_callback'] ?? null ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: ability name */
					esc_html__( 'Ability "%s" must have a label, description and a callable execute_callback.', 'my-site-hand' ),
					esc_html( $name )
				),
				'1.0.0'
			);
			return false;
		}

		// Check for duplicate registration.
		if ( isset( $this->abilities[ $name ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: ability name */
					esc_html__( 'Ability "%s" is already registered.', 'my-site-hand' ),
					esc_html( $name )
				),
				'1.0.0'
			);
			return false;
		}

		// Validate permission_callback if provided.
		if ( isset( $args['permission_callback'] ) && ! is_callable( $args['permission_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: ability name */
					esc_html__( 'Ability "%s" has a non-callable permission_callback.', 'my-site-hand' ),
					esc_html( $name )
				),
				'1.0.0'
			);
			return false;
		}

		// Normalize the ability definition.
		$this->abilities[ $name ] = [
			'name'                => $name,
			'label'               => (string) $args['label'],
			'description'         => (string) $args['description'],
			'execute_callback'    => $args['execute_callback'],
			'input_schema'        => $args['input_schema'] ?? [],
			'permission_callback' => $args['permission_callback'] ?? null,
			'annotations'         => $args['annotations'] ?? [],
		];

		return true;
	}

	/**
	 * Execute an ability by name.
	 *
	 * @param string $name    Ability name.
	 * @param array  $input   Input parameters.
	 * @param int    $user_id Executing user ID (0 for machine/token auth).
	 * @return mixed Result from execute_callback, or WP_Error on failure.
	 */
	public function execute( string $name, array $input, int $user_id = 0 ): mixed {
		// Check ability exists.
		if ( ! isset( $this->abilities[ $name ] ) ) {
			return new \WP_Error(
				'ability_not_found',
				sprintf(
					/* translators: %s: ability name */
					__( 'Ability "%s" is not registered.', 'my-site-hand' ),
					$name
				),
				[ 'status' => 404 ]
			);
		}

		$ability = $this->abilities[ $name ];

		// Check permission.
		if ( is_callable( $ability['permission_callback'] ) ) {
			$permitted = call_user_func( $ability['permission_callback'], $user_id, $input );
			if ( ! $permitted || ( $permitted instanceof \WP_Error ) ) {
				return new \WP_Error(
					'forbidden',
					__( 'You do not have permission to execute this ability.', 'my-site-hand' ),
					[ 'status' => 403 ]
				);
			}
		}

		// Validate input against schema.
		$validated_input = $this->validate_input( $input, $ability['input_schema'] );
		if ( is_wp_error( $validated_input ) ) {
			return $validated_input;
		}

		// Apply input filter.
		$validated_input = apply_filters( 'my_site_hand_input', $validated_input, $name );

		do_action( 'my_site_hand_before_execute', $name, $validated_input, $user_id );

		// Execute the ability.
		try {
			$result = call_user_func( $ability['execute_callback'], $validated_input );
		} catch ( \Throwable $e ) {
			$result = new \WP_Error(
				'execution_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}

		// Apply result filter.
		$result = apply_filters( 'my_site_hand_result', $result, $name );

		do_action( 'my_site_hand_after_execute', $name, $result, $user_id );

		return $result;
	}

	/**
	 * Get all registered abilities.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all(): array {
		return $this->abilities;
	}

	/**
	 * Get only MCP-public abilities.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_mcp_public(): array {
		$disabled = (array) get_option( 'mysitehand_disabled_abilities', [] );
		
		$public = array_filter(
			$this->abilities,
			static function ( array $ability ) use ( $disabled ): bool {
				// Must be marked public AND not be disabled.
				return ! empty( $ability['annotations']['meta']['mcp']['public'] ) 
					&& ! in_array( $ability['name'], $disabled, true );
			}
		);

		return apply_filters( 'my_site_hand_mcp_public_abilities', $public );
	}

	/**
	 * Get a single ability by name.
	 *
	 * @param string $name Ability name.
	 * @return array<string, mixed>|null
	 */
	public function get( string $name ): ?array {
		return $this->abilities[ $name ] ?? null;
	}

	/**
	 * Convert an ability definition to an MCP tool schema.
	 *
	 * @param string $name Ability name.
	 * @return array<string, mixed> MCP tool schema.
	 */
	public function get_as_mcp_tool_schema( string $name ): array {
		$ability = $this->get( $name );
		if ( null === $ability ) {
			return [];
		}
		$schema = [
			'name'        => $name,
			'description' => $ability['description'],
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
				'required'   => [],
			],
		];
		if ( ! empty( $ability['input_schema']['properties'] ) ) {
			$schema['inputSchema']['properties'] = $ability['input_schema']['properties'];
		}

		if ( ! empty( $ability['input_schema']['required'] ) ) {
			$schema['inputSchema']['required'] = $ability['input_schema']['required'];
		}

		return $schema;
	}

	/**
	 * Get all MCP-public abilities as MCP tool schemas.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_as_mcp_tool_schemas(): array {
		$public  = $this->get_mcp_public();
		$schemas = [];

		foreach ( array_keys( $public ) as $name ) {
			$schemas[] = $this->get_as_mcp_tool_schema( $name );
		}

		return $schemas;
	}

	/**
	 * Validate input array against a JSON Schema-style schema.
	 *
	 * @param array<string, mixed> $input  Input to validate.
	 * @param array<string, mixed> $schema JSON Schema definition.
	 * @return array<string, mixed>|\WP_Error Validated (and coerced) input or WP_Error.
	 */
	private function validate_input( array $input, array $schema ): array|\WP_Error {
		if ( empty( $schema ) ) {
			return $input;
		}

		$properties = $schema['properties'] ?? [];
		$required   = $schema['required'] ?? [];
		$errors     = [];
		$validated  = [];

		// Check required fields.
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				$errors[] = sprintf(
					/* translators: %s: field name */
					__( 'Required field "%s" is missing.', 'my-site-hand' ),
					$field
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'invalid_input',
				implode( ' ', $errors ),
				[ 'status' => 400, 'errors' => $errors ]
			);
		}

		// Apply defaults and coerce types.
		foreach ( $properties as $field => $def ) {
			if ( array_key_exists( $field, $input ) ) {
				$value = $input[ $field ];

				// Type coercion.
				if ( isset( $def['type'] ) ) {
					$value = $this->coerce_type( $value, $def['type'] );
				}

				// Enum validation.
				if ( isset( $def['enum'] ) && ! in_array( $value, $def['enum'], true ) ) {
					$errors[] = sprintf(
						/* translators: 1: field name 2: allowed values */
						__( 'Field "%1$s" must be one of: %2$s.', 'my-site-hand' ),
						$field,
						implode( ', ', array_map( 'strval', $def['enum'] ) )
					);
				}

				// Integer range validation.
				if ( 'integer' === ( $def['type'] ?? '' ) ) {
					if ( isset( $def['minimum'] ) && $value < $def['minimum'] ) {
						$value = $def['minimum'];
					}
					if ( isset( $def['maximum'] ) && $value > $def['maximum'] ) {
						$value = $def['maximum'];
					}
				}

				$validated[ $field ] = $value;
			} elseif ( array_key_exists( 'default', $def ) ) {
				$validated[ $field ] = $def['default'];
			}
		}

		// Pass through any extra fields not in schema (allow extensions).
		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, $validated ) ) {
				$validated[ $key ] = $value;
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'invalid_input',
				implode( ' ', $errors ),
				[ 'status' => 400, 'errors' => $errors ]
			);
		}

		return $validated;
	}

	/**
	 * Coerce a value to the given JSON Schema type.
	 *
	 * @param mixed  $value The value to coerce.
	 * @param string $type  JSON Schema type string.
	 * @return mixed Coerced value.
	 */
	private function coerce_type( mixed $value, string $type ): mixed {
		return match ( $type ) {
			'integer' => (int) $value,
			'number'  => (float) $value,
			'boolean' => (bool) $value,
			'string'  => (string) $value,
			'array'   => is_array( $value ) ? $value : [ $value ],
			default   => $value,
		};
	}
}




