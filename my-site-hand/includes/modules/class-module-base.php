<?php
/**
 * Abstract base class for all ability modules.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

use MySiteHand\Abilities_Registry;

/**
 * Module Base abstract class.
 *
 * All ability modules extend this class. Provides helper methods for
 * registering abilities, checking enabled state, and formatting results.
 */
abstract class Module_Base {

	/**
	 * Abilities registry instance.
	 *
	 * @var Abilities_Registry
	 */
	protected Abilities_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Abilities_Registry $registry The abilities registry.
	 */
	public function __construct( Abilities_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Get the module's machine-readable name.
	 *
	 * @return string Module name (e.g. 'content', 'seo').
	 */
	abstract public function get_module_name(): string;

	/**
	 * Register all abilities for this module.
	 *
	 * Called only when the module is enabled.
	 *
	 * @return void
	 */
	abstract public function register_abilities(): void;

	/**
	 * Boot the module — registers abilities if enabled.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->is_enabled() ) {
			$this->register_abilities();
		}
	}

	/**
	 * Check if this module is enabled in plugin settings.
	 *
	 * @return bool
	 */
	protected function is_enabled(): bool {
		$default = [ 'content', 'seo', 'diagnostics', 'media', 'users', 'woocommerce' ];
		$enabled = get_option( 'mysitehand_enabled_modules', $default );

		// If it's set but empty, we still want to default to enabled for core functionality
		if ( empty( $enabled ) ) {
			$enabled = $default;
		}

		return in_array( $this->get_module_name(), (array) $enabled, true );
	}

	/**
	 * Get the count of abilities registered by this module.
	 *
	 * @return int
	 */
	public function get_ability_count(): int {
		$all     = $this->registry->get_all();
		$prefix  = 'my-site-hand/';
		$count   = 0;

		foreach ( array_keys( $all ) as $name ) {
			// We can't easily know which module registered which ability, so we
			// expose a method modules can override.
			if ( in_array( $name, $this->get_ability_names(), true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get all ability names registered by this module.
	 *
	 * Override in each module to return the list of ability names.
	 *
	 * @return array<string>
	 */
	public function get_ability_names(): array {
		return [];
	}

	/**
	 * Register an ability via the central registry.
	 *
	 * @param string               $name Ability name.
	 * @param array<string, mixed> $args Ability definition.
	 * @return void
	 */
	protected function register( string $name, array $args ): void {
		$this->registry->register( $name, $args );
	}

	/**
	 * Build a success result array.
	 *
	 * @param mixed  $data    Result data.
	 * @param string $message Optional message.
	 * @return array{success: bool, data: mixed, message: string}
	 */
	protected function success( mixed $data, string $message = '' ): array {
		return [
			'success' => true,
			'data'    => $data,
			'message' => $message,
		];
	}

	/**
	 * Build an error WP_Error.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @return \WP_Error
	 */
	protected function error( string $message, string $code = 'error' ): \WP_Error {
		return new \WP_Error( $code, $message );
	}

	/**
	 * Check if the given user has a capability.
	 *
	 * When user_id is 0, checks the currently logged-in user.
	 *
	 * @param string $cap     Capability to check.
	 * @param int    $user_id User ID (0 = current user).
	 * @return bool
	 */
	protected function user_can( string $cap, int $user_id = 0 ): bool {
		if ( 0 === $user_id ) {
			return current_user_can( $cap );
		}
		$user = get_user_by( 'id', $user_id );
		return $user ? $user->has_cap( $cap ) : false;
	}
}




