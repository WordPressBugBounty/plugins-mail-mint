<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin/Security
 */

namespace Mint\MRM\Internal\Admin;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * [Manages sensitive data as in password, api keys, secret keys etc.]
 *
 * @package /app/Internal/Admin/Security
 * @since 1.0.0
 */
class MRMSecurity {

	use Singleton;

	/**
	 * The cipher method
	 *
	 * @var $ciphering
	 * @since 1.0.0
	 */
	private $ciphering;

	/**
	 * The length of the authentication tag. Its value can be between 4 and 16 for GCM mode
	 *
	 * @var $iv_length
	 * @since 1.0.0
	 */
	private $iv_length;

	/**
	 * Is a bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING
	 *
	 * @var $options
	 * @since 1.0.0
	 */
	private $options;

	/**
	 * A non-NULL Initialization Vector
	 *
	 * @var $encryption_iv
	 * @since 1.0.0
	 */
	private $encryption_iv;

	/**
	 * The passphrase. If the passphrase is shorter than expected, it is silently padded with NUL characters;
	 * if the passphrase is longer than expected, it is silently truncated.
	 *
	 * @var $encryption_key
	 * @since 1.0.0
	 */
	private $encryption_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->ciphering      = 'AES-128-CTR';
		$this->iv_length      = openssl_cipher_iv_length( $this->ciphering );
		$this->options        = 0;
		$this->encryption_iv  = '2767360247209320';
		$this->encryption_key = defined( 'MRM_ITEM_ID' ) ? MRM_ITEM_ID : 124124;
	}

	/**
	 * Encrypting given string key(s)
	 *
	 * @param string $key String key/data to encrypt.
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function encrypt( $key ) {
		return openssl_encrypt( $key, $this->ciphering, $this->encryption_key, $this->options, $this->encryption_iv );
	}

	/**
	 * Decrypting given string key(s)
	 *
	 * @param string $key String key/data to decrypt.
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function decrypt( $key ) {
		return openssl_decrypt( $key, $this->ciphering, $this->encryption_key, $this->options, $this->encryption_iv );
	}
}
