<?php
/**
 * Mailchimp Marketing API v3 provider.
 *
 * ⚠ CREDENTIALS REQUIRED — enter API Key and Audience ID in
 *   Settings → Newsletter before this provider will work.
 *
 * API docs: https://mailchimp.com/developer/marketing/api/list-members/
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Email;

defined( 'ABSPATH' ) || exit;

class Provider_Mailchimp extends Provider_Base {

	private string $api_key;
	private string $list_id;
	private bool   $double_optin;

	public function __construct( string $api_key, string $list_id, bool $double_optin = true ) {
		$this->api_key      = $api_key;
		$this->list_id      = $list_id;
		$this->double_optin = $double_optin;
	}

	public function label(): string {
		return __( 'Mailchimp', 'peptidestore' );
	}

	public function requires_api_key(): bool {
		return true;
	}

	public function subscribe( string $email, string $name, string $source ): array {
		if ( empty( $this->api_key ) || empty( $this->list_id ) ) {
			return array( 'success' => false, 'message' => 'Mailchimp not configured — stored locally.' );
		}

		// Derive data-centre from API key (e.g. "abc123-us1" → dc = "us1").
		$parts = explode( '-', $this->api_key );
		$dc    = end( $parts );
		if ( ! $dc || $dc === $this->api_key ) {
			return array( 'success' => false, 'message' => 'Invalid Mailchimp API key format.' );
		}

		$endpoint = "https://{$dc}.api.mailchimp.com/3.0/lists/{$this->list_id}/members";

		$name_parts = explode( ' ', trim( $name ), 2 );
		$fname     = $name_parts[0] ?? '';
		$lname     = $name_parts[1] ?? '';

		$body = array(
			'email_address' => $email,
			'status'        => $this->double_optin ? 'pending' : 'subscribed',
			'merge_fields'  => array(
				'FNAME' => $fname,
				'LNAME' => $lname,
			),
			'tags'          => array( 'research-updates', $source ),
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $this->api_key ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// 200 = already subscribed (update), 400 w/ title "Member Exists" = same
		if ( $code === 200 || $code === 201 ) {
			return array( 'success' => true, 'message' => 'Added to Mailchimp.' );
		}
		if ( $code === 400 && ( $data['title'] ?? '' ) === 'Member Exists' ) {
			return array( 'success' => true, 'message' => 'Already subscribed in Mailchimp.' );
		}

		$error = $data['detail'] ?? $data['title'] ?? "HTTP {$code}";
		return array( 'success' => false, 'message' => "Mailchimp error: {$error}" );
	}
}
