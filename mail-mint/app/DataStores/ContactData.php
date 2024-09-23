<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/DataStores
 */

namespace Mint\MRM\DataStores;

use MRM\Common\MrmCommon;

/**
 * [Manage contact data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class ContactData {

	/**
	 * Contact Email
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $email;

	/**
	 * Contact First name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $first_name;

	/**
	 * Contact Last name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $last_name;

	/**
	 * Contact Contact number
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $phone;

	/**
	 * Contact status
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $status;

	/**
	 * Contact date of birth
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $date_of_birth;

	/**
	 * Contact timezone
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $timezone;

	/**
	 * Contact source
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $source;

	/**
	 * Contact address line 1
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $address_line_1;


	/**
	 * Contact address line 2
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $address_line_2;

	/**
	 * Contact city
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $city;

	/**
	 * Contact state
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $state;

	/**
	 * Contact country
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $country;

	/**
	 * Contact postal code
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $postal_code;

	/**
	 * Contact company name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $company_name;

	/**
	 * Contact owner
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $contact_owner;


	/**
	 * Contact ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $contact_id;

	/**
	 * Created By WP user ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $created_by;

	/**
	 * Contact Meta Fileds
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $meta_fields;

	/**
	 * Native WordPress user ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $wp_user_id;

	/**
	 * Initialize class functionalities
	 *
	 * @param string $email Contact email.
	 * @param array  $args Contact data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $email, $args = array() ) {
		$this->email          = $email;
		$this->first_name     = isset( $args[ 'first_name' ] ) ? sanitize_text_field( $args[ 'first_name' ] ) : '';
		$this->last_name      = isset( $args[ 'last_name' ] ) ? sanitize_text_field( $args[ 'last_name' ] ) : '';
		$this->phone          = isset( $args[ 'phone' ] ) ? sanitize_text_field( $args[ 'phone' ] ) : '';
		$this->status         = isset( $args[ 'status' ] ) ? $args[ 'status' ] : 'pending';
		$this->source         = isset( $args[ 'source' ] ) ? sanitize_text_field( $args[ 'source' ] ) : 'Manual';
		$this->date_of_birth  = isset( $args[ 'date_of_birth' ] ) ? sanitize_text_field( $args[ 'date_of_birth' ] ) : '';
		$this->timezone       = isset( $args[ 'timezone' ] ) ? sanitize_text_field( $args[ 'timezone' ] ) : '';
		$this->address_line_1 = isset( $args[ 'address_line_1' ] ) ? sanitize_text_field( $args[ 'address_line_1' ] ) : '';
		$this->address_line_2 = isset( $args[ 'address_line_2' ] ) ? sanitize_text_field( $args[ 'address_line_2' ] ) : '';
		$this->city           = isset( $args[ 'city' ] ) ? sanitize_text_field( $args[ 'city' ] ) : '';
		$this->state          = isset( $args[ 'state' ] ) ? sanitize_text_field( $args[ 'state' ] ) : '';
		$this->country        = isset( $args[ 'country' ] ) ? sanitize_text_field( $args[ 'country' ] ) : '';
		$this->postal_code    = isset( $args[ 'postal_code' ] ) ? sanitize_text_field( $args[ 'postal_code' ] ) : '';
		$this->company_name   = isset( $args[ 'company_name' ] ) ? sanitize_text_field( $args[ 'company_name' ] ) : '';
		$this->contact_id     = isset( $args[ 'contact_id' ] ) ? sanitize_text_field( $args[ 'contact_id' ] ) : '';
		$this->meta_fields    = isset( $args[ 'meta_fields' ] ) ? $args[ 'meta_fields' ] : array();
		$this->created_by     = isset( $args[ 'created_by' ] ) ? sanitize_text_field( $args[ 'created_by' ] ) : '';
		$this->wp_user_id     = isset( $args[ 'wp_user_id' ] ) ? sanitize_text_field( $args[ 'wp_user_id' ] ) : '';
	}


	/**
	 * Get contact data as array
	 */
	public function get_contact_default_data_as_array() {
		return $this->object_to_array( $this );
	}

	/**
	 * Return Contact email
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_email() {
		return $this->email;
	}


	/**
	 * Return Contact first name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_first_name() {
		return $this->first_name;
	}


	/**
	 * Return Contact last name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_last_name() {
		return $this->last_name;
	}


	/**
	 * Return Contact phone
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_phone() {
		return $this->phone;
	}

	/**
	 * Return Contact status
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Return Contact source
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_source() {
		return $this->source;
	}


	/**
	 * Return contact date of birth
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_date_of_birth() {
		return $this->date_of_birth;
	}


	/**
	 * Return contact timezone
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_timezone() {
		return $this->timezone;
	}


	/**
	 * Return contact address line 1
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_address_line_1() {
		return $this->address_line_1;
	}


	/**
	 * Return contact address line 2
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_address_line_2() {
		return $this->address_line_2;
	}


	/**
	 * Return contact city
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_city() {
		return $this->city;
	}


	/**
	 * Return contact state
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_state() {
		return $this->state;
	}


	/**
	 * Return contact country
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_country() {
		return $this->country;
	}


	/**
	 * Return contact postal_code
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_postal_code() {
		return $this->postal_code;
	}


	/**
	 * Return contact company_name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_company_name() {
		return $this->company_name;
	}


	/**
	 * Return contact owner
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_contact_owner() {
		if ( is_user_logged_in() ) {
			$this->contact_owner = get_current_user_id();

			return $this->contact_owner;
		}
		$this->contact_owner = 1;

		return $this->contact_owner;
	}


	/**
	 * Return created by WP user ID
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_created_by() {
		return $this->created_by;
	}


	/**
	 * Return contact ID
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_contact_id() {
		return $this->contact_id;
	}


	/**
	 * Return Meta Fields
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_meta_fields() {
		return $this->meta_fields;
	}


	/**
	 * Return WP user ID
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_wp_user_id() {
		return $this->wp_user_id;
	}


	/**
	 * Object to array utility function
	 *
	 * @param mixed $data object to array conversion variable.
	 * @return array
	 * @since 1.0.0
	 */
	private function object_to_array( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			$result = array();
			foreach ( $data as $key => $value ) {
				$result[ $key ] = ( is_array( $data ) || is_object( $data ) ) ? $this->object_to_array( $value ) : $value;
			}
			return $result;
		}
		return $data;
	}

}
