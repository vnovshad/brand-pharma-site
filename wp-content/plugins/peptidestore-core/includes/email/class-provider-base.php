<?php
/**
 * Abstract email provider base.
 *
 * All concrete providers extend this class and implement subscribe().
 * The method must return an array with 'success' (bool) and 'message' (string).
 * Local DB writes are always handled by Email_Capture before providers are
 * called, so providers only need to handle the external API call.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Email;

defined( 'ABSPATH' ) || exit;

abstract class Provider_Base {

	/** Human-readable name shown in admin settings. */
	abstract public function label(): string;

	/**
	 * Subscribe an email address to the external provider.
	 *
	 * @param string $email   Validated email address.
	 * @param string $name    Optional subscriber name.
	 * @param string $source  Origin of the signup (e.g. 'footer', 'blog').
	 * @return array{success: bool, message: string}
	 */
	abstract public function subscribe( string $email, string $name, string $source ): array;

	/** Whether this provider requires an API key to function. */
	public function requires_api_key(): bool {
		return false;
	}
}
