<?php
/**
 * Local-only provider — no external API call.
 *
 * Subscribers are stored in wp_psc_subscribers (created by Email_Capture).
 * This provider simply returns success so Email_Capture can confirm
 * the signup without an external dependency.
 *
 * Used as the default when no external provider is configured, and as
 * a fallback if an external provider fails.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Email;

defined( 'ABSPATH' ) || exit;

class Provider_Local extends Provider_Base {

	public function label(): string {
		return __( 'Store locally (no external provider)', 'peptidestore' );
	}

	public function subscribe( string $email, string $name, string $source ): array {
		// The Email_Capture class already wrote to the DB before calling providers.
		// Nothing else to do here.
		return array( 'success' => true, 'message' => 'Stored locally.' );
	}
}
