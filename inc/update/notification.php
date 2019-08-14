<?php
/**
 * This class notifies users to enter or update license key.
 *
 * @package Meta Box
 */

/**
 * Meta Box Update Notification class
 *
 * @package Meta Box
 */
class RWMB_Update_Notification {
	/**
	 * The update option object.
	 *
	 * @var object
	 */
	private $option;

	/**
	 * Settings page ID.
	 *
	 * @var string
	 */
	private $page_id = 'meta-box-updater';

	/**
	 * The update checker object.
	 *
	 * @var object
	 */
	private $checker;

	/**
	 * Constructor.
	 *
	 * @param object $checker Update checker object.
	 * @param object $option  Update option object.
	 */
	public function __construct( $checker, $option ) {
		$this->checker = $checker;
		$this->option  = $option;
	}

	/**
	 * Add hooks to show admin notice.
	 */
	public function init() {
		// Show update message on Plugins page.
		$extensions = $this->checker->get_extensions();
		foreach ( $extensions as $extension ) {
			add_action( "in_plugin_update_message-{$extension}/{$extension}.php", array( $this, 'show_update_message' ), 10, 2 );
		}

		// Show global update notification.
		if ( $this->option->get( 'notification_dismissed' ) ) {
			return;
		}

		$admin_notices_hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';
		add_action( $admin_notices_hook, array( $this, 'notify' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_mb_dismiss_notification', array( $this, 'dismiss' ) );
	}

	/**
	 * Enqueue the notification script.
	 */
	public function enqueue() {
		wp_enqueue_script( 'mb-notification', RWMB_JS_URL . 'notification.js', array( 'jquery' ), RWMB_VER, true );
		wp_localize_script( 'mb-notification', 'MBNotification', array( 'nonce' => wp_create_nonce( 'dismiss' ) ) );
	}

	/**
	 * Dismiss the notification permanently via ajax.
	 */
	public function dismiss() {
		check_ajax_referer( 'dismiss', 'nonce' );
		$this->option->set( 'notification_dismissed', 1 );
		wp_send_json_success();
	}

	/**
	 * Notify users to enter license key.
	 */
	public function notify() {
		if ( ! $this->checker->has_extensions() ) {
			return;
		}

		// Do not show notification on License page.
		$screen = get_current_screen();
		if ( 'meta-box_page_meta-box-updater' === $screen->id ) {
			return;
		}

		$messages = array(
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'no_key'  => __( '<b>Warning!</b> You have not set your Meta Box license key yet, which means you are missing out on automatic updates and support! <a href="%1$s">Enter your license key</a> or <a href="%2$s" target="_blank">get one here</a>.', 'meta-box-updater' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'invalid' => __( '<b>Warning!</b> Your license key for Meta Box is <b>invalid</b>. Please <a href="%1$s">update your license key</a> or <a href="%2$s" target="_blank">get one here</a> to get automatic updates and premium support.', 'meta-box-updater' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'error'   => __( '<b>Warning!</b> Your license key for Meta Box is <b>invalid</b>. Please <a href="%1$s">update your license key</a> or <a href="%2$s" target="_blank">get one here</a> to get automatic updates and premium support.', 'meta-box-updater' ),
			// Translators: %3$s - URL to the My Account page.
			'expired' => __( '<b>Warning!</b> Your license key for Meta Box is <b>expired</b>. Please <a href="%3$s" target="_blank">renew here</a> to get automatic updates and premium support.', 'meta-box-updater' ),
		);
		$status   = $this->get_license_status();
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		$admin_url = is_multisite() ? network_admin_url( "settings.php?page={$this->page_id}" ) : admin_url( "admin.php?page={$this->page_id}" );
		echo '<div id="meta-box-notification" class="notice notice-warning is-dismissible"><p>', wp_kses_post( sprintf( $messages[ $status ], $admin_url, 'https://metabox.io/pricing/', 'https://metabox.io/my-account/' ) ), '</p></div>';
	}

	/**
	 * Show update message on Plugins page.
	 *
	 * @param  array  $plugin_data Plugin data.
	 * @param  object $response    Available plugin update data.
	 */
	public function show_update_message( $plugin_data, $response ) {
		// Users have an active license.
		if ( ! empty( $response->package ) ) {
			return;
		}

		$messages = array(
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'no_key'  => __( 'Please <a href="%1$s">enter your license key</a> or <a href="%2$s" target="_blank">get one here</a>.', 'meta-box-updater' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'invalid' => __( 'Your license key is <b>invalid</b>. Please <a href="%1$s">update your license key</a> or <a href="%2$s" target="_blank">get one here</a>.', 'meta-box-updater' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'error'   => __( 'Your license key is <b>invalid</b>. Please <a href="%1$s">update your license key</a> or <a href="%2$s" target="_blank">get one here</a>.', 'meta-box-updater' ),
			// Translators: %3$s - URL to the My Account page.
			'expired' => __( 'Your license key is <b>expired</b>. Please <a href="%3$s" target="_blank">renew here</a>.', 'meta-box-updater' ),
		);
		$status = $this->get_license_status();
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		$message = __( '<strong>UPDATE UNAVAILABLE!</strong>', 'meta-box' ) . '&nbsp;';
		$message .= $messages[ $status ];

		$admin_url = is_multisite() ? network_admin_url( 'settings.php?page=meta-box-updater' ) : admin_url( 'admin.php?page=meta-box-updater' );

		echo '<br><span style="width: 26px; height: 20px; display: inline-block;">&nbsp;</span>' . wp_kses_post( sprintf( $message, $admin_url, 'https://metabox.io/pricing/', 'https://metabox.io/my-account/' ) );
	}

	/**
	 * Get license status.
	 */
	private function get_license_status() {
		return $this->checker->get_api_key() ? $this->option->get( 'status', 'active' ) : 'no_key';
	}
}
