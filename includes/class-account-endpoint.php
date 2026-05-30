<?php
/**
 * WooCommerce My Account endpoint and form flow.
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

namespace DM_Account_Deletion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the customer-facing deletion endpoint.
 */
final class Account_Endpoint {
	public const ENDPOINT       = 'delete-account';
	public const NONCE_ACTION   = 'dm_account_deletion_request';
	public const NONCE_NAME     = 'dm_account_deletion_nonce';
	public const SUCCESS_QUERY  = 'dm-account-deleted';
	public const FORM_ACTION    = 'dm_account_deletion_submit';
	private const PENDING_META  = '_dmad_pending_deletion_at';

	private Settings $settings;

	private Deletion_Service $deletion_service;

	public function __construct( Settings $settings, Deletion_Service $deletion_service ) {
		$this->settings         = $settings;
		$this->deletion_service = $deletion_service;
	}

	public function init(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 40 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
		add_action( 'template_redirect', array( $this, 'render_success_screen' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'dm_account_deletion_process_scheduled', array( $this, 'process_scheduled_deletion' ) );
	}

	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = self::SUCCESS_QUERY;

		return $vars;
	}

	public function add_menu_item( array $items ): array {
		if ( ! $this->settings->is_enabled() ) {
			return $items;
		}

		$label = (string) apply_filters(
			'dm_account_deletion_menu_label',
			$this->settings->get( 'menu_label', __( 'Delete Account', 'dm-account-deletion' ) )
		);

		$new_items = array();

		foreach ( $items as $key => $value ) {
			if ( 'customer-logout' === $key ) {
				$new_items[ self::ENDPOINT ] = $label;
			}

			$new_items[ $key ] = $value;
		}

		if ( ! isset( $new_items[ self::ENDPOINT ] ) ) {
			$new_items[ self::ENDPOINT ] = $label;
		}

		return $new_items;
	}

	public function enqueue_assets(): void {
		if ( ! is_account_page() || ! $this->settings->is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'dm-account-deletion',
			DMAD_URL . 'assets/css/frontend.css',
			array(),
			DMAD_VERSION
		);
	}

	public function render_endpoint(): void {
		if ( ! $this->settings->is_enabled() ) {
			wc_print_notice( esc_html__( 'Account deletion is currently unavailable.', 'dm-account-deletion' ), 'notice' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			wc_print_notice( esc_html__( 'Please log in to delete your account.', 'dm-account-deletion' ), 'error' );
			return;
		}

		$user_id       = get_current_user_id();
		$pending_until = (int) get_user_meta( $user_id, self::PENDING_META, true );

		?>
		<section class="dmad-account-deletion" aria-labelledby="dmad-title">
			<h2 id="dmad-title"><?php echo esc_html( (string) $this->settings->get( 'menu_label', __( 'Delete Account', 'dm-account-deletion' ) ) ); ?></h2>

			<div class="dmad-warning" role="alert">
				<p><?php echo wp_kses_post( (string) apply_filters( 'dm_account_deletion_warning_text', $this->settings->get( 'warning_text', '' ) ) ); ?></p>
			</div>

			<?php if ( $pending_until > time() ) : ?>
				<div class="woocommerce-info dmad-pending">
					<?php
					printf(
						/* translators: %s: localized pending deletion date. */
						esc_html__( 'Your account is already scheduled for deletion on %s.', 'dm-account-deletion' ),
						esc_html( wp_date( get_option( 'date_format' ), $pending_until ) )
					);
					?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<form class="dmad-form" method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::FORM_ACTION ); ?>" />
				<p class="form-row dmad-confirm-row">
					<label for="dmad-confirm-delete">
						<input id="dmad-confirm-delete" type="checkbox" name="dmad_confirm_delete" value="1" required />
						<?php esc_html_e( 'I understand this action cannot be undone', 'dm-account-deletion' ); ?>
					</label>
				</p>
				<p class="form-row">
					<button type="submit" class="button dmad-delete-button">
						<?php esc_html_e( 'Delete My Account', 'dm-account-deletion' ); ?>
					</button>
				</p>
			</form>
		</section>
		<?php
	}

	public function handle_request(): void {
		if ( ! $this->is_delete_endpoint_request() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		if ( ! $this->settings->is_enabled() ) {
			wc_add_notice( esc_html__( 'Account deletion is currently unavailable.', 'dm-account-deletion' ), 'error' );
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		if ( self::FORM_ACTION !== $action ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wc_add_notice( esc_html__( 'Security check failed. Please try again.', 'dm-account-deletion' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}

		if ( empty( $_POST['dmad_confirm_delete'] ) ) {
			wc_add_notice( esc_html__( 'Please confirm that you understand this action cannot be undone.', 'dm-account-deletion' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		$email   = $user ? $user->user_email : '';

		do_action( 'dm_account_deletion_before_request', $user_id );

		if ( 'yes' === $this->settings->get( 'delayed_enabled', 'no' ) ) {
			$this->schedule_delayed_deletion( $user_id );
			$this->maybe_notify_admin( $user_id, 'scheduled' );
			$this->destroy_current_session();
			wp_safe_redirect( $this->success_url() );
			exit;
		}

		$result = $this->deletion_service->delete_user_account( $user_id );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}

		$this->maybe_notify_admin( $user_id, 'completed', $email );
		$this->destroy_current_session();

		wp_safe_redirect( $this->success_url() );
		exit;
	}

	public function render_success_screen(): void {
		$success = get_query_var( self::SUCCESS_QUERY );

		if ( empty( $success ) || '1' !== (string) $success ) {
			return;
		}

		status_header( 200 );
		nocache_headers();

		$message = (string) apply_filters(
			'dm_account_deletion_success_message',
			$this->settings->get( 'success_message', __( 'Your account has been successfully deleted.', 'dm-account-deletion' ) )
		);

		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<?php wp_head(); ?>
			<style>
				body.dmad-success-body{background:#f7f7f7;margin:0}
				.dmad-success-screen{align-items:center;box-sizing:border-box;display:flex;min-height:100vh;padding:24px}
				.dmad-success-card{background:#fff;border:1px solid #e3e3e3;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.06);margin:0 auto;max-width:560px;padding:32px;text-align:center;width:100%}
				.dmad-success-card h1{font-size:28px;line-height:1.2;margin:0 0 12px}
				.dmad-success-card p{font-size:17px;line-height:1.55;margin:0}
			</style>
		</head>
		<body <?php body_class( 'dmad-success-body' ); ?>>
			<?php wp_body_open(); ?>
			<main class="dmad-success-screen">
				<section class="dmad-success-card" aria-labelledby="dmad-success-title">
					<h1 id="dmad-success-title"><?php esc_html_e( 'Account Deleted', 'dm-account-deletion' ); ?></h1>
					<p><?php echo wp_kses_post( $message ); ?></p>
				</section>
			</main>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	private function is_delete_endpoint_request(): bool {
		global $wp;

		return isset( $wp->query_vars[ self::ENDPOINT ] );
	}

	private function schedule_delayed_deletion( int $user_id ): void {
		$days      = max( 1, (int) $this->settings->get( 'delay_days', 7 ) );
		$timestamp = time() + ( DAY_IN_SECONDS * $days );

		update_user_meta( $user_id, self::PENDING_META, $timestamp );

		if ( ! wp_next_scheduled( 'dm_account_deletion_process_scheduled', array( $user_id ) ) ) {
			wp_schedule_single_event( $timestamp, 'dm_account_deletion_process_scheduled', array( $user_id ) );
		}

		do_action( 'dm_account_deletion_scheduled', $user_id, $timestamp );
	}

	public function process_scheduled_deletion( int $user_id ): void {
		$pending_until = (int) get_user_meta( $user_id, self::PENDING_META, true );
		$user          = get_userdata( $user_id );
		$email         = $user ? $user->user_email : '';

		if ( $pending_until <= 0 || $pending_until > time() ) {
			return;
		}

		$result = $this->deletion_service->delete_user_account( $user_id, false );

		if ( is_wp_error( $result ) ) {
			do_action( 'dm_account_deletion_scheduled_failed', $user_id, $result );
			return;
		}

		delete_user_meta( $user_id, self::PENDING_META );
		$this->maybe_notify_admin( $user_id, 'completed', $email );
	}

	private function maybe_notify_admin( int $user_id, string $status, string $email = '' ): void {
		if ( 'yes' !== $this->settings->get( 'admin_notification', 'yes' ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user && '' === $email ) {
			return;
		}

		$email = '' !== $email ? $email : $user->user_email;

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] Customer account deletion %s', 'dm-account-deletion' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$status
		);

		$message = sprintf(
			/* translators: 1: user ID, 2: user email, 3: deletion status. */
			__( 'Customer account deletion %3$s for user ID %1$d (%2$s).', 'dm-account-deletion' ),
			$user_id,
			$email,
			$status
		);

		wp_mail( get_option( 'admin_email' ), $subject, $message );
	}

	private function destroy_current_session(): void {
		do_action( 'dm_account_deletion_before_logout', get_current_user_id() );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->destroy_session();
		}

		if ( function_exists( 'wc_empty_cart' ) ) {
			wc_empty_cart();
		}

		wp_logout();
		wp_clear_auth_cookie();

		do_action( 'dm_account_deletion_after_logout' );
	}

	private function success_url(): string {
		$redirect_url = (string) apply_filters(
			'dm_account_deletion_redirect_url',
			$this->settings->get( 'redirect_url', '' )
		);

		if ( '' !== $redirect_url ) {
			return esc_url_raw( $redirect_url );
		}

		return add_query_arg( self::SUCCESS_QUERY, '1', home_url( '/' ) );
	}
}
