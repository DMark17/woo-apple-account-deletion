<?php
/**
 * Activation and deactivation tasks.
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

namespace DM_Account_Deletion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin lifecycle hooks.
 */
final class Lifecycle {
	public static function activate(): void {
		Settings::add_defaults();
		add_rewrite_endpoint( Account_Endpoint::ENDPOINT, EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
