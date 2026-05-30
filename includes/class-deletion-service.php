<?php
/**
 * Account deletion and anonymisation logic.
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

namespace DM_Account_Deletion;

use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes or anonymises WooCommerce customer accounts.
 */
final class Deletion_Service {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function delete_user_account( int $user_id, bool $require_current_user = true ): true|WP_Error {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'dmad_user_not_found', __( 'We could not find the account to delete.', 'dm-account-deletion' ) );
		}

		if ( $require_current_user && get_current_user_id() !== $user_id ) {
			return new WP_Error( 'dmad_wrong_user', __( 'You can only delete your own account.', 'dm-account-deletion' ) );
		}

		if ( $require_current_user && ! current_user_can( 'read' ) ) {
			return new WP_Error( 'dmad_forbidden', __( 'You do not have permission to delete this account.', 'dm-account-deletion' ) );
		}

		if ( user_can( $user, 'manage_options' ) && ! (bool) apply_filters( 'dm_account_deletion_allow_admin_self_delete', false, $user_id ) ) {
			return new WP_Error( 'dmad_admin_blocked', __( 'Administrator accounts cannot be deleted through this customer flow.', 'dm-account-deletion' ) );
		}

		$mode = (string) apply_filters(
			'dm_account_deletion_mode',
			$this->settings->get( 'deletion_mode', 'anonymise' ),
			$user_id
		);

		if ( ! in_array( $mode, array( 'anonymise', 'hard_delete' ), true ) ) {
			$mode = 'anonymise';
		}

		do_action( 'dm_account_deletion_before_delete', $user_id, $mode );

		$this->detach_orders( $user_id );

		if ( 'hard_delete' === $mode ) {
			$result = $this->hard_delete_user( $user_id );
		} else {
			$result = $this->anonymise_user( $user );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		do_action( 'dm_account_deletion_after_delete', $user_id, $mode );

		return true;
	}

	private function detach_orders( int $user_id ): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		do {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => 50,
					'page'        => 1,
					'paginate'    => true,
					'return'      => 'objects',
					'status'      => array_keys( wc_get_order_statuses() ),
				)
			);

			if ( empty( $orders->orders ) ) {
				break;
			}

			foreach ( $orders->orders as $order ) {
				if ( ! is_a( $order, 'WC_Order' ) ) {
					continue;
				}

				$order->set_customer_id( 0 );
				$order->add_meta_data( '_dmad_customer_detached', gmdate( 'c' ), true );
				$order->save();
			}
		} while ( ! empty( $orders->orders ) );
	}

	private function anonymise_user( WP_User $user ): true|WP_Error {
		$user_id          = (int) $user->ID;
		$anonymous_email  = sprintf( 'deleted-user-%d-%s@example.invalid', $user_id, wp_generate_password( 8, false, false ) );
		$anonymous_login  = sprintf( 'deleted_user_%d_%s', $user_id, wp_generate_password( 6, false, false ) );
		$anonymous_label  = __( 'Deleted User', 'dm-account-deletion' );
		$anonymised_roles = (array) apply_filters( 'dm_account_deletion_anonymised_roles', array(), $user_id );

		$result = wp_update_user(
			array(
				'ID'           => $user_id,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $anonymous_email,
				'user_url'     => '',
				'display_name' => $anonymous_label,
				'nickname'     => $anonymous_label,
				'first_name'   => '',
				'last_name'    => '',
				'description'  => '',
				'role'         => '',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->anonymise_login( $user_id, $anonymous_login );

		$updated_user = new WP_User( $user_id );
		$updated_user->set_role( '' );

		foreach ( $anonymised_roles as $role ) {
			if ( is_string( $role ) && '' !== $role ) {
				$updated_user->add_role( sanitize_key( $role ) );
			}
		}

		$this->anonymise_user_meta( $user_id );

		update_user_meta( $user_id, '_dmad_account_anonymised', gmdate( 'c' ) );
		update_user_meta( $user_id, 'dmad_original_user_id', $user_id );

		do_action( 'dm_account_deletion_after_anonymise', $user_id );

		return true;
	}

	private function anonymise_login( int $user_id, string $anonymous_login ): void {
		global $wpdb;

		$anonymous_login = sanitize_user( $anonymous_login, true );

		if ( '' === $anonymous_login || username_exists( $anonymous_login ) ) {
			$anonymous_login = 'deleted_user_' . $user_id . '_' . wp_generate_password( 10, false, false );
		}

		$wpdb->update(
			$wpdb->users,
			array(
				'user_login'    => $anonymous_login,
				'user_nicename' => sanitize_title( $anonymous_login ),
			),
			array( 'ID' => $user_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		clean_user_cache( $user_id );
	}

	private function anonymise_user_meta( int $user_id ): void {
		$delete_keys = array(
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'billing_email',
			'billing_phone',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			'paying_customer',
			'last_update',
			'session_tokens',
			'woocommerce_api_consumer_key',
			'woocommerce_api_consumer_secret',
		);

		/**
		 * Filter user meta keys erased during anonymisation.
		 *
		 * @param array $delete_keys Meta keys.
		 * @param int   $user_id     User ID.
		 */
		$delete_keys = apply_filters( 'dm_account_deletion_meta_keys_to_delete', $delete_keys, $user_id );

		foreach ( $delete_keys as $meta_key ) {
			if ( is_string( $meta_key ) && '' !== $meta_key ) {
				delete_user_meta( $user_id, $meta_key );
			}
		}

		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new \WC_Customer( $user_id );
			$customer->set_billing_first_name( '' );
			$customer->set_billing_last_name( '' );
			$customer->set_billing_company( '' );
			$customer->set_billing_address_1( '' );
			$customer->set_billing_address_2( '' );
			$customer->set_billing_city( '' );
			$customer->set_billing_state( '' );
			$customer->set_billing_postcode( '' );
			$customer->set_billing_country( '' );
			$customer->set_billing_email( '' );
			$customer->set_billing_phone( '' );
			$customer->set_shipping_first_name( '' );
			$customer->set_shipping_last_name( '' );
			$customer->set_shipping_company( '' );
			$customer->set_shipping_address_1( '' );
			$customer->set_shipping_address_2( '' );
			$customer->set_shipping_city( '' );
			$customer->set_shipping_state( '' );
			$customer->set_shipping_postcode( '' );
			$customer->set_shipping_country( '' );
			$customer->save();
		}
	}

	private function hard_delete_user( int $user_id ): true|WP_Error {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$deleted = wp_delete_user( $user_id );

		if ( ! $deleted ) {
			return new WP_Error( 'dmad_delete_failed', __( 'The account could not be deleted. Please try again.', 'dm-account-deletion' ) );
		}

		return true;
	}
}
