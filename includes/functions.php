<?php
defined('ABSPATH') or exit; // Exit if accessed directly

require_once('metabox_render.php');     // Cmb2 Render MetaBoxes
require_once('metabox_validation.php'); // Sanitize Cmb2 MetaBoxes


/**
 * Valid Date Format: Y-m-j
 */
function ga_valid_date_format($date_input)
{
	$d = DateTime::createFromFormat('Y-m-j', $date_input);
	return $d && ($d->format('Y-m-j') == $date_input);
}

/**
 * Valid Month Year Date Format: Y-m
 */
function ga_valid_year_month_format($date_input)
{
	//$d = DateTime::createFromFormat('Y-m', $date_input);
	//return $d && $d->format('Y-m') === $date_input;
	return $date_input == date('Y-m', strtotime($date_input . '-01'));
}

/**
 * Valid Time Format: H:i //24h
 */
function ga_valid_time_format($time_input)
{
	$t = DateTime::createFromFormat('H:i', $time_input);
	return $t && $t->format('H:i') === $time_input;
}

/**
 * Valid Date & Time Format: Y-m-j H:i
 */
function ga_valid_date_time_format($date_time)
{
	$t = DateTime::createFromFormat('Y-m-j H:i', $date_time);
	return $t && $t->format('Y-m-j H:i') === $date_time;
}


/**
 * Get service end time duration
 */
function ga_get_time_end($slot_start, $service_id)
{
	$duration = (int) get_post_meta($service_id, 'ga_service_duration', true);

	$slot_end = new DateTime($slot_start);
	$interval = new DateInterval("PT" . $duration . "M");
	$slot_end->add($interval);
	$time_end = $slot_end->format('H:i') == '00:00' ? '23:59' : $slot_end->format('H:i');

	return $time_end;
}

/**
 * Translate calendar am_pm
 */
function ga_get_form_translated_am_pm($form, $data)
{
	$am = ga_get_form_translated_data($form, 'am');
	$pm = ga_get_form_translated_data($form, 'pm');

	$find = array(
		'am',
		'pm',
	);

	$replace = array(
		$am,
		$pm,
	);

	return str_ireplace($find, $replace, $data);
}

/**
 * Translate calendar space
 */
function ga_get_form_translated_space($form, $total)
{
	$data = ga_get_form_translated_data($form, 'space');

	return str_ireplace('[total]', $total, $data);
}

/**
 * Translate calendar spaces
 */
function ga_get_form_translated_spaces($form, $total)
{
	$data = ga_get_form_translated_data($form, 'spaces');

	return str_ireplace('[total]', $total, $data);
}


/**
 * Translate Date & Time
 */
function ga_get_form_translated_date_time($form, $month, $week, $day, $year, $time)
{
	$month      = strtolower($month);
	$data       = ga_get_form_translated_data($form, 'date_time_' . $month);
	$long_weeks = ga_get_form_translated_data($form, 'long_weeks');
	$week       = strtolower($week);
	$week       = isset($long_weeks[$week]) ? $long_weeks[$week] : $week;

	$time       = ga_get_form_translated_am_pm($form, $time);

	$find = array(
		'[week_long]',
		'[day]',
		'[year]',
		'[time]',
	);

	$replace = array(
		$week,
		$day,
		$year,
		$time,
	);

	return str_ireplace($find, $replace, $data);
}

/**
 * Translate calendar heading
 */
function ga_get_form_translated_month($form, $month, $year)
{
	$month = strtolower($month);
	$data  = ga_get_form_translated_data($form, $month);

	return str_ireplace('[year]', $year, $data);
}

/**
 * Translate calendar slots availability
 */
function ga_get_form_translated_slots_date($form, $month, $day, $year)
{
	$month = strtolower($month);
	$data = ga_get_form_translated_data($form, 'slots_' . $month);

	$find = array(
		'[day]',
		'[year]',
	);

	$replace = array(
		$day,
		$year,
	);

	return str_ireplace($find, $replace, $data);
}

/**
 * Translate: Error message
 */
function ga_get_form_translated_error_message($form, $error, $date = false)
{
	$data = ga_get_form_translated_data($form, $error);

	if ($date) {
		return str_ireplace('[date]', $date, $data);
	} else {
		return $data;
	}
}

/**
 * Translate: Error message
 */
function ga_get_form_translated_error_max_bookings($form, $date, $total)
{
	$data = ga_get_form_translated_data($form, 'error_max_bookings');

	$find = array(
		'[date]',
		'[total]',
	);

	$replace = array(
		$date,
		$total,
	);

	return str_ireplace($find, $replace, $data);
}

/**
 * Translate: Client Service Title
 */
function ga_get_translated_client_service($form = false, $service_name, $provider_name)
{
	$data = ga_get_form_translated_data($form, 'client_service');

	$find = array(
		'[service_name]',
		'[provider_name]',
	);

	$replace = array(
		$service_name,
		$provider_name,
	);

	return str_ireplace($find, $replace, $data);
}


/**
 * Translate: Client Provider Title
 */
function ga_get_translated_provider_service($form = false, $service_name, $client_name)
{
	$data = ga_get_form_translated_data($form, 'provider_service');

	$find = array(
		'[service_name]',
		'[client_name]',
	);

	$replace = array(
		$service_name,
		$client_name,
	);

	return str_ireplace($find, $replace, $data);
}

/**
 * Default Translation Data
 */
function ga_get_translated_data($translate)
{
	$lang = get_option('ga_appointments_translation');

	if (isset($lang[$translate])) {
		return $lang[$translate];
	}

	$default = array(
		// Calendar week days short names
		'weeks'     => array('sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri',  'sat' => 'Sat'),

		// Calendar week days long names
		'long_weeks'     => array('sunday' => 'Sunday', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday',  'saturday' => 'Saturday'),

		// Calendar month year
		'january'   => 'January [year]',
		'february'  => 'February [year]',
		'march'     => 'March [year]',
		'april'     => 'April [year]',
		'may'       => 'May [year]',
		'june'      => 'June [year]',
		'july'      => 'July [year]',
		'august'    => 'August [year]',
		'september' => 'September [year]',
		'october'   => 'October [year]',
		'november'  => 'November [year]',
		'december'  => 'December [year]',

		// Calendar time slots
		'slots_january'   => 'January [day], [year]',
		'slots_february'  => 'February [day], [year]',
		'slots_march'     => 'March [day], [year]',
		'slots_april'     => 'April [day], [year]',
		'slots_may'       => 'May [day], [year]',
		'slots_june'      => 'June [day], [year]',
		'slots_july'      => 'July [day], [year]',
		'slots_august'    => 'August [day], [year]',
		'slots_september' => 'September [day], [year]',
		'slots_october'   => 'October [day], [year]',
		'slots_november'  => 'November [day], [year]',
		'slots_december'  => 'December [day], [year]',

		// Calendar date/time
		'date_time_january'   => '[week_long], January [day] [year] at [time]',
		'date_time_february'  => '[week_long], February [day] [year] at [time]',
		'date_time_march'     => '[week_long], March [day] [year] at [time]',
		'date_time_april'     => '[week_long], April [day] [year] at [time]',
		'date_time_may'       => '[week_long], May [day] [year] at [time]',
		'date_time_june'      => '[week_long], June [day] [year] at [time]',
		'date_time_july'      => '[week_long], July [day] [year] at [time]',
		'date_time_august'    => '[week_long], August [day] [year] at [time]',
		'date_time_september' => '[week_long], September [day] [year] at [time]',
		'date_time_october'   => '[week_long], October [day] [year] at [time]',
		'date_time_november'  => '[week_long], November [day] [year] at [time]',
		'date_time_december'  => '[week_long], December [day] [year] at [time]',

		// AM/PM
		'am' => 'AM',
		'pm' => 'PM',

		// Capacity
		'space'  => '[total] space available',
		'spaces' => '[total] spaces available',

		// Front-end shortcodes
		'manage_text'       => 'Manage schedule',
		'schedule'          => 'Schedule',
		'breaks'            => 'Breaks',
		'holidays'          => 'Holidays',
		'schedule_updated'  => 'Work schedule updated.',
		'upcoming'          => 'Upcoming',
		'past'              => 'Past',
		'client_service'    => '[service_name] with [provider_name]',
		'provider_service'  => '[service_name] with [client_name]',
		'no_appointments'   => 'You do not have any appointments!',
		'user_set_app_pending' => 'Set appointment status to pending',
		'user_set_app_pending_btn_yes' => 'Set to pending',
		'add_to_calendar'   => 'Add to calendar',
		'apple_calendar'    => 'Apple calendar',
		'google_calendar'   => 'Google calendar',
		'outlook_calendar'  => 'Outlook calendar',
		'yahoo_calendar'    => 'Yahoo calendar',
		'bookable_date'     => 'Full day',
		'status_completed'  => 'Completed',
		'status_publish'    => 'Confirmed',
		'status_payment'    => 'Pending payment',
		'status_pending'    => 'Pending',
		'status_cancelled'  => 'Cancelled',
		'status_failed'     => 'Failed',
		'confirm_button'    => 'Confirm',
		'cancel_button'     => 'Cancel',
		'update_button'     => 'Update',
		'reschedule_button' => 'Reschedule',
		'confirm_text'      => 'Confirm appointment',
		'cancel_text'       => 'Cancel appointment',
		'reschedule_text'   => 'Reschedule appointment',
		'close_button'      => 'Close',
		'optional_text'     => 'Optional message',
		'reschedule_optional_text' => 'Optional message',
		'app_confirmed'     => 'Appointment has been confirmed.',
		'app_cancelled'     => 'Appointment has been cancelled.',
		'app_rescheduled'   => 'Appointment has been rescheduled',
		'app_set_pending'   => 'Appointment has been set to pending',
		'error'             => 'Something went wrong.',
		'unselected_time_date' => 'Please select time and date',
		'current_date_time' => 'Current date and time',

		// Appointment Cost
		'app_cost_text'  => 'Appointment Cost',

		// Validation messages
		'error_required'           => 'This field is required',
		'error_reached_max'        => 'You have reached the maximum number of booking allowed for [date]',
		'error_required_date'      => 'Date was not selected',
		'error_max_bookings'       => 'Maximum of [total] bookings allowed for [date]',
		'error_required_service'   => 'Service was not selected',
		'error_booked_date'        => 'You already booked [date]',
		'error_date_valid'         => 'Date [date] is not available.',
		'error_slot_valid'         => 'Time slot on [date] is not available',
		'error_required_slot'      => 'Time was not selected',
		'error_services_form'      => 'Add booking services field to form',
		'error_service_valid'      => 'Service not found',
		'error_required_provider'  => 'Provider not selected.',
		'error_providers_service'  => 'Providers service not found.',
		'error_no_services'        => 'No service found.',
	);

	if (isset($default[$translate])) {
		return $default[$translate];
	}

	return '';
}


/**
 * Form Translation Data
 */
function ga_get_form_translated_data($form_id, $translate)
{
	$form = GFAPI::get_form($form_id);
	$form_lang = rgar($form, 'gappointments_translation');

	if (isset($form_lang[$translate])) {
		return $form_lang[$translate];
	} else {
		return ga_get_translated_data($translate);
	}
}

/**
 * Sort Dates
 */
function ga_date_format_sort($a, $b)
{
	return strtotime($a) - strtotime($b);
}

/**
 * Sort DateTime
 */
function ga_date_time_sort($a, $b)
{

	try {
		$sort = new DateTime($a['date']) > new DateTime($a['date']);
		return $sort;
	} catch (Exception $e) {
		$sort = new DateTime($a['date_time']) > new DateTime($a['date_time']);
		return $sort;
	}

	return new DateTime($a) > new DateTime($b);
}

/**
 * Services exist, returns first id
 */
function ga_service_id($form)
{

	$form_cat_slug = rgar($form, 'ga_service_category');
	$cat           = term_exists($form_cat_slug, 'ga_service_cat');

	// The Query
	if ($cat) {
		$args = array(
			'post_type' => 'ga_services', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'tax_query' => array(array(
				'taxonomy' => 'ga_service_cat', // taxonomy name
				'field'    => 'slug',           // term_id, slug or name
				'terms'    => $form_cat_slug    // term id, term slug or term name
			))
		); // end array

	} else {
		$args = array('post_type' => 'ga_services', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC');
	}

	$the_query = new WP_Query($args);

	wp_reset_postdata();
	if ($the_query->have_posts()) {
		while ($the_query->have_posts()) {
			$the_query->the_post();
			return get_the_id();
		}
		wp_reset_postdata();
	} else {
		return false;
	}
}

/**
 * Add Appointment Cost To Total
 */
add_filter('gform_product_info', 'add_ga_appointment_fee', 10, 3);
function add_ga_appointment_fee($product_info, $form, $lead)
{
	if (gf_field_type_exists($form, 'appointment_services')) {
		if (is_numeric(gf_get_field_type_value($form, 'appointment_services'))) {
			$service_id    = absint(gf_get_field_type_value($form, 'appointment_services'));
			$service_price = get_post_meta($service_id, 'ga_service_price', true); // false for array
			$provider_id  = gf_field_type_exists($form, 'appointment_providers')
				&& 'ga_providers' == get_post_type(gf_get_field_type_value($form, 'appointment_providers'))
				? gf_get_field_type_value($form, 'appointment_providers')
				: 0;
			if (ga_get_provider_id($service_id) && $provider_id == 0) {
				$provider_id = ga_get_provider_id($service_id);
			}


			/**
			 * Multiple Bookings
			 */
			$form_id = $form['id'];
			$multiple_slots = (string) get_post_meta($service_id, 'ga_service_multiple_selection', true);
			$times_mode     = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true);
			$app_cost_text  = ga_get_form_translated_data($form_id, 'app_cost_text');
			$app_cost_text  = esc_html($app_cost_text);

			if ($multiple_slots == 'yes' && gf_field_type_exists($form, 'appointment_calendar')) {
				$calendar = gf_get_field_type_value($form, 'appointment_calendar');

				// Get Bookings
				$bookings = ga_get_multiple_bookings($calendar, $service_id, $provider_id);
				$quantity = count($bookings);
				if ($times_mode == 'custom') {
					$service_price = gf_to_money(ga_get_slots_total($form_id, $service_id, $provider_id, $bookings));
					$quantity = 1;
				}

				$product_info['products']['appointment_cost'] = array('name' => $app_cost_text, 'price' => $service_price, 'quantity' => $quantity);
			} else {
				if ($times_mode == 'custom') {
					$calendar      = gf_get_field_type_value($form, 'appointment_calendar');
					$booking       = ga_get_multiple_bookings($calendar, $service_id, $provider_id);
					$service_price = gf_to_money(ga_get_slots_total($form_id, $service_id, $provider_id, $booking));
				}
				/**
				 * Single Booking
				 */
				$product_info['products']['appointment_cost'] = array('name' => $app_cost_text, 'price' => $service_price, 'quantity' => 1);
			}
		}
	}

	return $product_info;
}

/**
 * Get Slots Total
 */
function ga_get_slots_total($form_id, $service_id, $provider_id, $bookings)
{
	if (!class_exists('GA_Calendar')) {
		require_once(ga_base_path . '/gf-fields/ga-calendar.php');
	}

	$now = ga_current_date_with_timezone();
	$ga_calendar  = new GA_Calendar($form_id, $now->format('n'), $now->format('Y'), $service_id, $provider_id);

	$service_price = 0;
	foreach ($bookings as $key => $booking) {
		$dateTime       = new DateTime(sprintf('%s %s', $booking['date'], $booking['time']), new DateTimeZone(ga_time_zone()));
		$service_price += $booking['price'];
	}

	return $service_price;
}

/**
 * Get Slots Total
 */
function ga_get_slot_price($form_id, $date, $service_id, $provider_id)
{
	if (!class_exists('GA_Calendar')) {
		require_once(ga_base_path . '/gf-fields/ga-calendar.php');
	}

	$ga_calendar = new GA_Calendar($form_id, $date->format('n'), $date->format('Y'), $service_id, $provider_id);
	$slots       = $ga_calendar->get_slots(clone $date);
	$time_slot   = $date->format('H:i');
	return $slots[$time_slot]['price'];
}


/**
 * Get Bookings
 */
function ga_get_multiple_bookings($value, $service_id, $provider_id)
{
	$bookings = array();
	$dates = isset($value['bookings']['date']) ? $value['bookings']['date'] : array();
	$times = isset($value['bookings']['time']) ? $value['bookings']['time'] : array();

	foreach ($dates as $key => $date) {
		$bookings[] = array(
			'date'   => $date,
			'time'   => $times[$key],
		);
	}

	$max_per_date    = ga_get_service_max_bookings($service_id);
	$max_total       = ga_get_service_max_selection($service_id);
	$times_mode      = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true);
	$no_doubles      = ga_get_service_double_bookings($service_id);

	// Add selected dateTime to bookings
	if (count($bookings) < 1) {
		$date_val = isset($value['date']) ? $value['date'] : '';
		$time_val = isset($value['time']) ? $value['time'] : '';
		if ($date_val != '') {
			$bookings[] = array(
				'date'  => $date_val,
				'time'  => $time_val,
			);
		}
	}

	// Unique values
	if ($no_doubles == 'yes') {
		$serializedBookings = array_map('serialize', $bookings);
		$uniqueBookings = array_unique($serializedBookings);
		$bookings = array_intersect_key($bookings, $uniqueBookings);
		// $bookings = array_unique($bookings, SORT_REGULAR);
	}

	// Total Bookings
	$bookings = array_slice($bookings, 0, $max_total);

	$filtered = array();
	$validBookings = array();
	foreach ($bookings as $booking) {
		$timeArray = explode("-", $booking['time']);

		$booking = array(
			'date'      => $booking['date'],
			'time'      => reset($timeArray),
			'time_end'  => end($timeArray),
			'time_id'   => $booking['time'],
		);

		if ($times_mode == 'no_slots') {
			$valid  = ga_valid_date_format($booking['date']);
			$format = 'Y-m-j';
			$booking['time'] = '';
		} else {
			$valid  = ga_valid_date_time_format(sprintf('%s %s', $booking['date'], $booking['time']));
			$format = 'Y-m-j H:i';
		}

		// Valid Date Format
		if ($valid) {
			$dateTime = new DateTime(sprintf('%s %s', $booking['date'], $booking['time']), new DateTimeZone(ga_time_zone()));
			$dateId = sprintf('%s %s', $booking['date'], $booking['time_id']);

			// Capacity
			require_once(ga_base_path . '/gf-fields/ga-calendar.php');
			$ga_calendar = new GA_Calendar($form_id = false, $dateTime->format('m'), $dateTime->format('Y'), $service_id, $provider_id);

			if ($times_mode == 'no_slots') {
				$capacity = $ga_calendar->date_capacity_text(clone $dateTime);
			} else {
				$slots    = $ga_calendar->get_slots(clone $dateTime);
				$slotData = $slots[$booking['time_id']];
				$capacity = $ga_calendar->slot_capacity_text(clone $dateTime, $slotData);
				$booking['end']      = $slotData['end'];
				$booking['duration'] = $slotData['duration'];
				$booking['price']    = $slotData['price'];
			}


			// Prevent Double Selections
			if ($no_doubles == 'yes') {
				if ($matches = preg_grep("/^{$dateId}/i", $filtered)) {

					if (count($matches) >= $max_per_date) {
						# not valid
					} else {
						$filtered[] = $dateId;
						$validBookings[] = $booking;
					}
				} else {
					$filtered[] = $dateId;
					$validBookings[] = $booking;
				}
			} else {
				if ($capacity) {
					// Doubles Count
					//$date_output = $dateTime->format($format);
					if ($matches = preg_grep("/^{$dateId}/i", $filtered)) {
						if (count($matches) >= $capacity) {
							# not valid
						} else {
							$filtered[] = $dateId;
							$validBookings[] = $booking;
						}
					} else {
						$filtered[] = $dateId;
						$validBookings[] = $booking;
					}
				}
			}
		}

	}

	// Sort
	//usort($filtered, 'ga_date_time_sort');

	return $validBookings;
}

/**
 * Service Max Bookings
 */
function ga_get_service_max_bookings($service_id)
{
	$range = range(1, 150);
	$max_bookings = (int) get_post_meta($service_id, 'ga_service_max_bookings', true);

	if (in_array($max_bookings, $range)) {
		return $max_bookings;
	} else {
		return 3;
	}
}

/**
 * Service Max Selection
 */
function ga_get_service_max_selection($service_id)
{
	$range = range(1, 150);
	$max_selection = (int) get_post_meta($service_id, 'ga_service_max_selection', true);

	if (in_array($max_selection, $range)) {
		return $max_selection;
	} else {
		return 3;
	}
}

/**
 * Service Prevent Double Bookings
 */
function ga_get_service_double_bookings($service_id)
{
	$defaults = array('yes', 'no');
	$double_bookings = (string) get_post_meta($service_id, 'ga_service_double_bookings', true);

	if (in_array($double_bookings, $defaults)) {
		return $double_bookings;
	} else {
		return 'yes';
	}
}



/**
 * Field Type Exists
 */
function gf_field_type_exists($form, $field_type)
{
	if (isset($form['fields'])) {
		foreach ($form['fields'] as $field) {
			if ($field['type'] == $field_type) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Get Field Type Value
 */
function gf_get_field_type_value($form, $field_type)
{
	if (isset($form['fields'])) {
		foreach ($form['fields'] as $field) {
			if ($field['type'] == $field_type) {

				$id = $field['id'];
				$input = "input_{$id}";

				if (isset($_POST[$input])) {
					if (is_array($_POST[$input])) {
						return $_POST[$input];
					} else {
						return esc_html($_POST[$input]);
					}
				}

				return false;
			}
		}
	}

	return '';
}

/**
 * Get Field Type Value
 */
function gf_get_name_field_value($form)
{

	if (isset($form['fields'])) {
		foreach ($form['fields'] as $field) {
			if ($field['type'] == 'name') {

				$field_id = $field['id'];
				$input_id = 'input_' . $field_id;

				$value = isset($_POST) ? $_POST : '';

				if (is_array($value)) {
					$prefix = trim(rgget($input_id . '_2', $value));
					$first  = trim(rgget($input_id . '_3', $value));
					$middle = trim(rgget($input_id . '_4', $value));
					$last   = trim(rgget($input_id . '_6', $value));
					$suffix = trim(rgget($input_id . '_8', $value));

					$name = $prefix;
					$name .= !empty($first) ? " $first" : $first;
					$name .= !empty($middle) ? " $middle" : $middle;
					$name .= !empty($last) ? " $last" : $last;
					$name .= !empty($suffix) ? " $suffix" : $suffix;
					$name  = esc_html($name);
				} else {
					$name = esc_html($value);
				}

				$name = trim($name);
				return $name;
			}
		}
	}

	return '';
}


/**
 * Service Time Format Display
 */
function ga_service_time_format_display($service_id)
{
	// Time Format
	$time_format = get_post_meta($service_id, 'ga_service_time_format', true);
	if ($time_format && in_array($time_format, array('12h', '24h'))) {
		$time_format = $time_format;
	} else {
		$time_format = '12h'; // 12h format
	}

	// Time Format
	$remove_am_pm = get_post_meta($service_id, 'ga_service_remove_am_pm', true);
	if ($remove_am_pm && in_array($remove_am_pm, array('no', 'yes'))) {
		$remove_am_pm = $remove_am_pm;
	} else {
		$remove_am_pm = 'no'; // 12h format
	}


	$am_pm    = $remove_am_pm == 'no' ? 'A' : '';
	$display  = $time_format == '24h' ? "G:i {$am_pm}" : "g:i {$am_pm}";

	return $display;
}


/**
 * Query Providers PostType
 */
function ga_provider_query($user_id)
{
	$providers = new WP_QUERY(
		array(
			'post_type'         => 'ga_providers',
			'post_status'       => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
			'posts_per_page'    => 1,
			'meta_query'        => array(
				array(
					'key'     => 'ga_provider_user',
					'value'   => $user_id,
				),
			),
		)
	);
	wp_reset_postdata();
	return $providers;
}

/**
 * Current Date & Time with TimeZone
 */
function ga_current_date_with_timezone()
{
	$timezone = ga_time_zone();

	$now = new DateTime();
	$now->setTimezone(new DateTimeZone($timezone));

	return $now;
}

/**
 * Is current user logged in a provider
 */
function is_user_logged_in_a_provider()
{
	if (!is_user_logged_in()) {
		return false;
	}

	$current_user = wp_get_current_user();
	$userID = $current_user->ID;

	$providers = ga_provider_query($userID);
	if ($providers->post_count == 1) {
		return true;
	}

	return false;
}

/**
 * Get Logged In Provider ID
 */
function get_logged_in_provider_id()
{
	if (!is_user_logged_in()) {
		return false;
	}

	$current_user = wp_get_current_user();
	$userID = $current_user->ID;

	$providers = ga_provider_query($userID);
	if ($providers->post_count == 1) {
		return $providers->post->ID;
	}
	return false;
}

/**
 * Get first provider ID
 */
function ga_get_provider_id($service_id)
{
	$service_id = absint($service_id);

	if ('ga_services' != get_post_type($service_id)) {
		return false;
	}

	$the_query = new WP_Query(array('post_type' => 'ga_providers', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array(array('key' => 'ga_provider_services', 'value' => serialize(strval($service_id)), 'compare' => 'LIKE'))));
	wp_reset_postdata();
	if ($the_query->have_posts()) {
		while ($the_query->have_posts()) {
			$the_query->the_post();
			return get_the_id();
		}
		wp_reset_postdata();
	} else {
		return false;
	}
}

/**
 * Provider has service
 */
function ga_provider_has_service($service_id, $provider_id)
{
	$service_id = absint($service_id);

	if ('ga_services' != get_post_type($service_id)) {
		return false;
	}

	$provider_id = absint($provider_id);
	$the_query = new WP_Query(array('post_type' => 'ga_providers', 'p' => $provider_id, 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array(array('key' => 'ga_provider_services', 'value' => serialize(strval($service_id)), 'compare' => 'LIKE'))));
	wp_reset_postdata();
	if ($the_query->have_posts()) {
		return true;
	}

	return false;
}

/**
 * Service has providers
 */
function ga_service_has_providers($service_id)
{
	$service_id = absint($service_id);

	if ('ga_services' != get_post_type($service_id)) {
		return false;
	}

	$the_query = new WP_Query(array('post_type' => 'ga_providers', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array(array('key' => 'ga_provider_services', 'value' => serialize(strval($service_id)), 'compare' => 'LIKE'))));
	wp_reset_postdata();
	if ($the_query->have_posts()) {
		return true;
	}

	return false;
}

/**
 * Get Settings TimeZone
 */
function ga_time_zone()
{
	$sel_timezone = get_option('ga_appointments_calendar');
	return isset($sel_timezone['time_zone']) ? $sel_timezone['time_zone'] : 'Europe/Bucharest';
}

/**
 * Appointment PostType Statuses
 */
function ga_appointment_statuses()
{

	$statuses = array(
		'completed'            => 'Completed',         // appointment completed
		'publish'              => 'Confirmed',         // appointment is was submited without a payment gateway
		'payment'              => 'Pending Payment',   // pending payment
		'pending'              => 'Pending',           // pending when not auto-confirmed on settings
		'cancelled'            => 'Cancelled',         // appointment cancelled by client or administrator
		'draft'                => 'Draft',             // appointment draft
	);

	return $statuses;
}


/**
 * Schedule Max Future Minutes Options
 */
function ga_schedule_lead_time_minutes()
{
	$options =  array(
		'no'   => 'No lead time',
		'30'   => '30 minutes',
		'60'   => '1 hour',
		'120'  => '2 hours',
		'180'  => '3 hours',
		'240'  => '4 hours',
		'300'  => '5 hours',
		'360'  => '6 hours',
		'420'  => '7 hours',
		'480'  => '8 hours',
		'720'  => '12 hours',
		'1080' => '18 hours',
		'1440' => '24 hours',
		'2880' => '48 hours',
		'4320' => '3 days',
		'5760' => '4 days',
		'7200' => '5 days',
		'8640' => '6 days',
		'10080' => '7 days',
		'20160' => '14 days',
		'43200' => '30 days',
	);
	return $options;
}

/**
 * Schedule Max Future Days Options
 */
function ga_schedule_max_future_days()
{
	$options =  array(
		'1'   => '1 day',
		'2'   => '2 days',
		'3'   => '3 days',
		'4'   => '4 days',
		'5'   => '5 days',
		'6'   => '6 days',
		'7'   => '7 days',
		'8'   => '8 days',
		'9'   => '9 days',
		'10'  => '10 days',
		'14'  => '2 weeks',
		'21'  => '3 weeks',
		'28'  => '4 weeks',
		'35'  => '5 weeks',
		'42'  => '6 weeks',
		'60'  => '2 months',
		'90'  => '3 months',
		'120' => '4 months',
		'150' => '5 months',
		'180' => '6 months',
		'210' => '7 months',
		'240' => '8 months',
		'270' => '9 months',
		'300' => '10 months',
		'330' => '11 months',
		'365' => '1 year',
		'730' => '2 years'
	);
	return $options;
}


/**
 * Time Slots Services
 */
function ga_get_services_type_ids($type)
{

	$services_id = new WP_Query(array(
		'post_type' => 'ga_services', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
		'meta_query' => array(array('key' => 'ga_service_available_times_mode', 'value' => $type,))
	));

	wp_reset_postdata();

	if ($services_id->posts) {
		return $services_id->posts;
	} else {
		return array();
	}
}

/**
 * in_array_r multidimensional
 */
function in_array_r($needle, $haystack, $strict = false)
{
	foreach ($haystack as $item) {
		if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
			return true;
		}
	}
	return false;
}

/*
* Notifications Dynamic Populate Conditional Logic Routing
*/
add_filter('gform_routing_field_types', 'add_gform_routing_field_types');
function add_gform_routing_field_types($field_types)
{
	$field_types = array_merge($field_types, array('appointment_services', 'appointment_providers'));
	return $field_types;
}

/*
* Dynamic Populate Services Choices for Conditional Logic
*/
add_filter('gform_pre_render',       'populate_services_for_conditional_logic');
add_filter('gform_pre_validation',   'populate_services_for_conditional_logic');
add_filter('gform_pre_submission',   'populate_services_for_conditional_logic');
add_filter('gform_admin_pre_render', 'populate_services_for_conditional_logic');
function populate_services_for_conditional_logic($form)
{

	if ($form['fields']) {
		foreach ($form['fields'] as &$field) {

			if ($field->type != 'appointment_services') {
				continue;
			}

			$posts = get_posts('post_type=ga_services&numberposts=-1&post_status=publish');

			$choices = array();

			foreach ($posts as $post) {
				$choices[] = array('value' => $post->ID, 'text' => $post->post_title);
			}

			$field->choices = $choices;
		}
	}
	return $form;
}


/*
* Show service title on entry list with add_filter
*/
add_filter('gform_entries_field_value', 'gform_entries_list_service_title', 10, 4);
function gform_entries_list_service_title($value, $form_id, $field_id, $entry)
{

	$form         = GFAPI::get_form($form_id);
	$field        = RGFormsModel::get_field($form, $field_id);
	$value_fields = array('appointment_services',);

	if (is_object($field) && in_array($field->get_input_type(), $value_fields)) {
		//$value = $field->get_value_entry_detail( RGFormsModel::get_lead_field_value( $entry, $field ), '', true, 'text' );
		$post_id = absint($value);
		if ('ga_services' == get_post_type($post_id)) {
			$value = get_the_title($post_id);
			return esc_html($value);
		}
	}

	return $value; //$value;

}

/*
* Show provider title on entry list with add_filter
*/
add_filter('gform_entries_field_value', 'gform_entries_list_provider_title', 10, 4);
function gform_entries_list_provider_title($value, $form_id, $field_id, $entry)
{

	$form         = GFAPI::get_form($form_id);
	$field        = RGFormsModel::get_field($form, $field_id);
	$value_fields = array('appointment_providers');

	//print_r( $value . '<br>');

	if (is_object($field) && in_array($field->get_input_type(), $value_fields)) {
		//$value = $field->get_value_entry_detail( RGFormsModel::get_lead_field_value( $entry, $field ), '', true, 'text' );
		$post_id = absint($value);

		if ('ga_providers' == get_post_type($post_id)) {
			$value = get_the_title($post_id);
			return esc_html($value);
		}

		if ($value == 0) {
			return 'No preference';
		}
	}

	return $value; //$value;

}

/*
* Dynamic Populate providers Choices for Conditional Logic
*/
add_filter('gform_pre_render',       'populate_providers_for_conditional_logic');
add_filter('gform_pre_validation',   'populate_providers_for_conditional_logic');
add_filter('gform_pre_submission',   'populate_providers_for_conditional_logic');
add_filter('gform_admin_pre_render', 'populate_providers_for_conditional_logic');
function populate_providers_for_conditional_logic($form)
{
	if ($form['fields']) {
		foreach ($form['fields'] as &$field) {
			if ($field->type != 'appointment_providers') {
				continue;
			}
			$posts = get_posts('post_type=ga_providers&numberposts=-1&post_status=publish');
			$choices = array();

			foreach ($posts as $post) {
				$choices[] = array('value' => $post->ID, 'text' => $post->post_title);
			}

			$field->choices = $choices;
		}
	}
	return $form;
}


/*
* GF Entry Calendar date
*/
add_filter('gform_entries_field_value', 'gform_entries_list_calendar_date', 10, 4);
function gform_entries_list_calendar_date($value, $form_id, $field_id, $entry)
{
	$form         = GFAPI::get_form($form_id);
	$field        = RGFormsModel::get_field($form, $field_id);
	$value_fields = array('appointment_calendar');

	if (is_object($field) && in_array($field->get_input_type(), $value_fields)) {
		$dates = explode('&lt;br&gt', $value);

		if (count($dates) > 1) {
			return 'Multiple bookings';
		} elseif (count($dates) == 1) {
			return reset($dates);
		} else {
			return '';
		}
	}

	return $value; //$value;
}


/**
 * Get GravityForms Entry IDS
 */
function get_gravity_form_entries_ids()
{
	$forms = RGFormsModel::get_forms(null, 'title');
	$ids = array();

	if (isset($forms)) {
		foreach ($forms as $form) {
			$form_id = $form->id;

			if (GFAPI::get_entries($form_id)) {
				//if(RGFormsModel::get_leads($form_id) ) {
				foreach (GFAPI::get_entries($form_id) as $entry) {
					$ids[$entry['id']] = "{$entry['id']} - Form: {$form->title}";
				}
			}
		}
	}

	return $ids;
}

/**
 * Service Duration Options
 */
function ga_service_duration_options()
{
	$duration = array();
	foreach (range(5, 480, 5) as $minutes) {
		$duration[$minutes] = convertToHoursMins($minutes);
	}

	return $duration;
}

/**
 * Service Cleanup Options
 */
function ga_service_cleanup_options()
{
	$duration = array();
	foreach (range(0, 480, 5) as $minutes) {
		$duration[$minutes] = convertToHoursMins($minutes);
	}

	return $duration;
}

/**
 * Convert Hours/Minutes to human format
 */
function convertToHoursMins($time)
{
	$time = (int) $time;
	if ($time < 0) {
		return;
	}
	$hours = floor($time / 60);
	$minutes = ($time % 60);

	if ($time < 60) {
		$format = '%2d minutes';
		return sprintf($format, $minutes);
	} else {
		$format = '%2d hours %2d minutes';

		if ($hours == 1) {
			if ($minutes == 0) {
				$format = '%2d hour';
				return sprintf($format, $hours);
			} else {
				$format = '%2d hour %2d minutes';
				return sprintf($format, $hours, $minutes);
			}
		}

		if ($minutes == 0 && $hours != 1) {
			$format = '%2d hours';
			return sprintf($format, $hours);
		}

		return sprintf($format, $hours, $minutes);
	}
}


/**
 * Available Schedule Times
 */
function get_ga_appointment_time($schedule = '', $_24 = false)
{
	$interval = '+5 minutes';
	$output = array();

	if ($schedule == 'schedule') {
		$output['out'] = "Out";
	}

	$current = strtotime('00:00');
	$end     = strtotime('23:59');

	if ($_24) {
		$current = strtotime('00:05');
	}

	while ($current <= $end) {
		$time = date('H:i', $current);

		$output[$time] = date('g:i a', $current);
		$current = strtotime($interval, $current);
	}

	if ($_24) {
		$output['24:00'] = "12:00 am";
	}

	return $output;
}

/**
 * Service Capacity Options
 */
function ga_services_capacity_options()
{
	$capacity = array();

	foreach (range(1, 500) as $num) {
		$capacity[$num] = $num;
	}

	return $capacity;
}


/**
 * Appointment Services Options
 */
function get_ga_appointment_services()
{
	// The Query
	$args = array('post_type' => 'ga_services', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC');
	$the_query = new WP_Query($args);
	wp_reset_postdata();
	$services = array();

	// The Loop
	if ($the_query->have_posts()) {
		while ($the_query->have_posts()) {
			$the_query->the_post();
			$title = get_the_title();
			if (!empty($title)) {
				$services[get_the_ID()] = $title;
			}
		}

		wp_reset_postdata();
	}


	return $services;
}

/**
 * Appointment Users Options
 */
function get_ga_appointment_users()
{
	$users = get_users();
	$users_array = array();

	$users_array['new_client'] = 'New client';

	if ($users) {
		// Array of WP_User objects.
		foreach ($users as $user) {
			$users_array[$user->ID] = $user->user_login;
		}
	}

	return $users_array;
}

/**
 * Appointment Providers Options
 */
function get_ga_appointment_providers()
{
	// The Query
	$args = array('post_type' => 'ga_providers', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC');
	$the_query = new WP_Query($args);
	wp_reset_postdata();
	$providers = array();
	$providers['0'] = 'No provider';

	//print_r( $providers );

	// The Loop
	if ($the_query->have_posts()) {
		while ($the_query->have_posts()) {
			$the_query->the_post();
			$title = get_the_title();
			if (!empty($title)) {
				$providers[get_the_ID()] = $title;
			}
		}
		wp_reset_postdata();
	}



	return $providers;
}

/**
 * Provider Users Options
 */
function get_ga_provider_users()
{
	$users = get_users();
	$users_array = array();

	if ($users) {
		// Array of WP_User objects.
		foreach ($users as $user) {
			$users_array[$user->ID] = $user->user_login;
		}
	}

	return $users_array;
}


/**
 * GF - Get Currency Symbol
 */
function gf_get_currency_symbol()
{

	$gf_currency = gf_current_currency();

	$symbol_left  = !empty($gf_currency['symbol_left']) ? $gf_currency['symbol_left'] . $gf_currency['symbol_padding'] : '';
	$symbol_right = !empty($gf_currency['symbol_right']) ? $gf_currency['symbol_padding'] . $gf_currency['symbol_right'] : '';

	$gf_currency_code = $symbol_left . $symbol_right;

	return $gf_currency_code;
}

/**
 * GF - Price to Money
 */
function gf_to_money($money)
{

	$gf_currency = gf_current_currency();

	$negative = '';

	if (strpos(strval($money), '-') !== false) {
		$negative = '-';
		$money   = floatval(substr($money, 1));
	}


	if ($money == '0') {
		$negative = '';
	}

	$symbol_left  = !empty($gf_currency['symbol_left']) ? $gf_currency['symbol_left'] . $gf_currency['symbol_padding'] : '';
	$symbol_right = !empty($gf_currency['symbol_right']) ? $gf_currency['symbol_padding'] . $gf_currency['symbol_right'] : '';

	return $negative . $symbol_left . $money . $symbol_right;
}

/**
 * GF - Current Currency Symbol
 */
function gf_current_currency()
{

	if (!class_exists('RGCurrency')) {
		require_once(GFCommon::get_base_path() . '/currency.php');
	}

	return RGCurrency::get_currency(GFCommon::get_currency());
}


/**
 * User Profile Fields: Add Phone Field to Contact Info
 */
function ga_user_phone_field($contactmethods)
{
	// Add Phone
	if (!array_key_exists('phone', $contactmethods)) {
		$contactmethods['phone'] = 'Phone';
	}

	return $contactmethods;
}
add_filter('user_contactmethods', 'ga_user_phone_field', 10, 1);

/**
 * Ics File generator
 */
if (isset($_GET['ap-ics'])) :
	// Include link generator class
	if (!class_exists('ga_add_to_calendar'))
		require_once(ga_base_path . '/includes/add_to_calendar.php');

	ga_add_to_calendar::generate_ics_file($_GET['ap-ics']);
endif;

/**
 * Provider Schedule Front-End Form
 */
function ga_provider_schedule_form($provider_id)
{
	if (!class_exists('ga_work_schedule')) {
		require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
	}

	$work_schedule = get_post_meta($provider_id, 'ga_provider_work_schedule', true);
	$breaks        = get_post_meta($provider_id, 'ga_provider_breaks', true);
	$holidays      = get_post_meta($provider_id, 'ga_provider_holidays', true);
	$schedule      = new ga_work_schedule($provider_id);

	$out = '<input type="hidden" name="action" value="ga_provider_schedule_update">

			<div id="ga_schedule_tabs">
				<span section_go="ga_schedule" class="active">' . esc_html(ga_get_translated_data('schedule')) . '</span>
				<span section_go="ga_breaks" class="">' . esc_html(ga_get_translated_data('breaks')) . '</span>
				<span section_go="ga_holidays" class="">' . esc_html(ga_get_translated_data('holidays')) . '</span>
			</div>

			<div class="ajax-response"></div>

			<div id="ga_schedule_content">
				<section id="ga_schedule" class="ga_schedule_content">'           . $schedule->display_schedule('ga_provider_work_schedule', $work_schedule) . '</section>
				<section id="ga_breaks" class="ga_schedule_content ga-hidden">'   . $schedule->display_breaks('ga_provider_breaks', $breaks) . '</section>
				<section id="ga_holidays" class="ga_schedule_content ga-hidden">' . $schedule->display_holidays('ga_provider_holidays', $holidays) . '</section>
			</div>

			<div class="hr"></div>
			<div class="ga_modal_footer">
				<button type="submit" class="ga-button">' . esc_html(ga_get_translated_data('update_button')) . '</button>
			</div>';
	return $out;
}

add_filter('gform_export_field_value', 'ga_set_export_values', 10, 4);
function ga_set_export_values($value, $form_id, $field_id, $entry)
{

	$formFields = GFAPI::get_form($form_id)['fields'];
	foreach ($formFields as $formField) {
		if ($formField->id == $field_id) {
			if ($formField->type) {
				$fieldType = $formField->type;
			}
		}
	}
	if ($fieldType) {
		switch ($fieldType) {
			case 'appointment_services':
				$value = get_the_title($value);
				break;
			case 'appointment_providers':
				$provider = get_the_title($value);
				$value = $provider ? $provider : 'No preference';
				break;
		}
	}
	return $value;
}

function ga_get_field_type_value($form, $field_type)
{
	if (isset($form['fields'])) {
		$fieldCount = 0;
		foreach ($form['fields'] as $field) {
			if ($field['type'] === $field_type) {
				$fieldCount++;
			}
		}
		foreach ($form['fields'] as $field) {
			if ($field['type'] == $field_type) {
				if ($fieldCount > 1) {
					if (strpos($field['cssClass'], 'ga-field') !== false) {
						$id = $field['id'];
						$input = "input_{$id}";
						if (isset($_POST[$input])) {
							if (is_array($_POST[$input])) {
								return $_POST[$input];
							} else {
								return esc_html($_POST[$input]);
							}
						}
					}
				} else {
					return gf_get_field_type_value($form, $field_type);
				}
			}
		}
	}

	return '';
}

// add_filter( 'gform_field_value_price', 'my_custom_population_function' );
// function my_custom_population_function( $value ) {
//     return 0;
// }
// add_filter( 'gform_field_value_needsPayment', 'needsPaymentPopulation' );
// function needsPaymentPopulation( $value ) {
//     return 'true';
// }

// add_filter( 'gform_replace_merge_tags', 'replace_custom_merge_tags', 10, 7 );

// function replace_custom_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ){

// 	$tag = '{calendar_selection}';

// 	if(!$form || strpos( $text, $tag ) === false )
// 		return $text;
// 	foreach($form['fields'] as $field){
// 		if($field['type'] == 'appointment_calendar'){
// 			$id = $field['id'];
// 			$key = "{$id}_date";
// 			$value = $entry[$key];
// 			$text = str_replace( $tag, $value, $text );
// 			return $text;
// 		}
// 	}

// 	return $text;

// }

add_filter('gform_pre_replace_merge_tags', function ($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {

	foreach ($form['fields'] as $field) {
		if ($field['type'] == 'appointment_calendar') {
			$id = $field['id'];
			$label = $field['label'];
			$tag = '{' . $label . ':' . $id . '}';
			$key = "{$id}_date";
			if (isset($entry[$key])) {
				$text = str_replace($tag, $entry[$key], $text);
			}
			return $text;
		}
	}
	return $text;
}, 10, 7);

add_action('gform_after_submission', 'after_submission', 10, 2);

function after_submission($entry, $form)
{

	foreach ($form['fields'] as $field) {
		if ($field['type'] == 'appointment_calendar') {
			$id = $field['id'];
			$key = "{$id}_date";
			$entry[$id] = isset($entry[$key]) ? $entry[$key] : '';
		}
	}
}

function UserCanCancelAppointment($cancellation_notice_timeframe, $app_date, $app_time)
{
	$current_date = date_create_from_format('F d, Y g:i A', date('F d, Y g:i A'));
	$appointment_time = date_create_from_format('F d, Y g:i A', $app_date . ' ' . $app_time);
	$appointment_time->sub(new DateInterval(('PT' . $cancellation_notice_timeframe . 'H')));
	if ($appointment_time > $current_date) {
		return true;
	}

	return false;
}

function complete_appointment()
{
	$options = get_option('ga_appointments_calendar');
	$auto_complete = isset($options['auto_complete']) ? $options['auto_complete'] : 'no';
	if ($auto_complete === 'custom') {

		$appointments = new WP_QUERY(
			array(
				'post_type'         => 'ga_appointments',
				'posts_per_page'    => -1,
				'post_status'       => array('publish'),
				'orderby'           => 'meta_value',
				'order'             => 'ASC',
			)
		);

		wp_reset_postdata();

		if ($appointments->have_posts()) {
			while ($appointments->have_posts()) : $appointments->the_post();
				$shortcode = new ga_appointment_shortcodes();
				$auto_complete_custom = isset($options['auto_complete_custom']) ? $options['auto_complete_custom'] : 10;

				$type = get_post_meta(get_the_ID(), 'ga_appointment_type', true);
				if ($type === 'date') {
					$auto_complete_custom = 12;
				}

				$time = $shortcode->ga_time(get_the_ID());
				if ($time === false) {
					$time = '12:00 AM';
				}

				if (!UserCanCancelAppointment($auto_complete_custom, $shortcode->ga_date(get_the_ID()), $time)) {
					wp_update_post(array('ID' => get_the_ID(), 'post_status' => 'completed'));
				}

			endwhile;
			wp_reset_postdata();
		}
	}
}
add_action('complete_appointment_cronjob', 'complete_appointment');
