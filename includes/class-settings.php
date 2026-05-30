<?php
/**
 * Admin settings.
 *
 * @package DMAccountDeletion
 */

declare(strict_types=1);

namespace DM_Account_Deletion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and renders plugin options.
 */
final class Settings {
	public const OPTION_NAME = 'dm_account_deletion_options';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . DMAD_BASENAME, array( $this, 'settings_link' ) );
	}

	public static function defaults(): array {
		$defaults = array(
			'enabled'            => 'yes',
			'menu_label'         => __( 'Delete Account', 'dm-account-deletion' ),
			'warning_text'       => __(
				'Account deletion is permanent. Your personal profile data will be removed. Your order history may be retained where required for legal, tax, fraud-prevention, or accounting reasons.',
				'dm-account-deletion'
			),
			'success_message'    => __( 'Your account has been successfully deleted.', 'dm-account-deletion' ),
			'deletion_mode'      => 'anonymise',
			'redirect_url'       => '',
			'delayed_enabled'    => 'no',
			'delay_days'         => 7,
			'admin_notification' => 'yes',
		);

		/**
		 * Filter the default plugin settings.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'dm_account_deletion_default_settings', $defaults );
	}

	public static function add_defaults(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults() );
		}
	}

	public function get_all(): array {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::defaults() );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$options = $this->get_all();

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public function is_enabled(): bool {
		return 'yes' === $this->get( 'enabled', 'yes' );
	}

	public function add_settings_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Account Deletion', 'dm-account-deletion' ),
			__( 'Account Deletion', 'dm-account-deletion' ),
			'manage_woocommerce',
			'dm-account-deletion',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'dm_account_deletion',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function settings_link( array $links ): array {
		$url = admin_url( 'admin.php?page=dm-account-deletion' );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'dm-account-deletion' )
			)
		);

		return $links;
	}

	public function sanitize( mixed $input ): array {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		$sanitized = array(
			'enabled'            => empty( $input['enabled'] ) ? 'no' : 'yes',
			'menu_label'         => sanitize_text_field( $input['menu_label'] ?? $defaults['menu_label'] ),
			'warning_text'       => wp_kses_post( $input['warning_text'] ?? $defaults['warning_text'] ),
			'success_message'    => wp_kses_post( $input['success_message'] ?? $defaults['success_message'] ),
			'deletion_mode'      => in_array( $input['deletion_mode'] ?? '', array( 'anonymise', 'hard_delete' ), true ) ? $input['deletion_mode'] : 'anonymise',
			'redirect_url'       => esc_url_raw( $input['redirect_url'] ?? '' ),
			'delayed_enabled'    => empty( $input['delayed_enabled'] ) ? 'no' : 'yes',
			'delay_days'         => absint( $input['delay_days'] ?? $defaults['delay_days'] ),
			'admin_notification' => empty( $input['admin_notification'] ) ? 'no' : 'yes',
		);

		if ( $sanitized['delay_days'] < 1 ) {
			$sanitized['delay_days'] = 1;
		}

		return $sanitized;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage account deletion settings.', 'dm-account-deletion' ) );
		}

		$options = $this->get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Account Deletion', 'dm-account-deletion' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'dm_account_deletion' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable plugin', 'dm-account-deletion' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="yes" <?php checked( $options['enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Show the Delete Account flow in WooCommerce My Account.', 'dm-account-deletion' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dmad-menu-label"><?php esc_html_e( 'Menu item label', 'dm-account-deletion' ); ?></label></th>
						<td><input class="regular-text" id="dmad-menu-label" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[menu_label]" value="<?php echo esc_attr( $options['menu_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="dmad-warning-text"><?php esc_html_e( 'Warning text', 'dm-account-deletion' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="5" id="dmad-warning-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[warning_text]"><?php echo esc_textarea( $options['warning_text'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dmad-success-message"><?php esc_html_e( 'Success message', 'dm-account-deletion' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="dmad-success-message" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[success_message]"><?php echo esc_textarea( $options['success_message'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Deletion mode', 'dm-account-deletion' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deletion_mode]" value="anonymise" <?php checked( $options['deletion_mode'], 'anonymise' ); ?> /> <?php esc_html_e( 'Anonymise only', 'dm-account-deletion' ); ?></label><br />
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deletion_mode]" value="hard_delete" <?php checked( $options['deletion_mode'], 'hard_delete' ); ?> /> <?php esc_html_e( 'Hard delete user account', 'dm-account-deletion' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dmad-redirect-url"><?php esc_html_e( 'Redirect URL after deletion', 'dm-account-deletion' ); ?></label></th>
						<td>
							<input class="regular-text" id="dmad-redirect-url" type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[redirect_url]" value="<?php echo esc_url( $options['redirect_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to use the built-in success screen.', 'dm-account-deletion' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delayed deletion', 'dm-account-deletion' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delayed_enabled]" value="yes" <?php checked( $options['delayed_enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Mark accounts as pending deletion instead of deleting immediately.', 'dm-account-deletion' ); ?>
							</label>
							<p>
								<label for="dmad-delay-days"><?php esc_html_e( 'Delay days', 'dm-account-deletion' ); ?></label>
								<input class="small-text" id="dmad-delay-days" min="1" type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delay_days]" value="<?php echo esc_attr( (string) $options['delay_days'] ); ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin email notification', 'dm-account-deletion' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[admin_notification]" value="yes" <?php checked( $options['admin_notification'], 'yes' ); ?> />
								<?php esc_html_e( 'Notify the site administrator when an account deletion is requested or completed.', 'dm-account-deletion' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
