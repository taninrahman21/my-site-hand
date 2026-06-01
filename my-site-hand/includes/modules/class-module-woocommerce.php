<?php
/**
 * WooCommerce module — product and order management abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module WooCommerce class.
 *
 * Provides abilities for managing WooCommerce products, orders, coupons,
 * and store analytics. Only boots when WooCommerce is active.
 */
class Module_Woocommerce extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'woocommerce';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/woo-list-products',
			'my-site-hand/woo-get-product',
			'my-site-hand/woo-update-product',
			'my-site-hand/woo-create-product',
			'my-site-hand/woo-delete-product',
			'my-site-hand/woo-list-orders',
			'my-site-hand/woo-get-order',
			'my-site-hand/woo-update-order-status',
			'my-site-hand/woo-store-summary',
			'my-site-hand/woo-list-coupons',
			'my-site-hand/woo-create-coupon',
			'my-site-hand/woo-list-customers',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Only boot if WooCommerce is active — runtime check.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		parent::boot();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_woo_list_products();
		$this->register_woo_get_product();
		$this->register_woo_update_product();
		$this->register_woo_create_product();
		$this->register_woo_delete_product();
		$this->register_woo_list_orders();
		$this->register_woo_get_order();
		$this->register_woo_update_order_status();
		$this->register_woo_store_summary();
		$this->register_woo_list_coupons();
		$this->register_woo_create_coupon();
		$this->register_woo_list_customers();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-list-products
	// -------------------------------------------------------------------------

	/**
	 * Register woo-list-products ability.
	 *
	 * @return void
	 */
	private function register_woo_list_products(): void {
		$this->register(
			'my-site-hand/woo-list-products',
			[
				'label'            => __( 'List Products', 'my-site-hand' ),
				'description'      => __( 'List WooCommerce products with filtering options.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'limit'      => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
						'offset'     => [ 'type' => 'integer', 'default' => 0 ],
						'category'   => [ 'type' => 'string', 'description' => 'Product category slug' ],
						'status'     => [ 'type' => 'string', 'default' => 'publish' ],
						'low_stock'  => [ 'type' => 'boolean', 'default' => false ],
						'search'     => [ 'type' => 'string' ],
						'sku'        => [ 'type' => 'string' ],
					],
				],
				'execute_callback' => [ $this, 'execute_woo_list_products' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-list-products.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_woo_list_products( array $input ): array {
		$args = [
			'limit'   => min( absint( $input['limit'] ?? 20 ), 100 ),
			'offset'  => absint( $input['offset'] ?? 0 ),
			'status'  => sanitize_text_field( $input['status'] ?? 'publish' ),
			'return'  => 'objects',
		];

		if ( ! empty( $input['category'] ) ) {
			$args['category'] = [ sanitize_text_field( $input['category'] ) ];
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['sku'] ) ) {
			$args['sku'] = sanitize_text_field( $input['sku'] );
		}

		if ( ! empty( $input['low_stock'] ) ) {
			$args['stock_status'] = 'onbackorder';
			// We'll filter for low stock after.
		}

		$products = wc_get_products( $args );
		$result   = [];

		foreach ( $products as $product ) {
			if ( ! ( $product instanceof \WC_Product ) ) {
				continue;
			}

			// Skip non-low-stock if filter active.
			if ( ! empty( $input['low_stock'] ) ) {
				$manage_stock = $product->get_manage_stock();
				$stock_qty    = $product->get_stock_quantity();
				if ( ! $manage_stock || $stock_qty > get_option( 'woocommerce_notify_low_stock_amount', 2 ) ) {
					continue;
				}
			}

			$categories = array_map(
				static fn( $term ) => $term->name,
				get_the_terms( $product->get_id(), 'product_cat' ) ?: []
			);

			$result[] = [
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'sku'            => $product->get_sku(),
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_quantity' => $product->get_stock_quantity(),
				'stock_status'   => $product->get_stock_status(),
				'categories'     => $categories,
				'type'           => $product->get_type(),
				'status'         => $product->get_status(),
				'url'            => get_permalink( $product->get_id() ),
			];
		}

		return [
			'products' => $result,
			'count'    => count( $result ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-get-product
	// -------------------------------------------------------------------------

	/**
	 * Register woo-get-product ability.
	 *
	 * @return void
	 */
	private function register_woo_get_product(): void {
		$this->register(
			'my-site-hand/woo-get-product',
			[
				'label'            => __( 'Get Product', 'my-site-hand' ),
				'description'      => __( 'Get a single WooCommerce product with all details.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'product_id' ],
					'properties' => [
						'product_id' => [ 'type' => 'integer' ],
					],
				],
				'execute_callback' => [ $this, 'execute_woo_get_product' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-get-product.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_get_product( array $input ): array|\WP_Error {
		$product_id = absint( $input['product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error( __( 'Product not found.', 'my-site-hand' ), 'not_found' );
		}

		$categories = array_map(
			static fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ],
			get_the_terms( $product_id, 'product_cat' ) ?: []
		);

		$data = [
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'on_sale'           => $product->is_on_sale(),
			'weight'            => $product->get_weight(),
			'dimensions'        => [
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			],
			'manage_stock'      => $product->get_manage_stock(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'stock_status'      => $product->get_stock_status(),
			'backorders'        => $product->get_backorders(),
			'categories'        => $categories,
			'url'               => get_permalink( $product_id ),
			'images'            => array_map(
				static fn( $id ) => wp_get_attachment_url( $id ),
				$product->get_gallery_image_ids()
			),
		];

		// Include variations if variable.
		if ( $product->is_type( 'variable' ) ) {
			/** @var \WC_Product_Variable $product */
			$variations = [];
			foreach ( $product->get_children() as $var_id ) {
				$variation = wc_get_product( $var_id );
				if ( $variation instanceof \WC_Product_Variation ) {
					$variations[] = [
						'id'            => $variation->get_id(),
						'attributes'    => $variation->get_variation_attributes(),
						'price'         => $variation->get_price(),
						'regular_price' => $variation->get_regular_price(),
						'stock_quantity' => $variation->get_stock_quantity(),
						'sku'           => $variation->get_sku(),
					];
				}
			}
			$data['variations'] = $variations;
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-update-product
	// -------------------------------------------------------------------------

	/**
	 * Register woo-update-product ability.
	 *
	 * @return void
	 */
	private function register_woo_update_product(): void {
		$this->register(
			'my-site-hand/woo-update-product',
			[
				'label'            => __( 'Update Product', 'my-site-hand' ),
				'description'      => __( 'Update WooCommerce product fields.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'product_id' ],
					'properties' => [
						'product_id'        => [ 'type' => 'integer' ],
						'price'             => [ 'type' => 'string' ],
						'sale_price'        => [ 'type' => 'string' ],
						'stock_quantity'    => [ 'type' => 'integer' ],
						'status'            => [ 'type' => 'string' ],
						'description'       => [ 'type' => 'string' ],
						'short_description' => [ 'type' => 'string' ],
						'sku'               => [ 'type' => 'string' ],
						'meta'              => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_products' );
					}
					return current_user_can( 'edit_products' );
				},
				'execute_callback' => [ $this, 'execute_woo_update_product' ],
				'annotations'      => [
					'idempotent' => true,
					'meta'       => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-update-product.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_update_product( array $input ): array|\WP_Error {
		$product = wc_get_product( absint( $input['product_id'] ) );

		if ( ! $product ) {
			return $this->error( __( 'Product not found.', 'my-site-hand' ), 'not_found' );
		}

		if ( isset( $input['price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $input['price'] ) );
		}
		if ( isset( $input['sale_price'] ) ) {
			$product->set_sale_price( sanitize_text_field( $input['sale_price'] ) );
		}
		if ( isset( $input['stock_quantity'] ) ) {
			$product->set_stock_quantity( absint( $input['stock_quantity'] ) );
			$product->set_manage_stock( true );
		}
		if ( isset( $input['status'] ) ) {
			$product->set_status( sanitize_text_field( $input['status'] ) );
		}
		if ( isset( $input['description'] ) ) {
			$product->set_description( wp_kses_post( $input['description'] ) );
		}
		if ( isset( $input['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $input['short_description'] ) );
		}
		if ( isset( $input['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}

		$product->save();

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $product->get_id(), sanitize_key( $key ), $value );
			}
		}

		return [
			'updated'    => true,
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-create-product
	// -------------------------------------------------------------------------

	/**
	 * Register woo-create-product ability.
	 *
	 * @return void
	 */
	private function register_woo_create_product(): void {
		$this->register(
			'my-site-hand/woo-create-product',
			[
				'label'               => __( 'Create Product', 'my-site-hand' ),
				'description'         => __( 'Create a new WooCommerce product.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'name' ],
					'properties' => [
						'name'        => [ 'type' => 'string' ],
						'type'        => [ 'type' => 'string', 'enum' => [ 'simple', 'variable', 'grouped' ], 'default' => 'simple' ],
						'price'       => [ 'type' => 'string' ],
						'sku'         => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'categories'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'status'      => [ 'type' => 'string', 'default' => 'publish' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'publish_products' );
					}
					return current_user_can( 'publish_products' );
				},
				'execute_callback'    => [ $this, 'execute_woo_create_product' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-create-product.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_create_product( array $input ): array|\WP_Error {
		$product = new \WC_Product_Simple();

		$product->set_name( sanitize_text_field( $input['name'] ) );
		$product->set_status( sanitize_text_field( $input['status'] ?? 'publish' ) );

		if ( ! empty( $input['price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $input['price'] ) );
		}
		if ( ! empty( $input['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}
		if ( ! empty( $input['description'] ) ) {
			$product->set_description( wp_kses_post( $input['description'] ) );
		}
		if ( ! empty( $input['categories'] ) ) {
			$product->set_category_ids( array_map( 'absint', $input['categories'] ) );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return $this->error( __( 'Failed to create product.', 'my-site-hand' ) );
		}

		return [
			'product_id' => $product_id,
			'url'        => get_permalink( $product_id ),
			'name'       => $product->get_name(),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-delete-product
	// -------------------------------------------------------------------------

	/**
	 * Register woo-delete-product ability.
	 *
	 * @return void
	 */
	private function register_woo_delete_product(): void {
		$this->register(
			'my-site-hand/woo-delete-product',
			[
				'label'               => __( 'Delete Product', 'my-site-hand' ),
				'description'         => __( 'Delete or trash a WooCommerce product.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'product_id' ],
					'properties' => [
						'product_id' => [ 'type' => 'integer' ],
						'force'      => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'delete_products' );
					}
					return current_user_can( 'delete_products' );
				},
				'execute_callback'    => [ $this, 'execute_woo_delete_product' ],
				'annotations'         => [
					'destructive' => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-delete-product.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_delete_product( array $input ): array|\WP_Error {
		$product_id = absint( $input['product_id'] );
		$force      = (bool) ( $input['force'] ?? false );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error( __( 'Product not found.', 'my-site-hand' ), 'not_found' );
		}

		$result = wp_delete_post( $product_id, $force );

		return [
			'deleted'    => (bool) $result,
			'method'     => $force ? 'deleted' : 'trashed',
			'product_id' => $product_id,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-list-orders
	// -------------------------------------------------------------------------

	/**
	 * Register woo-list-orders ability.
	 *
	 * @return void
	 */
	private function register_woo_list_orders(): void {
		$this->register(
			'my-site-hand/woo-list-orders',
			[
				'label'            => __( 'List Orders', 'my-site-hand' ),
				'description'      => __( 'List WooCommerce orders with filtering.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'limit'       => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
						'offset'      => [ 'type' => 'integer', 'default' => 0 ],
						'status'      => [ 'type' => 'string', 'description' => 'pending|processing|on-hold|completed|cancelled|refunded' ],
						'date_after'  => [ 'type' => 'string' ],
						'date_before' => [ 'type' => 'string' ],
						'customer_id' => [ 'type' => 'integer' ],
						'min_total'   => [ 'type' => 'number' ],
						'max_total'   => [ 'type' => 'number' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_shop_orders' );
					}
					return current_user_can( 'edit_shop_orders' );
				},
				'execute_callback' => [ $this, 'execute_woo_list_orders' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-list-orders.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_woo_list_orders( array $input ): array {
		$args = [
			'limit'  => min( absint( $input['limit'] ?? 20 ), 100 ),
			'offset' => absint( $input['offset'] ?? 0 ),
			'return' => 'objects',
		];

		if ( ! empty( $input['status'] ) ) {
			$args['status'] = 'wc-' . ltrim( sanitize_text_field( $input['status'] ), 'wc-' );
		}
		if ( ! empty( $input['date_after'] ) ) {
			$args['date_created'] = '>' . sanitize_text_field( $input['date_after'] );
		}
		if ( ! empty( $input['customer_id'] ) ) {
			$args['customer_id'] = absint( $input['customer_id'] );
		}

		$orders = wc_get_orders( $args );
		$result = [];

		foreach ( $orders as $order ) {
			if ( ! ( $order instanceof \WC_Order ) ) {
				continue;
			}

			$total = (float) $order->get_total();

			if ( isset( $input['min_total'] ) && $total < (float) $input['min_total'] ) {
				continue;
			}
			if ( isset( $input['max_total'] ) && $total > (float) $input['max_total'] ) {
				continue;
			}

			$result[] = [
				'id'           => $order->get_id(),
				'status'       => $order->get_status(),
				'total'        => $order->get_formatted_order_total(),
				'customer'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'email'        => $order->get_billing_email(),
				'date_created' => $order->get_date_created()?->date( 'Y-m-d H:i:s' ),
				'items_count'  => count( $order->get_items() ),
				'payment_method' => $order->get_payment_method_title(),
			];
		}

		return [
			'orders' => $result,
			'count'  => count( $result ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-get-order
	// -------------------------------------------------------------------------

	/**
	 * Register woo-get-order ability.
	 *
	 * @return void
	 */
	private function register_woo_get_order(): void {
		$this->register(
			'my-site-hand/woo-get-order',
			[
				'label'               => __( 'Get Order', 'my-site-hand' ),
				'description'         => __( 'Get a WooCommerce order with all details.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'order_id' ],
					'properties' => [
						'order_id' => [ 'type' => 'integer' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_shop_orders' );
					}
					return current_user_can( 'edit_shop_orders' );
				},
				'execute_callback'    => [ $this, 'execute_woo_get_order' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-get-order.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_get_order( array $input ): array|\WP_Error {
		$order = wc_get_order( absint( $input['order_id'] ) );

		if ( ! $order ) {
			return $this->error( __( 'Order not found.', 'my-site-hand' ), 'not_found' );
		}

		$items = [];
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$items[] = [
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			];
		}

		return [
			'id'             => $order->get_id(),
			'status'         => $order->get_status(),
			'date_created'   => $order->get_date_created()?->date( 'Y-m-d H:i:s' ),
			'date_modified'  => $order->get_date_modified()?->date( 'Y-m-d H:i:s' ),
			'total'          => $order->get_total(),
			'subtotal'       => $order->get_subtotal(),
			'shipping_total' => $order->get_shipping_total(),
			'tax_total'      => $order->get_total_tax(),
			'discount_total' => $order->get_discount_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method_title(),
			'billing'        => [
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'address'    => $order->get_formatted_billing_address(),
			],
			'shipping'       => [
				'name'    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
				'address' => $order->get_formatted_shipping_address(),
				'method'  => $order->get_shipping_method(),
			],
			'items'          => $items,
			'notes'          => array_map(
				static fn( $note ) => [ 'date' => $note->comment_date, 'note' => $note->comment_content ],
				wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) ?: []
			),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-update-order-status
	// -------------------------------------------------------------------------

	/**
	 * Register woo-update-order-status ability.
	 *
	 * @return void
	 */
	private function register_woo_update_order_status(): void {
		$this->register(
			'my-site-hand/woo-update-order-status',
			[
				'label'               => __( 'Update Order Status', 'my-site-hand' ),
				'description'         => __( 'Update the status of a WooCommerce order.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'order_id', 'status' ],
					'properties' => [
						'order_id' => [ 'type' => 'integer' ],
						'status'   => [ 'type' => 'string' ],
						'note'     => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_shop_orders' );
					}
					return current_user_can( 'edit_shop_orders' );
				},
				'execute_callback'    => [ $this, 'execute_woo_update_order_status' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-update-order-status.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_update_order_status( array $input ): array|\WP_Error {
		$order = wc_get_order( absint( $input['order_id'] ) );

		if ( ! $order ) {
			return $this->error( __( 'Order not found.', 'my-site-hand' ), 'not_found' );
		}

		$new_status = sanitize_text_field( $input['status'] );
		$note       = sanitize_textarea_field( $input['note'] ?? '' );

		$order->update_status( $new_status, $note );

		return [
			'updated'  => true,
			'order_id' => $order->get_id(),
			'status'   => $order->get_status(),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-store-summary
	// -------------------------------------------------------------------------

	/**
	 * Register woo-store-summary ability.
	 *
	 * @return void
	 */
	private function register_woo_store_summary(): void {
		$this->register(
			'my-site-hand/woo-store-summary',
			[
				'label'               => __( 'Store Summary', 'my-site-hand' ),
				'description'         => __( 'Get a WooCommerce store summary for a given time period.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'period' => [ 'type' => 'string', 'enum' => [ 'today', 'week', 'month', 'year' ], 'default' => 'month' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_woocommerce' );
					}
					return current_user_can( 'manage_woocommerce' );
				},
				'execute_callback'    => [ $this, 'execute_woo_store_summary' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-store-summary.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_woo_store_summary( array $input ): array {
		global $wpdb;

		$period = sanitize_text_field( $input['period'] ?? 'month' );

		$date_from = match ( $period ) {
			'today'  => gmdate( 'Y-m-d 00:00:00' ),
			'week'   => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
			'year'   => gmdate( 'Y-01-01 00:00:00' ),
			default  => gmdate( 'Y-m-01 00:00:00' ),
		};

		$cache_key = 'woo_store_summary_' . $period;
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Revenue from completed/processing orders.
		$orders = wc_get_orders( [
			'status'         => [ 'wc-completed', 'wc-processing' ],
			'date_created'   => '>' . $date_from,
			'return'         => 'objects',
			'limit'          => -1,
		] );

		$revenue      = 0.0;
		$refunds      = 0.0;
		$customers    = [];

		foreach ( $orders as $order ) {
			if ( ! ( $order instanceof \WC_Order ) ) {
				continue;
			}
			$revenue += (float) $order->get_total();
			$customers[] = $order->get_customer_id();
		}

		// Refunds.
		$refund_orders = wc_get_orders( [
			'type'         => 'shop_order_refund',
			'date_created' => '>' . $date_from,
			'limit'        => -1,
			'return'       => 'objects',
		] );

		foreach ( $refund_orders as $refund ) {
			if ( $refund instanceof \WC_Order_Refund ) {
				$refunds += abs( (float) $refund->get_amount() );
			}
		}

		$orders_count       = count( $orders );
		$avg_order_value    = $orders_count > 0 ? round( $revenue / $orders_count, 2 ) : 0.0;
		$unique_customers   = array_unique( array_filter( $customers ) );

		// Pending orders.
		$pending_count = count( wc_get_orders( [ 'status' => 'wc-pending', 'return' => 'ids', 'limit' => -1 ] ) );

		// Low stock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$low_stock = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_manage_stock' AND pm2.meta_value = 'yes'
				WHERE pm.meta_key = '_stock' AND CAST(pm.meta_value AS SIGNED) <= %d",
				(int) get_option( 'woocommerce_notify_low_stock_amount', 2 )
			)
		);

		$summary = [
			'revenue'             => number_format( $revenue, 2 ),
			'orders_count'        => $orders_count,
			'average_order_value' => number_format( $avg_order_value, 2 ),
			'refunds_total'       => number_format( $refunds, 2 ),
			'unique_customers'    => count( $unique_customers ),
			'pending_orders'      => (int) $pending_count,
			'low_stock_products'  => (int) $low_stock,
			'period'              => $period,
			'from'                => $date_from,
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $summary, 15 * MINUTE_IN_SECONDS );

		return $summary;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-list-coupons
	// -------------------------------------------------------------------------

	/**
	 * Register woo-list-coupons ability.
	 *
	 * @return void
	 */
	private function register_woo_list_coupons(): void {
		$this->register(
			'my-site-hand/woo-list-coupons',
			[
				'label'               => __( 'List Coupons', 'my-site-hand' ),
				'description'         => __( 'List WooCommerce coupons.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit'  => [ 'type' => 'integer', 'default' => 20 ],
						'offset' => [ 'type' => 'integer', 'default' => 0 ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_woocommerce' );
					}
					return current_user_can( 'manage_woocommerce' );
				},
				'execute_callback'    => [ $this, 'execute_woo_list_coupons' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-list-coupons.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_woo_list_coupons( array $input ): array {
		$posts = get_posts( [
			'post_type'      => 'shop_coupon',
			'posts_per_page' => min( absint( $input['limit'] ?? 20 ), 100 ),
			'offset'         => absint( $input['offset'] ?? 0 ),
			'post_status'    => 'publish',
		] );

		$coupons = [];
		foreach ( $posts as $post ) {
			$coupon    = new \WC_Coupon( $post->ID );
			$coupons[] = [
				'id'              => $coupon->get_id(),
				'code'            => $coupon->get_code(),
				'type'            => $coupon->get_discount_type(),
				'amount'          => $coupon->get_amount(),
				'expiry_date'     => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
				'usage_count'     => $coupon->get_usage_count(),
				'usage_limit'     => $coupon->get_usage_limit(),
				'minimum_amount'  => $coupon->get_minimum_amount(),
			];
		}

		return [ 'coupons' => $coupons, 'count' => count( $coupons ) ];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-create-coupon
	// -------------------------------------------------------------------------

	/**
	 * Register woo-create-coupon ability.
	 *
	 * @return void
	 */
	private function register_woo_create_coupon(): void {
		$this->register(
			'my-site-hand/woo-create-coupon',
			[
				'label'               => __( 'Create Coupon', 'my-site-hand' ),
				'description'         => __( 'Create a new WooCommerce coupon code.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'code', 'type', 'amount' ],
					'properties' => [
						'code'           => [ 'type' => 'string' ],
						'type'           => [ 'type' => 'string', 'enum' => [ 'percent', 'fixed_cart', 'fixed_product' ] ],
						'amount'         => [ 'type' => 'string' ],
						'expiry_date'    => [ 'type' => 'string' ],
						'usage_limit'    => [ 'type' => 'integer' ],
						'minimum_amount' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_woocommerce' );
					}
					return current_user_can( 'manage_woocommerce' );
				},
				'execute_callback'    => [ $this, 'execute_woo_create_coupon' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-create-coupon.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_woo_create_coupon( array $input ): array|\WP_Error {
		$coupon = new \WC_Coupon();

		$coupon->set_code( sanitize_text_field( $input['code'] ) );
		$coupon->set_discount_type( sanitize_text_field( $input['type'] ) );
		$coupon->set_amount( sanitize_text_field( $input['amount'] ) );

		if ( ! empty( $input['expiry_date'] ) ) {
			$coupon->set_date_expires( sanitize_text_field( $input['expiry_date'] ) );
		}
		if ( ! empty( $input['usage_limit'] ) ) {
			$coupon->set_usage_limit( absint( $input['usage_limit'] ) );
		}
		if ( ! empty( $input['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( sanitize_text_field( $input['minimum_amount'] ) );
		}

		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return $this->error( __( 'Failed to create coupon.', 'my-site-hand' ) );
		}

		return [
			'coupon_id' => $coupon_id,
			'code'      => $coupon->get_code(),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_woo-list-customers
	// -------------------------------------------------------------------------

	/**
	 * Register woo-list-customers ability.
	 *
	 * @return void
	 */
	private function register_woo_list_customers(): void {
		$this->register(
			'my-site-hand/woo-list-customers',
			[
				'label'               => __( 'List Customers', 'my-site-hand' ),
				'description'         => __( 'List WooCommerce customers.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit'      => [ 'type' => 'integer', 'default' => 20 ],
						'search'     => [ 'type' => 'string' ],
						'date_after' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_woocommerce' );
					}
					return current_user_can( 'manage_woocommerce' );
				},
				'execute_callback'    => [ $this, 'execute_woo_list_customers' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute woo-list-customers.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_woo_list_customers( array $input ): array {
		$args = [
			'role'   => 'customer',
			'number' => min( absint( $input['limit'] ?? 20 ), 100 ),
		];

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*';
		}

		if ( ! empty( $input['date_after'] ) ) {
			$args['date_query'] = [
				[ 'after' => sanitize_text_field( $input['date_after'] ), 'inclusive' => false ],
			];
		}

		$users     = get_users( $args );
		$customers = [];

		foreach ( $users as $user ) {
			$customers[] = [
				'id'           => $user->ID,
				'email'        => $user->user_email,
				'username'     => $user->user_login,
				'display_name' => $user->display_name,
				'registered'   => $user->user_registered,
				'order_count'  => wc_get_customer_order_count( $user->ID ),
				'total_spent'  => wc_get_customer_total_spent( $user->ID ),
			];
		}

		return [ 'customers' => $customers, 'count' => count( $customers ) ];
	}
}




