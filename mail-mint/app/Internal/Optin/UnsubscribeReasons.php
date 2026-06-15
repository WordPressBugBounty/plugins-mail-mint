<?php
/**
 * Unsubscribe survey reason definitions.
 *
 * @author   MRM Team
 * @category Internal
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Internal\Optin;

/**
 * Provides the list of reasons shown to subscribers on the unsubscribe survey.
 *
 * Developers can extend or replace the list via the `mint_unsubscribe_reasons` filter:
 *
 *   add_filter( 'mint_unsubscribe_reasons', function( $reasons ) {
 *       $reasons['my_reason'] = __( 'My custom reason', 'my-plugin' );
 *       return $reasons;
 *   } );
 *
 * @since 1.20.0
 */
class UnsubscribeReasons {

	/**
	 * Returns the slug => label map of available unsubscribe reasons.
	 *
	 * @return array<string, string>
	 * @since 1.20.0
	 */
	public static function get_reasons(): array {
		$reasons = array(
			'no_longer_interested' => __( 'I no longer want to receive these emails', 'mrm' ),
			'too_many_emails'      => __( 'I receive too many emails', 'mrm' ),
			'never_signed_up'      => __( 'I never signed up for this list', 'mrm' ),
			'not_relevant'         => __( 'The content is not relevant to me', 'mrm' ),
			'other'                => __( 'Other', 'mrm' ),
		);

		return (array) apply_filters( 'mint_unsubscribe_reasons', $reasons );
	}

	/**
	 * Checks whether a given slug is a valid reason.
	 *
	 * @param string $reason Reason slug to validate.
	 * @return bool
	 * @since 1.20.0
	 */
	public static function is_valid_reason( string $reason ): bool {
		return array_key_exists( $reason, self::get_reasons() );
	}

	/**
	 * Returns the human-readable label for a reason slug, or an empty string if not found.
	 *
	 * @param string $reason Reason slug.
	 * @return string
	 * @since 1.20.0
	 */
	public static function get_label( string $reason ): string {
		$reasons = self::get_reasons();
		return isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : '';
	}
}
