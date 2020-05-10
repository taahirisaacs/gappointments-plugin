<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( class_exists( 'GFForms' ) ) {
	class GF_Appointment_Booking_Calendar extends GF_Field {
		public $type = 'appointment_calendar';

		public function get_form_editor_field_title() {
			return esc_attr__( 'Booking Calendar', 'gravityforms' );
		}

		/*
		* Where to assign this widget
		*/
		public function get_form_editor_button() {
			return array(
				//'group' => 'advanced_fields',
				'group' => 'appointment_calendar',
				'text'  => $this->get_form_editor_field_title()
			);
		}
		/*
		* Add button to the group
		*/
		public function add_button( $field_groups ) {
			$field_groups = $this->ga_appointment_services_gf_group( $field_groups );
			return parent::add_button( $field_groups );
		}
		/*
		* Add our group
		*/
		public function ga_appointment_services_gf_group( $field_groups ) {
			foreach ( $field_groups as $field_group ) {
				if ( $field_group['name'] == 'appointment_calendar' ) {
					return $field_groups;
				}
			}
			$field_groups[] = array(
				'name'   => 'appointment_calendar',
				'label'  => __( 'Appointment Booking', 'simplefieldaddon' ),
				'fields' => array(
				)
			);

			return $field_groups;
		}

		/*
		* Widget settings
		*/
		function get_form_editor_field_settings() {
			return array(
				'label_setting',
				'error_message_setting',
				'label_placement_setting',
				'admin_label_setting',
				'description_setting',
				'css_class_setting',
				'rules_setting',
				'size_setting',
				'conditional_logic_field_setting',
			);
		}

		public function is_conditional_logic_supported() {
			return true;
		}

		
		public function is_value_submission_empty($form_id) {
			return false;
		}

		/**
		 * Field Markup
		 */
		public function get_field_input( $form, $value = '', $entry = null, $service_id = null, $selected_date = null) {
			$form_id         = absint( $form['id'] );
			$is_entry_detail = $this->is_entry_detail();
			$is_form_editor  = $this->is_form_editor();

			$id                 = $this->id;
			//$field_id           = 'gf_appointment_booking_calendar'; // the html id
			$field_id           = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
			$size               = $this->size;
			$class_suffix       = $is_entry_detail ? '_admin' : '';
			$class              = $size . $class_suffix;
			$css_class          = trim( esc_attr( $class ) . ' gfield_select' );
			$tabindex           = $this->get_tabindex();
			$disabled_text      = $is_form_editor ? 'disabled="disabled"' : '';
			$required_attribute = $this->isRequired ? 'aria-required="true"' : '';
			$invalid_attribute  = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';


			$calendar = "<div class='ginput_container'>";

			if( $this->is_entry_edit() ) {

				$calendar .= 'This field is not editable';
				$calendar .= "<input type='hidden' name='input_{$id}' id='{$field_id}' value='{$value}'/>";

			} elseif( !$this->is_form_editor() ) {
				ob_start();

				$calendar .= '<div class="grid-row"><div class="'.$this->field_size().' grid-sm-12 grid-xs-12" id="gappointments_calendar">' . PHP_EOL;

				if( ga_service_id($form) && gf_field_type_exists( $form, 'appointment_services' ) ) {
				    $continue = true;
				}
				elseif($service_id != null){
				    $continue = true;
                }
				else {
					$continue = false;
				}

				if($continue = true){
                    $current_date   = ga_current_date_with_timezone();
                    if($service_id === null){
                        $service_id     = ga_service_id($form);
                    }


                    // Form submited service ID
                    $services_field_value = gf_get_field_type_value( $form, 'appointment_services' );
                    if( is_numeric($services_field_value) && 'ga_services' == get_post_type($services_field_value) ) {
                        $service_id = $services_field_value;
                    }


                    $provider_id = ga_get_provider_id( $service_id ) ? ga_get_provider_id( $service_id ) : 0;
                    // Form submited provider ID
                    if( gf_field_type_exists( $form, 'appointment_providers' ) ) {

                        $providers_field_value  = gf_get_field_type_value( $form, 'appointment_providers' );

                        if( is_numeric($providers_field_value) && ga_get_provider_id($service_id) && 'ga_providers' == get_post_type($providers_field_value) ) {
                            $provider_id = $providers_field_value;
                        }
                    }

                    // Booking Date/Time Fields
                    $date_val = '';
                    $time_val = '';
                    $cost_val = '0';
                    if ( is_array( $value ) ) {
                        $date_val  = isset($value['date']) ? $value['date'] : $date_val;
                        $time_val  = isset($value['time']) ? $value['time'] : $time_val;
                        $cost_val  = isset($value['cost']) ? $value['cost'] : $cost_val;
                    }

                    // Service period type
                    $period_type = (string) get_post_meta($service_id, 'ga_service_period_type', true);
                    if( $period_type == 'date_range' ) {
                        $range = (array) get_post_meta($service_id, 'ga_service_date_range', true);
                        if( isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to']) ) {
                            $current_date = new DateTime($range['from'], new DateTimeZone( ga_time_zone() ));
                        }
                    }
                    if( $period_type == 'custom_dates' ) {
                        $custom_dates = (array) get_post_meta($service_id, 'ga_service_custom_dates', true);
                        if( is_array($custom_dates) && count($custom_dates) > 0 && ga_valid_date_format(reset($custom_dates)) ) {
                            $current_date = new DateTime(reset($custom_dates), new DateTimeZone( ga_time_zone() ));
                        }
                    }

                    // Form submited date & time
                    $selected_date      = false;
                    $selected_slot      = $time_val;
                    if( ga_valid_date_format($date_val) ) {
                        $current_date   = new DateTime( $date_val, new DateTimeZone(ga_time_zone()) );
                        $selected_date  = clone $current_date;
                    }
                    // Form submited date & time

                    // Calendar HTML
                    $calendar   .= '<div id="ga_appointments_calendar" form_id="'.$form_id.'"><div class="ga_monthly_schedule_wrapper">' . PHP_EOL;
                    $ga_calendar = new GA_Calendar( $form_id, $current_date->format('m'), $current_date->format('Y'), $service_id, $provider_id, $selected_date, $selected_slot );
                    $calendar   .= $ga_calendar->show();
                    $calendar   .= '</div></div>' . PHP_EOL; // end #ga_appointments_calendar
                    // End Calendar HTML

                    // Multiple Slots Selection
                    $calendar .= '<div id="ga_selected_bookings">' . PHP_EOL;
                    $calendar .= $this->multiple_bookings_markup($form_id, $value, $service_id, $provider_id);
                    $calendar .= '</div>' . PHP_EOL; // end #ga_selected_bookings
                    // Multiple Slots Selection
                }
				else{
                    return '<p>' .ga_get_form_translated_data($form_id, 'error_no_services'). '</p>';
                }

				$calendar .= '</div></div>' . PHP_EOL; // end grid-row

				$calendar .= "<input type='hidden' name='input_{$id}[date]' id='{$field_id}' class='{$class} ginput_{$this->type}_input appointment_booking_date' value='{$date_val}'/>";
				$calendar .= "<input type='hidden' name='input_{$id}[time]' id='{$field_id}_time' class='{$class} ginput_{$this->type}_input appointment_booking_time' value='{$time_val}'/>";

				// Appointment cost hidden field just in case
				$calendar .= "<input type='hidden' name='input_{$id}[cost]' class='ginput_appointment_cost_input gform_hidden' value='{$cost_val}'/>";

				$calendar .= ob_get_clean();
			}

			$calendar .= '</div>' . PHP_EOL; // end ginput_container
			return $calendar;
		}


		/**
		 * Is Entry Edit
		 */
		public function is_entry_edit() {
			if ( rgget( 'page' ) == 'gf_entries' && rgget( 'view' ) == 'entry' && rgpost( 'screen_mode' ) == 'edit' ) {
				return true;
			}

			return false;
		}

		public function get_inline_price_styles() {
			return '';
		}


		/**
		 * Multiple Bookings Markup
		 */
		public function multiple_bookings_markup($form_id, $value, $service_id, $provider_id) {
			$id  = $this->id;
			$out = '';

			// Service multiple slots
			$multiple_slots = (string) get_post_meta( $service_id, 'ga_service_multiple_selection', true );
			if( $multiple_slots != 'yes' ) {
				return '';
			}

			// Time Format Display
			$time_display = ga_service_time_format_display($service_id);

			// Service price
			$service_price = get_post_meta($service_id, 'ga_service_price', true);

			// Service mode
			$available_times_mode = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );

			// Get Bookings
			$bookings = ga_get_multiple_bookings($value, $service_id, $provider_id);

			if( count($bookings) > 0 ) {
				foreach( $bookings as $key => $booking ) {
					$date = new dateTime( sprintf( '%s %s', $booking['date'], $booking['time'] ), new DateTimeZone(ga_time_zone()) );

					// Translation Support
					if( $available_times_mode == 'no_slots' )  {
						$month = $date->format('F');
						$day   = $date->format('j');
						$year  = $date->format('Y');
						$appointment_date = ga_get_form_translated_slots_date($form_id, $month, $day, $year);
					} else {
						$month = $date->format('F');
						$week  = $date->format('l');
						$day   = $date->format('j');
						$year  = $date->format('Y');
						$_time = $date->format($time_display);
						$appointment_date = ga_get_form_translated_date_time($form_id, $month, $week, $day, $year, $_time);
					}

					if( $available_times_mode == 'custom' ) {
						$slot_price = $booking['price'];
					} else {
						$slot_price = $service_price;
					}

					$out .= '<div class="ga_selected_booking">';
						$out .= '<div class="ga_delete_booking"><i class="fa fa-times-circle"></i></div>';
						$out .= '<input type="hidden" class="ga_hidden_input ga_selected_booking_date" name="input_'.$id.'[bookings][date][]" value="'. $booking['date'] .'" slot_cost="'.$slot_price.'">
								<input type="hidden" class="ga_hidden_input ga_selected_booking_time" name="input_'.$id.'[bookings][time][]" value="'. $booking['time_id'] .'" slot_cost="'.$slot_price.'">'.$appointment_date;
					$out .= '</div>';
				}
			}

			return $out;
		}


		/**
		 * Field Size Class
		 */
		public function field_size() {

			if( isset( $this->size ) ) {
				switch ($this->size) {
					case "small":
						$gf_size      = 'ga_wrapper_small grid-lg-4 grid-md-4 grid-sm-6 grid-sx-12';
						break;
					case "medium":
						$gf_size      = 'ga_wrapper_medium grid-lg-6 grid-md-6';
						break;
					case "large":
						$gf_size      = 'ga_wrapper_large grid-lg-12 grid-md-12';
						break;
					default:
						$gf_size      = 'ga_wrapper_medium here grid-lg-6 grid-md-6';
				}
			} else {
				$gf_size              = 'ga_wrapper_medium grid-lg-6 grid-md-6';
			}

			return $gf_size;
		}

		/**
		 * Validation Failed Message
		 */
		private function validationFailed( $message = '' ) {
			$this->failed_validation = true;
			$message = esc_html__( $message, 'gravityforms' );
			$this->validation_message = empty( $this->errorMessage ) ? $message : $this->errorMessage;
		}

		/**
		 * Validate
		 */
		public function validate( $value, $form ) {
			$form_id = absint( $form['id'] );
			
			$dateValue = '';
			$timeValue = '';
			$slotID    = '';

			if ( is_array( $value ) ) {
				$dateValue = isset($value['date']) ? $value['date'] : $date;
				$timeArray = isset($value['time']) ? explode( "-", $value['time'] ) : array();
				$timeValue = reset( $timeArray );
				$slotID    = isset($value['time']) ? $value['time'] : $slotID;
			}

			// Check if services field exists
			if( gf_field_type_exists($form, 'appointment_services') && 'ga_services' == get_post_type( gf_get_field_type_value($form, 'appointment_services') ) ) {

				// Service & Provider ID
				$service_id   = gf_get_field_type_value( $form, 'appointment_services' );
				$provider_id  = gf_field_type_exists($form, 'appointment_providers') && 'ga_providers' == get_post_type(gf_get_field_type_value($form, 'appointment_providers'))
								? gf_get_field_type_value($form, 'appointment_providers')
								: 0;


				if( ga_get_provider_id($service_id) && $provider_id == 0 ) {
					$provider_id = ga_get_provider_id($service_id);
				}

				// Selected service exists in form category term
				$form_cat_slug = rgar($form, 'ga_service_category');
				$cat           = term_exists( $form_cat_slug, 'ga_service_cat' );

				if( $cat ) {
					if( has_term( $cat, 'ga_service_cat', $service_id ) ) {
						# valid
					} else {
						$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_service') );
						return;
					}
				}

			} else {
				$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_service') );
				return;
			}

			$available_times_mode = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );


			/**
			 * Multiple Bookings Validation
			 */
			// Service multiple slots
			$multiple_slots      = (string) get_post_meta( $service_id, 'ga_service_multiple_selection', true );

			// Get bookings
			$bookings = ga_get_multiple_bookings($value, $service_id, $provider_id);

			if( $multiple_slots == 'yes' ) {
				if( count($bookings) > 0 ) {
					foreach ($bookings as $key => $booking) {
						$dateTime = new DateTime( $booking['date'], new DateTimeZone( ga_time_zone() ) );

						// Date Slots Mode
						if( $available_times_mode == 'no_slots' )  {
							# date validation failed
							if( $this->date_valid($form, $service_id, $provider_id, $dateTime) !== true) {
								$message = $this->date_valid($form, $service_id, $provider_id, $dateTime);
								$this->validationFailed( $message );
								return;
							}
							continue;
						}

						// Client max bookings
						$client_max_bookings = $this->client_max_bookings( $form, $service_id, $dateTime, $bookings );
						if( $client_max_bookings ) {
							$max_bookings = ga_get_service_max_bookings($service_id);
							$booked = $dateTime->format('F j, Y');

							// Translation
							$month  = $dateTime->format('F');
							$day    = $dateTime->format('j');
							$year   = $dateTime->format('Y');
							$booked = ga_get_form_translated_slots_date($form_id, $month, $day, $year);
							$booked = ga_get_form_translated_error_max_bookings($form_id, $booked, $max_bookings);
							// Translation

							$this->validationFailed( "{$booked}" );
							return;
						}

						// Time Slots Mode
						if( $this->slot_valid($form, $service_id, $provider_id, $dateTime, $booking['time_id']) !== true ) {
							# time & date validation failed
							$message = $this->slot_valid($form, $service_id, $provider_id, $dateTime, $booking['time_id']);
							$this->validationFailed( $message );
							return;
						}

					}
				}

				if( count($bookings) < 1 && $this->isRequired ) {
					$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required') );
					return;
				}
				return;
			}
			// Multiple Bookings Validation

			/**
			 * Single Bookings Validation
			 */
			if( ga_valid_date_format($dateValue) ) {
				$dateTime = new DateTime( $dateValue, new DateTimeZone( ga_time_zone() ) );

				// Date Slots Mode
				if( $available_times_mode == 'no_slots' )  {

					if( $this->date_valid($form, $service_id, $provider_id, $dateTime) !== true) {
						$message = $this->date_valid($form, $service_id, $provider_id, $dateTime);
						$this->validationFailed( $message );
						return;
					}

					return;
				}

				// Client max bookings
				$client_max_bookings = $this->client_max_bookings( $form, $service_id, $dateTime, $bookings = array($dateTime->format('Y-m-j')) );
				if( $client_max_bookings ) {
					$booked = $dateTime->format('F j, Y');

					// Translation
					$month   = $dateTime->format('F');
					$day     = $dateTime->format('j');
					$year    = $dateTime->format('Y');
					$booked  = ga_get_form_translated_slots_date($form_id, $month, $day, $year);
					$reached = ga_get_form_translated_error_message($form_id, 'error_reached_max', $booked);
					// Translation

					$this->validationFailed( "{$reached}" );
					return;
				}

				// Time Slots Mode
				if( $this->slot_valid($form, $service_id, $provider_id, $dateTime, $slotID) !== true ) {
					$message = $this->slot_valid($form, $service_id, $provider_id, $dateTime, $slotID);
					$this->validationFailed( $message );
					return;
				}


			} else {
				$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_date') );
				return;
			}

		} // end validate function



		/**
		 * Date Valid
		 */
		private function date_valid( $form, $service_id, $provider_id, $dateTime ) {
			$form_id = absint( $form['id'] );

			// Date Validation
			$date       = $dateTime->format('Y-m-j');

			// Translation
			$month       = $dateTime->format('F');
			$day         = $dateTime->format('j');
			$year        = $dateTime->format('Y');
			$lang_date   = ga_get_form_translated_slots_date($form_id, $month, $day, $year);
			// Translation

			if( !class_exists('GA_Calendar') ) {
				require_once( ga_base_path . '/gf-fields/ga-calendar.php' );
			}

			$ga_calendar       = new GA_Calendar( $form_id, $dateTime->format('n'), $dateTime->format('Y'), $service_id, $provider_id );
			$date_available    = $ga_calendar->is_date_available( $dateTime );

			if( $date_available ) {
				# valid date
				if( $this->client_booked_date_slot($form, $service_id, $provider_id, $dateTime) ) {
					return ga_get_form_translated_error_message($form_id, 'error_booked_date', $lang_date);
				}
			} else {
				return ga_get_form_translated_error_message($form_id, 'error_date_valid', $lang_date);
			}

			return true;
		}

		/**
		 * Slot Valid
		 */
		private function slot_valid( $form, $service_id, $provider_id, $date, $time ) {
			$form_id = absint( $form['id'] );

			// Translation
			$time_display = ga_service_time_format_display($service_id);			
			$human_date = new DateTime( "{$date->format('Y-m-j')} {$time}", new DateTimeZone(ga_time_zone()) );
			$month = $human_date->format('F');
			$week  = $human_date->format('l');
			$day   = $human_date->format('j');
			$year  = $human_date->format('Y');
			$_time = $human_date->format($time_display);
			$lang_date = ga_get_form_translated_date_time($form_id, $month, $week, $day, $year, $_time);			
			// Translation

			if( $time == '' ) {
				return ga_get_form_translated_error_message($form_id, 'error_required_slot', $lang_date);
			}

			// Time Slots Validation
			if( !class_exists('GA_Calendar') ) {
				require_once( ga_base_path . '/gf-fields/ga-calendar.php' );
			}

			$ga_calendar = new GA_Calendar( $form_id, $date->format('n'), $date->format('Y'), $service_id, $provider_id );
			$slots_available = $ga_calendar->get_slots( $date );

			// Is slot available
			$is_slot_available = array_key_exists($time, $slots_available);
			
			if( ! $is_slot_available ) {
				return ga_get_form_translated_error_message($form_id, 'error_slot_valid', $lang_date);
			}


			// Client already booked slot
			$already_booked_slot = $this->client_booked_slot( $form, $service_id, $provider_id, $date, $time );
			if( $already_booked_slot ) {
				return ga_get_form_translated_error_message($form_id, 'error_booked_date', $lang_date);
			}
			return true;
		}


		/**
		 * Escape SQL RegexP
		 * Characters must be escaped such as: \ ^ . $ | ( ) [ ] * + ? { } ,
		 */
		private function esc_sql_regexp( $str ) {
			return preg_replace('/[.\\\\+*?[\\^\\]$(){}=!|:,\\-]/', '\\\\\\\\\\\\${0}', $str);
		}

		/**
		 * Get Email Value From Submitted Form
		 */
		private function email_field_value( $form ) {

			$exists = gf_field_type_exists($form, 'email');
			$email_value = '';
			if($exists){
				$email_value = esc_sql(ga_get_field_type_value($form, 'email'));
			}
			return $this->esc_sql_regexp($email_value);
		}



		/**
		 * Get Phone Value From Submitted Form
		 */
		private function phone_field_value( $form ) {
			$phone_value = gf_field_type_exists($form, 'phone') ? esc_sql(ga_get_field_type_value($form, 'phone')) : '';
			return $this->esc_sql_regexp($phone_value);
		}

		/**
		 * Client Booked Time Slot
		 * @ $form array
		 * @ $service_id
		 * @ $provider_id
		 * @ $dateTime
		 * @ $slot_start
		 */
		private function client_booked_slot($form, $service_id, $provider_id, $dateTime, $slot_start) {
			// Prevent Double Bookings
			$double_bookings = ga_get_service_double_bookings($service_id);
			if( $double_bookings == 'no' ) {
				return false;
			}

			$date         = $dateTime->format("Y-m-j");
			$slot_end     = ga_get_time_end($slot_start, $service_id);

			// Client Booked Time Slot
			$email_value  = $this->email_field_value( $form );
			$phone_value  = $this->phone_field_value( $form );

			global $wpdb;
			$querystr = "SELECT $wpdb->posts.ID
				FROM
				   $wpdb->posts,
				   $wpdb->postmeta AS app_date,
				   $wpdb->postmeta AS provider,
				   $wpdb->postmeta AS time1,
				   $wpdb->postmeta AS time2,
				   $wpdb->postmeta AS client
				WHERE
				   $wpdb->posts.ID = app_date.post_id
				AND
				   $wpdb->posts.ID = provider.post_id
				AND
				   $wpdb->posts.ID = time1.post_id
				AND
				   $wpdb->posts.ID = time2.post_id
				AND
				   $wpdb->posts.ID = client.post_id


				AND $wpdb->posts.post_type = 'ga_appointments'
				AND $wpdb->posts.post_status IN ('completed', 'publish', 'payment', 'pending')

				AND app_date.meta_key   = 'ga_appointment_date'
				AND app_date.meta_value = %s

				AND provider.meta_key   = 'ga_appointment_provider'
				AND provider.meta_value = %s

				AND time1.meta_key = 'ga_appointment_time_end'
				AND time1.meta_value > %s

				AND time2.meta_key = 'ga_appointment_time'
				AND time2.meta_value < %s

				AND client.meta_key = 'ga_appointment_new_client'
				AND (client.meta_value REGEXP '\"email\";s:[1-9]+:\"{$email_value}\"'
				OR client.meta_value REGEXP '\"phone\";s:[1-9]+:\"{$phone_value}\"')
			";

			$wpdb->query('SET SQL_BIG_SELECTS = 1');
			$sql_prepare  = $wpdb->prepare($querystr, $date, $provider_id, $slot_start, $slot_end);
			$appointments = $wpdb->get_results( $sql_prepare, ARRAY_A );

			if ( count($appointments) > 0 ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Client Booked Date Slot
		 * @ $form array
		 * @ $service_id
		 * @ $provider_id
		 * @ $dateTime
		 */
		private function client_booked_date_slot( $form, $service_id, $provider_id, $dateTime ) {
			$date = $dateTime->format("Y-m-j");

			// Prevent Double Bookings
			$double_bookings = ga_get_service_double_bookings($service_id);
			if( $double_bookings == 'no' ) {
				return false;
			}

			// Client Booked Date Slot
			$email_value  = $this->email_field_value( $form );
			$phone_value  = $this->phone_field_value( $form );

			global $wpdb;
			$querystr = "SELECT $wpdb->posts.ID
				FROM
				   $wpdb->posts,
				   $wpdb->postmeta AS app_date,
				   $wpdb->postmeta AS provider,
				   $wpdb->postmeta AS client
				WHERE
				   $wpdb->posts.ID = app_date.post_id
				AND
				   $wpdb->posts.ID = provider.post_id
				AND
				   $wpdb->posts.ID = client.post_id


				AND $wpdb->posts.post_type = 'ga_appointments'
				AND $wpdb->posts.post_status IN ('completed', 'publish', 'payment', 'pending')

				AND app_date.meta_key   = 'ga_appointment_date'
				AND app_date.meta_value = %s

				AND provider.meta_key   = 'ga_appointment_provider'
				AND provider.meta_value = %s

				AND client.meta_key = 'ga_appointment_new_client'
				AND (client.meta_value REGEXP '\"email\";s:[1-9]+:\"{$email_value}\"'
				OR client.meta_value REGEXP '\"phone\";s:[1-9]+:\"{$phone_value}\"')
			";

			$wpdb->query('SET SQL_BIG_SELECTS = 1');
			$sql_prepare  = $wpdb->prepare($querystr, $date, $provider_id);
			$appointments = $wpdb->get_results( $sql_prepare, ARRAY_A );

			if ( count($appointments) > 0 ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Client Max Bookings
		 * @ $form array
		 * @ $service_id
		 * @ $dateTime
		 * @ $bookings count
		 */
		private function client_max_bookings( $form, $service_id, $dateTime, $bookings ) {
			$date = $dateTime->format("Y-m-j");
			$max_bookings = ga_get_service_max_bookings($service_id);

			// Client Max Bookings
			$email_value  = $this->email_field_value( $form );
			$phone_value  = $this->phone_field_value( $form );

			global $wpdb;
			$querystr = "SELECT $wpdb->posts.ID
				FROM
				   $wpdb->posts,
				   $wpdb->postmeta AS app_date,
				   $wpdb->postmeta AS service,
				   $wpdb->postmeta AS client
				WHERE
				   $wpdb->posts.ID = app_date.post_id
				AND
				   $wpdb->posts.ID = service.post_id
				AND
				   $wpdb->posts.ID = client.post_id


				AND $wpdb->posts.post_type = 'ga_appointments'
				AND $wpdb->posts.post_status IN ('completed', 'publish', 'payment', 'pending')

				AND app_date.meta_key   = 'ga_appointment_date'
				AND app_date.meta_value = %s

				AND service.meta_key   = 'ga_appointment_service'
				AND service.meta_value = %s

				AND client.meta_key = 'ga_appointment_new_client'
				AND (client.meta_value REGEXP '\"email\";s:[1-9]+:\"{$email_value}\"'
				OR client.meta_value REGEXP '\"phone\";s:[1-9]+:\"{$phone_value}\"')
			";

			$wpdb->query('SET SQL_BIG_SELECTS = 1');
			$sql_prepare  = $wpdb->prepare($querystr, $date, $service_id);
			$appointments = $wpdb->get_results( $sql_prepare, ARRAY_A );

			$bookingDates = array();
			foreach( $bookings as $booking ) {
				$bookingDates[] = is_array($booking) ? sprintf( '%s', $booking['date'] ) : $booking;
			}	
			
			$found_dates = 0;
			if( $matches = preg_grep("/^{$date}/i", $bookingDates) ) {
				$found_dates = count($matches);
			}

			$post_count = count($appointments) + $found_dates;
			if ( $post_count > $max_bookings ) {
				return true;
			}
			return false;
		}

		/**
		* Save value or save single entry edit
		*/
		public function get_value_save_entry( $value, $form, $input_name, $entry_id, $entry ) {
			// GF Admin Entry Edit
			if( is_admin() ) {
				return $value;
			}
			
			$value = gf_get_field_type_value( $form, 'appointment_calendar' );
			
			$form_id = absint( $form['id'] );
			// Check if services field exists
			if( gf_field_type_exists($form, 'appointment_services') && 'ga_services' == get_post_type( gf_get_field_type_value($form, 'appointment_services') ) ) {
				return $this->TranslateDateToText($value, $form_id, true, $entry_id);
			} else {
				return '';
			}
		}

		/**
		* Show date on entry single
		*/
		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
			$fieldId = $this->id;
			$inputStr = 'input_' . $fieldId;
			if ( ! empty( $_POST[ 'is_submit_' . $this->formId ] ) ) {
				$value = rgpost($inputStr);
				return $this->TranslateDateToText($value, $this->formId);
				
			}
			return $value;
		}

		/**
		* Merge tag, on notifications, confirmations
		*/
		public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
			$dates = explode('&lt;br&gt', $value);

			if( count($dates) > 1 ) {
				return implode(', ', $dates);
			} elseif( count($dates) == 1 ) {
				return reset( $dates );
			} else {
				return '';
			}
		}

		public function get_form_editor_inline_script_on_page_render() {
			return "
			gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
				if (type == 'appointment_calendar') {
					if (GetFieldsByType(['appointment_calendar']).length > 0) {
						alert(" . json_encode( esc_html__( 'Only one Booking Calendar field can be added to the form', 'gravityformscoupons' ) ) . ");
						return false;
					}
				}
				return canFieldBeAdded;
			});";
		}

		/**
		 * Get timestamp from valid date
		 */
		public function get_date_timestamp( $date_time ) {
			$date = new DateTime( $date_time, new DateTimeZone( ga_time_zone() ) );
			return $date->getTimestamp();
		}


		/**
		* Format the entry value for display on the entries list page.
		* Return a value that's safe to display on the page.
		*/
		public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		 	echo $value;
		}
		
		public function save_entry( $entry_value, $entry_id ) {
			gform_update_meta( $entry_id, $this->id, $entry_value );
		}	

		private function TranslateDateToText($value, $formId, $saveOnReturn = false, $entryId = ''){
			$date = '';
			$time = '';
			$slotID = '';

			if ( is_array( $value ) ) {
				$date      = isset($value['date']) ? $value['date'] : $date;
				$timeArray = isset($value['time']) ? explode( "-", $value['time'] ) : array();
				$time      = reset( $timeArray );
				$slotID    = isset($value['time']) ? $value['time'] : $slotID;
			}

			$form = GFAPI::get_form( $formId );

			// Service & Provider ID
			$service_id   = gf_get_field_type_value( $form, 'appointment_services' );
			$provider_id  = gf_field_type_exists($form, 'appointment_providers')
							&& 'ga_providers' == get_post_type(gf_get_field_type_value($form, 'appointment_providers'))
							? gf_get_field_type_value($form, 'appointment_providers')
							: 0;
			if( ga_get_provider_id($service_id) && $provider_id == 0 ) {
				$provider_id = ga_get_provider_id($service_id);
			}

			if( !class_exists('GA_Calendar') ) {
				require_once( ga_base_path . '/gf-fields/ga-calendar.php' );
			}

			// Time Format Display
			$time_display         = ga_service_time_format_display($service_id);

			// Service Mode
			$available_times_mode = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );

			// Service multiple slots
			$multiple_slots       = (string) get_post_meta( $service_id, 'ga_service_multiple_selection', true );

			// Get Bookings
			$bookings             = ga_get_multiple_bookings($value, $service_id, $provider_id);

			/**
			 * Multiple Bookings
			 */
			$booking_dates = array();
			if( $multiple_slots == 'yes' ) {
				if( count($bookings) > 0 ) {
					foreach ($bookings as $key => $booking) {
						$dateTime = new DateTime( sprintf( '%s %s', $booking['date'], $booking['time'] ), new DateTimeZone(ga_time_zone()) );

						// Translation Support
						if( $available_times_mode == 'no_slots' )  {
							$month = $dateTime->format('F');
							$day   = $dateTime->format('j');
							$year  = $dateTime->format('Y');
							$appointment_date = ga_get_form_translated_slots_date($formId, $month, $day, $year);
						} else {
							$month = $dateTime->format('F');
							$week  = $dateTime->format('l');
							$day   = $dateTime->format('j');
							$year  = $dateTime->format('Y');
							$_time = $dateTime->format($time_display);

							$appointment_date = ga_get_form_translated_date_time($formId, $month, $week, $day, $year, $_time);
						}

						$booking_dates[] = $appointment_date;

					}
					$return_value = implode("<br>", $booking_dates);
					if($saveOnReturn){
						$this->save_entry( $return_value, $entryId );
					}
					return $return_value;
				} else {
					if ($saveOnReturn) {
						$this->save_entry( '', $entryId );
					}
					return array();
				}
			} else {
				/**
				 * Single Booking
				 */
				// DATE
				$app_date            = (string) $date;
				$date                = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
				$app_date_text       = $date ? $date->format('l, F j Y') : '(Date not selected)';

				// Time
				$app_time            = $time;
				$time                = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
				$app_time_text       = $time ? $time->format('g:i a') : '(Time not selected)';

				// Translation Support
				if( $available_times_mode == 'no_slots' )  {
					if( $date ) {
						$month = $date->format('F');
						$day   = $date->format('j');
						$year  = $date->format('Y');
						$appointment_date = ga_get_form_translated_slots_date($formId, $month, $day, $year);
					} else {
						$appointment_date = $app_date_text;
					}
				} else {
					if( $date && $time ) {
						$month = $date->format('F');
						$week  = $date->format('l');
						$day   = $date->format('j');
						$year  = $date->format('Y');
						$_time = $time->format($time_display);

						$appointment_date = ga_get_form_translated_date_time($formId, $month, $week, $day, $year, $_time);
					} else {
						$appointment_date = "{$app_date_text} at {$app_time_text}";
					}
				}
			}

			$merge = apply_filters('ga_booking_merge_value', 'date_format');
			$entry_date = $merge == 'timestamp' && $date && $time ? $this->get_date_timestamp( "{$date->format('Y-m-j')} {$time->format('H:i')}" ) : $appointment_date;
			if($saveOnReturn){
				$this->save_entry( $entry_date, $entryId );
			}
			return $entry_date;
		}

	} // end class
	GF_Fields::register( new GF_Appointment_Booking_Calendar() );
} // end if
