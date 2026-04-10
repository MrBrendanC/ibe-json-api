<?php
declare(strict_types=1);

namespace Brendan\Includes\HotelMapper;

/**
 * Hotel and Room Mapping Service
 * 
 * Provides fast lookups for hotel and room information using JSON mapping files.
 * Uses WordPress transients for caching to optimize performance.
 * 
 * @package Brendan\Includes\HotelMapper
 * @author Brendan Cornelius
 */
class BC_Hotel_Mapper {

	/**
	 * Cache duration for transients (24 hours)
	 */
	private const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Path to hotels mapping file
	 */
	private string $hotels_file;

	/**
	 * Path to rooms mapping file
	 */
	private string $rooms_file;

	/**
	 * Constructor
	 */
	public function __construct() {
		$plugin_dir = dirname( __DIR__ );
		$this->hotels_file = $plugin_dir . '/data/hotels-mapping.json';
		$this->rooms_file = $plugin_dir . '/data/rooms-mapping.json';
	}

	/**
	 * Get hotel information by site ID
	 *
	 * @param int|string $site_id The hotel site ID
	 * @return array|null Hotel information or null if not found
	 */
	public function get_hotel( $site_id ): ?array {
		$hotels = $this->get_all_hotels();
		$site_id_str = (string) $site_id;
		
		return $hotels[$site_id_str] ?? null;
	}

	/**
	 * Get room information by room ID
	 *
	 * @param int|string $room_id The room ID
	 * @return array|null Room information or null if not found
	 */
	public function get_room( $room_id ): ?array {
		$rooms = $this->get_all_rooms();
		$room_id_str = (string) $room_id;
		
		return $rooms[$room_id_str] ?? null;
	}

	/**
	 * Get room information with hotel details included
	 *
	 * @param int|string $room_id The room ID
	 * @return array|null Combined room and hotel information or null if not found
	 */
	public function get_room_with_hotel( $room_id ): ?array {
		$room = $this->get_room( $room_id );
		
		if ( ! $room ) {
			return null;
		}

		$hotel = $this->get_hotel( $room['site_id'] );
		
		return [
			'room' => $room,
			'hotel' => $hotel,
		];
	}

	/**
	 * Get all rooms for a specific hotel
	 *
	 * @param int|string $site_id The hotel site ID
	 * @return array Array of rooms for the hotel
	 */
	public function get_hotel_rooms( $site_id ): array {
		$all_rooms = $this->get_all_rooms();
		$site_id_str = (string) $site_id;
		$hotel_rooms = [];

		foreach ( $all_rooms as $room_id => $room_data ) {
			if ( $room_data['site_id'] === $site_id_str ) {
				$hotel_rooms[$room_id] = $room_data;
			}
		}

		return $hotel_rooms;
	}

	/**
	 * Enrich API response with hotel and room details
	 *
	 * @param array $api_response The API response from the booking system
	 * @return array Enriched response with hotel and room details
	 */
	public function enrich_api_response( array $api_response ): array {
		if ( ! isset( $api_response['siteList'] ) || ! is_array( $api_response['siteList'] ) ) {
			return $api_response;
		}

		foreach ( $api_response['siteList'] as &$site ) {
			// Add hotel details
			$hotel_info = $this->get_hotel( $site['siteID'] );
			if ( $hotel_info ) {
				$site['hotelDetails'] = $hotel_info;
			}

			// Add room details to each rate
			if ( isset( $site['rates'] ) && is_array( $site['rates'] ) ) {
				foreach ( $site['rates'] as &$rate ) {
					if ( isset( $rate['allRoomOptions'] ) && is_array( $rate['allRoomOptions'] ) ) {
						foreach ( $rate['allRoomOptions'] as &$room_option ) {
							$room_info = $this->get_room( $room_option['roomID'] );
							if ( $room_info ) {
								$room_option['roomDetails'] = $room_info;
							}
						}
					}
				}
			}
		}

		return $api_response;
	}

	/**
	 * Get all hotels from mapping file (with caching)
	 *
	 * @return array All hotels data
	 */
	private function get_all_hotels(): array {
		$cache_key = 'bc_hotels_mapping';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->load_json_file( $this->hotels_file );
		set_transient( $cache_key, $data, self::CACHE_DURATION );

		return $data;
	}

	/**
	 * Get all rooms from mapping file (with caching)
	 *
	 * @return array All rooms data
	 */
	private function get_all_rooms(): array {
		$cache_key = 'bc_rooms_mapping';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->load_json_file( $this->rooms_file );
		set_transient( $cache_key, $data, self::CACHE_DURATION );

		return $data;
	}

	/**
	 * Load and decode JSON file
	 *
	 * @param string $file_path Path to JSON file
	 * @return array Decoded JSON data or empty array on failure
	 */
	private function load_json_file( string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return [];
		}

		$json_content = file_get_contents( $file_path );
		if ( false === $json_content ) {
			return [];
		}

		$data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Clear cached mapping data
	 * Useful after updating JSON files
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( 'bc_hotels_mapping' );
		delete_transient( 'bc_rooms_mapping' );
	}

	/**
	 * Search hotels by name
	 *
	 * @param string $search_term Search term
	 * @return array Array of matching hotels
	 */
	public function search_hotels( string $search_term ): array {
		$all_hotels = $this->get_all_hotels();
		$search_term = strtolower( $search_term );
		$results = [];

		foreach ( $all_hotels as $site_id => $hotel ) {
			if ( isset( $hotel['name'] ) && 
				 str_contains( strtolower( $hotel['name'] ), $search_term ) ) {
				$results[$site_id] = $hotel;
			}
		}

		return $results;
	}
}
