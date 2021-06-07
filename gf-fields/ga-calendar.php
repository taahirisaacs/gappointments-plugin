<?php
defined( 'ABSPATH' ) or exit; // Exit if accessed directly


class GA_Calendar {
	private $month;
	private $year;
	private $selected_date;
	private $selected_slot;
	private $days_of_week = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
	private $week_starts;
	private $num_days;
	private $date_info;
	private $day_of_week;
	private $time_zone;
	private $service_id;
	private $provider_id;
	private $form_id = 0;
    private $form_lang;
	private $current_month_appointments;
	private $provider_availability;

	public function __construct( $form_id, $month, $year, $service_id, $provider_id, $selected_date = false, $selected_slot = false, $execute_sync = true  ) {
		// FORM ID
		$this->form_id = $form_id ? $form_id : 0;

		// MONTH & DATE
		$this->month         = $month;
		$this->year          = $year;
		$this->selected_date = $selected_date ? $selected_date : false;
		$this->selected_slot = $selected_slot ? $selected_slot : false;

		// SERVICE & PROVIDER ID
		$this->service_id  = (int) $service_id;
		$this->provider_id = (int) $provider_id;

		// TIMEZONE
		$this->time_zone = ga_time_zone();

		// DATEINFO
		$date = new DateTime();
		$date->setTimezone( new DateTimeZone( $this->time_zone ) );
		$this->date_info = $date->setDate( (int) $this->year, (int) $this->month, 1 );

		$this->num_days = $date->format('t');
		$this->day_of_week = $this->date_info->format('w');

        // Form translations
        $this->form_lang = get_form_translations( null, $form_id );

        // Days of week translated
        $this->days_of_week = ga_get_form_translated_data($this->form_lang, 'weeks');

		// Week starts on
		$calendar = get_option('ga_appointments_calendar');
		$this->week_starts = isset( $calendar['week_starts'] ) ? $calendar['week_starts'] : 'sunday';

		//Maybe pull in appointments from google calendar
        if ($execute_sync) {
            $this->two_way_sync();
        }
        $this->provider_availability = $this->get_availability_option();

		$this->get_appointments_query();
	}

	/**
	 * Get Available Days from Schedule
	 */
	private function get_available_days($array, $timestamp) {
		// SERVICE PERIOD TYPE
		$period_type = (string) get_post_meta($this->service_id, 'ga_service_period_type', true);

		if( $period_type == 'date_range' ) {
			$range = (array) get_post_meta($this->service_id, 'ga_service_date_range', true);

			$dates = array();
			if( isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to']) ) {
				$period = new DatePeriod(
				    new DateTime($range['from']),
				    new DateInterval('P1D'),
				    new DateTime($range['to'])
				);
				foreach ($period as $key => $value) {
				    $dates[] = $value->format('Y-m-j');
				}

				 $dates[] = $range['to'];
			}
			return $dates;
		}

		if( $period_type == 'custom_dates' ) {
			$custom_dates = (array) get_post_meta($this->service_id, 'ga_service_custom_dates', true);
			return $custom_dates;
		}


		$array = (array) $array;
		$weeks = array('sunday' ,'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
		$dates = array();

		foreach( $array as $week ) {
			if( in_array($week, $weeks) ) {
				$date = new DateTime();
				$date->setTimezone( new DateTimeZone( $this->time_zone ) );
				$date->setTimestamp($timestamp);
				$date->modify("first $week of this month");
				$thisMonth = $date->format('m');
				while ($date->format('m') == $thisMonth) {
					$dates[] = $date->format('Y-m-j');
					$date->modify("next $week");
				}
			}
		}
		return $dates;
	}

	/**
	 * Get Date Timestamp
	 */
	private function get_date_timestamp($year, $month, $day = false) {
		$date = new DateTime();
		$date->setTimezone( new DateTimeZone( $this->time_zone ) );
		$day = $day ? $day : 1;
		$date->setDate( (int) $year, (int) $month, $day );
		return $date->getTimestamp();
	}

	/**
	 * Display Calendar
	 */
	public function show() {

		// Calendar caption header
		$output = '<div class="ga_appointments_calendar_header">' . PHP_EOL;

		// Previous Month
		if( $this->previous_month() ) {
			$output .= '<a class="arrow-left" date-go="'. $this->date_info->format('Y-m') .'" service_id="'.$this->service_id.'" provider_id="'.$this->provider_id.'" id="ga_calendar_prev_month"><i class="fa fa-caret-left" aria-hidden="true"></i></a>' . PHP_EOL;
		}

		// Translation: Month/Year Caption
		$month = $this->date_info->format('F');
		$year  = $this->date_info->format('Y');
        $lang  = ga_get_form_translated_month( $this->form_lang, $month, $year );

		$output .= '<h3>'. $lang .'</h3>' . PHP_EOL;

		// Next Month
		if( $this->next_month() ) {
			$output .= '<a class="arrow-right" date-go="'. $this->date_info->format('Y-m') .'" service_id="'.$this->service_id.'" provider_id="'.$this->provider_id.'" id="ga_calendar_next_month"><i class="fa fa-caret-right" aria-hidden="true"></i></a>' . PHP_EOL;
		}

		$output .= '</div>' . PHP_EOL;

		// Table start
		$output .= '<table class="table_fixed" width="100%">' . PHP_EOL;
		$output .= '<thead>' . PHP_EOL;
		$output .= '<tr>' . PHP_EOL;

		// Week starts on monday
		if( $this->week_starts == 'monday' ) {
			$sunday = $this->days_of_week['sun'];
			unset( $this->days_of_week['sun'] );
			$this->days_of_week['sun'] = $sunday;
			$this->day_of_week = $this->day_of_week - 1;
			if ($this->day_of_week < 0) {
				$this->day_of_week = 6;
			}
		}

		// Days of the week header
		foreach( $this->days_of_week as $day ) {
			$output .= '<th class="ga_header">' . $day . '</th>' . PHP_EOL;
		}

		// Close header row and open first row of days
		$output .= '</tr>' . PHP_EOL;
		$output .= '</thead>' . PHP_EOL;

		$output .= '<tbody id="service-working-days">' . PHP_EOL;
		$output .= '<tr>' . PHP_EOL;

		// If first day of a month does not fall on a Sunday, then we need to fill
		if( $this->day_of_week > 0 ) {
			$output .= str_repeat( '<td class="ga_day_past"></td>', $this->day_of_week );
		}

		// start num_days counter
		$current_day = 1;

		if( $this->month_passed() ) {
			$available_days = array();
		}
		else{
			// available days
			$available_days = $this->get_available_days( array_keys( $this->get_week_day_schedule() ), $this->get_date_timestamp($this->year, $this->month) );
		}

		$service_price = $this->service_price(); // false for array

		$slots_html = '';

		$this->get_appointments_query();
		// Loop and build days
		while( $current_day <= $this->num_days ) {
			// Reset 'days of week' counter and close each row if end of row

			$current_date = new DateTime( $this->date_info->format("Y-m-$current_day"), new DateTimeZone( $this->time_zone ) );
			$time_slots = $this->calendar_time_slots( $current_date, $slot = false );
			$classes = in_array($current_date->format('Y-m-j'), $available_days)
						&& !in_array( $current_date->format("Y-m-j"), $this->get_holidays() )
						&& $time_slots !== false
						&& !$this->date_passed($current_date)
						&& !$this->max_schedule_days( $current_date )
						? 'day_available ga_time_slots' : 'day_unavailable ga_time_slots';

			if( $time_slots !== false ) {
				$slots_html .= $time_slots;
			}

			// Date selected from form submission
			$selected = '';
			if( $this->selected_date && in_array($this->selected_date->format('Y-m-j'), $available_days) )  {
				// Add selected class
				if( $this->selected_date->format('Y-m-j') == $current_date->format('Y-m-j') )  {
					$selected = ' selected';
					$classes .= $selected;
				}

				// Date Slots Mode
				if( $this->available_times_mode() == 'no_slots' )  {
					# do nothing
				} else {
					// Time Slots Mode
					$placed_it = $this->selected_date;
					$placed_it->modify("next {$this->week_starts}");

					if( $current_date->format('Y-m-j') == $placed_it->format('Y-m-j') && $this->day_of_week = 7 ) {
						$output  .= '</tr><tr id="gappointments_calendar_slots"><td colspan="7" class="calendar_slots"><div class="calendar_time_slots">';
						$output  .= $this->calendar_time_slots($this->selected_date, $this->selected_slot);
						$output  .= '</div></td>';
					}
				}
			}
			// Date selected from form submission

			// Reset 'days of week' counter and close each row if end of row
			if( $this->day_of_week == 7 ) {
				$output .= '</tr><tr>' . PHP_EOL;
				$this->day_of_week = 0;
			}

			// Current date is today
			if( $this->is_today( $current_date ) ) {
				$today    = ' ga_today';
				$classes .= $today;
			} else {
				$today = '';
			}

			// Date Slots Mode
			if( $this->available_times_mode() == 'no_slots' )  {

				if( $this->is_date_available($current_date) && !$this->max_schedule_days($current_date) ) {
					// Translation Support
					$month = $current_date->format("F");
					$day   = $current_date->format("j");
					$year  = $current_date->format("Y");
					$translate = ga_get_form_translated_slots_date( $this->form_lang,  $month, $day, $year );
					$lang_slot = ' lang_slot="'.$translate.'"';
					// Translation Support

					// Date is available
					$classes = "day_available{$today} ga_date_slots{$selected}";

					// Multiple Slots selection
					$multi_select = (string) get_post_meta($this->service_id, 'ga_service_multiple_selection', true);
					$max_bookings = ga_get_service_max_bookings($this->service_id);
					$max_total    = ga_get_service_max_selection($this->service_id);
					$double       = ga_get_service_double_bookings($this->service_id);
					$multiple     = $multi_select == 'yes' ? ' multi-select="enabled" select-total="'.$max_total.'"  no_double="'.$double.'"' : '';

					// Capacity available
                    $count = $this->date_capacity_text( $current_date );
					if( !empty( $count ) && $count !== 1 ) {
                        $capacity = $this->get_translation( $this->form_lang, $count );
						$classes .= ' ga_tooltip';

						$output .= '<td class="'.$classes.'" ga-tooltip="'.$capacity.'" date-go="'.$this->date_info->format("Y-m-$current_day").'" service_cost="'.$service_price.'" service_id="'.$this->service_id.'" provider_id="'.$this->provider_id.'"'. $multiple . $lang_slot . 'capacity="'.$count.'"><span>'. $current_day .'</span></td>' . PHP_EOL;

					} else {
						$output .= '<td class="'.$classes.'" date-go="'.$this->date_info->format("Y-m-$current_day").'" service_cost="'.$service_price.'" service_id="'.$this->service_id.'" provider_id="'.$this->provider_id.'"'. $multiple . $lang_slot .' capacity="1"><span>'. $current_day .'</span></td>' . PHP_EOL;

					}


				} else {
					// Date not available
					$classes = "day_unavailable{$today} ga_date_slots";
					$output .= '<td class="'.$classes.'" date-go="'.$this->date_info->format("Y-m-$current_day").'" service_cost="'.$service_price.'"><span>'. $current_day .'</span></td>' . PHP_EOL;
				}

			} else {
				// Time Slots Mode
				$output .= '<td class="'.$classes.'" date-go="'.$this->date_info->format("Y-m-$current_day").'" service_id="'.$this->service_id.'" provider_id="'.$this->provider_id.'" service_cost="'.$service_price.'"><span>'. $current_day .'</span></td>' . PHP_EOL;

			}

			// Increment counters
			$current_day++;
			$this->day_of_week++;
		}


		// Once num_days counter stops, if day of week counter is not 7, then we
		// need to fill remaining space on the row using colspan
		if( $this->day_of_week != 7 ) {
			$remaining_days = 7 - $this->day_of_week;
			$output .= str_repeat( '<td class="ga_day_future"></td>', $remaining_days );
		}

		// Date selected from form submission
		// Place at the end
		if( $this->available_times_mode() == 'no_slots' )  {
			# do nothing
		} else {
			if( $this->selected_date && in_array($this->selected_date->format('Y-m-j'), $available_days) )  {
				// Last day of month from selected date
				$month_end = $this->selected_date;
				$month_end->modify("last day of this month");

				// Next sunday from selected date
				$placed_end = $this->selected_date;
				$placed_end->modify("next {$this->week_starts}");


				if( $placed_end > $month_end ) {
					$output  .= '</tr><tr id="gappointments_calendar_slots"><td colspan="7" class="calendar_slots"><div class="calendar_time_slots">';
					$output  .= $this->calendar_time_slots($this->selected_date, $this->selected_slot);
					$output  .= '</div></td>';
				}
			}
		}
		// Date selected from form submission

		// Close final row and table
		$output .= '</tr>' . PHP_EOL;

		$output .= '</tbody>' . PHP_EOL;
		$output .= '</table>' . PHP_EOL;
		$output .= sprintf( '<div id="ga_slots_data">%s</div>', $slots_html );
		return $output;
		//echo $output;
	}

    /**
     * Fetch a list of booked appointments that should be removed from the booking calendar widget (calendar time slot availability).
     */
	public function get_appointments_query(){

        $month_start          = $this->date_info->format('Y-m-j');
        $month_end            = $this->date_info->format('Y-m-t');
        $appointments_cache   = wp_cache_get( "ga_provider_{$this->provider_id}_service_{$this->service_id}_appointments_between_{$month_start}_to_{$month_end}", "ga_calendar" );

        if( $appointments_cache === false ) {
            global $wpdb;

            $calendar_id          = '';
            $gcal_from_statement  = '';
            $gcal_where_statement = '';
            $service_based_query  = '';
            $provider_based_query = '';
            $sync_service         = get_default_sync_service();

            // Prepare Google Calendar query variables
            if( !empty( $sync_service ) ) {
                $gcal_from_statement  = "LEFT JOIN {$wpdb->postmeta} AS gcal_id on gcal_id.post_id = p.ID";
                $gcal_where_statement = "AND gcal_id.meta_key = 'ga_appointment_gcal_calendar_id'";
                if( $this->provider_id === 0 ) {
                    $options = (array)get_option('ga_appointments_gcal');
                } else {
                    $options = (array)get_post_meta($this->provider_id, 'ga_provider_gcal', true);
                }
                $calendar_id = isset($options['calendar_id']) && !empty($options['calendar_id']) ? $options['calendar_id'] : 'primary';
            }

            // Global appointment availability query
            if( !empty( $calendar_id ) && !empty( $sync_service ) && $calendar_id !== 'primary' ) {
                // Get all provider appointments plus all Google Calendar two-way sync appointments
                $provider_based_query = "AND ( provider.meta_value = '{$this->provider_id}' OR gcal_id.meta_value = '{$calendar_id}' )";

                // Service-based appointment availability query.
                if( $this->provider_availability === 'non-global' ) {
                    $service_based_query = "AND ( service.meta_value = '{$this->service_id}' OR gcal_id.meta_value = '{$calendar_id}' )" ;
                }
            } else {
                // Get all provider appointments when sync is not enabled.
                $provider_based_query = "AND provider.meta_value = '{$this->provider_id}'";

                // Service-based appointment availability query.
                if( $this->provider_availability === 'non-global' ) {
                    $service_based_query = "AND service.meta_value = '{$this->service_id}'" ;
                }
            }

            $querystr = "
				SELECT p.ID,
				start.meta_value AS start,
				end.meta_value AS end,
				date.meta_value AS date,
                provider.meta_value AS provider,
                service.meta_value AS service
                FROM $wpdb->posts p
                LEFT JOIN $wpdb->postmeta AS start on start.post_id = p.ID
                LEFT JOIN $wpdb->postmeta AS end on end.post_id = p.ID
                LEFT JOIN $wpdb->postmeta AS date on date.post_id = p.ID
                LEFT JOIN $wpdb->postmeta AS provider on provider.post_id = p.ID
                LEFT JOIN $wpdb->postmeta AS service on service.post_id = p.ID
                {$gcal_from_statement}
                WHERE p.post_type = 'ga_appointments'
                and p.post_status IN ('completed', 'publish', 'payment', 'pending')
                and start.meta_key = 'ga_appointment_time'
                and end.meta_key = 'ga_appointment_time_end'
                and date.meta_key = 'ga_appointment_date'
                and service.meta_key = 'ga_appointment_service'
                and provider.meta_key = 'ga_appointment_provider'
                {$gcal_where_statement}
                and STR_TO_DATE(date.meta_value, '%Y-%m-%d') BETWEEN '{$month_start}' AND '{$month_end}'
                {$provider_based_query}
                {$service_based_query};
		    ";

            $appointments_cache = $wpdb->get_results( $querystr, ARRAY_A );
            wp_cache_set( "ga_provider_{$this->provider_id}_service_{$this->service_id}_appointments_between_{$month_start}_to_{$month_end}", $appointments_cache, "ga_calendar", 120 );
        }

        $this->current_month_appointments = $appointments_cache;
	}

	/**
	 * Slot Available Query
	 */
	public function slot_availability_query( $dateTime, $slot ) {
		$slot_start = $slot['start'];

		if( $this->schedule_lead_time( $dateTime, $slot_start ) ) {
			# valid time & date
		} else {
			return false;
		}

		$date     = $dateTime->format("Y-m-j");
		// $slot_end = ga_get_time_end($slot_start, $this->service_id);



		 $clashingAppointments = [];
		 $appointments = $this->current_month_appointments;

		 foreach($appointments as $appointment){
			$appointmentStart = $appointment['start'];
			$appointmentEnd = $appointment['end'];
			$appointmentDate = $appointment['date'];

			if($appointmentDate === $date){
				if(($slot['start'] > $appointmentStart && $slot['start'] < $appointmentEnd) ||
				($slot['end'] > $appointmentStart && $slot['end'] < $appointmentEnd) ||
				($slot['start'] <= $appointmentStart && $slot['end'] >= $appointmentEnd)){
					$clashingAppointments[] = $appointment;
				}
			}

		 }
		 if(count($clashingAppointments) != 0){
			return count($clashingAppointments);
		 }
		 return count($clashingAppointments);
	}

	/**
	 * Is slot available
	 */
	public function is_slot_available( $dateTime, $slot ) {
		$appointments = $this->slot_availability_query( $dateTime, $slot );

		if( $appointments === false ) {
			return false;
		}

		if( $appointments == 0 ) {
			return true;
		}

		if( $this->available_times_mode() == 'custom' ) {
			$capacity = $slot['capacity'];
		} else {
			$capacity = $this->service_capacity();
		}

		if ( $appointments >= $capacity ) {
			return false;
		}
		return true;
	}


	/**
	 * Capacity Available Text
	 */
	public function slot_capacity_text( $dateTime, $slot ) {
		$appointments = $this->slot_availability_query( $dateTime, $slot );


		if( $appointments === false ) {
			return false;
		}


		if( $this->available_times_mode() == 'custom' ) {
			$capacity = $slot['capacity'];
		} else {
			$capacity = $this->service_capacity();
		}


		if( $appointments > 0 && $appointments < $capacity ) {
			return ($capacity - $appointments);
		} elseif( $appointments == 0 && $capacity >= 1 ) {
			return $capacity;
		}

		return false;
	}

	/**
	 * Date Available Query
	 */
	public function date_availability_query( $dateTime ) {
		if( $this->schedule_lead_time_days( $dateTime ) ) {
			# valid date
		} else {
			return false;
		}

		$date = $dateTime->format("Y-m-j");

		$appointments = new WP_QUERY(
			array(
				'post_type'         => 'ga_appointments',
				'posts_per_page'    => -1,
				'post_status'       => array( 'completed', 'publish', 'payment', 'pending' ),
				'orderby'           => 'meta_value',
				'order'             => 'ASC',
				'meta_query'        => array( 'relation' => 'AND',
					array( 'key'    => 'ga_appointment_date', 'value' => $date ),
					array( 'key'    => 'ga_appointment_provider', 'value' => $this->provider_id ),
				),
			)
		);
		return $appointments;
	}

	/**
	 * Date Available
	 */
	public function is_date_available( $dateTime ) {
		// Week day shedule is not out
		$week_day = (string) strtolower( $dateTime->format('l') );
		$schedule = $this->get_week_day_schedule();
		if( isset($schedule[$week_day]) ) {
			$schedule = $schedule[$week_day];
		} else {
			return false;
		}

		$appointments = $this->date_availability_query( $dateTime );

		if( !$appointments || $this->date_slot_passed($dateTime) ) {
			return false;
		}

		$available_days = $this->get_available_days( array_keys( $this->get_week_day_schedule() ), $this->get_date_timestamp($this->year, $this->month) );

		if( $this->month_passed() ) {
			$available_days = array();
		}

		if( in_array($dateTime->format('Y-m-j'), $available_days) && !in_array($dateTime->format("Y-m-j"), $this->get_holidays()) ) {
			# valid
		} else {
			return false;
		}

		$capacity = $this->service_capacity();

		if ( $appointments->have_posts() && $capacity <= $appointments->post_count ) {
			return false;
		}

		return true;
	}


	/**
	 * Date Capacity Text
	 */
	public function date_capacity_text( $dateTime ) {
		$appointments = $this->date_availability_query( $dateTime );

		if( !$appointments ) {
			return false;
		}

		$capacity = $this->service_capacity();

		if( $appointments->post_count > 0 && $appointments->post_count < $capacity ) {
			return ($capacity - $appointments->post_count);
		} elseif( $appointments->post_count == 0 && $capacity >= 1 ) {
			return $capacity;
		}

		return false;
	}


	/**
	 * New appointment lead time require for new appointments
	 * @ returns true if valid
	 */
	public function schedule_lead_time( $dateTime, $slot_start ) {
		// Lead time minutes
		$service_lead_time  = get_post_meta($this->service_id, 'ga_service_schedule_lead_time_minutes', true);
		$lead_time          = $service_lead_time && array_key_exists( $service_lead_time, ga_schedule_lead_time_minutes() ) ? $service_lead_time : '240';

		// Today's date
		$today = ga_current_date_with_timezone();


		// Slot time with date
		$slot_time = new DateTime( $slot_start, new DateTimeZone( $this->time_zone ) );
		$slot_time->setDate( $dateTime->format("Y"), $dateTime->format("m"), $dateTime->format("j") );

		// If no lead time, is slot dateTime less or equal then today
		if( $lead_time == 'no' ) {
			if( $slot_time <= $today ) {
				return false;
			}
			return true;
		}

		$lead = $today;
		$lead = $lead->modify("+{$lead_time} minutes");

		//print_r( "Start: {$slot_time->format('Y-m-j H:i')} | End: {$lead->format('Y-m-j H:i')}" . '<br>');

		if( $slot_time <= $lead ) {
			//echo "Not valid: {$slot_time->format('Y-m-j H:i')} | Lead end: {$lead->format('Y-m-j H:i')}<br>";
			return false;
		}

		return true;
	}


	/**
	 * New appointment lead time require for bookable dates
	 * @ returns true if valid
	 */
	public function schedule_lead_time_days( $dateTime ) {
		// Lead time minutes
		$service_lead_time  = get_post_meta($this->service_id, 'ga_service_schedule_lead_time_minutes', true);
		$lead_time          = $service_lead_time && array_key_exists( $service_lead_time, ga_schedule_lead_time_minutes() ) ? $service_lead_time : '240';

		// Today's date
		$today = ga_current_date_with_timezone();


		// If no lead time, is slot dateTime less or equal then today
		if( $lead_time == 'no' ) {
			$dateTime->setTime(23, 59);
			if( $dateTime <= $today ) {
				return false;
			}
			return true;
		}

		$lead = $today;
		$lead = $lead->modify("+{$lead_time} minutes");

		if( $dateTime <= $lead ) {
			//echo "Not valid: {$slot_time->format('Y-m-j H:i')} | Lead end: {$lead->format('Y-m-j H:i')}<br>";
			return false;
		}

		return true;
	}


	/**
	 * Display Slots
	 */
	public function get_slots( $date ) {
		switch ( $this->available_times_mode() ) {
			case 'interval':
				return $this->generate_time_slots( $date );
			case 'custom':
				return $this->get_custom_slots( $date );
			case 'no_slots':
				return array();
			default:
			   return array();
		}
	}

	/**
	 * Get Date Schedule
	 */
	public function get_date_schedule( $date ) {
		$week_day = (string) strtolower( $date->format('l') );
		$schedule = $this->get_week_day_schedule();

		$available_days = $this->get_available_days( array_keys( $this->get_week_day_schedule() ), $this->get_date_timestamp($this->year, $this->month) );
		if( !in_array($date->format('Y-m-j'), $available_days) || $this->date_passed($date) || $this->max_schedule_days($date) ) {
			return false;
		}

		if( isset($schedule[$week_day]) ) {
			return $schedule[$week_day];
		} else {
			return false;
		}
	}


	/**
	 * Custom Time Slots
	 */
	public function get_custom_slots( $date ) {
		$week_day = (string) strtolower( $date->format('l') );

		if( !is_array( $this->custom_slots() ) || $this->get_date_schedule( $date ) === false ) {
			return array();
		}

		$slots = array();
		foreach( $this->custom_slots() as $slot_id => $slot ) {
			if( isset($slot['availability']) && in_array($week_day, $slot['availability']) ) {
				if( $this->is_slot_available( $date, $slot ) ) {
					$startTime = new DateTime( $slot['start'] );
					$endTime   = new DateTime( $slot['end'] );

					$slots[sprintf( '%s-%s', $slot['start'], $slot['end'] )] = array(
						'start'        => $slot['start'],
						'end'          => $slot['end'],
						'text'         => $this->slot_text( $startTime, $endTime ),
						'duration'     => $this->get_slot_duration( $startTime, $endTime ),
						'availability' => $slot['availability'],
						'capacity'     => $slot['capacity'],
						'price'        => $slot['price'],
						'text'         => $this->slot_text( $startTime, $endTime ),
					);
				}
				;
			}
		}
		return $slots;
	}

	public function get_slot_duration( $startTime, $endTime ) {
		$diff    = $startTime->diff( $endTime );
		$minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
		return $minutes;
	}

	/**
	 * Generate Time Slots
	 */
	public function generate_time_slots( $date ) {
		$week_day = (string) strtolower( $date->format('l') );

		if( $this->get_date_schedule( $date ) === false  ) {
			return array();
		} else {
			$schedule = $this->get_date_schedule( $date );
		}

		$duration        = (int) get_post_meta($this->service_id, 'ga_service_duration', true);
		$cleanup         = (int) get_post_meta($this->service_id, 'ga_service_cleanup', true);
		$start           = (string) $schedule['begin'];
		$end             = (string) $schedule['end'];

		$start           = new DateTime($start);
		$end             = new DateTime($end);
		$interval        = new DateInterval("PT" . $duration . "M");
		$cleanupInterval = new DateInterval("PT" . $cleanup . "M");
		$slots           = array();
		$exclude_slots   = array();

		for ($intStart = $start; $intStart < $end; $intStart->add($interval)->add($cleanupInterval)) {
			$endPeriod = clone $intStart;
			$endPeriod->add($interval);

			if( $endPeriod > $end ) {
				break;
			}

			if( $this->get_breaks( $week_day ) ) {
				foreach( $this->get_breaks( $week_day ) as $break_start => $break_end ) {
					$break_start = new DateTime( $break_start );
					$break_end   = new DateTime( $break_end );

					if( $intStart >= $break_end || $endPeriod <= $break_start ) {
						// Slot available
                        // TODO: test slot_available function. 300+ queries on client's server
						if( $this->is_slot_available( $date, $this->generate_slot_data($intStart, $endPeriod) ) ) {
							$slots[sprintf( '%s-%s', $intStart->format('H:i'), $endPeriod->format('H:i') )] = $this->generate_slot_data($intStart, $endPeriod);

						}
					} else {
						$exclude_slots[sprintf( '%s-%s', $intStart->format('H:i'), $endPeriod->format('H:i') )] = $this->generate_slot_data($intStart, $endPeriod);

						if( $this->reduce_gaps() ) {
							$intStart = $break_end;
							$resetInterval = new DateInterval("PT0M");
							$intStart->add($resetInterval)->add($cleanupInterval);
							$endPeriod = clone $intStart;
							$endPeriod->add($interval);

							// Reset interval greater than end time
							if( $endPeriod > $end ) {
								break;
							}

							// Slot available
							if( $this->is_slot_available($date, $this->generate_slot_data($intStart, $endPeriod)) ) {
								$slots[sprintf( '%s-%s', $intStart->format('H:i'), $endPeriod->format('H:i') )] = $this->generate_slot_data($intStart, $endPeriod);
							}
						}
					}
				} // endforeach
			} else {
				// Slot available
				if( $this->is_slot_available($date, $this->generate_slot_data($intStart, $endPeriod)) ) {
					$slots[sprintf( '%s-%s', $intStart->format('H:i'), $endPeriod->format('H:i') )] = $this->generate_slot_data($intStart, $endPeriod);
				}
			}
		}

		return array_diff_key($slots, $exclude_slots);
	}

	private function generate_slot_data( $start, $end ) {
		$slot = array();
		$slot['start']    = $start->format('H:i');
		$slot['end']      = $end->format('H:i');
		$slot['text']     = $this->slot_text($start, $end);
		$slot['duration'] = (int) get_post_meta($this->service_id, 'ga_service_duration', true);
		$slot['price']    = get_post_meta($this->service_id, 'ga_service_price', true);
		$slot['capacity'] = get_post_meta($this->service_id, 'ga_service_capacity', true);
		return $slot;
	}

	/**
	 * Schedule Available Week Days
	 */
	public function get_week_day_schedule() {
		if( $this->provider_id == 0 ) {
			$provider_schedule = $this->get_calendar_schedule();
		} else {
			$provider_calendar = (string) get_post_meta( $this->provider_id, 'ga_provider_calendar', true );
			if( $provider_calendar == 'on' ) {
				$provider_schedule = (array) get_post_meta( $this->provider_id, 'ga_provider_work_schedule', true );
			}
			else{
				$provider_schedule = $this->get_calendar_schedule();
			}
		}

		foreach( $provider_schedule as $key => $schedule ) {
			if( $schedule['begin'] == 'out' ) {
				unset( $provider_schedule[$key] );
			}
		}

		return $provider_schedule;
	}

	/**
	 * Schedule Available Breaks
	 */
	private function get_breaks( $week_day ) {
		$sort     = array();
		$breaks = $this->get_calendar_breaks();

		if( $this->provider_id == 0 ) {
			# do nothing
		} else {
			$provider_calendar = (string) get_post_meta( $this->provider_id, 'ga_provider_calendar', true );
			if( $provider_calendar == 'on' ) {
				$breaks = (array) get_post_meta( $this->provider_id, 'ga_provider_breaks', true );
			}
		}

		if( isset( $breaks[$week_day] ) && count($breaks[$week_day]) > 0 ) {
			$breaks = $breaks[$week_day];
		} else {
			return array();
		}

		foreach($breaks as $key => $part) {
			$sort[$key] = new DateTime($part);
		}


		array_multisort($sort, SORT_ASC, $breaks);

		return $breaks;

	}

	/**
	 * Holidays
	 */
	public function get_holidays() {
		$provider_holidays = $this->get_calendar_holidays();

		if( $this->provider_id == 0 ) {
			# do nothing
		} else {
			$provider_calendar = (string) get_post_meta( $this->provider_id, 'ga_provider_calendar', true );
			if( $provider_calendar == 'on' ) {
				$provider_holidays = (array) get_post_meta( $this->provider_id, 'ga_provider_holidays', true );
			}
		}

		return $provider_holidays;
	}

	private function get_calendar_schedule() {
		$options = get_option( 'ga_appointments_work_schedule' );
		$work_schedule = $options && is_array($options) ? $options : $this->get_calendar_defauls();
		return $work_schedule;
	}

	private function get_calendar_breaks() {
		$options = get_option( 'ga_appointments_schedule_breaks' );
		$breaks = (array) $options;
		return $breaks;
	}

	private function get_calendar_holidays() {
		$options  = get_option( 'ga_appointments_holidays' );
		$holidays = (array) $options;
		return $holidays;
	}

	/**
	 * Default Schedule
	 */
	private function get_calendar_defauls() {
		$schedule = array( 'sunday'    => array('begin' => 'out',   'end' => 'out'),
						   'monday'    => array('begin' => '09:00', 'end' => '17:00'),
						   'tuesday'   => array('begin' => '09:00', 'end' => '17:00'),
						   'wednesday' => array('begin' => '09:00', 'end' => '17:00'),
						   'thursday'  => array('begin' => '09:00', 'end' => '17:00'),
						   'friday'    => array('begin' => 'out',   'end' => 'out'),
						   'saturday'  => array('begin' => 'out',   'end' => 'out'),
		);

		return $schedule;
	}

	/**
	 * Reduce gaps
	 */
	private function reduce_gaps() {
		$reduce_gaps = get_post_meta( $this->service_id, 'ga_service_reduce_gaps', true );

		// Reduce Gaps
		if( $reduce_gaps && in_array($reduce_gaps, array('yes', 'no')) ) {
			$gaps = $reduce_gaps;
		} else {
			$gaps = 'yes'; // days
		}

		if( $gaps == 'yes' ) {
			return true;
		}

		return false;
	}

	/**
	 * Prior Days To Book Appointment
	 */
	private function max_schedule_days( $date_input ) {
		$max_days = get_post_meta( $this->service_id, 'ga_service_schedule_max_future_days', true );
		$max_days = $max_days && array_key_exists( $max_days, ga_schedule_max_future_days() ) ? $max_days : 90;


		// SERVICE PERIOD TYPE
		$period_type = (string) get_post_meta($this->service_id, 'ga_service_period_type', true);

		if( $period_type == 'date_range' ) {
			$range = (array) get_post_meta($this->service_id, 'ga_service_date_range', true);
			if( isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to']) ) {
				$end_range = new DateTime($range['to'], new DateTimeZone( $this->time_zone ));
				$end_range->setTime(24,00);
				return $date_input > $end_range;
			}
			return true;
		}

		if( $period_type == 'custom_dates' ) {
			$custom_dates = (array) get_post_meta($this->service_id, 'ga_service_custom_dates', true);
			if( is_array($custom_dates) && count($custom_dates) > 0 && ga_valid_date_format(end($custom_dates)) ) {
				$end_custom_date = new DateTime(end($custom_dates), new DateTimeZone( $this->time_zone ));
				$end_custom_date->setTime(24,00);
				return $date_input > $end_custom_date;
			}

			return true;
		}


		// Future Days Period
		$date = new DateTime();
		$date->setTimezone( new DateTimeZone( $this->time_zone ) );
		$date->add(new DateInterval( "P" . $max_days . "D" ));
		$date->setTime(00, 00);

		$calendar = $date_input; // DateTime Object
		$calendar->setTimezone( new DateTimeZone( $this->time_zone ) );
		$calendar->setTime(00, 00);

		return $calendar > $date;

	}


	public function calendar_time_slots($date, $sel_slot = false) {

		// Translation Support
		$month = $date->format("F");
		$week  = $date->format("l");
		$day   = $date->format("j");
		$year  = $date->format("Y");
		$text  = ga_get_form_translated_slots_date($this->form_lang, $month, $day, $year);
		// Translation Support

		// Slot size
		$slots_size = $this->show_end_times() ? 'slot_large grid-lg-12 grid-md-12 grid-sm-12 grid-xs-12' : 'slot_small grid-lg-3 grid-md-3 grid-sm-3 grid-xs-6';

		// Time Format Display
		$time_display  = ga_service_time_format_display($this->service_id);

		// Multiple Slots selection
		$multi_select  = (string) get_post_meta($this->service_id, 'ga_service_multiple_selection', true);
		$max_bookings  = ga_get_service_max_bookings($this->service_id);
		$max_total     = ga_get_service_max_selection($this->service_id);
		$double        = ga_get_service_double_bookings($this->service_id);
		$time_format   = (string) get_post_meta($this->service_id, 'ga_service_time_format', true);
		$remove_am_pm  = (string) get_post_meta($this->service_id, 'ga_service_remove_am_pm', true);
		$multiple      = $multi_select == 'yes' ? ' multi-select="enabled" select-max="'.$max_bookings.'" select-total="'.$max_total.'" time_format="'.$time_format.'" remove_am_pm="'.$remove_am_pm.'" no_double="'.$double.'"' : '';
		$out = '';

		$timeSlots = $this->get_slots( $date );

		if( count($timeSlots) > 0  ) {
			$out .= sprintf( '<div id="%s">', $date->format('Y-m-j') ); // eg. February 24, 2018
				$out .= '<h3 class="slots-title">' .$text. '</h3>'; // eg. February 24, 2018
				$out .= '<div class="grid-row grid_no_pad">';
					foreach( $timeSlots as $slot ) {
						//TODO: Possibly returns bad custom time slot price
						$sel_class = $sel_slot && $sel_slot == sprintf( '%s-%s', $slot['start'], $slot['end'] ) ? ' time_selected' : '';
						$slot_cost  = $this->available_times_mode() == 'custom' ? $slot['price'] : $this->service_price();

						// Slot Language
						if( $multi_select == 'yes' ) {
							$time      = new DateTime($slot['start'], new DateTimeZone( $this->time_zone ));
							$slot_time = $time->format($time_display);
							$translate = ga_get_form_translated_date_time( $this->form_lang, $month, $week, $day, $year, $slot_time );
							$lang_slot = ' lang_slot="'. esc_html($translate) .'"';
						} else {
							$lang_slot = '';
						}

						// Slot Language
						$slotID = sprintf( '%s-%s', $slot['start'], $slot['end'] );
                        $count = $this->slot_capacity_text( $date, $slot );
						if( !empty( $count ) && ( (int) $count !== 1 || (int) $slot['capacity'] > 1 ) ) {
							$capacity = $this->get_translation( $this->form_lang, $count );
							$out .= '<div class="'.$slots_size.' grid_no_pad">
									<label class="time_slot ga_tooltip'.$sel_class.'" time_slot="'.$slotID.'" ga-tooltip="'.$capacity.'"'.$multiple . $lang_slot.' capacity="'.$count.'" service_id="'.$this->service_id.'" slot_cost="'.$slot_cost.'"><div>'.$slot['text'].'</div></label>
								</div>';
						} else {
							$out .= '<div class="'.$slots_size.' grid_no_pad">
									<label class="time_slot'.$sel_class.'" time_slot="'.$slotID.'" '.$multiple . $lang_slot.' capacity="1" service_id="'.$this->service_id.'" slot_cost="'.$slot_cost.'"><div>'.$slot['text'].'</div></label>
								</div>';
						}
					}
				$out .= '</div>';
			$out .= '</div>';
		 } else {
			$out = false;
		}

		return $out;
	}


	/**
	 * Slot Text
	 */
	private function slot_text($intStart, $endPeriod) {
		$remove_am_pm = $this->remove_am_pm() == 'no' ? 'A' : '';
		$time_format  = $this->time_format() == '24h' ? "G:i {$remove_am_pm}" : "g:i {$remove_am_pm}";

		$start = ga_get_form_translated_am_pm($this->form_lang, $intStart->format($time_format));
		$end   = ga_get_form_translated_am_pm($this->form_lang, $endPeriod->format($time_format));

		if( $this->show_end_times()  ) {
			return $start . ' - ' . $end;
		} else {
			return $start;
		}

	}

	/**
	 * Show End Times
	 */
	public function show_end_times() {
		$show_end_times = get_post_meta( $this->service_id, 'ga_service_show_end_times', true );

		// Show End Times
		if( $show_end_times && in_array($show_end_times, array('yes', 'no')) ) {
			$end_times = $show_end_times;
		} else {
			$end_times = 'no'; // days
		}

		if( $end_times == 'yes'  ) {
			return true;
		}

		return false;

	}

	/**
	 * Time Format
	 */
	public function time_format() {
		$time_format = get_post_meta( $this->service_id, 'ga_service_time_format', true );

		// Time Format
		if( $time_format && in_array($time_format, array('12h', '24h')) ) {
			$format = $time_format;
		} else {
			$format = '12h'; // 12h format
		}


		return $format;

	}

	/**
	 * Time Format
	 */
	public function remove_am_pm() {
		$remove_am_pm = get_post_meta( $this->service_id, 'ga_service_remove_am_pm', true );

		// Time Format
		if( $remove_am_pm && in_array($remove_am_pm, array('no', 'yes')) ) {
			$remove = $remove_am_pm;
		} else {
			$remove = 'no'; // 12h format
		}
		return $remove;
	}


	/**
	 * Current Date Today
	 */
	private function is_today( $current_date ) {
		$today = ga_current_date_with_timezone();
		return $today->format('Y-m-j') == $current_date->format('Y-m-j');
	}


	/**
	* Date Passed
	*/
	private function date_passed($dateTime) {
		$day = $dateTime->format('j');
		$now = ga_current_date_with_timezone();

		$calendar = new DateTime();
		$calendar->setTimezone( new DateTimeZone( $this->time_zone ) );
		$calendar->setDate( (int) $this->year, (int) $this->month, $day );

		return $now > $calendar;
	}

	/**
	* Date Slot Passed
	*/
	private function date_slot_passed($dateTime) {
		$now = ga_current_date_with_timezone();
		return $now > $dateTime;
	}

	/**
	* Month Passed
	*/
	private function month_passed() {
		$month = new DateTime();
		$month->setTimezone( new DateTimeZone( $this->time_zone ) );
		$month->setDate( $month->format('Y'), $month->format('n'), 1 );

		$now = ga_current_date_with_timezone();;

		return $month > $now;
	}

	/**
	* Previous Month
	*/
	private function previous_month() {
		$previous = new DateTime();
		$previous->setTimezone( new DateTimeZone( $this->time_zone ) );
		$previous->setDate( $this->date_info->format('Y'), $this->date_info->format('n'), 1 );
		$previous->modify( 'last day of previous month' );


		// SERVICE PERIOD TYPE
		$period_type = (string) get_post_meta($this->service_id, 'ga_service_period_type', true);

		if( $period_type == 'date_range' ) {
			$range = (array) get_post_meta($this->service_id, 'ga_service_date_range', true);
			if( isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to']) ) {
				$begin_range = new DateTime($range['from'], new DateTimeZone( $this->time_zone ));
				return $previous > $begin_range;
			}
			return false;
		}

		if( $period_type == 'custom_dates' ) {
			$custom_dates = (array) get_post_meta($this->service_id, 'ga_service_custom_dates', true);
			if( is_array($custom_dates) && count($custom_dates) > 0 && ga_valid_date_format(reset($custom_dates)) ) {
				$begin_custom_date = new DateTime(reset($custom_dates), new DateTimeZone( $this->time_zone ));
				return $previous > $begin_custom_date;
			}
			return false;
		}

		return $previous >= ga_current_date_with_timezone();
	}


	/**
	* Next Month
	*/
	private function next_month() {
		$next = new DateTime();
		$next->setTimezone( new DateTimeZone( $this->time_zone ) );
		$next->setDate( $this->date_info->format('Y'), $this->date_info->format('n'), 1 );
		$next->modify( 'first day of next month' );

		if( $this->max_schedule_days($next) ) {
			return false;
		}
		return true;
	}

	/**
	* Month future
	*/
	private function month_future() {
		$now = new DateTime();
		$now->setTimezone( new DateTimeZone( $this->time_zone ) );
		$now->setDate( $now->format('Y'), $now->format('n'), 1 );
		return $now > $this->date_info;
	}

	private function available_times_mode() {
		return (string) get_post_meta( $this->service_id, 'ga_service_available_times_mode', true );
	}

	private function service_capacity() {
		return (int) get_post_meta( $this->service_id, 'ga_service_capacity', true );
	}

	private function custom_slots() {
		return get_post_meta( $this->service_id, 'ga_service_custom_slots', true );
	}

	private function service_price() {
		return get_post_meta( $this->service_id, 'ga_service_price', true );
	}

	private function two_way_sync() {
        $sync = new ga_gcal_sync( null, $this->provider_id );

        if( $sync->is_sync_enabled() ) {
            $sync->sync_events();
        } else {
            return;
        }
	}
	private function get_translation( $form_lang, $count ) {
	   return $count == 1 ? ga_get_form_translated_space($form_lang, $count) : ga_get_form_translated_spaces($form_lang, $count);
    }

    private function get_availability_option() {
        if( $this->provider_id === 0 ) {
            $global_availability = get_option( 'ga_appointments_appointment_availability' );
            $availability = $global_availability !== false ? $global_availability : 'non-global';
        } else {
            $provider_availability = get_post_meta( $this->provider_id, 'ga_provider_appointment_availability', true );
            $availability = !empty( $provider_availability ) ? $provider_availability : 'non-global';
        }
        return $availability;
    }

} // end class
