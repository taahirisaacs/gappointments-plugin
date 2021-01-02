<?php
defined( 'ABSPATH' ) or exit; // Exit if accessed directly

new ga_appointment_shortcodes();
class ga_appointment_shortcodes {
	public $perPage = 8;
	public $statuses;
	public $page;
	public $offset;

	public function __construct() {
		add_shortcode( 'ga_appointments', array($this,'ga_appointments_shortcode') );
		add_shortcode( 'ga_provider_appointments', array($this,'ga_provider_appointments_shortcode') );

		$this->page = isset($_GET['ga_page']) ? (int) $_GET['ga_page'] : 1;
		$this->offset = ( $this->page - 1 ) * $this->perPage;

		if( isset( $_GET['ga_filter'] ) && $_GET['ga_filter'] == 'past' ) {
			$this->statuses = $this->ga_past_statuses();
		} else {
			$this->statuses = $this->ga_upcoming_statuses();
		}

	}

	public function ga_upcoming_statuses() {
		return array('publish' => 'Confirmed', 'payment' => 'Pending Payment', 'pending' => 'Pending');
	}

	public function ga_past_statuses() {
		return array('completed' => 'Completed', 'cancelled' => 'Cancelled');
	}

	public function get_user_id() {
		$current_user = wp_get_current_user();
		return $current_user->ID;
	}

	public function ga_filters() {
		$upcoming_active = isset( $_GET['ga_filter'] ) && $_GET['ga_filter'] == 'upcoming' ? ' active' : '';
		$past_active = isset( $_GET['ga_filter'] ) && $_GET['ga_filter'] == 'past' ? ' active' : '';

		if( !isset( $_GET['ga_filter'] ) ) {
			$upcoming_active = ' active';
		}

		$lang_upcoming_tab = ga_get_translated_data('upcoming');
		$lang_past_tab     = ga_get_translated_data('past');

		$out = '<div class="thead">';
			$out .= '<div class="th'.$upcoming_active.'"><a href="?ga_filter=upcoming">'.$lang_upcoming_tab.'</a></div>';
			$out .= '<div class="th'.$past_active.'"><a href="?ga_filter=past">'.$lang_past_tab.'</a></div>';
		$out .= '</div>';

		return $out;
	}

	public function show_links() {
		$options    = get_option( 'ga_appointments_add_to_calendar' );
		$show_links = isset($options['show_links']) ? $options['show_links'] : 'yes';
		if( $show_links == 'yes' ) {
			return true;
		}
		return false;
	}


	public function generate_calendar_links($appointment_id, $title) {
		$options    = get_option( 'ga_appointments_add_to_calendar' );
		$location   = isset($options['location']) && !empty($options['location']) ? $options['location'] : get_bloginfo();

		$post_status       = get_post_status( $appointment_id );
		$valid_post_status = array('publish', 'pending');

		if( in_array($post_status, $valid_post_status) ) {
			# valid
		} else {
			return '';
		}

		// date & time
		$app_date = $this->add_to_cal_date( $appointment_id );
		$app_time = $this->add_to_cal_time( $appointment_id );

		if( ga_valid_date_format($app_date) && ga_valid_time_format($app_time) ) {
			# valid date & time
			$timezone      = ga_time_zone();
			$duration      = (int) get_post_meta( $appointment_id, 'ga_appointment_duration', true );

			$start_date    = new DateTime( "{$app_date} {$app_time}", new DateTimeZone($timezone) ); // Appointment Time
			$interval      = new DateInterval("PT" . $duration . "M");
			$end_date      = clone $start_date;
			$end_date      = $end_date->add( $interval );

			$time_start = $start_date->format('Y-m-d H:i');
			$time_end   = $end_date->format('Y-m-d H:i');
		} else {
			return '';
		}

		//print_r( "{$start_date->format('H:i')} - {$end_date->format('H:i')}" );

		// Include link generator class
		if( !class_exists('ga_add_to_calendar') ) {
			require_once( 'add_to_calendar.php' );
		}

		// Date Slots Mode
		$service_id = (int) get_post_meta( $appointment_id, 'ga_appointment_service', true );
		$available_times_mode = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );
		if( $service_id && $available_times_mode == 'no_slots' )  {
			$time_start = $start_date->format('Y-m-d') . '00:00';
			$time_end   = $start_date->format('Y-m-d') . '23:59';
		}

		// Link Generator Options
		$from        = DateTime::createFromFormat('Y-m-d H:i', $time_start);
		$to          = DateTime::createFromFormat('Y-m-d H:i', $time_end);
		$description = $start_date->format('l, F j Y \a\t g:i a');
		if( $service_id && $available_times_mode == 'no_slots' )  {
			$description = $start_date->format('l, F j Y');
		}

		// Generate Links
		$link = ga_add_to_calendar::create($title, $from, $to)->description($description)->address($location);

		$add_to_apple    = isset( $options['apple'] )   ? $options['apple'] : 'yes';
		$add_to_google   = isset( $options['google'] )  ? $options['google'] : 'yes';
		$add_to_outlook  = isset( $options['outlook'] ) ? $options['outlook'] : 'yes';
		$add_to_yahoo    = isset( $options['yahoo'] )   ? $options['yahoo'] : 'yes';

		$links = array();

		if( $add_to_apple == 'yes' ) {
			$text = ga_get_translated_data('apple_calendar');
			$links[$text]['link']   = $link->ics();
			$links[$text]['target'] = 'target="_self"';
		}

		if( $add_to_google == 'yes' ) {
			$text = ga_get_translated_data('google_calendar');
			$links[$text]['link'] = $link->google();
			$links[$text]['target'] = 'target="_blank"';
		}

		if( $add_to_outlook == 'yes' ) {
			$text = ga_get_translated_data('outlook_calendar');
			$links[$text]['link']   = $link->outlook();
			$links[$text]['target'] = 'target="_blank"';
		}

		if( $add_to_yahoo == 'yes' ) {
			$text = ga_get_translated_data('yahoo_calendar');
			$links[$text]['link'] = $link->yahoo();
			$links[$text]['target'] = 'target="_blank"';
		}

		if( count($links) > 0 ) {
			# we have links
		} else {
			return '';
		}


		$out = '<div class="appointment-add-to-calendar">';
			$out .= '<div class="add-to-calendar-title"><span></span> '.ga_get_translated_data('add_to_calendar').'</div>';
			$out .= '<div class="ga_add_to_calendar_links">';
			foreach($links as $cal => $link) {
				$_blank = isset($link['target']) ? $link['target'] : '';
				$out .= '<a '.$_blank.' href="'.$link['link'].'">'.$cal.'</a>';
			}
			$out .= '</div>';
		$out .= '</div>';

		return $out;
	}



	/*
	 * Date add to calendar
	 */
	public function add_to_cal_date( $appointment_id ) {
		$app_date = (string) get_post_meta( $appointment_id, 'ga_appointment_date', true );
		$date     = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
		return $date ? $date->format('Y-m-j') : false;
	}

	/*
	 * Time add to calendar
	 */
	public function add_to_cal_time( $appointment_id ) {
		$app_time = (string) get_post_meta( $appointment_id, 'ga_appointment_time', true );
		$time     = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
		return $time ? $time->format('H:i') : '';
	}


	public function ga_provider_id( $appointment_id ) {
		return (int) get_post_meta( $appointment_id, 'ga_appointment_provider', true );
	}

	public function ga_provider_name( $provider_id ) {
		return 'ga_providers' == get_post_type($provider_id) ? esc_html( get_the_title($provider_id) ) : '';
	}

	public function ga_provider_email( $provider_id ) {

		if( $provider_id == 0 ) {
			return false;
		}


		$user_assigned = get_post_meta($provider_id, 'ga_provider_user', true);

		if( $user_info = get_userdata( $user_assigned ) ) {
			return $user_info->user_email;
		} else {
			return false;
		}

	}


	public function ga_service_id( $appointment_id ) {
		return (int) get_post_meta( $appointment_id, 'ga_appointment_service', true );
	}

	public function ga_service_time_format( $appointment_id ){

	    return (string) get_post_meta( $this->ga_service_id($appointment_id), 'ga_service_time_format', true);
    }

	public function ga_service_name( $service_id ) {
		return 'ga_services' == get_post_type($service_id) ? esc_html( get_the_title( $service_id ) ) : '(Not defined)';
	}

	public function ga_client_id( $appointment_id ) {
		return get_post_meta( $appointment_id, 'ga_appointment_client', true );
	}

	public function ga_client_name( $appointment_id, $client_id ) {

		if( $client_id == 'new_client') {
			$new_client = get_post_meta( $appointment_id, 'ga_appointment_new_client', true );
			$name = isset( $new_client['name'] ) && !empty( $new_client['name'] ) ? $new_client['name'] : '';
			return $name;
		} elseif( $user_info = get_userdata($client_id) ) {
			$new_client = get_post_meta( $appointment_id, 'ga_appointment_new_client', true );
			$name = isset( $new_client['name'] ) && !empty( $new_client['name'] ) ? $new_client['name'] : $user_info->user_nicename;
			return $name;
		} else {
			return '';
		}

	}

	public function ga_client_email( $appointment_id, $client_id ) {

		if( $client_id == 'new_client') {

			$new_client = get_post_meta( $appointment_id, 'ga_appointment_new_client', true );
			$email = isset( $new_client['email'] ) && !empty( $new_client['email'] ) ? $new_client['email'] : '';
			return $email;

		} elseif( $user_info = get_userdata($client_id) ) {
			$new_client = get_post_meta( $appointment_id, 'ga_appointment_new_client', true );
			$email = isset( $new_client['email'] ) && !empty( $new_client['email'] ) ? $new_client['email'] : $user_info->user_email;
			return $email;
		} else {
			return '';
		}

	}

	public function ga_client_phone( $appointment_id, $client_id ) {
		$new_client = get_post_meta( $appointment_id, 'ga_appointment_new_client', true ); // array
		$phone      = isset( $new_client['phone'] ) && !empty( $new_client['phone'] ) ? $new_client['phone'] : '';

		return $phone;
	}

	public function ga_duration( $appointment_id ) {
		$duration = (int) get_post_meta( $appointment_id, 'ga_appointment_duration', true );
		return convertToHoursMins($duration);
	}

	public function ga_date( $appointment_id, $translation = true ) {
		$app_date = (string) get_post_meta( $appointment_id, 'ga_appointment_date', true );
		$date     = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;

		// Translation Support
		if( $date )  {
			$month = $date->format('F');
			$day   = $date->format('j');
			$year  = $date->format('Y');
			return $translation ? ga_get_form_translated_slots_date($form = false, $month, $day, $year) : $date->format('Y-m-d');
		} else {
			return $translation ? '(Date not defined)' : null;
		}

	}

	public function ga_time( $appointment_id, $translation = true, $start_time = true ) {
        $meta_key = $start_time ? 'ga_appointment_time' : 'ga_appointment_time_end';
        $app_time = (string) get_post_meta( $appointment_id, $meta_key, true );
		$time     = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
        $time_format = $this->ga_service_time_format($appointment_id);
		// Translation Support
		if( $time ) {
		    if( $time_format == "12h" )
			    return $translation ? ga_get_form_translated_am_pm($form = false, $time->format('g:i a')) : $time->format('H:i');
		    else
		        return $translation ? $time->format('G:i') : $time->format('H:i');
		} else {
			return $translation ? '(Time not defined)': null;
		}
	}

	public function ga_status_class( $post_status ) {
		switch ( $post_status ) {
			case 'completed':
				$class = 'status-green';
				break;
			case 'publish': // confirmed
				$class = 'status-green';
				break;
			case 'payment':
				$class = 'status-yellow';
				break;
			case 'pending':
				$class = 'status-yellow';
				break;
			case 'cancelled':
				$class = 'status-red';
				break;
			case 'draft':
				$class = 'status-yellow';
				break;
			default:
				$class = 'status-red';
		}

		return $class;
	}

	public function ga_provider_initials( $provider_id ) {
		$out = '';

		$provider_name = (string) get_the_title( $provider_id );

		if( !empty($provider_name) ) {

			$initials = preg_split("/[\s,_-]+/", $provider_name);
			$initials = array_slice($initials, 0, 2); // 2

			foreach($initials as $initial) { $out .= $initial[0]; }
		}

		return strtoupper($out);
	}

	private function ga_query_appointments($meta_key, $meta_value) {
		$appointments = new WP_QUERY(
			array(
				'post_type'         => 'ga_appointments',
				'posts_per_page'    => $this->perPage,
				'offset'            => $this->offset,
				'post_status'       => array_keys( $this->statuses ),
				'meta_query'        => array( 'relation' => 'AND',
					array( 'key' => $meta_key, 'value' => $meta_value ),
					'date'   => array( 'key' => 'ga_appointment_date', 'type' => 'DATE' ),
					'time'   => array( 'key' => 'ga_appointment_time', 'compare' => 'BETWEEN', 'type' => 'TIME'  ), // This array needs to be first to displaying the correct order
				),
				'orderby'    => array(
					'date'   => 'ASC',
					'time'   => 'ASC',
				),


			)
		);

		return $appointments;
	}

	/**
	 * Appointments Numeric Pagination
	 */
	public function ga_numeric_posts_nav( $appointments ) {

		$paged = $this->page;
		$max = intval( $appointments->max_num_pages );

		/** Stop execution if there's only 1 page */
		if( $max <= 1 ) {
			return;
		}

		/**	Add current page to the array */
		if ( $paged >= 1 )
			$links[] = $paged;

		/**	Add the pages around the current page to the array */
		if ( $paged >= 3 ) {
			$links[] = $paged - 1;
			$links[] = $paged - 2;
		}

		if ( ( $paged + 2 ) <= $max ) {
			$links[] = $paged + 2;
			$links[] = $paged + 1;
		}

		$out = '';

		/**	Previous Post Link */
		if ( $paged > 1 ) {
			$out = '<a href="' . esc_url( add_query_arg( array('ga_page' => $paged - 1) ) ) . '">&laquo;</a>';
		}

		/**	Link to first page, plus ellipses if necessary */
		if ( ! in_array( 1, $links ) ) {
			$class = 1 == $paged ? ' class="active"' : '';
			$out .= '<a '.$class.' href="' . esc_url( add_query_arg( array('ga_page' => 1) ) ) . '">1</a>';

			if ( ! in_array( 2, $links ) )
				$out .= '<a>…</a>';
		}

		/**	Link to current page, plus 2 pages in either direction if necessary */
		sort( $links );
		foreach ( (array) $links as $link ) {
			$class = $paged == $link ? ' class="active"' : '';
			$out .= '<a '.$class.' href="' . esc_url( add_query_arg( array('ga_page' => $link) ) ) . '">'.$link.'</a>';
		}


		/**	Link to last page, plus ellipses if necessary */
		if ( ! in_array( $max, $links ) ) {
			if ( ! in_array( $max - 1, $links ) )
				$out .= '<a>…</a>';

			$class = $paged == $max ? ' class="active"' : '';
			$out .= '<a '.$class.' href="' . esc_url( add_query_arg( array('ga_page' => $max) ) ) . '">'.$max.'</a>';
		}

		/**	Next Post Link */
		if ( $paged < $max ) {
			$out .= '<a href="' . esc_url( add_query_arg( array('ga_page' => $paged + 1) ) ) . '">&raquo;</a>';
		}
		return $out;
	}

	/**
	 * Client Appointments Display
	 */
	public function ga_appointments_shortcode($atts) {
		if( !is_user_logged_in() ) {
			return;
		}
		$a = shortcode_atts([ 'pagelen' => $this->perPage ], $atts);
		$attrLength = ctype_digit($a['pagelen']) ? intval($a['pagelen']) : null;
		if($attrLength != $this->perPage && is_int($attrLength)){
			$this->perPage = $a['pagelen'];
		}
		$user_id = $this->get_user_id();

		$appointments = $this->ga_query_appointments( 'ga_appointment_client', $user_id );

		$options = get_option( 'ga_appointments_policies' );

		$user_set_appointment_pending = isset( $options['user_set_appointment_pending'] ) ? $options['user_set_appointment_pending'] : 'no';

		$out = '<div class="appointments-table-wrapper">';
		$out .= $this->ga_filters();

		/**
		 * Before client appointments loop
		 * @param array [ client_id, appointments ] holds the client id and it's appointments
		 */
		do_action( 'gAppointments/appointments/client/before_loop', [
			'client_id'    => $user_id,
			'appointments' => $appointments
		]);

		if ( $appointments->have_posts() ) {
			$out .= '<div class="appointments-table">';

			while ( $appointments->have_posts() ) : $appointments->the_post();
				// Provider id & name
				$provider_id    = $this->ga_provider_id( get_the_id() );
				$provider_name  = $this->ga_provider_name( $provider_id );
				$provider_email = $this->ga_provider_email( $provider_id ) ? '<div class="appointment-email"><i class="dashicons dashicons-email"></i> '. strtolower($this->ga_provider_email( $provider_id )) .'</div>' : '';

				// Service id & name
				$service_id    = $this->ga_service_id( get_the_id() );
				$service_name  = $this->ga_service_name( $service_id );

				// Duration
				$duration = $this->ga_duration( get_the_id() );

				/**
				 *  Duration filter
				 *  @param integer $duration duration in minutes
				*/
				$duration = apply_filters( 'gappointments/appointments/duration', $duration );

				// Appointment title
				//$appointment_title = ucfirst($service_name) . $provider_name;
				$appointment_title = ga_get_translated_client_service($form = false, ucfirst($service_name), $provider_name);

				/**
				 *  Appointment filter
				 *  @param string $appointment_title appointment title
				 *  @param string $service_name      service name
				*/
				$appointment_title = apply_filters( 'gappointments/appointments/appointment_title', $appointment_title, $service_name );

				// Gcal link
				$cal_links = $this->show_links() ? $this->generate_calendar_links( get_the_id(), $appointment_title ) : '';

				/**
				 *  Appointment links filter
				 *  @param string $cal_links    generated calender links
				 *  @param string $service_name service name
				*/
				$cal_links = apply_filters( 'gappointments/appointments/cal_links', $cal_links, $service_name );

				// Date & Time
				$app_date = $this->ga_date( get_the_id() );
				$app_time = $this->ga_time( get_the_id() );

				// Date Slots Mode
				if( $available_times_mode = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true) == 'no_slots' ) {
					$app_time = ga_get_translated_data('bookable_date');
				}

				// Post Status
				$post_status = get_post_status( get_the_id() );
				$app_status = array_key_exists($post_status, $this->statuses ) ? $post_status : 'failed';
				$app_status_name = array_key_exists($post_status, $this->statuses ) ? ga_get_translated_data('status_' . $post_status) : ga_get_translated_data('status_failed');

				/**
				 *  Appointment status filter
				 *  @param string $app_status_name appointment status name
				*/
				$app_status_name = apply_filters( 'gappointments/appointments/app_status_name', $app_status_name );

				// Cancel button
				// Allow customers to cancel appointments
				$actions = in_array($app_status, array('publish', 'pending')) ? $this->cancellation_notice() : '';

				//Edit button
                //Allow customers to edit appointments
                $actions .= in_array($app_status, array('publish', 'pending')) ? $this->reschedule_appointment() : '';

				// Status Class
				$class = $this->ga_status_class( $app_status );

				/**
				 *  Appointment row classes filter
				 *  @param string $class row class
				*/
				$class = apply_filters( 'gappointments/appointments/class', $class );

				// Select status
				// Allow user to change status to pending if setting is on else return just status
				$def_appointment_status = true;
				if($user_set_appointment_pending === 'yes'){
					if($post_status === 'publish'){
						$def_appointment_status = false;
						$title = ga_get_translated_data('user_set_app_pending');
						$button_text = ga_get_translated_data('user_set_app_pending_btn_yes');
						//$set_to_pending = ga_get_translated_data('set_to_pending');
						$appointment_status = '<span class="appointment_status_change_span appointment-'.strtolower($class).'">'.ucfirst($app_status_name).'</span>';
						/*$appointment_status.= '<select id="sel-'.get_the_id().'" button-text="'.$button_text.'" title="'.$title.'" app-id="'.get_the_id().'" class="user_appointment_status_set user_appointment_status_set_hide">
											   <option value="publish" disabled selected>'.ucfirst($app_status_name).'</option>
											   <option value="pending">'.ga_get_translated_data("status_pending").'</option>
										   </select>';*/
						$appointment_status.='<span id="sel-'.get_the_id().'" button-text="'.$button_text.'" title="'.$title.'" app-id="'.get_the_id().'"
						  title="Change status" class="appointment-status-yellow user_appointment_status_set">'.ucfirst($button_text).'</span>';
					}
				}
				if($def_appointment_status){
					$appointment_status = '<span class="appointment-'.strtolower($class).'">'.ucfirst($app_status_name).'</span>';
				}
				$out .= '<div class="tr '.$class.'">
							<div class="td">
								<div class="appointment_date_time">
									<span class="appointment-time">'.$app_time.'</span>
									<span class="appointment-date">'.$app_date.'</span>
								</div>

								<div class="appointment_service_provider">
									<div class="appointment-title">'. $appointment_title .'</div>
									<div class="appointment-duration"><i class="dashicons dashicons-clock"></i> '.$duration.'</div>
									'. $provider_email
									 . $cal_links . '
									<div class="appointment-status">'.$appointment_status.$actions.'</div>
								</div>
							</div>
						</div>';
			endwhile;

			/**
			 * After client appointments loop
			 * @param integer $user_id holds the client id.
			 */
			do_action( 'gAppointments/appointments/client/after_loop', $user_id );

			wp_reset_postdata( );

			$out .= '<div class="ga_pagination">' . $this->ga_numeric_posts_nav( $appointments ) . '</div>';
			$out .= '</div>';

		} else {
			$out .= '<div class="no-appointments">'.ga_get_translated_data('no_appointments').'</div>';
		}

		$out .= '</div>';
		$out .= '<div id="ga_appointment_modal"></div>';

		return $out;

	}

	public function ga_provider_schedule( $provider_id ) {
		$own_schedule = get_post_meta( $provider_id, 'ga_provider_calendar', true );

		if( $own_schedule == 'on' ) {
			$out = '<div class="ga-manage-schedule">('. esc_html(ga_get_translated_data('manage_text')) .')</div>';
			$out .= '<div id="ga_schedule_model" class="ga_modal_bg ga-hidden">
						<div class="ga_dialog">
							<div class="ga_dialog_wrapper">
								<div class="ga_modal_wrapper">
									<div class="ga_modal_container"><span title="Close" class="ga_close"></span>
										<h3 class="modal-title">'. esc_html(ga_get_translated_data('manage_text')) .'</h3>
										<div class="hr"></div>
										<form id="provider-schedule-update" action="" method="post" class="clearfix">
										'. ga_provider_schedule_form( $provider_id ) .'
										</form>
										<div class="modal_overlay"></div>
									</div>
								</div>
							</div>
						</div>
					</div>';
			return $out;
		}
		return '';
	}

	/**
	 * Provider Appointments Display
	 * @return string $out a generated shortcode
	 */
	public function ga_provider_appointments_shortcode( $atts ) {


		wp_enqueue_script( 'jquery-ui-datepicker' ); 	// Datepicker UI
		wp_enqueue_style( 'jquery-ui-datepicker-base-theme', GA_ASSETS_URL . 'datepicker.css', false, '1.2.3' ); // Datepicker Css

		if( !is_user_logged_in( ) )
			return;

		$a = shortcode_atts([ 'pagelen' => $this->perPage ], $atts);
		$attrLength = ctype_digit($a['pagelen']) ? intval($a['pagelen']) : null;
		if($attrLength != $this->perPage && is_int($attrLength)){
			$this->perPage = $a['pagelen'];
		}
		$user_id = $this -> get_user_id( );
		$out     = '';

		$providers = ga_provider_query( $user_id );
		if( $providers->post_count == 1 ) {
			$provider_id = $providers -> post -> ID;

			$out .= '<div class="avatar-circle-wrapper"><div class="avatar-circle"><span class="initials">'. $this->ga_provider_initials( $provider_id ) .'</span></div><div class="provider-name">'. $this->ga_provider_name( $provider_id ). '</div></div>';
			$out .= '<div id="provider-schedule">' . $this->ga_provider_schedule( $provider_id ) . '</div>'; // Provider Schedule Edit

			$out .= '<div class="appointments-table-wrapper">';
			$out .= $this->ga_filters();

			$appointments = $this->ga_query_appointments( 'ga_appointment_provider', $provider_id );

			/**
			 *  Hook before provider appointment loop
			 *  @param integer $provider_id  the providers id
			 *  @param object  $appointments provider appointments
			*/
			do_action( 'gappointments/appointments/provider/before_loop', [ $provider_id, $appointments ] );

			 if ( $appointments->have_posts() ) {
                 $out .= '<div class="appointments-table">';

                 while ($appointments->have_posts()) {

                     $appointments->the_post();
                     // Provider id & name
                     $provider_id = $this->ga_provider_id(get_the_id());
                     $provider_name = $this->ga_provider_name($provider_id);

                     // Service id & name
                     $service_id = $this->ga_service_id(get_the_id());
                     $service_name = $this->ga_service_name($service_id);

                     // Client id & name
                     $client_id = $this->ga_client_id(get_the_id());

                     // Client Name
                     $client_name = $this->ga_client_name(get_the_id(), $client_id);

                     /**
                      *  Client name filter
                      * @param string $client_name client name
                      * @param integer $client_id client id
                      */
                     $client_name = apply_filters('gappointments/appointments/client_name', $client_name, $client_id);

                     // Client provider Email
                     $client_email = $this->ga_client_email(get_the_id(), $client_id);
                     $client_email = !empty($client_email) ? '<div class="appointment-email"><i class="dashicons dashicons-email"></i> ' . strtolower($client_email) . '</div>' : '';

                     /**
                      *  Client email filter
                      * @param string $client_email client email
                      * @param integer $client_id client id
                      */
                     $client_email = apply_filters('gappointments/appointments/client_email', $client_email, $client_id);

                     // Client Phone
                     $client_phone = $this->ga_client_phone(get_the_id(), $client_id);
                     $client_phone = !empty($client_phone) ? '<div class="appointment-phone"><i class="dashicons dashicons-phone"></i> ' . strtolower($client_phone) . '</div>' : '';

                     /**
                      *  Client phone filter
                      * @param string $client_phone client phone
                      * @param integer $client_id client id
                      */
                     $client_phone = apply_filters('gappointments/appointments/client_phone', $client_phone, $client_id);

                     // Duration
                     $duration = $this->ga_duration(get_the_id());

                     /**
                      *  Duration filter
                      * @param integer $duration duration in minutes
                      */
                     $duration = apply_filters('gappointments/appointments/duration', $duration);

                     // Appointment  title
                     $appointment_title = ga_get_translated_provider_service($form = false, ucfirst($service_name), $client_name);

                     /**
                      *  Appointment title filter
                      * @param string $appointment_title appointment title
                      * @param string $service_name service name
                      */
                     $appointment_title = apply_filters('gappointments/appointments/appointment_title', $appointment_title, $service_name);

                     // Gcal link
                     $cal_links = $this->show_links() ? $this->generate_calendar_links(get_the_id(), $appointment_title) : '';

                     /**
                      *  Appointment links filter
                      * @param string $cal_links generated calender links
                      * @param string $service_name service name
                      */
                     $cal_links = apply_filters('gappointments/appointments/cal_links', $cal_links, $service_name);

                     // Date & Time
                     $app_date = $this->ga_date(get_the_id());
                     $app_time = $this->ga_time(get_the_id());

                     // Date Slots Mode
                     if ($available_times_mode = (string)get_post_meta($service_id, 'ga_service_available_times_mode', true) == 'no_slots') {
                         $app_time = ga_get_translated_data('bookable_date');
                     }

                     // Post provider Status
                     $post_status = get_post_status(get_the_id());
                     $app_status = array_key_exists($post_status, $this->statuses) ? $post_status : 'failed';
                     $app_status_name = array_key_exists($post_status, $this->statuses) ? ga_get_translated_data('status_' . $post_status) : ga_get_translated_data('status_failed');

                     /**
                      *  Appointment status filter
                      * @param string $app_status_name appointment status name
                      */
                     $app_status_name = apply_filters('gappointments/appointments/app_status_name', $app_status_name);

                     $actions = $app_status == 'pending' ? $this->provider_confirms_appointments() : '';
                     $actions .= in_array($app_status, array('publish', 'pending')) ? $this->provider_cancellation_notice() : '';

                     // Status Class
                     $class = $this->ga_status_class($post_status);

                     /**
                      *  Appointment row classes filter
                      * @param string $class row class
                      */
                     $class = apply_filters('gappointments/appointments/class', $class);

                     /**
                      * Appointment provider additional cells
                      * @param integer $client_id the id of the client
                      * @param integer $service_id the id of the service
                      * @param integer $provider_id the id of the provider
                      */
                     $custom_row = apply_filters('gappointments/appointments/provider/custom_row', '', $client_id, $service_id, $provider_id);

                     $out .= '<div class="tr ' . $class . '">
								<div class="td">
									<div class="appointment_date_time">
										<span class="appointment-time">' . $app_time . '</span>
										<span class="appointment-date">' . $app_date . '</span>
									</div>

									<div class="appointment_service_provider">
										<div class="appointment-title">' . $appointment_title . '</div>
										<div class="appointment-duration"><i class="dashicons dashicons-clock"></i> ' . $duration . '</div>
										' . $client_email . '
										' . $client_phone . '
										' . $cal_links . '
										' . $custom_row . '
										<div class="appointment-status"><span class="appointment-' . strtolower($class) . '">' . ucfirst($app_status_name) . '</span>' . $actions . '</div>
									</div>
								</div>
							</div>';
                 }

				/**
				 * Hook after provider appointment loop
				 * @param integer $provider_id  the id of the provider
				 * @param object  $appointments the object of appointments
				 */
				do_action( 'gappointments/appointments/provider/after_loop', [ $provider_id, $appointments ] );

				wp_reset_postdata( );

				$out .= '<div class="ga_pagination">' . $this -> ga_numeric_posts_nav( $appointments ) . '</div>';
				$out .= '</div>';

			} else {
				$out .= '<div class="no-appointments">'.ga_get_translated_data( 'no_appointments' ).'</div>';
			}

			$out .= '</div>';
			$out .= '<div id="ga_appointment_modal"></div>';

		}


		return $out;

	}

	/**
	 * Client Cancels Appointments
	 */
	public function cancellation_notice() {
		// Allow Provider To Cancel Appointments
		$ga_policies = get_option('ga_appointments_policies');
		$cancellation_notice = isset( $ga_policies['cancellation_notice'] ) ? $ga_policies['cancellation_notice'] : 'no';

		if( $cancellation_notice == 'yes') {
			return '<a href="#" app-id="'.get_the_id().'" class="appointment-action" optional_text="'.esc_html(ga_get_translated_data('optional_text')).'" title="'.esc_html(ga_get_translated_data('cancel_text')).'">'.esc_html(ga_get_translated_data('cancel_button')).'</a>';
		}
		else if($cancellation_notice == 'custom' ){
		    $cancellation_notice_timeframe = isset( $ga_policies['cancellation_notice_timeframe'] ) ? $ga_policies['cancellation_notice_timeframe'] : 10;

            if( user_can_cancel_appointment( $cancellation_notice_timeframe, $this->ga_date(get_the_id(), $translation = false ), $this->ga_time( get_the_id(), $translation = false ) ) ) {
                return '<a href="#" app-id="'.get_the_id().'" class="appointment-action" optional_text="'.esc_html(ga_get_translated_data('optional_text')).'" title="'.esc_html(ga_get_translated_data('cancel_text')).'">'.esc_html(ga_get_translated_data('cancel_button')).'</a>';
            }
        }
		else {
			return '';
		}
	}

	/*
	 * Client reschedule appointments
	 */
	public function reschedule_appointment(){
	    //Allow client to reschedule appointment
        $ga_policies = get_option('ga_appointments_policies');
        $appointment_reschedule = isset( $ga_policies['appointment_reschedule'] ) ? $ga_policies['appointment_reschedule'] : 'no';
        if( $appointment_reschedule == 'yes' ) {
            return '<a href="#" app-id="'.get_the_id().'" class="reschedule-appointment-action" optional_text="'.esc_html(ga_get_translated_data('reschedule_optional_text')).'" title="'.esc_html(ga_get_translated_data('reschedule_text')).'">'.esc_html(ga_get_translated_data('reschedule_button')).'</a>';
        } else {
            return '';
        }
    }

	/**
	 * Provider Cancels Appointments
	 */
	public function provider_cancellation_notice() {
		// Allow Provider To Cancel Appointments
		$ga_policies = get_option('ga_appointments_policies');
		$provider_cancellation_notice = isset( $ga_policies['provider_cancellation_notice'] ) ? $ga_policies['provider_cancellation_notice'] : 'no';

		if( $provider_cancellation_notice == 'yes' ) {
			return '<a href="#" app-id="'.get_the_id().'" class="appointment-action provider-cancel" optional_text="'.esc_html(ga_get_translated_data('optional_text')).'" title="'.esc_html(ga_get_translated_data('cancel_text')).'">'.esc_html(ga_get_translated_data('cancel_button')).'</a>';
		} else {
			return '';
		}
	}


	/**
	 * Provider Confirms Appointments
	 */
	public function provider_confirms_appointments() {
		// Allow Provider Confirm Appointments
		$ga_policies = get_option('ga_appointments_policies');
		$provider_confirms = isset( $ga_policies['provider_confirms'] ) ? $ga_policies['provider_confirms'] : 'no';


		if( $provider_confirms == 'yes' ) {
			return '<a href="#" app-id="'.get_the_id().'" class="appointment-action provider-confirm" optional_text="'.esc_html(ga_get_translated_data('optional_text')).'" title="'.esc_html(ga_get_translated_data('confirm_text')).'">'.esc_html(ga_get_translated_data('confirm_button')).'</a>';
		} else {
			return '';
		}


	}

} // end class
