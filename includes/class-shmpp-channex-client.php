<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal Channex REST client.
 */
class ShmppChannexClient {

	/** @var string */
	private $api_key;

	/** @var string */
	private $base_url;

	/**
	 * @param string $api_key     Channex API key.
	 * @param string $environment staging|live.
	 */
	public function __construct( $api_key, $environment = 'staging' ) {
		$this->api_key  = (string) $api_key;
		$this->base_url = ( 'live' === $environment )
			? 'https://app.channex.io/api/v1'
			: 'https://staging.channex.io/api/v1';
	}

	/**
	 * @param string               $method GET|POST|PUT|DELETE.
	 * @param string               $path   Path after /api/v1.
	 * @param array<string,mixed>|null $body JSON body.
	 * @param array<string,string> $query  Query args.
	 * @return array|WP_Error Decoded JSON or error.
	 */
	public function request( $method, $path, $body = null, $query = array() ) {
		if ( '' === $this->api_key ) {
			return new WP_Error( 'channex_no_key', 'Channex API key is not configured', array( 'status' => 400 ) );
		}

		$url = trailingslashit( $this->base_url ) . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'user-api-key'  => $this->api_key,
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['errors']['title'] ) ? $data['errors']['title'] : 'Channex API error';
			if ( ! empty( $data['errors']['details'] ) && is_array( $data['errors']['details'] ) ) {
				$message .= ': ' . implode( ', ', $data['errors']['details'] );
			}
			return new WP_Error( 'channex_http', $message, array( 'status' => $code, 'body' => $data ) );
		}

		return $data;
	}

	public function list_properties() {
		return $this->request( 'GET', 'properties', null, array( 'pagination[limit]' => 100 ) );
	}

	public function list_room_types( $property_id ) {
		return $this->request(
			'GET',
			'room_types',
			null,
			array(
				'filter[property_id]' => $property_id,
				'pagination[limit]'   => 100,
			)
		);
	}

	public function list_rate_plans( $property_id ) {
		return $this->request(
			'GET',
			'rate_plans',
			null,
			array(
				'filter[property_id]' => $property_id,
				'pagination[limit]'   => 100,
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $values Availability values.
	 * @return array|WP_Error
	 */
	public function update_availability( array $values ) {
		return $this->request( 'POST', 'availability', array( 'values' => $values ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $values Restriction values.
	 * @return array|WP_Error
	 */
	public function update_restrictions( array $values ) {
		return $this->request( 'POST', 'restrictions', array( 'values' => $values ) );
	}

	public function booking_revisions_feed( $property_id = '' ) {
		$query = array();
		if ( $property_id ) {
			$query['filter[property_id]'] = $property_id;
		}
		return $this->request( 'GET', 'booking_revisions/feed', null, $query );
	}

	public function get_booking_revision( $revision_id ) {
		return $this->request( 'GET', 'booking_revisions/' . rawurlencode( $revision_id ) );
	}

	public function ack_booking_revision( $revision_id ) {
		return $this->request( 'POST', 'booking_revisions/' . rawurlencode( $revision_id ) . '/ack' );
	}
}
