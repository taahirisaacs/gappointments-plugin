<?php
defined('ABSPATH') or exit; // Exit if accessed directly

class ga_appointment_emails
{
    private $form_lang;

    public function __construct( $post_id = false )
    {
        $this->form_lang = !empty( $post_id ) ? get_form_translations( null, null, $post_id ) : false;
    }

    private function add_to_cal()
	{
		$notifications = get_option('ga_appointments_notifications');
		$add_to_cal    = isset($notifications['add_to_cal']) ? $notifications['add_to_cal'] : 'yes';

		if ($add_to_cal == 'yes') {
			return true;
		}

		return false;
	}

	private function provider_add_to_cal()
	{
		$notifications       = get_option('ga_appointments_notifications');
		$provider_add_to_cal = isset($notifications['provider_add_to_cal']) ? $notifications['provider_add_to_cal'] : 'yes';

		if ($provider_add_to_cal == 'yes') {
			return true;
		}

		return false;
	}

	public function get_client_calendar_links($post_id, $form_lang)
	{
		$provider_id       = $this->get_provider_id($post_id);
		$service_name      = $this->get_service_name($post_id);
		$provider_name     = $this->get_provider_name_title($provider_id);

		// Client Links
		$client_title      = ga_get_translated_client_service($form_lang, ucfirst($service_name), $provider_name);
		$client_links      = $this->generate_calendar_links($post_id, $client_title, $form_lang);

		return $client_links;
	}

	public function get_provider_calendar_links($post_id, $form_lang)
	{
		$service_name      = $this->get_service_name($post_id);
		$client_name       = $this->get_client_name($post_id);
		$client_name       = !empty($client_name) ? ucwords($client_name) : '';

		// Provider Links
		$provider_title    = ga_get_translated_provider_service($form_lang, ucfirst($service_name), $client_name);
		$provider_links    = $this->generate_calendar_links($post_id, $provider_title, $form_lang);

		return $provider_links;
	}

	/*
	 * Date add to calendar
	 */
	public function add_to_cal_date($appointment_id)
	{
		$app_date = (string) get_post_meta($appointment_id, 'ga_appointment_date', true);
		$date     = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
		return $date ? $date->format('Y-m-j') : false;
	}

	/*
	 * Time add to calendar
	 */
	public function add_to_cal_time($appointment_id)
	{
		$app_time = (string) get_post_meta($appointment_id, 'ga_appointment_time', true);
		$time     = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
		return $time ? $time->format('H:i') : '';
	}

	public function get_provider_name_title($provider_id)
	{
		return 'ga_providers' == get_post_type($provider_id) ? esc_html(ucwords(get_the_title($provider_id))) : '';
	}

	public function generate_calendar_links($appointment_id, $title, $form_lang)
	{
		$notifications    = get_option('ga_appointments_notifications');
		$options          = get_option('ga_appointments_add_to_calendar');
		$location   = isset($options['location']) && !empty($options['location']) ? $options['location'] : get_bloginfo();

		$post_status       = get_post_status($appointment_id);
		$valid_post_status = array('publish', 'pending');

		if (in_array($post_status, $valid_post_status)) {
			# valid
		} else {
			return '';
		}

		// date & time
		$app_date = $this->add_to_cal_date($appointment_id);
		$app_time = $this->add_to_cal_time($appointment_id);

		if (ga_valid_date_format($app_date) && ga_valid_time_format($app_time)) {
			# valid date & time
			$timezone      = ga_time_zone();
			$duration      = (int) get_post_meta($appointment_id, 'ga_appointment_duration', true);

			$start_date    = new DateTime("{$app_date} {$app_time}", new DateTimeZone($timezone)); // Appointment Time
			$interval      = new DateInterval("PT" . $duration . "M");
			$end_date      = clone $start_date;
			$end_date      = $end_date->add($interval);

			$time_start = $start_date->format('Y-m-d H:i');
			$time_end   = $end_date->format('Y-m-d H:i');
		} else {
			return '';
		}

		// Include link generator class
		if (!class_exists('ga_add_to_calendar')) {
			require_once(ga_base_path . '/includes/add_to_calendar.php');
		}

		// Date Slots Mode
		$service_id = (int) get_post_meta($appointment_id, 'ga_appointment_service', true);
		$available_times_mode = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true);
		if ($service_id && $available_times_mode == 'no_slots') {
			$time_start = $start_date->format('Y-m-d') . '00:00';
			$time_end   = $start_date->format('Y-m-d') . '23:59';
		}

		// Time Format Display
		$time_display = ga_service_time_format_display($service_id);

		// Link Generator Options
		$from        = DateTime::createFromFormat('Y-m-d H:i', $time_start);
		$to          = DateTime::createFromFormat('Y-m-d H:i', $time_end);

		if ($service_id && $available_times_mode == 'no_slots') {
			// Translation Support
			$month = $start_date->format('F');
			$day   = $start_date->format('j');
			$year  = $start_date->format('Y');
			$description = ga_get_form_translated_slots_date($form_lang, $month, $day, $year);
			// Translation Support
		} else {
			// Translation Support
			$month = $start_date->format('F');
			$week  = $start_date->format('l');
			$day   = $start_date->format('j');
			$year  = $start_date->format('Y');
			$_time = $start_date->format($time_display);
			$description = ga_get_form_translated_date_time($form_lang, $month, $week, $day, $year, $_time);
			// Translation Support
		}

		$description .= PHP_EOL . $this->AddToCalendarDescription($appointment_id);

		// Generate Links
		$link = ga_add_to_calendar::create($title, $from, $to)->description($description)->address($location);

		$add_to_google   = isset($notifications['google'])  ? $notifications['google']  : 'yes';
		$add_to_yahoo    = isset($notifications['yahoo'])   ? $notifications['yahoo']   : 'yes';
		$add_to_outlook  = isset($notifications['outlook']) ? $notifications['outlook'] : 'yes';

		$links = array();

		if ($add_to_google === 'yes') {
			$links['gCAL'] = $link->google();
		}

		if ($add_to_yahoo === 'yes') {
			$links['Yahoo!'] = $link->yahoo();
		}

		if ($add_to_outlook === 'yes') :
			$links['Outlook'] = $link->outlook();
		endif;

		if (count($links) > 0) {
			# we have links
		} else {
			return '';
		}

		$out = '';
		$out .= '<div class="ga_add_to_calendar_links">(';
		foreach ($links as $text => $link) {
			$out .= '<a target="_blank" href="' . $link . '">' . $text . '</a>';
		}
		$out .= ')</div>';

		return $out;
	}


	private function get_provider_id($post_id)
	{
		return (int) get_post_meta($post_id, 'ga_appointment_provider', true);
	}


	public function get_service_id($post_id)
	{
		return (int) get_post_meta($post_id, 'ga_appointment_service', true);
	}


	private function get_client_name($post_id)
	{
		$client_id  = get_post_meta($post_id, 'ga_appointment_client', true);
		$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);

		if ($client_id == 'new_client') {
			$name = isset($new_client['name']) && !empty($new_client['name']) ? $new_client['name'] : '';
			return $name;
		} elseif ($user_info = get_userdata($client_id)) {
			$name = isset($new_client['name']) && !empty($new_client['name']) ? $new_client['name'] : $user_info->user_nicename;
			return $name;
		} else {
			return '';
		}
	}

	private function get_client_email($post_id)
	{
		$client_id  = get_post_meta($post_id, 'ga_appointment_client', true);
		$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);

		if ($client_id == 'new_client') {
			$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);
			$email = isset($new_client['email']) && !empty($new_client['email']) ? $new_client['email'] : '';
			return $email;
		} elseif ($user_info = get_userdata($client_id)) {
			$email = isset($new_client['email']) && !empty($new_client['email']) ? $new_client['email'] : $user_info->user_email;
			return $email;
		} else {
			return '';
		}
	}

	private function get_client_phone($post_id)
	{

		$client_id  = get_post_meta($post_id, 'ga_appointment_client', true);
		$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);

		if ($client_id == 'new_client') {
			$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);
			$phone = isset($new_client['phone']) && !empty($new_client['phone']) ? $new_client['phone'] : '';
			return $phone;
		} elseif ($user_phone = get_user_meta($client_id, 'phone', true)) {
			$phone = isset($new_client['phone']) && !empty($new_client['phone']) ? $new_client['phone'] : $user_phone;
			return $phone;
		} elseif (isset($new_client['phone']) && !empty($new_client['phone'])) {
			return $new_client['phone'];
		} else {
			return '';
		}
	}

	private function get_provider_email($post_id)
	{
		$provider_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);

		if ('ga_providers' == get_post_type($provider_id)) {

			$user_assigned = (int) get_post_meta($provider_id, 'ga_provider_user', true);

			if ($provider_data = get_userdata($user_assigned)) {
				$provider_email = $provider_data->user_email;
				return $provider_email;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	private function get_service_name($post_id)
	{
		$service_id = (int) get_post_meta($post_id, 'ga_appointment_service', true);
		$service_name = 'ga_services' == get_post_type($service_id) ? esc_html(get_the_title($service_id)) : 'Not defined';

		return $service_name;
	}

	private function get_provider_name($post_id)
	{
		$provider_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);
		$provider_name = 'ga_providers' == get_post_type($provider_id) ? esc_html(get_the_title($provider_id)) : '';

		return $provider_name;
	}

	private function get_provider_phone($post_id)
	{
		$provider_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);

		if ('ga_providers' == get_post_type($provider_id)) {
			$user_assigned = (int) get_post_meta($provider_id, 'ga_provider_user', true);

			if ($userdata = get_userdata($user_assigned)) {
				$user_id  = $userdata->ID;
				$phone    = get_user_meta($user_id, 'phone', true);
				return trim($phone);
			} else {
				return '';
			}
		} else {
			return '';
		}
	}


	private function get_time_zone()
	{
		return ga_time_zone();
	}

	private function get_date_time($post_id, $form_lang)
	{
		// Date
		$app_date            = (string) get_post_meta($post_id, 'ga_appointment_date', true);
		$date                = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
		$app_date_text       = $date ? $date->format('l, F j Y') : '(Date not defined)';

		// Time
		$app_time            = (string) get_post_meta($post_id, 'ga_appointment_time', true);
		$time                = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
		$app_time_text       = $time ? $time->format('g:i a') : '(Time not defined)';

		// Date Slots Mode
		$service_id = (int) get_post_meta($post_id, 'ga_appointment_service', true);

		// Time Format Display
		$time_display = ga_service_time_format_display($service_id);

		// Service Mode
		$available_times_mode = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true);

		// Translation Support
		if ($available_times_mode == 'no_slots') {
			if ($date) {
				$month = $date->format('F');
				$day   = $date->format('j');
				$year  = $date->format('Y');
				$appointment_date = ga_get_form_translated_slots_date($form_lang, $month, $day, $year);
			} else {
				$appointment_date = $app_date_text;
			}
		} else {
			if ($date && $time) {
				$month = $date->format('F');
				$week  = $date->format('l');
				$day   = $date->format('j');
				$year  = $date->format('Y');
				$_time = $time->format($time_display);

				$appointment_date = ga_get_form_translated_date_time($form_lang, $month, $week, $day, $year, $_time);
			} else {
				$appointment_date = "{$app_date_text} at {$app_time_text}";
			}
		}


		return $appointment_date;
	}

	private function get_appointment_duration($post_id)
	{
		$appointmentType = (string) get_post_meta($post_id, 'ga_appointment_type', true);
		if ($appointmentType == 'date') {
			return __('Full day', 'gappointments');
		}
		if (isset($_POST['ga_appointment_duration']))
			$duration_minutes = (string) $_POST['ga_appointment_duration'];
		else
			$duration_minutes = (string) get_post_meta($post_id, 'ga_appointment_duration', true);

		$hours = floor($duration_minutes / 60);
		$minutes = ($duration_minutes % 60);
		switch ($hours) {
			case 1:
				$hr_text = "$hours hour ";
				break;
			case 0:
				$hr_text = '';
				break;
			default:
				$hr_text = "$hours hours ";
				break;
		}
		switch ($minutes) {
			case 1:
				$minute_text = "$minutes minute";
				break;
			case 0:
				$minute_text = '';
				break;
			default:
				$minute_text = "$minutes minutes";
		}
		return "{$hr_text}{$minute_text}";
	}

	/**
	 * Change WP_MAIL Name From
	 */
	public function wp_mail_from_name($old)
	{
		$options    = get_option('ga_appointments_notifications');
		$from_name = isset($options['from_name']) ? $options['from_name'] : get_bloginfo();
		return $from_name;
	}

	/**
	 * Change WP_MAIL Email From
	 */
	public function wp_mail_from($old)
	{
		$options    = get_option('ga_appointments_notifications');
		$from_email = isset($options['from_email']) ? $options['from_email'] : get_bloginfo('admin_email');
		return $from_email;
	}

	/**
	 * Send Email using WP_MAIL
	 */
	public function ga_mail($to, $subject, $body)
	{
		// Change sender name
		add_filter('wp_mail_from_name', array($this, 'wp_mail_from_name'));

		// Change sender email
		add_filter('wp_mail_from', array($this, 'wp_mail_from'));

		// Html email headers
		$headers = array('Content-Type: text/html; charset=UTF-8');

		// Email
		wp_mail($to, $subject, $body, $headers);

		// Remove sender name
		remove_filter('wp_mail_from_name', array($this, 'wp_mail_from_name'));

		// Remove sender email
		remove_filter('wp_mail_from', array($this, 'wp_mail_from'));
	}

	/**
	 * WP Twilio Core: Plugin active
	 */
	public function twl_active()
	{
		if (in_array('wp-twilio-core/core.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Send SMS using WP Twilio Core
	 */
	public function ga_sms($number_to, $message)
	{
		if (function_exists('twl_send_sms')) {
			// Send SMS
			$args = array(
				'number_to' => $number_to,
				'message'   => $message,
			);
			twl_send_sms($args);
		}
	}




	/******************************* CLIENT SMS *********************************/
	/**
	 * Client Pending SMS
	 */
	public function pending_sms($post_id, $multiple_dates = false)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options       = get_option('ga_appointments_sms_notifications');
		$sms           = isset($options['client_sms_pending']) ? $options['client_sms_pending'] : 'no';
		$client_phone  = $this->get_client_phone($post_id);

		if ($sms == 'no' || empty($client_phone)) {
			return;
		}

		if ($multiple_dates) {
			$date_time = $multiple_dates;
		} else {
			$date_time = $this->get_date_time($post_id, $this->form_lang);
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%'
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$date_time,
			$this->get_appointment_duration($post_id)
		);

		$message = isset($options['pending_body'])
			? str_ireplace($find, $replace, $options['pending_body'])
			: str_ireplace($find, $replace, $this->pending_body());

		$this->ga_sms($client_phone, $message);
	}

	/**
	 * Client Confirmation SMS
	 */
	public function confirmation_sms($post_id, $multiple_dates = false)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options       = get_option('ga_appointments_sms_notifications');
		$sms           = isset($options['client_sms_confirmation']) ? $options['client_sms_confirmation'] : 'no';
		$client_phone  = $this->get_client_phone($post_id);

		if ($sms == 'no' || empty($client_phone)) {
			return;
		}

		if ($multiple_dates) {
			$date_time = $multiple_dates;
		} else {
            $date_time = $this->get_date_time($post_id, $this->form_lang);
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);

		$message = isset($options['confirmation_body'])
			? str_ireplace($find, $replace, $options['confirmation_body'])
			: str_ireplace($find, $replace, $this->confirmation_body());

		$this->ga_sms($client_phone, $message);
	}


	public function cancellation_sms($post_id, $message)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options       = get_option('ga_appointments_sms_notifications');
		$sms           = isset($options['client_sms_cancelled']) ? $options['client_sms_cancelled'] : 'no';
		$client_phone  = $this->get_client_phone($post_id);

		if ($sms == 'no' || empty($client_phone)) {
			return;
		}
		$date_time = $this->get_date_time($post_id, $this->form_lang);

		$find = array(
			'%client_name%',
			'%message%',
			'%service_name%',
			'%provider_name%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$message,
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);

		$msg = isset($options['cancelled_body'])
			? str_ireplace($find, $replace, $options['cancelled_body'])
			: str_ireplace($find, $replace, $this->cancelled_body());

		$this->ga_sms($client_phone, $msg);
	}
	/******************************* CLIENT SMS *********************************/

	/******************************* PROVIDER SMS *********************************/
	/**
	 * Provider Pending SMS
	 */
	public function provider_pending_sms($post_id, $multiple_dates = false)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options         = get_option('ga_appointments_sms_notifications');
		$sms             = isset($options['provider_sms_pending']) ? $options['provider_sms_pending'] : 'no';
		$provider_phone  = $this->get_provider_phone($post_id);

		if ($sms == 'no' || empty($provider_phone)) {
			return;
		}

		if ($multiple_dates) {
			$date_time = $multiple_dates;
		} else {
			$date_time = $this->get_date_time($post_id, $this->form_lang);
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%'
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);


		$message = isset($options['provider_pending_body'])
			? str_ireplace($find, $replace, $options['provider_pending_body'])
			: str_ireplace($find, $replace, $this->provider_pending_body());


		$this->ga_sms($provider_phone, $message);
	}

	/**
	 * Provider Confirmation SMS
	 */
	public function provider_confirmation_sms($post_id, $multiple_dates = false)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options         = get_option('ga_appointments_sms_notifications');
		$sms             = isset($options['provider_sms_confirmation']) ? $options['provider_sms_confirmation'] : 'no';
		$provider_phone  = $this->get_provider_phone($post_id);

		if ($sms == 'no' || empty($provider_phone)) {
			return;
		}

		if ($multiple_dates) {
			$date_time = $multiple_dates;
		} else {
			$date_time = $this->get_date_time($post_id, $this->form_lang);
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);


		$message = isset($options['provider_confirmation_body'])
			? str_ireplace($find, $replace, $options['provider_confirmation_body'])
			: str_ireplace($find, $replace, $this->provider_confirmation_body());


		$this->ga_sms($provider_phone, $message);
	}


	/**
	 * Send Cancellation Email To Provider
	 */
	public function provider_cancellation_sms($post_id, $message)
	{
		if (!$this->twl_active()) {
			return;
		}

		$options         = get_option('ga_appointments_sms_notifications');
		$sms             = isset($options['provider_sms_cancelled']) ? $options['provider_sms_cancelled'] : 'no';
		$provider_phone  = $this->get_provider_phone($post_id);

		if ($sms == 'no' || empty($provider_phone)) {
			return;
		}

		$date_time = $this->get_date_time($post_id, $this->form_lang);

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%message%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$message,
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);

		$msg = isset($options['provider_cancelled_body'])
			? str_ireplace($find, $replace, $options['provider_cancelled_body'])
			: str_ireplace($find, $replace, $this->provider_cancelled_body());


		$this->ga_sms($provider_phone, $msg);
	}

	/******************************* PROVIDER SMS *********************************/



	/******************************* CLIENT EMAILS *********************************/
	/**
	 * Send Pending Email To Client
	 */
	public function pending($post_id)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['client_notifications_pending']) ? $options['client_notifications_pending'] : 'yes';
		$heading_title = isset($options['pending_title']) ? $options['pending_title'] : $this->pending_title();

        $this->pending_sms($post_id);

		if ($notifications != 'yes') {
			return;
		}

		if ($this->get_client_email($post_id) == '') {
			return;
		}

        $date_time = $this->get_date_time($post_id, $this->form_lang);

		if ($this->add_to_cal()) {
			$body_date = '<div class="ga_appointment-date">' . $date_time . $this->get_client_calendar_links($post_id, $this->form_lang) . '</div>';
		} else {
			$body_date = $date_time;
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$body_date,
			$this->get_appointment_duration($post_id),
		);

		$subject = isset($options['pending_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['pending_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->pending_subject());

		$body    = isset($options['pending_body'])
			? str_ireplace($find, $replace, wpautop($options['pending_body']))
			: str_ireplace($find, $replace, wpautop($this->pending_body()));

		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_client_email($post_id), $subject, $body);
	}

	/**
	 * Send Confirmation Email To Client
	 */
	public function confirmation($post_id)
	{
		$options        = get_option('ga_appointments_notifications');
		$notifications = isset($options['client_notifications_confirmation']) ? $options['client_notifications_confirmation'] : 'yes';
		$heading_title = isset($options['confirmation_title']) ? $options['confirmation_title'] : $this->confirmation_title();

		$this->confirmation_sms($post_id);

		if ($notifications != 'yes') {
			return;
		}

		if ($this->get_client_email($post_id) == '') {
			return;
		}

        $date_time = $this->get_date_time($post_id, $this->form_lang);

		if ($this->add_to_cal()) {
			$body_date = '<div class="ga_appointment-date">' . $date_time . $this->get_client_calendar_links($post_id, $this->form_lang) . '</div>';
		} else {
			$body_date = $date_time;
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$body_date,
			$this->get_appointment_duration($post_id),
		);

		$subject = isset($options['confirmation_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['confirmation_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->confirmation_subject());

		$body    = isset($options['confirmation_body'])
			? str_ireplace($find, $replace, wpautop($options['confirmation_body']))
			: str_ireplace($find, $replace, wpautop($this->confirmation_body()));

		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_client_email($post_id), $subject, $body);
	}

	/**
	 * Send Cancellation Email To Client
	 */
	public function cancellation($post_id, $message)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['client_notifications_cancelled']) ? $options['client_notifications_cancelled'] : 'yes';
		$heading_title = isset($options['cancelled_title']) ? $options['cancelled_title'] : $this->cancelled_title();

		$this->cancellation_sms($post_id, $message);

		if ($notifications != 'yes') {
			return;
		}

		if ($this->get_client_email($post_id) == '') {
			return;
		}

		$date_time = $this->get_date_time($post_id, $this->form_lang);

		$find = array(
			'%client_name%',
			'%message%',
			'%service_name%',
			'%provider_name%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$message,
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);


		$subject = isset($options['cancelled_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['cancelled_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->cancelled_subject());

		$body    = isset($options['cancelled_body'])
			? str_ireplace($find, $replace, wpautop($options['cancelled_body']))
			: str_ireplace($find, $replace, wpautop($this->cancelled_body()));

		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_client_email($post_id), $subject, $body);
	}



	/******************************* PROVIDER EMAILS *********************************/
	/**
	 * Send Pending Email To Provider
	 */
	public function provider_pending($post_id)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['provider_notifications_pending']) ? $options['provider_notifications_pending'] : 'yes';
		$heading_title = isset($options['provider_pending_title']) ? $options['provider_pending_title'] : $this->provider_pending_title();

		$this->provider_pending_sms($post_id);

		if ($notifications != 'yes') {
			return;
		}

		if (!$this->get_provider_email($post_id)) {
			return;
		}

        $date_time = $this->get_date_time($post_id, $this->form_lang);

        if ($this->provider_add_to_cal()) {
			$body_date = '<div class="ga_appointment-date">' . $date_time . $this->get_provider_calendar_links($post_id, $this->form_lang) . '</div>';
		} else {
			$body_date = $date_time;
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$body_date,
			$this->get_appointment_duration($post_id),
		);


		$subject = isset($options['provider_pending_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['provider_pending_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->provider_pending_subject());

		$body = isset($options['provider_pending_body'])
			? str_ireplace($find, $replace, wpautop($options['provider_pending_body']))
			: str_ireplace($find, $replace, wpautop($this->provider_pending_body()));


		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_provider_email($post_id), $subject, $body);
	}

	/**
	 * Send Confirmation Email To Provider
	 */
	public function provider_confirmation($post_id)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['provider_notifications_confirmation']) ? $options['provider_notifications_confirmation'] : 'yes';
		$heading_title = isset($options['provider_confirmation_title']) ? $options['provider_confirmation_title'] : $this->provider_confirmation_title();

		$this->provider_confirmation_sms($post_id);

		if ($notifications != 'yes') {
			return;
		}

		if (!$this->get_provider_email($post_id)) {
			return;
		}

        $date_time = $this->get_date_time($post_id, $this->form_lang);

        if ($this->provider_add_to_cal()) {
			$body_date = '<div class="ga_appointment-date">' . $date_time . $this->get_provider_calendar_links($post_id, $this->form_lang) . '</div>';
		} else {
			$body_date = $date_time;
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$body_date,
			$this->get_appointment_duration($post_id),
		);


		$subject = isset($options['provider_confirmation_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['provider_confirmation_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->provider_confirmation_subject());

		$body = isset($options['provider_confirmation_body'])
			? str_ireplace($find, $replace, wpautop($options['provider_confirmation_body']))
			: str_ireplace($find, $replace, wpautop($this->provider_confirmation_body()));


		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_provider_email($post_id), $subject, $body);
	}


	/**
	 * Send Cancellation Email To Provider
	 */
	public function provider_cancellation($post_id, $message)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['provider_notifications_cancelled']) ? $options['provider_notifications_cancelled'] : 'yes';
		$heading_title = isset($options['provider_cancelled_title']) ? $options['provider_cancelled_title'] : $this->provider_cancelled_title();

		$this->provider_cancellation_sms($post_id, $message);

		if ($notifications != 'yes') {
			return;
		}

		if (!$this->get_provider_email($post_id)) {
			return;
		}

		$date_time = $this->get_date_time($post_id, $this->form_lang);

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%message%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$message,
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$date_time,
			$this->get_appointment_duration($post_id),
		);


		$subject = isset($options['provider_cancelled_subject'])
			? str_ireplace('%appointment_date%', $date_time, $options['provider_cancelled_subject'])
			: str_ireplace('%appointment_date%', $date_time, $this->provider_cancelled_subject());

		$body = isset($options['provider_cancelled_body'])
			? str_ireplace($find, $replace, wpautop($options['provider_cancelled_body']))
			: str_ireplace($find, $replace, wpautop($this->provider_cancelled_body()));


		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_provider_email($post_id), $subject, $body);
	}


	/**
	 ********************************** BULK EMAILING **************************************
	 */
	/**
	 * Send Bulk Dates Confirmation Email To Client
	 */
	public function bulk_confirmation($post_id, $bulk_dates, $array, $sms_dates)
	{
		$options = get_option('ga_appointments_notifications');
		$notifications = isset($options['client_notifications_pending']) ? $options['client_notifications_pending'] : 'yes';

		$this->confirmation_sms($post_id, $sms_dates);

		if ($notifications != 'yes') {
			return;
		}

		if ($this->get_client_email($post_id) == '') {
			return;
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$bulk_dates,
			$this->get_appointment_duration($post_id),
		);

		$date_time = $this->get_date_time($post_id, $this->form_lang);
		if (count($array) > 1) {
			$heading_title = isset($options['confirmation_multi_title']) ? $options['confirmation_multi_title'] : $this->confirmation_multi_title();
			$subject = isset($options['confirmation_multi_subject']) ? $options['confirmation_multi_subject'] : $this->confirmation_multi_subject();

			$body = isset($options['confirmation_multi_body'])
				? str_ireplace($find, $replace, wpautop($options['confirmation_multi_body']))
				: str_ireplace($find, $replace, wpautop($this->confirmation_multi_body()));
		} else {
			$heading_title = isset($options['confirmation_title']) ? $options['confirmation_title'] : $this->confirmation_title();
			$subject = isset($options['confirmation_subject'])
				? str_ireplace('%appointment_date%', $date_time, $options['confirmation_subject'])
				: str_ireplace('%appointment_date%', $date_time, $this->confirmation_subject());

			$body = isset($options['confirmation_body'])
				? str_ireplace($find, $replace, wpautop($options['confirmation_body']))
				: str_ireplace($find, $replace, wpautop($this->confirmation_body()));
		}

		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_client_email($post_id), $subject, $body);
	}

	/**
	 * Send Bulk Dates Pending Email To Client
	 */
	public function bulk_pending($post_id, $bulk_dates, $array, $sms_dates)
	{
		$options = get_option('ga_appointments_notifications');
		$notifications = isset($options['client_notifications_pending']) ? $options['client_notifications_pending'] : 'yes';

		$this->pending_sms($post_id, $sms_dates);

		if ($notifications != 'yes') {
			return;
		}

		if ($this->get_client_email($post_id) == '') {
			return;
		}

		$find = array(
			'%client_name%',
			'%service_name%',
			'%provider_name%',
			'%provider_email%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_service_name($post_id),
			$this->get_provider_name($post_id),
			$this->get_provider_email($post_id),
			$bulk_dates,
			$this->get_appointment_duration($post_id),
		);

		$date_time = $this->get_date_time($post_id, $this->form_lang);
		if (count($array) > 1) {
			$heading_title = isset($options['pending_multi_title']) ? $options['pending_multi_title'] : $this->pending_multi_title();
			$subject = isset($options['pending_multi_subject']) ? $options['pending_multi_subject'] : $this->pending_multi_subject();

			$body = isset($options['pending_multi_body'])
				? str_ireplace($find, $replace, wpautop($options['pending_multi_body']))
				: str_ireplace($find, $replace, wpautop($this->pending_multi_body()));
		} else {
			$heading_title = isset($options['pending_title']) ? $options['pending_title'] : $this->pending_title();
			$subject = isset($options['pending_subject'])
				? str_ireplace('%appointment_date%', $date_time, $options['pending_subject'])
				: str_ireplace('%appointment_date%', $date_time, $this->pending_subject());

			$body = isset($options['pending_body'])
				? str_ireplace($find, $replace, wpautop($options['pending_body']))
				: str_ireplace($find, $replace, wpautop($this->pending_body()));
		}


		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_client_email($post_id), $subject, $body);
	}


	/**
	 * Send Bulk Dates Confirmation Email To Provider
	 */
	public function provider_bulk_confirmation($post_id, $bulk_dates, $array, $sms_dates)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['provider_notifications_pending']) ? $options['provider_notifications_pending'] : 'yes';

		$this->provider_confirmation_sms($post_id, $sms_dates);

		if ($notifications != 'yes') {
			return;
		}

		if (!$this->get_provider_email($post_id)) {
			return;
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$bulk_dates,
			$this->get_appointment_duration($post_id),
		);

		$date_time = $this->get_date_time($post_id, $this->form_lang);
		if (count($array) > 1) {
			$heading_title = isset($options['provider_confirmation_multi_title']) ? $options['provider_confirmation_multi_title'] : $this->provider_confirmation_multi_title();
			$subject = isset($options['provider_confirmation_multi_subject']) ? $options['provider_confirmation_multi_subject'] : $this->provider_confirmation_multi_subject();
			$body = isset($options['provider_confirmation_multi_body'])
				? str_ireplace($find, $replace, wpautop($options['provider_confirmation_multi_body']))
				: str_ireplace($find, $replace, wpautop($this->provider_confirmation_multi_body()));
		} else {
			$heading_title = isset($options['provider_confirmation_title']) ? $options['provider_confirmation_title'] : $this->provider_confirmation_title();

			$subject = isset($options['provider_confirmation_subject'])
				? str_ireplace('%appointment_date%', $date_time, $options['provider_confirmation_subject'])
				: str_ireplace('%appointment_date%', $date_time, $this->provider_confirmation_subject());

			$body = isset($options['provider_confirmation_body'])
				? str_ireplace($find, $replace, wpautop($options['provider_confirmation_body']))
				: str_ireplace($find, $replace, wpautop($this->provider_confirmation_body()));
		}

		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_provider_email($post_id), $subject, $body);
	}


	/**
	 * Send Bulk Dates Pending Email To Provider
	 */
	public function provider_bulk_pending($post_id, $bulk_dates, $array, $sms_dates)
	{
		$options       = get_option('ga_appointments_notifications');
		$notifications = isset($options['provider_notifications_pending']) ? $options['provider_notifications_pending'] : 'yes';

		$this->provider_pending_sms($post_id, $sms_dates);

		if ($notifications != 'yes') {
			return;
		}

		if (!$this->get_provider_email($post_id)) {
			return;
		}

		$find = array(
			'%provider_name%',
			'%service_name%',
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%appointment_date%',
			'%appointment_duration%',
		);

		$replace = array(
			$this->get_provider_name($post_id),
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$bulk_dates,
			$this->get_appointment_duration($post_id),
		);

		$date_time = $this->get_date_time($post_id, $this->form_lang);
		if (count($array) > 1) {
			$heading_title = isset($options['provider_pending_multi_title']) ? $options['provider_pending_multi_title'] : $this->provider_pending_multi_title();

			$subject = isset($options['provider_pending_multi_subject']) ? $options['provider_pending_multi_subject'] : $this->provider_pending_multi_subject();

			$body = isset($options['provider_pending_multi_body'])
				? str_ireplace($find, $replace, wpautop($options['provider_pending_multi_body']))
				: str_ireplace($find, $replace, wpautop($this->provider_pending_multi_body()));
		} else {
			$heading_title = isset($options['provider_pending_title']) ? $options['provider_pending_title'] : $this->provider_pending_title();

			$subject = isset($options['provider_pending_subject'])
				? str_ireplace('%appointment_date%', $date_time, $options['provider_pending_subject'])
				: str_ireplace('%appointment_date%', $date_time, $this->provider_pending_subject());

			$body = isset($options['provider_pending_body'])
				? str_ireplace($find, $replace, wpautop($options['provider_pending_body']))
				: str_ireplace($find, $replace, wpautop($this->provider_pending_body()));
		}


		// Html template
		ob_start();
		$this->require_email_template();
		$html_email = ob_get_clean();
		// Html template

		$find = array(
			'%appointment_heading_content%',
			'%appointment_body_content%'
		);

		$replace = array(
			$heading_title,
			$body
		);

		$body = str_ireplace($find, $replace, $html_email);

		$this->ga_mail($this->get_provider_email($post_id), $subject, $body);
	}

	public function AddToCalendarDescription($post_id)
	{

		$AddToCalendarOptions = get_option('ga_appointments_add_to_calendar');

		$find = array(
			'%client_name%',
			'%client_email%',
			'%client_phone%',
			'%service_name%'
		);

		$replace = array(
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),
			$this->get_service_name($post_id),
		);

		$description = isset($AddToCalendarOptions['description'])
			? str_ireplace($find, $replace, $AddToCalendarOptions['description'])
			: '';

		return $description;
	}


	/***********************************************
	 ************ EMAIL HTML TEMPLATES *************
	 ***********************************************/
	/**
	 * Pending Confirmation Email Sent To Client
	 */
	public function pending_subject()
	{
		return 'Appointment Pending - %appointment_date%';
	}

	public function pending_title()
	{
		return 'Appointment Pending';
	}

	public function pending_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% with %provider_name%(%provider_email%) is pending confirmation.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%appointment_date%';
		return $output;
	}


	/**
	 * Confirmation Email Sent To Client
	 */
	public function confirmation_subject()
	{
		return 'Appointment confirmed - %appointment_date%';
	}

	public function confirmation_title()
	{
		return 'Appointment Confirmed';
	}

	public function confirmation_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% with %provider_name%(%provider_email%) is confirmed.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%appointment_date%';
		return $output;
	}

	/**
	 * Cancelled Email Sent To Client
	 */
	public function cancelled_subject()
	{
		return 'Appointment CANCELLED - %appointment_date%';
	}

	public function cancelled_title()
	{
		return 'Appointment Cancelled';
	}

	public function cancelled_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your appointment with %provider_name%(%service_name%) on %appointment_date% has been cancelled.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Message (optional): %message%';
		return $output;
	}


	/**
	 * Pending Email Sent To Provider
	 */
	public function provider_pending_subject()
	{
		return 'New appointment pending - %appointment_date%';
	}

	public function provider_pending_title()
	{
		return 'Appointment pending';
	}

	public function provider_pending_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled by your client is pending confirmation.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_email%' . PHP_EOL;
		$output .= '%client_phone%' . PHP_EOL;
		$output .= '%service_name%' . PHP_EOL;
		$output .= '%appointment_date%';
		return $output;
	}

	/**
	 * Confirmation Email Sent To Provider
	 */
	public function provider_confirmation_subject()
	{
		return 'New appointment confirmed - %appointment_date%';
	}

	public function provider_confirmation_title()
	{
		return 'New appointment';
	}

	public function provider_confirmation_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled by your client' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_email%' . PHP_EOL;
		$output .= '%client_phone%' . PHP_EOL;
		$output .= '%service_name%' . PHP_EOL;
		$output .= '%appointment_date%';
		return $output;
	}


	/**
	 * Cancelled Email Sent To Provider
	 */
	public function provider_cancelled_subject()
	{
		return 'Appointment cancelled - %appointment_date%';
	}

	public function provider_cancelled_title()
	{
		return 'Appointment Cancelled';
	}

	public function provider_cancelled_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'The %service_name% on %appointment_date% has been cancelled.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '"Message (optional): %message%"' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_email%' . PHP_EOL;
		$output .= '%client_phone%' . PHP_EOL;
		$output .= '%service_name%' . PHP_EOL;
		$output .= '%appointment_date%';
		return $output;
	}

	/**************************
	 * Multiple Appointments
	 *************************/
	// Client Pending Multiple Bookings
	public function pending_multi_subject()
	{
		return 'Appointments Pending';
	}

	public function pending_multi_title()
	{
		return 'Appointments Pending';
	}

	public function pending_multi_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% with %provider_name%(%provider_email%) is pending confirmation.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%appointment_date%';
		return $output;
	}

	// Client Confirmation Multiple Bookings
	public function confirmation_multi_subject()
	{
		return 'Appointments Confirmed';
	}

	public function confirmation_multi_title()
	{
		return 'Appointments Confirmed';
	}

	public function confirmation_multi_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% with %provider_name%(%provider_email%) is confirmed.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%appointment_date%';
		return $output;
	}

	// Provider Pending Multiple Bookings
	public function provider_pending_multi_subject()
	{
		return 'New appointments pending';
	}

	public function provider_pending_multi_title()
	{
		return 'Appointments Pending';
	}
	public function provider_pending_multi_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled by your client is pending confirmation.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_email%' . PHP_EOL;
		$output .= '%client_phone%' . PHP_EOL;
		$output .= '%service_name%' . PHP_EOL;
		$output .= '%appointment_date%';
		return $output;
	}

	// Provider Confirmation Multiple Bookings
	public function provider_confirmation_multi_subject()
	{
		return 'New appointments confirmed';
	}

	public function provider_confirmation_multi_title()
	{
		return 'Appointments Confirmed';
	}

	public function provider_confirmation_multi_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled by your client' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_email%' . PHP_EOL;
		$output .= '%client_phone%' . PHP_EOL;
		$output .= '%service_name%' . PHP_EOL;
		$output .= '%appointment_date%';
		return $output;
	}


	private function require_email_template()
	{
		$require = 'html_email.php';
		$path_to_overriden = get_stylesheet_directory() . '/gappointments/html_email.php';
		if (file_exists($path_to_overriden)) {
			$require = $path_to_overriden;
		}
		require($require);
	}

	/**************************
	 * SMS Body Templates
	 *************************/
	public function pending_sms_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% on %appointment_date% is pending confirmation.';
		return $output;
	}

	public function confirmation_sms_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your %service_name% on %appointment_date% is confirmed.';
		return $output;
	}

	public function cancelled_sms_body()
	{
		$output = 'Hi %client_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Your appointment on %appointment_date% has been cancelled.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'Message (optional): %message%';
		return $output;
	}

	public function provider_pending_sms_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled on %appointment_date% is pending confirmation.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_phone%';
		return $output;
	}

	public function provider_confirmation_sms_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'A %service_name% has been scheduled on %appointment_date%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_phone%';
		return $output;
	}

	public function provider_cancelled_sms_body()
	{
		$output = 'Hi %provider_name%' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= 'The %service_name% on %appointment_date% has been cancelled.' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '"Message (optional): %message%"' . PHP_EOL;
		$output .= '' . PHP_EOL; // new line
		$output .= '' . PHP_EOL; // new line
		$output .= '%client_name%' . PHP_EOL;
		$output .= '%client_phone%';
		return $output;
	}

	/**************************
	 * SMS Body Templates
	 *************************/
} // end class
