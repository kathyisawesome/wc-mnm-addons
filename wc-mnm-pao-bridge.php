<?php
/**
 * Plugin Name: WooCommerce Mix and Match + Product Add-ons Bridge
 * Plugin URI:  http://www.woocommerce.com/products/woocommerce-mix-and-match-products?aff=5151&cid=4951026
 * Description: Adds Product Add-ons support to products in the container.
 * Version: 1.0.0-beta
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * WC requires at least: 3.8.0
 * WC tested up to: 3.8.0
 *
 *
 * Copyright: © 2019 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_MNM_Product_Addons {

	/**
	 * Plugin version.
	 */
	const VERSION = '1.0.0-beta-1';

	/**
	 * Min required versions.
	 *
	 * @var array
	 */
	static $required = array(
			'mnm'     => '1.7.0',
			'addons' => '3.0.0',
		);

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		
		// Load translation files.
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );

		// Check dependencies.
		if ( function_exists( 'WC_Mix_and_Match' )
			&& version_compare( WC_Mix_and_Match()->version, self::$required['mnm'] ) >= 0
			&& class_exists( 'WC_Product_Addons' ) 
			&& defined( 'WC_PRODUCT_ADDONS_VERSION' ) 
			&& version_compare( WC_PRODUCT_ADDONS_VERSION, self::$required[ 'addons' ] ) >= 0
		) {
			self::attach_hooks();
		} else {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
			
		}
		
	}

	/**
	 * All hooks and filters.
	 */
	public static function attach_hooks() {

		/**
		 * Admin.
		 */
		add_action( 'woocommerce_mnm_product_options', array( __CLASS__, 'additional_container_option') , 25, 2 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'process_meta' ), 20 );

		/**
		 * Display.
		 */
		add_action( 'woocommerce_mnm_child_item_details', array( __CLASS__, 'addons_support' ), 68, 2 );

		/**
		 * Cart.
		 */
		// Validate add to cart Addons.
		add_filter( 'woocommerce_mnm_item_add_to_cart_validation', array( __CLASS__, 'validate_child_item_addons' ), 10, 5 );

		// Add addons identifier to bundled item stamp.
		add_filter( 'woocommerce_bundled_item_cart_item_identifier', array( __CLASS__, 'bundled_item_addons_stamp' ), 10, 2 );
		
	}

	/*-----------------------------------------------------------------------------------*/
	/* Localization */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Make the plugin translation ready
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-mnm-addons' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Admin */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Add a notice if versions not met.
	 */
	public static function version_notice() {

		echo '<div class="error"><p>' . 

			sprintf( __( '<strong>WooCommerce Mix & Match: Product Addon Support is inactive.</strong> The %1$sWooCommerce Mix and Match plugin%2$s must be active and at least version %3$s for this mini-extension to function. And %4$sWooCommerce Product Add-ons%2$s must be active and at least version %5$s. Please upgrade or activate these plugins.', 'wc-mnm-addons' ),
				'<a href="https://woocommerce.com/products/woocommerce-mix-and-match-products/">',
				'</a>',
				self::$required['mnm'],
				'<a href="https://woocommerce.com/products/product-add-ons/">',
				self::$required['addons']
			)

			. '</p></div>';
	}

	/**
	 * Adds the writepanel options.
	 *
	 * @param int $post_id
	 * @param  WC_Product_Mix_and_Match  $mnm_product_object
	 */
	public static function additional_container_option( $post_id, $mnm_product_object ) {

		woocommerce_wp_checkbox( 
			array(
				'id'      => 'mnm_child_addons_support',
				'label'   => __( 'Enable Product Add-ons for contents', 'wc-mnm-addons' ),
				'value'	  => $mnm_product_object->get_meta( '_mnm_child_addons_support' ) === 'yes' ? 'yes' : 'no',
				'description' => __( 'By default Product Add-ons are supported for the container, this adds support for the individual products in the container.', 'wc-mnm-addons' ),
				'desc_tip'    => true
			)
		);

	}


	/**
	 * Saves the new meta field.
	 *
	 * @param  WC_Product_Mix_and_Match  $product
	 */
	public static function process_meta( $product ) {
		if( isset( $_POST[ 'mnm_child_addons_support' ] ) && $_POST[ 'mnm_child_addons_support' ] === 'yes' ) {
			$product->update_meta_data( '_mnm_child_addons_support', 'yes' );
		} else {
			$product->delete_meta_data( '_mnm_child_addons_support' );
		}

	}


	/*-----------------------------------------------------------------------------------*/
	/* Front End Display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Support for child product addons.
	 *
	 * @param  obj $child_product WC_Product
	 * @param  obj $container_product WC_Product_Mix_and_Match
	 */
	public static function addons_support( $child_product, $container_product ) {

		global $Product_Addon_Display, $product;

		if ( ! empty( $Product_Addon_Display ) ) {

			if( ! self::supports_addons( $container_product ) || ! $child_product->is_purchasable() ) {
				return;
			}

			$Product_Addon_Display->display( $child_product->get_id(), false );

		}
	}


	/*-----------------------------------------------------------------------------------*/
	/* Cart Handler */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Validate child item addons.
	 *
	 * @param bool $is_valid
	 * @param obj $container_product WC_Product_Mix_and_Match of parent container.
	 * @param obj $child_product WC_Product of child item.
	 * @param int $child_quantity Quantity of child item.
	 * @param int $container_quantity Quantity of parent container.
	 * @return bool
	 */
	public static function validate_child_item_addons( $is_valid, $container_product, $child_product, $child_quantity, $container_quantity ) {

		// Ordering again? When ordering again, do not revalidate addons.
		$order_again = isset( $_GET[ 'order_again' ] ) && isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( $_GET[ '_wpnonce' ], 'woocommerce-order_again' );

		if ( $order_again  ) {
			return $is_valid;
		}

		if( ! self::supports_addons( $container_product ) ) {
			return $is_valid;
		}

		// Validate add-ons.
		global $Product_Addon_Cart;

		if ( ! empty( $Product_Addon_Cart ) ) {

			if ( false === $Product_Addon_Cart->validate_add_cart_item( true, $child_product->get_id(), $child_quantity ) ) {
				$is_valid = false;
			}

		}

		return $is_valid;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helpers */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Does this product support child-level addons?
	 *
	 * @param  WC_Product $product
	 * @return bool
	 */
	public static function supports_addons( $product ) {
		return 'yes' ===  $product->get_meta( '_mnm_child_addons_support' );
	}

}
add_action( 'plugins_loaded', array( 'WC_MNM_Product_Addons', 'init' ) );