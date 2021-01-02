<?php 
/**
 * Functions to Sanitize Cmb2 MetaBoxes
 * 
 */
 
defined( 'ABSPATH' ) or exit; // Exit if accessed directly

/**
 * Validation: Work Schedule
 */
function sanitize_get_ga_provider_work_schedule( $value, $field_args, $field ) {	
	if( !class_exists('ga_work_schedule') ) {
		require_once( ga_base_path . '/admin/includes/ga_work_schedule.php' );
	}
	$validate = new ga_work_schedule( $field->object_id );
	$schedule = $validate->validate_work_schedule( $value );
	
	return $schedule;
}

/**
 * Validation: Break Schedule
 */
function sanitize_get_ga_provider_breaks( $breaks, $field_args, $field ) {
	if( !class_exists('ga_work_schedule') ) {
		require_once( ga_base_path . '/admin/includes/ga_work_schedule.php' );
	}
	
	$validate = new ga_work_schedule( $field->object_id );
	$breaks = $validate->validate_breaks( $breaks );

	return $breaks;
}

/**
 * Validation: Provider Holidays
 */

function sanitize_get_ga_provider_holidays($holidays, $field_args, $field) {
	if( !class_exists('ga_work_schedule') ) {
		require_once( ga_base_path . '/admin/includes/ga_work_schedule.php' );
	}
	
	$validate = new ga_work_schedule( $field->object_id );
	$holidays = $validate->validate_holidays( $holidays );

	return $holidays;
}

/**
 * Validation: GravityForms Entry ID
 */
function sanitize_get_gravity_form_entries_ids($value, $field_args, $field) {
	if( array_key_exists( $value, get_gravity_form_entries_ids() ) ) {
		return $value;
	} else {
		return '';
	}
}

/**
 * Validation: Appointment Services
 */
function sanitize_ga_appointment_services($value, $field_args, $field) {

	if( array_key_exists( $value, get_ga_appointment_services() ) ) {
		return $value;
	} else {
		return '';
	}
}

/**
 * Validation: Appointment IP
 */
function sanitize_ga_appointment_ip($value, $field_args, $field) {
	// Validate ip
	if ( filter_var($value, FILTER_VALIDATE_IP) ) {
		return $value;
	} else {
		return '';
	} 
}

/**
 * Validation: Client
 */
function sanitize_get_ga_appointment_users($value, $field_args, $field) {
	// Validate ip
	if( array_key_exists( $value, get_ga_appointment_users() ) ) {
		return $value;
	} else {
		return 'new_client';
	}
}

/**
 * Validation: New Customer
 */
function sanitize_get_ga_appointment_new_client($value, $field_args, $field) {
	$client = array();
	
	if( isset( $value['name'] ) ) {
		$client['name'] = sanitize_text_field( $value['name'] );
	}
	
	if( isset( $value['email'] ) ) {
		$client['email'] = sanitize_text_field( $value['email'] );		
	}	
	
	
	if( isset( $value['phone'] ) ) {
		$client['phone'] = sanitize_text_field( $value['phone'] );
	}
	
	return $client;
	
}

/**
 * Validation: Appointment Time
 */
function sanitize_get_ga_appointment_time($value, $field_args, $field) {
		
	if( array_key_exists( $value, get_ga_appointment_time('schedule') ) ) {
		$time_value = $value;
	} else {
		$time_value = '09:00';
	}
	
	if( isset($_POST['ga_appointment_duration']) && array_key_exists( $_POST['ga_appointment_duration'], ga_service_duration_options() ) ) {
		$duration = $_POST['ga_appointment_duration'];
	} else {
		$duration = '30';
	}
	
	// Date Slots Mode
	$appointment_type = isset($_POST['ga_appointment_type']) 
					&& in_array($_POST['ga_appointment_type'], array('time_slot', 'date')) 
					? $_POST['ga_appointment_type']
					: 'time_slot';
					
	if( $appointment_type == 'date' ) {		
		$time_value = '00:00';
		$end_time   = '23:59';
	} else {
		$slot_time     = new DateTime( $time_value ); // Appointment Time
		$interval      = new DateInterval("PT" . $duration . "M");
		$slot_end      = clone $slot_time;
		$slot_end->add( $interval );
		$end_time      = $slot_end->format('H:i');
		
		if( $slot_time->format('A') == 'PM' && $slot_end->format('A') == 'AM' ) {
			$end_time = '23:59';
		}				
	}
	
	update_post_meta( $field->object_id, 'ga_appointment_time_end', $end_time ); // 24h format	
	return $time_value;	
}

/**
 * Validation: Appointment Providers
 */
function sanitize_get_ga_appointment_providers($value, $field_args, $field) {
	
	if( array_key_exists( $value, get_ga_appointment_providers() ) ) {
		$old_value = get_post_meta( $field->object_id, 'ga_appointment_provider', true );

		if( $value != $old_value ) {
			do_action( 'ga_appointment_provider_switch', $field->object_id );
		}

		return $value;
	} else {
		return '0';
	}

}

/**
 * Validation: Provider Assigned User
 */
function sanitize_get_ga_provider_users($value, $field_args, $field) {
	$post_id = $field->object_id;
	
	if( array_key_exists( $value, get_ga_provider_users() ) ) {
		// Check if user is already assigned
		$providers = new WP_QUERY(
			array(
				'post_type'         => 'ga_providers',
				'post_status'       => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
				'posts_per_page'    => 1,
				'meta_query'        => array(
					array(
						'key'     => 'ga_provider_user',
						'value'   => $value,
					),
				),		
			)
		);
		wp_reset_postdata();
		// check if user already exists but is not current post that has the value
		// Check if user is already assigned
		if( $providers->post_count == 1 && $providers->post->ID == $post_id ) {
			return $value;
		} elseif( $providers->post_count == 0 ) {
			return $value;
		} else {
			return '';
		}

	} else {
		return '';
	}
}

/**
 * Validation: Provider Services
 */
function sanitize_ga_provider_services($value, $field_args, $field) {
	
	$provider_services    = (array) $value;
	$services             = array();
	
	foreach( $provider_services as $service_id ) {
		if( array_key_exists( $service_id, get_ga_appointment_services() ) ) {
			$services[] = $service_id;
		}	
	}
	
	return $services;
}

/**
 * Validation: Provider Services
 */
function sanitize_ga_provider_gcal($value, $field_args, $field) {
	if( isset( $value['reset_api'] ) ) {
		$value['client_id']     = '';
		$value['client_secret'] = '';
		$value['access_code']   = '';
		$value['calendar_id']   = '';
		
		// Delete tokens
		update_post_meta( $field->object_id, 'ga_provider_gcal_token', array('access_token' => '', 'refresh_token' => '') );
	}	
	
	return $value;
}

/**
 * Validation: Service Duration
 */
function ga_sanitize_service_duration_options($value, $field_args, $field) {
	
	if( array_key_exists( $value, ga_service_duration_options() ) ) {
		return $value;
	} else {
		return '30';
	}
	
}

/**
 * Validation: Service Cleanup
 */
function sanitize_ga_service_cleanup_options($value, $field_args, $field) {
	
	if( array_key_exists( $value, ga_service_cleanup_options() ) ) {
		return $value;
	} else {
		return '0';
	}
	
}

/**
 * Validation: Service Date
 */
function sanitize_get_ga_services_date( $value, $field_args, $field ) {
	if( ga_valid_date_format( $value ) ) {
		return $value;
	} else {
		return '';
	}

}

/**
 * Validation: Service Capacity
 */
function sanitize_ga_services_capacity_options( $value, $field_args, $field ) {
	
	if( array_key_exists( $value, ga_services_capacity_options() ) ) {
		return $value;
	} else {
		return '1';
	}
	
}


/**
 * Filter: Price
 */
function ga_filter_price( $value ) {
	$price    = esc_html($value);
	$filtered = filter_var($price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
	
	if( strpos($filtered, '.') !== false ) {
		return number_format($filtered, 2, ".", "");
	} else {
		return absint( $filtered );
	}
}

/**
 * Validation: Service Price
 */
function sanitize_ga_services_price( $value, $field_args, $field ) {
	return ga_filter_price( $value );
}

/**
 * Validation: Service Date Range Period
 */
function sanitize_get_ga_service_date_range($value, $field_args, $field) {	
	if( isset($value['from']) && ga_valid_date_format($value['from']) && isset($value['to']) && ga_valid_date_format($value['to']) ) {
		
		if ( new DateTime($value['to']) > new DateTime($value['from'])  ) {
			# do nothing
		} else {
			$value['to'] = $value['from'];
		}
		
	} else {
		$value = '';
	}

	return $value;
}

/**
 * Validation: Service Custom Dates Period
 */
function sanitize_get_ga_service_custom_dates($value, $field_args, $field) {
		
	if( is_array($value) ) {	
	
		foreach( $value as $key => $date ) {
			$date = trim( $date );
			if( !ga_valid_date_format($date) ) {
				unset($value[$key]);
			}
		}	
		
		usort($value, "ga_date_format_sort");
	
		$value = array_unique($value);
		return $value;
	} else{
		return '';
	}

}




/**
 * Validation: Service Custom Time Slots
 */
function sanitize_get_ga_service_custom_slots($value, $field_args, $field) {
	if( is_array($value) ) {	
		$slots = array();
		foreach( $value as $key => $slot ) {
			if( !array_key_exists( $slot['start'], get_ga_appointment_time( $out = false, $_24 = false) ) || !array_key_exists( $slot['end'], get_ga_appointment_time( $out = false, true ) ) ) {
				unset( $value[$key] );
				continue;
			}
			
			if( new DateTime($slot['end']) <= new DateTime($slot['start']) ) {
				unset( $value[$key] );
				continue;
			}

			$clash = false;

			foreach ($slot['availability'] as $day){
			    $parsedSlot = sprintf('%s-%s-%s', $slot['start'], $slot['end'], $day);
			    if (in_array($parsedSlot, $slots)){
			        unset ($value[$key]);
			        $clash = true;
			        break;
                }
			    else{
			        $slots[] = $parsedSlot;
                }
            }

			if ($clash){
                continue;
            }

//			if( in_array( sprintf('%s-%s', $slot['start'], $slot['end']), $slots) ) {
//				unset( $value[$key] );
//				continue;
//			} else {
//				$slots[] = sprintf('%s-%s', $slot['start'], $slot['end']);
//			}
			
			$value[$key]['price'] = ga_filter_price( $slot['price'] );
		}
		return $value;
	} else {
		return array();
	}
}