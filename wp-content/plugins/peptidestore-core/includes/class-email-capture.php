<?php
/**
 * Email capture module.
 *
 * - Registers [peptidestore_signup] shortcode.
 * - Injects a signup section above the Storefront footer on every page.
 * - Handles AJAX subscription with honeypot spam protection.
 * - Stores all subscribers in a local DB table (wp_psc_subscribers) regardless
 *   of external provider so no subscriber data is ever lost.
 * - Dispatches to Mailchimp, Klaviyo, or local-only based on admin settings.
 * - Adds a Settings → Newsletter admin page to configure credentials and view
 *   the subscriber list.
 *
 * ⚠ PROVIDER CREDENTIALS REQUIRED before external sync works.
 *   Settings → Newsletter → API Credentials.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Email_Capture {

	const TABLE_VERSION = '1';
	const OPTION_SETTINGS = 'psc_email_settings';
	const OPTION_DB_VER   = 'psc_subscriber_db_version';
	const NONCE_ACTION    = 'psc_subscribe_nonce';

	public function __construct() {
		add_action( 'init',                   array( $this, 'maybe_create_table' ) );
		add_shortcode( 'peptidestore_signup', array( $this, 'render_form' ) );
		add_action( 'wp_enqueue_scripts',     array( $this, 'enqueue_assets' ) );

		// Inject above the Storefront footer (fires on every page).
		add_action( 'storefront_before_footer', array( $this, 'render_footer_signup' ), 5 );

		// AJAX handlers (logged-in and logged-out users).
		add_action( 'wp_ajax_psc_subscribe',        array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_psc_subscribe', array( $this, 'handle_ajax' ) );

		// Admin settings page.
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	// ── Database table ────────────────────────────────────────────────────────

	public function maybe_create_table(): void {
		if ( get_option( self::OPTION_DB_VER ) === self::TABLE_VERSION ) {
			return;
		}
		global $wpdb;
		$table   = $wpdb->prefix . 'psc_subscribers';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          bigint(20)   NOT NULL AUTO_INCREMENT,
			email       varchar(200) NOT NULL,
			name        varchar(100) NOT NULL DEFAULT '',
			status      varchar(20)  NOT NULL DEFAULT 'subscribed',
			source      varchar(100) NOT NULL DEFAULT 'footer',
			ip_hash     varchar(64)  NOT NULL DEFAULT '',
			created_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY email (email)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::OPTION_DB_VER, self::TABLE_VERSION );
	}

	// ── Settings accessors ────────────────────────────────────────────────────

	private function settings(): array {
		return wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), array(
			'provider'      => 'local',
			'api_key'       => '',
			'list_id'       => '',
			'notify_email'  => '',
			'double_optin'  => '1',
		) );
	}

	// ── Provider factory ──────────────────────────────────────────────────────

	private function make_provider(): Email\Provider_Base {
		$s = $this->settings();
		switch ( $s['provider'] ) {
			case 'mailchimp':
				return new Email\Provider_Mailchimp( $s['api_key'], $s['list_id'], (bool) $s['double_optin'] );
			case 'klaviyo':
				return new Email\Provider_Klaviyo( $s['api_key'], $s['list_id'] );
			default:
				return new Email\Provider_Local();
		}
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		wp_enqueue_script(
			'peptidestore-signup',
			PEPTIDE_STORE_URL . 'assets/js/signup.js',
			array(),
			PEPTIDE_STORE_VERSION,
			true
		);
		wp_localize_script( 'peptidestore-signup', 'pscSignup', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'strings' => array(
				'success'  => __( 'Thank you, you\'ve been added to the research updates list.', 'peptidestore' ),
				'exists'   => __( 'This address is already subscribed.', 'peptidestore' ),
				'error'    => __( 'Something went wrong. Please try again.', 'peptidestore' ),
				'invalid'  => __( 'Please enter a valid email address.', 'peptidestore' ),
				'sending'  => __( 'Subscribing…', 'peptidestore' ),
			),
		) );
	}

	// ── Shortcode / form renderer ─────────────────────────────────────────────

	public function render_form( $atts = array() ): string {
		$atts = shortcode_atts( array(
			'title'       => __( 'Research Updates', 'peptidestore' ),
			'description' => __( 'New compound availability, batch restocks, and technical resources, for research professionals.', 'peptidestore' ),
			'source'      => 'shortcode',
			'compact'     => 'no',
		), $atts, 'peptidestore_signup' );

		$compact = $atts['compact'] === 'yes';
		ob_start();
		$this->output_form_html( $atts['title'], $atts['description'], $atts['source'], $compact );
		return ob_get_clean();
	}

	public function render_footer_signup(): void {
		$this->output_form_html(
			__( 'Research Updates', 'peptidestore' ),
			__( 'New compound availability, batch restocks, and technical resources, for research professionals.', 'peptidestore' ),
			'footer',
			false,
			true  // footer style (dark background)
		);
	}

	private function output_form_html(
		string $title,
		string $description,
		string $source,
		bool   $compact     = false,
		bool   $footer_dark = false
	): void {
		static $instance = 0;
		++$instance;
		$uid = 'psc-signup-' . $instance;
		$classes = 'psc-signup' . ( $footer_dark ? ' psc-signup--dark' : '' ) . ( $compact ? ' psc-signup--compact' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $uid ); ?>">
			<div class="psc-signup__inner">

				<?php if ( ! $compact ) : ?>
				<div class="psc-signup__copy">
					<h3 class="psc-signup__title"><?php echo esc_html( $title ); ?></h3>
					<p class="psc-signup__desc"><?php echo esc_html( $description ); ?></p>
				</div>
				<?php endif; ?>

				<form class="psc-signup__form"
				      data-source="<?php echo esc_attr( $source ); ?>"
				      novalidate>

					<div class="psc-signup__fields">
						<input
							type="text"
							name="psc_name"
							class="psc-signup__input psc-signup__input--name"
							placeholder="<?php esc_attr_e( 'First name (optional)', 'peptidestore' ); ?>"
							autocomplete="given-name"
						/>
						<input
							type="email"
							name="psc_email"
							class="psc-signup__input psc-signup__input--email"
							placeholder="<?php esc_attr_e( 'Email address', 'peptidestore' ); ?>"
							required
							autocomplete="email"
						/>
						<!-- Honeypot — bots fill this, humans don't see it -->
						<input
							type="text"
							name="psc_hp"
							class="psc-signup__hp"
							tabindex="-1"
							autocomplete="off"
							aria-hidden="true"
						/>
						<button type="submit" class="psc-signup__btn">
							<?php esc_html_e( 'Subscribe', 'peptidestore' ); ?>
						</button>
					</div>

					<p class="psc-signup__status" role="status" aria-live="polite"></p>

					<p class="psc-signup__legal">
						<?php esc_html_e( 'Research professionals only. No health claims or marketing copy. Unsubscribe any time.', 'peptidestore' ); ?>
					</p>

				</form>
			</div>
		</div>
		<?php
	}

	// ── AJAX handler ──────────────────────────────────────────────────────────

	public function handle_ajax(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'bad_nonce' ), 403 );
		}

		// Honeypot check — silently succeed so bots don't know they're blocked.
		if ( ! empty( $_POST['psc_hp'] ) ) {
			wp_send_json_success( array( 'code' => 'ok' ) );
		}

		$email  = sanitize_email( wp_unslash( $_POST['psc_email'] ?? '' ) );
		$name   = sanitize_text_field( wp_unslash( $_POST['psc_name']  ?? '' ) );
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? 'footer' ) );

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'code' => 'invalid_email' ), 400 );
		}

		// Limit source to known values.
		$allowed_sources = array( 'footer', 'shortcode', 'blog', 'checkout' );
		if ( ! in_array( $source, $allowed_sources, true ) ) {
			$source = 'footer';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'psc_subscribers';

		// Check for duplicate.
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE email = %s LIMIT 1",
			$email
		) );
		if ( $exists ) {
			wp_send_json_error( array( 'code' => 'exists' ), 200 );
		}

		// Write to local DB.
		$ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );
		$inserted = $wpdb->insert( $table, array(
			'email'    => $email,
			'name'     => $name,
			'status'   => 'subscribed',
			'source'   => $source,
			'ip_hash'  => $ip_hash,
		) );

		if ( ! $inserted ) {
			wp_send_json_error( array( 'code' => 'db_error' ), 500 );
		}

		// Dispatch to external provider (best-effort; failure doesn't block the signup).
		$provider = $this->make_provider();
		$result   = $provider->subscribe( $email, $name, $source );

		// Notify admin if configured.
		$settings = $this->settings();
		if ( ! empty( $settings['notify_email'] ) && is_email( $settings['notify_email'] ) ) {
			wp_mail(
				$settings['notify_email'],
				sprintf( __( 'New newsletter signup — %s', 'peptidestore' ), get_bloginfo( 'name' ) ),
				sprintf( "Email: %s\nName: %s\nSource: %s\n", $email, $name, $source )
			);
		}

		wp_send_json_success( array( 'code' => 'ok', 'provider' => $result ) );
	}

	// ── Admin settings page ───────────────────────────────────────────────────

	public function register_admin_page(): void {
		add_options_page(
			__( 'Newsletter', 'peptidestore' ),
			__( 'Newsletter', 'peptidestore' ),
			'manage_options',
			'psc-newsletter',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'psc_newsletter',
			self::OPTION_SETTINGS,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $input ): array {
		$clean = array();
		$clean['provider']     = in_array( $input['provider'] ?? '', array( 'local', 'mailchimp', 'klaviyo' ), true )
			? $input['provider']
			: 'local';
		$clean['api_key']      = sanitize_text_field( $input['api_key']     ?? '' );
		$clean['list_id']      = sanitize_text_field( $input['list_id']     ?? '' );
		$clean['notify_email'] = sanitize_email(      $input['notify_email'] ?? '' );
		$clean['double_optin'] = empty( $input['double_optin'] ) ? '0' : '1';
		return $clean;
	}

	public function render_admin_page(): void {
		global $wpdb;
		$table    = $wpdb->prefix . 'psc_subscribers';
		$settings = $this->settings();
		$count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$recents  = $wpdb->get_results( "SELECT email, name, source, created_at FROM {$table} ORDER BY created_at DESC LIMIT 20" );

		// CSV export
		if ( isset( $_GET['psc_export'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'psc_export_csv' );
			$all = $wpdb->get_results( "SELECT email, name, source, created_at FROM {$table} ORDER BY created_at DESC" );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="subscribers-' . date( 'Y-m-d' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'Email', 'Name', 'Source', 'Date' ) );
			foreach ( $all as $row ) { fputcsv( $out, array( $row->email, $row->name, $row->source, $row->created_at ) ); }
			fclose( $out );
			exit;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Newsletter / Email Capture', 'peptidestore' ); ?></h1>

			<div style="background:#fff;border:1px solid #c3c4c7;padding:1rem 1.5rem;margin:1rem 0 1.5rem;border-radius:4px;">
				<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
				<?php esc_html_e( 'subscriber(s) stored locally.', 'peptidestore' ); ?>
				&nbsp;
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'psc_export', '1' ), 'psc_export_csv' ) ); ?>">
					<?php esc_html_e( 'Export CSV', 'peptidestore' ); ?>
				</a>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'psc_newsletter' ); ?>
				<table class="form-table">

					<tr>
						<th><?php esc_html_e( 'Email Provider', 'peptidestore' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[provider]">
								<option value="local"     <?php selected( $settings['provider'], 'local' );     ?>><?php esc_html_e( 'Store locally only (no external sync)', 'peptidestore' ); ?></option>
								<option value="mailchimp" <?php selected( $settings['provider'], 'mailchimp' ); ?>><?php esc_html_e( 'Mailchimp', 'peptidestore' ); ?></option>
								<option value="klaviyo"   <?php selected( $settings['provider'], 'klaviyo' );   ?>><?php esc_html_e( 'Klaviyo', 'peptidestore' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th colspan="2">
							<strong style="color:#b32d2e;">⚠ <?php esc_html_e( 'API Credentials — Required for external sync', 'peptidestore' ); ?></strong><br>
							<span style="font-weight:400;color:#50575e;">
							<?php esc_html_e( 'Leave blank to store subscribers locally only. Subscribers are always saved to the local database first, so no data is lost if the external provider fails or is not yet configured.', 'peptidestore' ); ?>
							</span>
						</th>
					</tr>

					<tr>
						<th><?php esc_html_e( 'API Key', 'peptidestore' ); ?></th>
						<td>
							<input type="password" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[api_key]"
							       value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text"/>
							<p class="description">
								<?php esc_html_e( 'Mailchimp: Settings → API Keys. Klaviyo: Account → API Keys (Private Key).', 'peptidestore' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Audience / List ID', 'peptidestore' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[list_id]"
							       value="<?php echo esc_attr( $settings['list_id'] ); ?>" class="regular-text"/>
							<p class="description">
								<?php esc_html_e( 'Mailchimp: Audience → Settings → Audience name and defaults. Klaviyo: Lists → (select list) → URL contains the List ID.', 'peptidestore' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Double Opt-In', 'peptidestore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[double_optin]"
								       value="1" <?php checked( $settings['double_optin'], '1' ); ?> />
								<?php esc_html_e( 'Send confirmation email before activating subscription (Mailchimp only)', 'peptidestore' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Admin Notification Email', 'peptidestore' ); ?></th>
						<td>
							<input type="email" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[notify_email]"
							       value="<?php echo esc_attr( $settings['notify_email'] ); ?>" class="regular-text"/>
							<p class="description"><?php esc_html_e( 'Optional. Receive an email each time someone subscribes.', 'peptidestore' ); ?></p>
						</td>
					</tr>

				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( $recents ) : ?>
			<h2><?php esc_html_e( 'Recent Subscribers', 'peptidestore' ); ?></h2>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr>
					<th><?php esc_html_e( 'Email', 'peptidestore' ); ?></th>
					<th><?php esc_html_e( 'Name', 'peptidestore' ); ?></th>
					<th><?php esc_html_e( 'Source', 'peptidestore' ); ?></th>
					<th><?php esc_html_e( 'Date', 'peptidestore' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $recents as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->email ); ?></td>
					<td><?php echo esc_html( $row->name ); ?></td>
					<td><?php echo esc_html( $row->source ); ?></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->created_at ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
