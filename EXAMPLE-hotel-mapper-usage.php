<?php
/**
 * Example Usage: BC_Hotel_Mapper Class
 * 
 * This file demonstrates how to use the hotel and room mapping service.
 * DO NOT include this file in production - it's just for reference.
 */

use Brendan\Includes\HotelMapper\BC_Hotel_Mapper;

// Initialize the mapper
$mapper = new BC_Hotel_Mapper();

// ============================================
// Example 1: Get hotel information
// ============================================
$hotel = $mapper->get_hotel( 16305 );
if ( $hotel ) {
	echo "Hotel Name: " . $hotel['name'] . "\n";
	echo "Photos: " . implode( ', ', $hotel['photos'] ) . "\n";
}

// ============================================
// Example 2: Get room information
// ============================================
$room = $mapper->get_room( 96747104 );
if ( $room ) {
	echo "Room Type: " . $room['name'] . "\n";
	echo "Max Occupancy: " . $room['max_occupancy'] . "\n";
	echo "Price: Available from API\n";
}

// ============================================
// Example 3: Get room with hotel details
// ============================================
$room_with_hotel = $mapper->get_room_with_hotel( 96747104 );
if ( $room_with_hotel ) {
	echo "Hotel: " . $room_with_hotel['hotel']['name'] . "\n";
	echo "Room: " . $room_with_hotel['room']['name'] . "\n";
}

// ============================================
// Example 4: Get all rooms for a hotel
// ============================================
$hotel_rooms = $mapper->get_hotel_rooms( 16305 );
foreach ( $hotel_rooms as $room_id => $room_data ) {
	echo "Room ID: {$room_id} - {$room_data['name']}\n";
}

// ============================================
// Example 5: Enrich API response (RECOMMENDED)
// ============================================
// This is the most useful method - it adds hotel and room details
// directly to your API response
$api_response = json_decode( '{
	"siteList": [
		{
			"siteID": 16305,
			"primaryName": "The Capital Zimbali",
			"rates": [
				{
					"rateID": 96748358,
					"allRoomOptions": [
						{
							"roomID": 96747104,
							"price": 3202.60
						}
					]
				}
			]
		}
	]
}', true );

$enriched_response = $mapper->enrich_api_response( $api_response );

// Now the response includes:
// - $enriched_response['siteList'][0]['hotelDetails'] - Full hotel info
// - $enriched_response['siteList'][0]['rates'][0]['allRoomOptions'][0]['roomDetails'] - Full room info

// ============================================
// Example 6: Use in WooCommerce product display
// ============================================
add_action( 'woocommerce_after_shop_loop_item_title', function() {
	global $product;
	
	$mapper = new BC_Hotel_Mapper();
	$room_id = get_post_meta( $product->get_id(), '_room_id', true );
	
	if ( $room_id ) {
		$room_info = $mapper->get_room_with_hotel( $room_id );
		
		if ( $room_info ) {
			echo '<div class="room-details">';
			echo '<p class="hotel-name">' . esc_html( $room_info['hotel']['name'] ) . '</p>';
			echo '<p class="room-type">' . esc_html( $room_info['room']['name'] ) . '</p>';
			
			// Display photos
			if ( ! empty( $room_info['room']['photos'] ) ) {
				echo '<div class="room-photos">';
				foreach ( $room_info['room']['photos'] as $photo_url ) {
					echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $room_info['room']['name'] ) . '" />';
				}
				echo '</div>';
			}
			echo '</div>';
		}
	}
}, 10 );

// ============================================
// Example 7: Use in AJAX handler for search results
// ============================================
add_action( 'wp_ajax_get_hotel_search_results', function() {
	$api_response = $_POST['api_response'] ?? '';
	
	if ( empty( $api_response ) ) {
		wp_send_json_error( 'No API response provided' );
	}
	
	$mapper = new BC_Hotel_Mapper();
	$enriched_data = $mapper->enrich_api_response( json_decode( $api_response, true ) );
	
	wp_send_json_success( $enriched_data );
} );

add_action( 'wp_ajax_nopriv_get_hotel_search_results', function() {
	// Same as above for non-logged-in users
} );

// ============================================
// Example 8: Search hotels
// ============================================
$search_results = $mapper->search_hotels( 'Capital' );
foreach ( $search_results as $site_id => $hotel ) {
	echo "Found: {$hotel['name']} (ID: {$site_id})\n";
}

// ============================================
// Example 9: Clear cache after updating JSON files
// ============================================
// Run this after you edit hotels-mapping.json or rooms-mapping.json
$mapper->clear_cache();

// ============================================
// Example 10: React Component Data Preparation
// ============================================
function prepare_data_for_react( $api_response ) {
	$mapper = new BC_Hotel_Mapper();
	$enriched = $mapper->enrich_api_response( $api_response );
	
	// Now pass this to your React component via wp_localize_script
	wp_localize_script( 'your-react-app', 'hotelData', [
		'sites' => $enriched['siteList']
	] );
	
	return $enriched;
}
