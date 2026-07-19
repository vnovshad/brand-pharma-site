<?php
/**
 * Klaviyo API v2 provider.
 *
 * ⚠ CREDENTIALS REQUIRED — enter API Key and List ID in
 *   Settings → Newsletter before this provider will work.
 *
 * API docs: https://developers.klaviyo.com/en/reference/subscribe-profiles
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Email;

defined( 'ABSPATH' ) || exit;

class Provider_Klaviyo extends Provider_Base {

	private string $api_key;
	private string $list_id;

	public function __construct( string $api_key, string $list_id ) {
		$this->api_key = $api_key;
		$this->list_id = $list_id;
	}

	public function label(): string {
		return __( 'Klaviyo', 'peptidestore' );
	}

	public function requires_api_key(): bool {
		return true;
	}

	public function subscribe( string $email, string $name, string $source ): array {
		if ( empty( $this->api_key ) || empty( $this->list_id ) ) {
			return array( 'success' => false, 'message' => 'Klaviyo not configured — stored locally.' );
		}

		$name_parts = explode( ' ', trim( $name ), 2 );

		// Klaviyo Profiles API v3 (2023-02-22)
		$endpoint = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/';

		$body = array(
			'data' => array(
				'type'       => 'profile-subscription-bulk-create-job',
				'attributes' => array(
					'list_id'  => $this->list_id,
					'profiles' => array(
						'data' => array(
							array(
								'type'       => 'profile',
								'attributes' => array(
									'email'      => $email,
									'first_name' => $name_parts[0] ?? '',
									'last_name'  => $name_parts[1] ?? '',
									'properties' => array( 'source' => $source ),
									'subscriptions' => array(
										'email' => array(
											'marketing' => array( 'consent' => 'SUBSCRIBED' ),
										),
									),
								),
							),
						),
					),
				),
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Klaviyo-API-Key ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'revision'      => '2023-02-22',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 202 ) {
			return array( 'success' => true, 'message' => 'Queued in Klaviyo.' );
		}

		$data  = json_decode( wp_remote_retrieve_body( $response ), true );
		$error = $data['errors'][0]['detail'] ?? "HTTP {$code}";
		return array( 'success' => false, 'message' => "Klaviyo error: {$error}" );
	}
}
