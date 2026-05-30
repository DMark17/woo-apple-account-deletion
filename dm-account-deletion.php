<?php
/**
 * Plugin Name: DM Account Deletion
 * Plugin URI: https://example.com/dm-account-deletion
 * Description: Self-service WooCommerce customer account deletion for WebView apps and Apple App Store account deletion compliance.
 * Version: 1.0.0
 * Author: DM
 * Author URI: https://example.com
 * Text Domain: dm-account-deletion
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DMAD_VERSION', '1.0.0' );
define( 'DMAD_FILE', __FILE__ );
define( 'DMAD_PATH', plugin_dir_path( __FILE__ ) );
define( 'DMAD_URL', plugin_dir_url( __FILE__ ) );
define( 'DMAD_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'DM_Account_Deletion\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = DMAD_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'DM_Account_Deletion\\Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DM_Account_Deletion\\Lifecycle', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'dm-account-deletion', false, dirname( DMAD_BASENAME ) . '/languages' );

		DM_Account_Deletion\Plugin::instance()->init();
	}
);
