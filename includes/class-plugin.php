<?php
/**
 * Main plugin coordinator.
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

namespace DM_Account_Deletion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin together once WordPress has loaded plugins.
 */
final class Plugin {
	private static ?Plugin $instance = null;

	private Settings $settings;

	private Account_Endpoint $endpoint;

	private Deletion_Service $deletion_service;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings         = new Settings();
		$this->deletion_service = new Deletion_Service( $this->settings );
		$this->endpoint         = new Account_Endpoint( $this->settings, $this->deletion_service );
	}

	public function init(): void {
		if ( is_admin() ) {
			$this->settings->init();
		}

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->endpoint->init();
	}

	public function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'DM Account Deletion requires WooCommerce to be installed and active.', 'dm-account-deletion' )
		);
	}

	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}
}
