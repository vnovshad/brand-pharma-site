<?php
/**
 * Contact form.
 *
 * Registers [peptidestore_contact_form], a styled contact form that emails
 * submissions to the site contact address via wp_mail() (routed through the
 * configured SMTP provider, currently Brevo). Includes nonce + honeypot spam
 * protection. Submissions are processed on the same request the form posts to;
 * a success or error notice is shown inline.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Contact {

	const NONCE_ACTION = 'psc_contact_send';
	const NONCE_FIELD  = 'psc_contact_nonce';

	public function __construct() {
		add_shortcode( 'peptidestore_contact_form', array( $this, 'render' ) );
	}

	/** Where contact submissions are delivered. Filterable. */
	private function recipient(): string {
		return apply_filters( 'peptidestore_contact_recipient', 'brandpharmacanada@gmail.com' );
	}

	public function render( $atts = array() ): string {
		$values = array( 'name' => '', 'email' => '', 'phone' => '', 'message' => '' );
		$status = '';

		// Process a submission posted to this page.
		if ( isset( $_POST[ self::NONCE_FIELD ] ) ) {
			$status = $this->handle_submission( $values );
		}

		ob_start();

		// On success, replace the form with a confirmation message.
		if ( 'sent' === $status ) {
			?>
			<p class="psc-contact-form__success" role="status"
			   style="background:#E2F5E9;border:1px solid #1B7A45;color:#1B7A45;border-radius:6px;padding:0.9rem 1.1rem;font-size:0.95rem;">
				<?php esc_html_e( 'Thank you for your message. We have received it and will get back to you, typically within one business day.', 'peptidestore' ); ?>
			</p>
			<?php
			return ob_get_clean();
		}
		?>
		<form class="psc-contact-form" method="post" aria-label="<?php esc_attr_e( 'Contact form', 'peptidestore' ); ?>">

			<?php if ( 'invalid' === $status ) : ?>
				<p class="psc-contact-form__error" role="alert"
				   style="background:#FDE7E4;border:1px solid #B23A2B;color:#B23A2B;border-radius:6px;padding:0.75rem 1rem;font-size:0.9rem;margin-bottom:1.5rem;">
					<?php esc_html_e( 'Please enter your name, a valid email address, and a message, then try again.', 'peptidestore' ); ?>
				</p>
			<?php elseif ( 'error' === $status ) : ?>
				<p class="psc-contact-form__error" role="alert"
				   style="background:#FDE7E4;border:1px solid #B23A2B;color:#B23A2B;border-radius:6px;padding:0.75rem 1rem;font-size:0.9rem;margin-bottom:1.5rem;">
					<?php
					printf(
						/* translators: %s: contact email address */
						esc_html__( 'Sorry, something went wrong and your message could not be sent. Please email us directly at %s.', 'peptidestore' ),
						esc_html( $this->recipient() )
					);
					?>
				</p>
			<?php endif; ?>

			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

			<?php // Honeypot: hidden from people, tempting to bots. ?>
			<input type="text" name="psc_hp" class="psc-contact-form__hp" tabindex="-1"
			       autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;" />

			<div class="psc-contact-form__row">
				<label class="psc-contact-form__label">
					<span><?php esc_html_e( 'Name', 'peptidestore' ); ?></span>
					<input type="text" name="name" autocomplete="name" required
					       value="<?php echo esc_attr( $values['name'] ); ?>"
					       placeholder="<?php esc_attr_e( 'Your name', 'peptidestore' ); ?>" />
				</label>
				<label class="psc-contact-form__label">
					<span><?php esc_html_e( 'Email', 'peptidestore' ); ?></span>
					<input type="email" name="email" autocomplete="email" required
					       value="<?php echo esc_attr( $values['email'] ); ?>"
					       placeholder="<?php esc_attr_e( 'you@example.com', 'peptidestore' ); ?>" />
				</label>
			</div>

			<label class="psc-contact-form__label">
				<span><?php esc_html_e( 'Phone', 'peptidestore' ); ?></span>
				<input type="tel" name="phone" autocomplete="tel"
				       value="<?php echo esc_attr( $values['phone'] ); ?>"
				       placeholder="<?php esc_attr_e( 'Optional', 'peptidestore' ); ?>" />
			</label>

			<label class="psc-contact-form__label">
				<span><?php esc_html_e( 'Message', 'peptidestore' ); ?></span>
				<textarea name="message" rows="6" required
				          placeholder="<?php esc_attr_e( 'How can we help?', 'peptidestore' ); ?>"><?php echo esc_textarea( $values['message'] ); ?></textarea>
			</label>

			<button type="submit" class="button psc-contact-form__submit">
				<?php esc_html_e( 'Send', 'peptidestore' ); ?>
			</button>

		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate + send a submission. Populates $values (for re-rendering on
	 * error) and returns a status: 'sent', 'invalid', or 'error'.
	 *
	 * @param array $values Passed by reference; filled with submitted values.
	 */
	private function handle_submission( array &$values ): string {
		$values['name']    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$values['email']   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$values['phone']   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$values['message'] = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) ) {
			return 'error';
		}

		// Honeypot: a filled field means a bot. Pretend success so it does not retry.
		if ( ! empty( $_POST['psc_hp'] ) ) {
			$values = array( 'name' => '', 'email' => '', 'phone' => '', 'message' => '' );
			return 'sent';
		}

		// Validate required fields.
		if ( '' === $values['name'] || ! is_email( $values['email'] ) || '' === $values['message'] ) {
			return 'invalid';
		}

		$to      = $this->recipient();
		$subject = sprintf( 'New contact form message from %s', $values['name'] );
		$body    = sprintf(
			"Name: %s\nEmail: %s\nPhone: %s\n\nMessage:\n%s\n",
			$values['name'],
			$values['email'],
			'' !== $values['phone'] ? $values['phone'] : '(not provided)',
			$values['message']
		);
		$headers = array( 'Reply-To: ' . $values['name'] . ' <' . $values['email'] . '>' );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			$values = array( 'name' => '', 'email' => '', 'phone' => '', 'message' => '' );
			return 'sent';
		}
		return 'error';
	}
}
