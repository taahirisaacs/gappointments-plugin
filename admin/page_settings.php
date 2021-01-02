<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

new ga_appointments_settings();
class ga_appointments_settings
{
	private $time_zone = 'Europe/Bucharest';
	private $calendar_colors = array(
		'main' => '#7C9299', 'secondary' => '#1dd59a', 'header_color' => '#ffffff', 'bg' => '#f1f3f6',
		'border' => 'rgba(38, 84, 124 ,0.07)', 'color' => '#6D8298', 'hover_color' => '#ffffff',
		'bg_available' => '#ffffff', 'color_available' => '#6D8298', 'loading_overlay' => 'rgba(250, 250, 250, 0.3)',
		'spinner_color' => '#1dd59a', 'slots_bg' => '#e4e8ea', 'slot_selected_bg' => '#1dd59a', 'slot_selected_color' => '#ffffff',
		'slots_title' => '#333333', 'slots_border' => 'rgba(0,0,0,0.03)', 'ajax_spinner' => '#25363F'
	);

	public function __construct()
	{
		// Admin menu
		add_action('admin_menu', array($this, 'admin_menu'));

		// Admin settings and fields
		add_action('admin_init', array($this, 'register_settings_and_fields'));

		// Submenu
		add_action('admin_menu', array($this, 'settings_submenu'));

		// Change menu-order
		add_filter('custom_menu_order', array($this, 'ga_appointments_submenu_order'));

		// Add menus to admin toolbar
		add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 250);

		// Remove some unusable metaboxes from post types
		add_action('admin_menu', array($this, 'remove_ga_appointments_meta_boxes'));


		// Remove Quick Edit From Post Types
		add_filter('post_row_actions', array($this, 'remove_ga_appointments_post_row_actions'), 10, 1);

		// Remove Edit from Bulk Actions Post Types
		add_filter( 'bulk_actions-edit-ga_appointments', array($this, 'add_ga_appointments_bulk_actions'));
		add_filter( 'handle_bulk_actions-edit-ga_appointments', array($this, 'handle_ga_appointments_bulk_actions'),10,3 );

		add_filter('bulk_actions-edit-ga_providers', array($this, 'remove_ga_appointments_bulk_actions'));
		add_filter('bulk_actions-edit-ga_services', array($this, 'remove_ga_appointments_bulk_actions'));

		// Calendar Display Style
		add_action('wp_head', array($this, 'generate_styles'));
	}


	/**
	 * Get logged in user ID
	 */
	public function get_user_id()
	{
		$current_user = wp_get_current_user();
		return $current_user->ID;
	}


	/**
	 * Change menu-order
	 */
	public function ga_appointments_submenu_order($menu_order)
	{
		global $submenu;

		if (!current_user_can('manage_options') || !is_array($submenu['ga_appointments_settings'])) {
			return $menu_order;
		}

		// Enable the next line to see all menu orders
		//echo '<pre>'.print_r($submenu,true).'</pre>';

		$array = array();

		foreach ($submenu['ga_appointments_settings'] as $ga_submenu) {
			$array[strtolower(current($ga_submenu))] = $ga_submenu;
		}

		$sorted = array();
		$sorted[] = $array['activity'];
		$sorted[] = $array['appointments'];
		$sorted[] = $array['services'];
		$sorted[] = $array['providers'];
		$sorted[] = $array['settings'];

		$submenu['ga_appointments_settings'] = $sorted;
		return $menu_order;
	}


	/**
	 * Add menus to admin toolbar
	 */
	function add_toolbar_items($admin_bar)
	{
		if (!current_user_can('manage_options')) {
			return;
		}


		$admin_bar->add_menu(array(
			'id'    => 'gappointments',
			'title' => '<span class="ab-icon"></span> gAppointments',
			'href'  => admin_url('admin.php?page=ga_appointments_activity'),
			'meta'  => array(
				'class'  => 'gappointments'
			),
		));

		$admin_bar->add_menu(array(
			'id'     => 'ga_activity',
			'parent' => 'gappointments',
			'title'  => 'Activity',
			'href'   => admin_url('admin.php?page=ga_appointments_activity'),
		));

		$admin_bar->add_menu(array(
			'id'     => 'ga_appointments',
			'parent' => 'gappointments',
			'title'  => 'Appointments',
			'href'   => admin_url('edit.php?post_type=ga_appointments'),
		));

		$admin_bar->add_menu(array(
			'id'     => 'ga_services',
			'parent' => 'gappointments',
			'title'  => 'Services',
			'href'   => admin_url('edit.php?post_type=ga_services'),
		));

		$admin_bar->add_menu(array(
			'id'     => 'ga_providers',
			'parent' => 'gappointments',
			'title'  => 'Providers',
			'href'   => admin_url('edit.php?post_type=ga_providers'),
		));


		$admin_bar->add_menu(array(
			'id'     => 'ga_settings',
			'parent' => 'gappointments',
			'title'  => 'Settings',
			'href'   => admin_url('admin.php?page=ga_appointments_settings'),
		));
	}


	/**
	 * Remove submitdiv & slub metaboxes from post types
	 */
	public function remove_ga_appointments_meta_boxes()
	{
		remove_meta_box('submitdiv', array('ga_appointments', 'ga_services', 'ga_providers'), 'side');
		remove_meta_box('slugdiv', array('ga_appointments', 'ga_services', 'ga_providers'), 'normal');
	}

	/**
	 * Remove Quick Edit
	 */
	public function remove_ga_appointments_post_row_actions($actions)
	{
		$post_types = array('ga_appointments', 'ga_services', 'ga_providers');

		if (in_array(get_post_type(), $post_types)) {
			unset($actions['view']);
			unset($actions['inline hide-if-no-js']);
		}

		return $actions;
	}

	/**
	 * Remove Bulk Actions
	 */
	public function add_ga_appointments_bulk_actions($actions){
		$post_types = array('ga_appointments', 'ga_providers', 'ga_services');
		if (in_array(get_post_type(), $post_types)) {
			unset($actions['edit']);
			$actions['publish'] = __('Confirm', 'cmb2');
			$actions['cancelled'] = __('Cancel', 'cmb2');
			$actions['Resend_Notific'] = __('Resend Email Notifications', 'cmb2');
		}
		return $actions;
	}
	public function handle_ga_appointments_bulk_actions($redirect_to, $action_name, $post_ids ){
		if($action_name == 'publish' || $action_name == 'cancelled'){
			foreach ( $post_ids as $post_id ) {
		  		$this->ga_appointments_update_post_status($post_id, $action_name);
	  		}
		}
		else if($action_name == "Resend_Notific"){
			foreach($post_ids as $post_id){
				$init_status = get_post_status($post_id);
				if($init_status == "publish"){
					$status = "pending";
				} 
				else if($init_status == "cancelled"){
					$status = "publish";
				}
				else{
					continue;
				}
				$this->ga_appointments_update_post_status($post_id, $status);
				$this->ga_appointments_update_post_status($post_id, $init_status);
			}
		} 	
		return $redirect_to;
	}
	public function ga_appointments_update_post_status($post_id, $status){
		wp_update_post(array(
					'ID' => $post_id,
					'post_status' => $status
		) );
	}
	public function remove_ga_appointments_bulk_actions($actions)
	{
		$post_types = array('ga_appointments', 'ga_providers', 'ga_services');

		if (in_array(get_post_type(), $post_types)) {
			unset($actions['edit']);
		}
		return $actions;
	}

	/**
	 * Admin Menu Settings and Fields
	 */
	public function register_settings_and_fields()
	{
		// options
		$page = 'ga_appointments_calendar';
		$section = 'ga_appointments_calendar_options_section';

		// Calendar Settings
		register_setting('ga_appointments_calendar_options', 'ga_appointments_calendar', array($this, 'validate_settings_fields')); // 2:options name

        register_setting('ga_appointments_schedule_options', 'ga_appointments_appointment_availability', array($this, 'validate_appointment_availability')); // global or server-based appointment availability

		// Calendar Schedule
		register_setting('ga_appointments_schedule_options', 'ga_appointments_work_schedule', array($this, 'validate_work_schedule_field')); // 2:options name

		// Calendar Breaks
		register_setting('ga_appointments_schedule_options', 'ga_appointments_schedule_breaks', array($this, 'validate_breaks_field')); // 2:options name

		// Calendar Holidays
		register_setting('ga_appointments_schedule_options', 'ga_appointments_holidays', array($this, 'validate_holidays_field')); // 2:options name

		// Calendar Policies
		register_setting('ga_appointments_policies_options', 'ga_appointments_policies', array($this, 'validate_policies_fields')); // 2:options name

		// Calendar Notifications
		register_setting('ga_appointments_notifications_options', 'ga_appointments_notifications', array($this, 'validate_notifications_fields')); // 2:options name

		// Calendar Sms Notifications
		register_setting('ga_appointments_sms_notifications_options', 'ga_appointments_sms_notifications', array($this, 'validate_sms_notifications_fields')); // 2:options name

		// GCAL	
		register_setting('ga_appointments_gcal_options', 'ga_appointments_gcal', array($this, 'validate_gcal_fields'));
		register_setting('ga_appointments_gcal_token_options', 'ga_appointments_gcal_token', array($this, 'validate_gcal_token_fields'));
		register_setting('ga_appointments_gcal_debug_options', 'ga_appointments_gcal_debug', array($this, 'validate_gcal_debug_fields'));


		// Calendar Display
		register_setting('ga_appointments_colors_options', 'ga_appointments_colors', array($this, 'validate_colors_fields')); // 2:options name

		//  Add to Calendar
		register_setting('ga_appointments_add_to_calendar_options', 'ga_appointments_add_to_calendar', array($this, 'validate_add_to_calendar_fields')); // 2:options name

		// Translation
		register_setting('ga_appointments_translation_options', 'ga_appointments_translation', array($this, 'validate_translation_fields')); // 2:options name

	}


	/**
	 * Validation: Settings
	 */
	public function validate_settings_fields($input)
	{
		// time zone validation
		$timezones = DateTimeZone::listIdentifiers();
		if (in_array_r($input['time_zone'], $timezones)) { } else {
			$input['time_zone'] = $this->time_zone;
		}

		// clear_appointment
		if (isset($input['clear_appointment'])) {
			$input['clear_appointment'] = absint($input['clear_appointment']);
		} else {
			$input['clear_appointment'] = 30;
		}

		// auto complete appointment
		if (isset($input['auto_complete']) && in_array($input['auto_complete'], array('yes', 'no', 'custom'))) {
			# do nothing
		} else {
			$input['auto_complete'] = 'no';
		}
		// auto complete appointment after x hours
		if (isset($input['auto_complete_custom']) && $input['auto_complete_custom'] > 0 && is_numeric($input['auto_complete_custom']) && $input['auto_complete_custom'] != '') {
			# do nothing
		} else {
			$input['auto_complete_custom'] = 10;
		}

		// week starts on
		if (isset($input['week_starts']) && in_array($input['week_starts'], array('sunday', 'monday'))) {
			# do nothing
		} else {
			$input['week_starts'] = 'sunday';
		}
		return $input;
	}

    /**
     * Validation: Appointment availability
     */
    public function validate_appointment_availability($input)
    {
        $options = array('global', 'non-global');

        if( !in_array( $input, $options, true ) ) {
            $input = 'non-global';
        }

        return $input;
    }

	/**
	 * Validation: Work Schedule
	 */
	public function validate_work_schedule_field($input)
	{
		if (!class_exists('ga_work_schedule')) {
			require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
		}

		$validate = new ga_work_schedule('no_provider');
		$schedule = $validate->validate_work_schedule($input);
		return $schedule;
	}

	/**
	 * Validation: Breaks
	 */
	public function validate_breaks_field($input)
	{
		if (!class_exists('ga_work_schedule')) {
			require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
		}

		$validate = new ga_work_schedule('no_provider');

		// Breaks
		$input = (array) $input;
		$input = $validate->validate_breaks($input);

		return $input;
	}


	/**
	 * Validation: Holidays
	 */
	public function validate_holidays_field($input)
	{
		if (!class_exists('ga_work_schedule')) {
			require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
		}
		$validate = new ga_work_schedule(false, 'no_provider', false, false);
		$input = $validate->validate_holidays($input);
		return $input;
	}

	/**
	 * Validation: Calendar Display
	 */
	public function validate_colors_fields($input)
	{
		return $input;
	}

	/**
	 * Validation: Policies
	 */
	public function validate_policies_fields($input)
	{
		// Defaults
		$valid_defaults = array('yes', 'no', 'custom');

		// Auto confirm
		if (isset($input['auto_confirm']) && in_array($input['auto_confirm'], $valid_defaults)) {
			# do nothing
		} else {
			$input['auto_confirm'] = 'yes';
		}

		// Allow Provider Confirm Appointments
		if (isset($input['provider_confirms']) && in_array($input['provider_confirms'], $valid_defaults)) {
			# do nothing
		} else {
			$input['provider_confirms'] = 'no';
		}


		// Cancellation Policy
		if (isset($input['cancellation_notice']) && in_array($input['cancellation_notice'], $valid_defaults)) {
			# do nothing
		} else {
			$input['cancellation_notice'] = 'no'; // hours
		}


		//Cancellation Policy timeframe
		if (isset($input['cancellation_notice_timeframe']) && $input['cancellation_notice_timeframe'] > 0 && is_numeric($input['cancellation_notice_timeframe'])) {
			# do nothing
		} else {
			$input['cancellation_notice_timeframe'] = 10;
		}


		// Provider Cancellation Policy
		if (isset($input['provider_cancellation_notice']) && in_array($input['provider_cancellation_notice'], $valid_defaults)) {
			# do nothing
		} else {
			$input['provider_cancellation_notice'] = 'no'; // hours
		}


		return $input;
	}


	/**
	 * Validation: GCAL fields
	 */
	public function validate_gcal_fields($input)
	{

		if (isset($input['reset_api'])) {
			$input['client_id']     = '';
			$input['client_secret'] = '';
			$input['access_code']   = '';
			$input['calendar_id']   = '';

			// Delete tokens
			update_option('ga_appointments_gcal_token', array('access_token' => '', 'refresh_token' => ''));
		}

		return $input;
	}

	/**
	 * Validation: GCAL Token
	 */
	public function validate_gcal_token_fields($input)
	{
		return $input;
	}

	/**
	 * Validation: GCAL Log
	 */
	public function validate_gcal_debug_fields($input)
	{
		return $input;
	}


	/**
	 * Validation: Add to Calendar
	 */
	public function validate_add_to_calendar_fields($input)
	{
		// GCalendar Show Link
		if (isset($input['show_links']) && in_array($input['show_links'], array('yes', 'no'))) {
			# do nothing
		} else {
			$input['show_links'] = 'yes'; // hours
		}

		// GCalendar Location
		if (isset($input['location'])) {
			$input['location'] = sanitize_text_field($input['location']);
		} else {
			$input['location'] = '';
		}

		// Add to Apple Calendar(ICS)
		if (isset($input['apple']) && $input['apple'] == 'yes') {
			# do nothing
		} else {
			$input['apple'] = 'no';
		}

		// Add to Google Calendar
		if (isset($input['google']) && $input['google'] == 'yes') {
			# do nothing
		} else {
			$input['google'] = 'no';
		}

		// Add to Outlook Calendar(ICS)
		if (isset($input['outlook']) && $input['outlook'] == 'yes') {
			# do nothing
		} else {
			$input['outlook'] = 'no';
		}

		// Add to Yahoo Calendar
		if (isset($input['yahoo']) && $input['yahoo'] == 'yes') {
			# do nothing
		} else {
			$input['yahoo'] = 'no';
		}

		if (isset($input['description'])) {
			$input['description'] = trim($input['description']);
		} else {
			$input['description'] = '';
		}

		return $input;
	}

	/**
	 * Validation: Translation fields
	 */
	public function validate_translation_fields($input)
	{
		return $input;
	}


	/**
	 * Validation: Notifications
	 */
	public function validate_notifications_fields($input)
	{
		/******************** WP_MAIL From & Email *******************/
		if (isset($input['from_name']) && !empty($input['from_name'])) {
			$input['from_name'] = esc_html($input['from_name']);
		} else {
			$input['from_name'] = get_bloginfo();
		}

		if (isset($input['from_email']) && !empty($input['from_email'])) {
			$input['from_email'] = esc_html($input['from_email']);
		} else {
			$input['from_email'] = get_bloginfo('admin_email');
		}

		if (isset($input['logo']) && !empty($input['logo'])) {
			$input['logo'] = esc_url($input['logo']);
		} else {
			$input['logo'] = '';
		}

		/******************** Add to calendar links in email *******************/
		if (isset($input['add_to_cal']) && in_array($input['add_to_cal'], array('yes', 'no'))) {
			# do nothing
		} else {
			$input['add_to_cal'] = 'yes';
		}

		if (isset($input['provider_add_to_cal']) && in_array($input['provider_add_to_cal'], array('yes', 'no'))) {
			# do nothing
		} else {
			$input['provider_add_to_cal'] = 'yes';
		}

		/******************** Which links *******************/
		// Add to Google Calendar
		if (isset($input['google']) && $input['google'] == 'yes') {
			# do nothing
		} else {
			$input['google'] = 'no';
		}

		// Add to Yahoo Calendar
		if (isset($input['yahoo']) && $input['yahoo'] == 'yes') {
			# do nothing
		} else {
			$input['yahoo'] = 'no';
		}

		// Add to Outlook Calendar
		if (isset($input['outlook']) && $input['outlook'] == 'yes') {
			# do nothing
		} else {
			$input['outlook'] = 'no';
		}

		/******************** Add to calendar links in email *******************/
		/******************** Client Notification *******************/
		// Client Notification Pending
		if (isset($input['client_notifications_pending']) && $input['client_notifications_pending'] == 'yes') {
			# do nothing
		} else {
			$input['client_notifications_pending'] = 'no';
		}

		// Client Notification Confirmed
		if (isset($input['client_notifications_confirmation']) && $input['client_notifications_confirmation'] == 'yes') {
			# do nothing
		} else {
			$input['client_notifications_confirmation'] = 'no';
		}

		// Client Notification Cancelled
		if (isset($input['client_notifications_cancelled']) && $input['client_notifications_cancelled'] == 'yes') {
			# do nothing
		} else {
			$input['client_notifications_cancelled'] = 'no';
		}
		/******************** Client Notification *******************/

		/******************** Provider Notification *******************/
		// Provider Notification Pending
		if (isset($input['provider_notifications_pending']) && $input['provider_notifications_pending'] == 'yes') {
			# do nothing
		} else {
			$input['provider_notifications_pending'] = 'no';
		}

		// Provider Notification Confirmed
		if (isset($input['provider_notifications_confirmation']) && $input['provider_notifications_confirmation'] == 'yes') {
			# do nothing
		} else {
			$input['provider_notifications_confirmation'] = 'no';
		}

		// Provider Notification Cancelled
		if (isset($input['provider_notifications_cancelled']) && $input['provider_notifications_cancelled'] == 'yes') {
			# do nothing
		} else {
			$input['provider_notifications_cancelled'] = 'no';
		}
		/******************** Provider Notification *******************/

		require_once('includes/ga_emails.php');
		$emails = new ga_appointment_emails();

		/******************** SUBJECT EMAIL *******************/
		// Pending Email Subject
		if (isset($input['pending_subject']) && !empty($input['pending_subject'])) {
			$input['pending_subject'] = trim(wp_filter_nohtml_kses($input['pending_subject']));
		} else {
			$input['pending_subject'] = $emails->pending_subject();
		}

		// Confirmation Email Subject
		if (isset($input['confirmation_subject']) && !empty($input['confirmation_subject'])) {
			$input['confirmation_subject'] = trim(wp_filter_nohtml_kses($input['confirmation_subject']));
		} else {
			$input['confirmation_subject'] = $emails->confirmation_subject();
		}

		// Cancelled Email Subject
		if (isset($input['cancelled_subject']) && !empty($input['cancelled_subject'])) {
			$input['cancelled_subject'] = trim(wp_filter_nohtml_kses($input['cancelled_subject']));
		} else {
			$input['cancelled_subject'] = $emails->cancelled_subject();
		}

		// Provider Pending Email Subject
		if (isset($input['provider_pending_subject']) && !empty($input['provider_pending_subject'])) {
			$input['provider_pending_subject'] = trim(wp_filter_nohtml_kses($input['provider_pending_subject']));
		} else {
			$input['provider_pending_subject'] = $emails->provider_pending_subject();
		}

		// Provider Confirmation Email Subject
		if (isset($input['provider_confirmation_subject']) && !empty($input['provider_confirmation_subject'])) {
			$input['provider_confirmation_subject'] = trim(wp_filter_nohtml_kses($input['provider_confirmation_subject']));
		} else {
			$input['provider_confirmation_subject'] = $emails->provider_confirmation_subject();
		}


		// Provider Cancelled Email Subject
		if (isset($input['provider_cancelled_subject']) && !empty($input['provider_cancelled_subject'])) {
			$input['provider_cancelled_subject'] = trim(wp_filter_nohtml_kses($input['provider_cancelled_subject']));
		} else {
			$input['provider_cancelled_subject'] = $emails->provider_cancelled_subject();
		}
		/******************** SUBJECT EMAIL *******************/



		/******************** MULTI SUBJECT EMAIL *******************/
		// Pending Email Subject
		if (isset($input['pending_multi_subject']) && !empty($input['pending_multi_subject'])) {
			$input['pending_multi_subject'] = trim(wp_filter_nohtml_kses($input['pending_multi_subject']));
		} else {
			$input['pending_multi_subject'] = $emails->pending_multi_subject();
		}

		// Confirmation Email Subject
		if (isset($input['confirmation_multi_subject']) && !empty($input['confirmation_multi_subject'])) {
			$input['confirmation_multi_subject'] = trim(wp_filter_nohtml_kses($input['confirmation_multi_subject']));
		} else {
			$input['confirmation_multi_subject'] = $emails->confirmation_multi_subject();
		}

		// Provider Pending Email Subject
		if (isset($input['provider_pending_multi_subject']) && !empty($input['provider_pending_multi_subject'])) {
			$input['provider_pending_multi_subject'] = trim(wp_filter_nohtml_kses($input['provider_pending_multi_subject']));
		} else {
			$input['provider_pending_multi_subject'] = $emails->provider_pending_multi_subject();
		}

		// Provider Confirmation Email Subject
		if (isset($input['provider_confirmation_multi_subject']) && !empty($input['provider_confirmation_multi_subject'])) {
			$input['provider_confirmation_multi_subject'] = trim(wp_filter_nohtml_kses($input['provider_confirmation_multi_subject']));
		} else {
			$input['provider_confirmation_multi_subject'] = $emails->provider_confirmation_multi_subject();
		}

		/******************** MULTI SUBJECT EMAIL *******************/



		/******************** BODY TITLE *******************/
		// Pending Email Title
		if (isset($input['pending_title']) && !empty($input['pending_title'])) {
			$input['pending_title'] = trim(wp_filter_nohtml_kses($input['pending_title']));
		} else {
			$input['pending_title'] = $emails->pending_title();
		}

		// Confirmation Email Title
		if (isset($input['confirmation_title']) && !empty($input['confirmation_title'])) {
			$input['confirmation_title'] = trim(wp_filter_nohtml_kses($input['confirmation_title']));
		} else {
			$input['confirmation_title'] = $emails->confirmation_title();
		}

		// Cancelled Email Title
		if (isset($input['cancelled_title']) && !empty($input['cancelled_title'])) {
			$input['cancelled_title'] = trim(wp_filter_nohtml_kses($input['cancelled_title']));
		} else {
			$input['cancelled_title'] = $emails->cancelled_title();
		}

		// Provider Pending Email Title
		if (isset($input['provider_pending_title']) && !empty($input['provider_pending_title'])) {
			$input['provider_pending_title'] = trim(wp_filter_nohtml_kses($input['provider_pending_title']));
		} else {
			$input['provider_pending_title'] = $emails->provider_pending_title();
		}

		// Provider Confirmation Email Title
		if (isset($input['provider_confirmation_title']) && !empty($input['provider_confirmation_title'])) {
			$input['provider_confirmation_title'] = trim(wp_filter_nohtml_kses($input['provider_confirmation_title']));
		} else {
			$input['provider_confirmation_title'] = $emails->provider_confirmation_title();
		}


		// Provider Cancelled Email Title
		if (isset($input['provider_cancelled_title']) && !empty($input['provider_cancelled_title'])) {
			$input['provider_cancelled_title'] = trim(wp_filter_nohtml_kses($input['provider_cancelled_title']));
		} else {
			$input['provider_cancelled_title'] = $emails->provider_cancelled_title();
		}

		/******************** BODY TITLE *******************/

		/******************** BODY MULTIPLE BOOKINGS TITLE *******************/
		// Pending Email Title
		if (isset($input['pending_multi_title']) && !empty($input['pending_multi_title'])) {
			$input['pending_multi_title'] = trim(wp_filter_nohtml_kses($input['pending_multi_title']));
		} else {
			$input['pending_multi_title'] = $emails->pending_multi_title();
		}

		// Confirmation Email Title
		if (isset($input['confirmation_multi_title']) && !empty($input['confirmation_multi_title'])) {
			$input['confirmation_multi_title'] = trim(wp_filter_nohtml_kses($input['confirmation_multi_title']));
		} else {
			$input['confirmation_multi_title'] = $emails->confirmation_multi_title();
		}

		// Provider Pending Email Title
		if (isset($input['provider_pending_multi_title']) && !empty($input['provider_pending_multi_title'])) {
			$input['provider_pending_multi_title'] = trim(wp_filter_nohtml_kses($input['provider_pending_multi_title']));
		} else {
			$input['provider_pending_multi_title'] = $emails->provider_pending_multi_title();
		}

		// Provider Confirmation Email Title
		if (isset($input['provider_confirmation_multi_title']) && !empty($input['provider_confirmation_multi_title'])) {
			$input['provider_confirmation_multi_title'] = trim(wp_filter_nohtml_kses($input['provider_confirmation_multi_title']));
		} else {
			$input['provider_confirmation_multi_title'] = $emails->provider_confirmation_multi_title();
		}

		/******************** BODY MULTIPLE BOOKINGS TITLE *******************/


		/******************** BODY EMAIL *******************/
		// Pending Email Body
		if (isset($input['pending_body']) && !empty($input['pending_body'])) {
			$input['pending_body'] = trim($input['pending_body']);
		} else {
			$input['pending_body'] = $emails->pending_body();
		}

		// Confirmation Email Body
		if (isset($input['confirmation_body']) && !empty($input['confirmation_body'])) {
			$input['confirmation_body'] = trim($input['confirmation_body']);
		} else {
			$input['confirmation_body'] = $emails->confirmation_body();
		}


		// Cancelled Email Body
		if (isset($input['cancelled_body']) && !empty($input['cancelled_body'])) {
			$input['cancelled_body'] = trim($input['cancelled_body']);
		} else {
			$input['cancelled_body'] = $emails->cancelled_body();
		}

		// Provider Confirmation Email Body
		if (isset($input['provider_pending_body']) && !empty($input['provider_pending_body'])) {
			$input['provider_pending_body'] = trim($input['provider_pending_body']);
		} else {
			$input['provider_pending_body'] = $emails->provider_pending_body();
		}

		// Provider Confirmation Email Body
		if (isset($input['provider_confirmation_body']) && !empty($input['provider_confirmation_body'])) {
			$input['provider_confirmation_body'] = trim($input['provider_confirmation_body']);
		} else {
			$input['provider_confirmation_body'] = $emails->provider_confirmation_body();
		}

		// Provider Cancelled Email Body
		if (isset($input['provider_cancelled_body']) && !empty($input['provider_cancelled_body'])) {
			$input['provider_cancelled_body'] = trim($input['provider_cancelled_body']);
		} else {
			$input['provider_cancelled_body'] = $emails->provider_cancelled_body();
		}
		/******************** BODY EMAIL *******************/


		/******************** MULTIPLE BODY EMAIL *******************/
		// Provider Confirmation Email Body
		if (isset($input['pending_multi_body']) && !empty($input['pending_multi_body'])) {
			$input['pending_multi_body'] = trim($input['pending_multi_body']);
		} else {
			$input['pending_multi_body'] = $emails->pending_multi_body();
		}

		// Confirmation Email Body
		if (isset($input['confirmation_multi_body']) && !empty($input['confirmation_multi_body'])) {
			$input['confirmation_multi_body'] = trim($input['confirmation_multi_body']);
		} else {
			$input['confirmation_multi_body'] = $emails->confirmation_multi_body();
		}

		if (isset($input['provider_pending_multi_body']) && !empty($input['provider_pending_multi_body'])) {
			$input['provider_pending_multi_body'] = trim($input['provider_pending_multi_body']);
		} else {
			$input['provider_pending_multi_body'] = $emails->provider_pending_multi_body();
		}

		// Provider Confirmation Email Body
		if (isset($input['provider_confirmation_multi_body']) && !empty($input['provider_confirmation_multi_body'])) {
			$input['provider_confirmation_multi_body'] = trim($input['provider_confirmation_multi_body']);
		} else {
			$input['provider_confirmation_multi_body'] = $emails->provider_confirmation_multi_body();
		}
		/******************** MULTIPLE BODY EMAIL *******************/

		return $input;
	}




	/**
	 * Validation: Sms Notifications
	 */
	public function validate_sms_notifications_fields($input)
	{
		/**
		 * Client Sms Notifications
		 */

		//wp_die( print_r( $input ) );

		// Client Notification Pending
		if (isset($input['client_sms_pending']) && $input['client_sms_pending'] == 'yes') {
			# do nothing
		} else {
			$input['client_sms_pending'] = 'no';
		}

		// Client Notification Confirmed
		if (isset($input['client_sms_confirmation']) && $input['client_sms_confirmation'] == 'yes') {
			# do nothing
		} else {
			$input['client_sms_confirmation'] = 'no';
		}

		// Client Notification Cancelled
		if (isset($input['client_sms_cancelled']) && $input['client_sms_cancelled'] == 'yes') {
			# do nothing
		} else {
			$input['client_sms_cancelled'] = 'no';
		}

		/**
		 * Provider Sms Notifications
		 */
		// Provider Notification Pending
		if (isset($input['provider_sms_pending']) && $input['provider_sms_pending'] == 'yes') {
			# do nothing
		} else {
			$input['provider_sms_pending'] = 'no';
		}

		// Provider Notification Confirmed
		if (isset($input['provider_sms_confirmation']) && $input['provider_sms_confirmation'] == 'yes') {
			# do nothing
		} else {
			$input['provider_sms_confirmation'] = 'no';
		}

		// Provider Notification Cancelled
		if (isset($input['provider_sms_cancelled']) && $input['provider_sms_cancelled'] == 'yes') {
			# do nothing
		} else {
			$input['provider_sms_cancelled'] = 'no';
		}

		/**
		 * Client SMS Body
		 */
		require_once('includes/ga_emails.php');
		$emails = new ga_appointment_emails();
		// Pending Email Body
		if (isset($input['pending_body']) && !empty($input['pending_body'])) {
			$input['pending_body'] = strip_tags($input['pending_body']);
		} else {
			$input['pending_body'] = $emails->pending_sms_body();
		}

		// Confirmation Email Body
		if (isset($input['confirmation_body']) && !empty($input['confirmation_body'])) {
			$input['confirmation_body'] = strip_tags($input['confirmation_body']);
		} else {
			$input['confirmation_body'] = $emails->confirmation_sms_body();
		}


		// Cancelled Email Body
		if (isset($input['cancelled_body']) && !empty($input['cancelled_body'])) {
			$input['cancelled_body'] = strip_tags($input['cancelled_body']);
		} else {
			$input['cancelled_body'] = $emails->cancelled_sms_body();
		}

		/**
		 * Provider SMS Body
		 */
		// Pending Email Body
		if (isset($input['provider_pending_body']) && !empty($input['provider_pending_body'])) {
			$input['provider_pending_body'] = strip_tags($input['provider_pending_body']);
		} else {
			$input['provider_pending_body'] = $emails->provider_pending_sms_body();
		}

		// Confirmation Email Body
		if (isset($input['provider_confirmation_body']) && !empty($input['provider_confirmation_body'])) {
			$input['provider_confirmation_body'] = strip_tags($input['provider_confirmation_body']);
		} else {
			$input['provider_confirmation_body'] = $emails->provider_confirmation_sms_body();
		}


		// Cancelled Email Body
		if (isset($input['provider_cancelled_body']) && !empty($input['provider_cancelled_body'])) {
			$input['provider_cancelled_body'] = strip_tags($input['provider_cancelled_body']);
		} else {
			$input['provider_cancelled_body'] = $emails->provider_cancelled_sms_body();
		}

		return $input;
	}


	/**
	 * Admin Submenu
	 */
	public function settings_submenu()
	{
		add_submenu_page('ga_appointments_settings', 'Settings', 'Settings', 'manage_options', 'ga_appointments_settings');
	}


	/**
	 * Admin Menu Settings Display
	 */
	public function settings_page()
	{ ?>
		<div class="wrap" id="ga_appointments_settings">
			<h1>gAppointments Settings</h1>
			<?php
					$this->render_tab();
					?>
		</div>
	<?php }


		/**
		 * CB
		 */
		public function ga_appointments_calendar_options_section_cb()
		{ }

		/**
		 * Widgets Options
		 */
		public function ga_appointments_calendar()
		{ }

		/**
		 * Settings Tabs
		 */
		public function get_tabs()
		{
			$tabs = array(
				'main'                  => 'General',
				'calendar'              => 'Calendar',
				'policies'              => 'Policies',
				'notifications'         => 'Notifications',
				'sms_notifications'     => 'Sms Notifications',
				'gcal'                  => 'Google Calendar',
				'display'               => 'Display',
				'add_to_calendar'       => 'Add To Calendar',
				'translation'           => 'Translation'
			);

			return $tabs;
		}

		/**
		 * Get current tab
		 */
		public function get_current_tab()
		{
			$tabs = $this->get_tabs();
			if (empty($_GET['tab'])) {
				return key($tabs);
			}

			if (!array_key_exists($_GET['tab'], $tabs)) {
				return key($tabs);
			}

			return $_GET['tab'];
		}

		/**
		 * Get tab link
		 */
		private function _get_tab_link($tab)
		{
			$url = add_query_arg('tab', $tab);
			$url = remove_query_arg(array('updated', 'added'), $url);
			return $url;
		}

		/**
		 *	Render the Settings page
		 */
		function render_tab()
		{
			$tabs = $this->get_tabs();
			$tab = $this->get_current_tab();

			?>
		<div class="wrap appointments-settings">
			<?php if (isset($_GET['updated'])) : ?>
				<div class="updated">
					<p><?php _e('Settings updated', 'appointments'); ?></p>
				</div>
			<?php endif; ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $stub => $title) : ?>
					<a href="<?php echo esc_url($this->_get_tab_link($stub)); ?>" class="nav-tab <?php echo $stub == $tab ? 'nav-tab-active' : ''; ?>" id="app_tab_<?php echo $stub; ?>">
						<?php echo esc_html($title); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
					if ($tab == 'main') {
						$this->main_tab_markup();
					} elseif ($tab == 'calendar') {
						$this->calendar_tab_markup();
					} elseif ($tab == 'policies') {
						$this->policies_tab_markup();
					} elseif ($tab == 'notifications') {
						$this->notifications_tab_markup();
					} elseif ($tab == 'sms_notifications') {
						$this->sms_notifications_tab_markup();
					} elseif ($tab == 'gcal') {
						$this->gcal_tab_markup();
					} elseif ($tab == 'display') {
						$this->display_tab_markup();
					} elseif ($tab == 'add_to_calendar') {
						$this->add_to_calendar_tab_markup();
					} elseif ($tab == 'translation') {
						$this->translation_tab_markup();
					}
					?>

		</div>
	<?php
		}

		public function main_tab_markup()
		{
			$options = get_option('ga_appointments_calendar');
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_calendar_options'); // needed to save the data
					?>

			<table class="form-table">

				<tr>
					<th scope="row">
						<label for="clear_time">Time Zone</label>
					</th>
					<td>
						<select name="ga_appointments_calendar[time_zone]" id="time_zone">
							<?php
							$sel_timezone = isset($options['time_zone'] ) ? $options['time_zone'] : $this->time_zone;
							$timezones = DateTimeZone::listIdentifiers();
							foreach ( $timezones as $timezone ) {
								echo '<option value="' . $timezone . '"' . ( $timezone == $sel_timezone ? ' selected' : '' ) . '>' . esc_html( $timezone, true ) . '</option>' . "\n";
							}
							?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Cancel unpaid appointments after (mins)</label>
					</th>
					<td>
						<?php
								$clear_appointment = isset($options['clear_appointment']) ? $options['clear_appointment'] : 30;
								?>
						<input type="number" class="small-text" name="ga_appointments_calendar[clear_appointment]" id="clear_time" value="<?php echo $clear_appointment; ?>">
						<br>
						<p class="description">Pending unpaid appointments will be automatically cancelled after this set time and that appointment time will be freed. Enter 0 to disable.</p>

					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Auto complete appointments</label>
					</th>
					<td>
						<?php
								$auto_complete = isset($options['auto_complete']) ? $options['auto_complete'] : 'no';
								?>
						<select name="ga_appointments_calendar[auto_complete]" id="auto_complete">
							<option value="no" <?php selected('no', $auto_complete); ?>>No</option>
							<option value="yes" <?php selected('yes', $auto_complete); ?>>Yes</option>
							<option value="custom" <?php selected('custom', $auto_complete); ?>>Complete after x hours</option>
						</select>
						<br>
						<p class="description">Confirmed appointments will be automatically completed after the duration ended.</p>
					</td>
				</tr>

				<tr id="tr_auto_complete_custom" class="<?php if ($auto_complete != 'custom') echo 'tr_auto_complete_custom_hide' ?>">
					<th scope="row">
						<label for="clear_time">Auto complete appointments after x hours</label>
					</th>
					<td>
						<?php $auto_complete_custom = isset($options['auto_complete_custom']) ? $options['auto_complete_custom'] : 10; ?>
						<input type="number" min="1" name="ga_appointments_calendar[auto_complete_custom]" id="auto_complete_custom" value="<?php echo $auto_complete_custom; ?>">
						<br>
						<p class="description">Auto complete appointment before selected amount of hours</p>
					</td>


				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Week Starts On</label>
					</th>
					<td>
						<?php
								$week_starts = isset($options['week_starts']) ? $options['week_starts'] : 'sunday';
								?>
						<select name="ga_appointments_calendar[week_starts]" id="week_starts">
							<option value="sunday" <?php selected('sunday', $week_starts); ?>>Sunday</option>
							<option value="monday" <?php selected('monday', $week_starts); ?>>Monday</option>
						</select>
						<br>
						<p class="description">Set calendar first day of the week.</p>
					</td>
				</tr>

			</table>
			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>
	<?php }


		public function calendar_tab_markup()
		{
			$work      = get_option('ga_appointments_work_schedule');
			$breaks    = get_option('ga_appointments_schedule_breaks');
			$holidays  = get_option('ga_appointments_holidays');
			$availability  = get_option('ga_appointments_appointment_availability');
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields("ga_appointments_schedule_options"); // needed to save the data
					//settings_fields('ga_appointments_holidays_options');     // needed to save the data
					?>
            <p> Here, the default work schedule and availability can be configured for all booking services that are not assigned to a specific service provider.</p>
            <table class="form-table">

                <tr>
                    <td>
                        <div class="grid-row">
                            <div class="grid-lg-2 grid-md-3 grid-sm-12 grid-xs-12">
                                <label for="ga_provider_availability"><b>Availability</b></label>
                            </div>
                            <div class="grid-lg-10 grid-md-9 grid-sm-12 grid-xs-12">
                                <?php
                                    $availability = $availability !== false ? $availability : 'non-global';
                                ?>
                                <select class="form-control" name="ga_appointments_appointment_availability" id="ga_provider_availability">
                                    <option value="global" <?php selected( 'global', $availability ); ?>>Global appointment availability</option>
                                    <option value="non-global" <?php selected( 'non-global', $availability ); ?>>Service-based appointment availability</option>
                                </select>
                                <p class="description">1. Global - all appointments from all services assigned to the provider will be hidden in the booking calendar form field.<br>
                                    2. Service-based - only specific service appointments will be hidden (based on booking service form field value).<br>
                                    PS All Google Calendar two-way sync appointments will also be hidden.</p>
                            </div>
                        </div>
                    </td>
                </tr>

                <tr>
					<td>
						<label for="clear_time"><b>Schedule</b></label>
						<div class="work_schedule_container">
							<div id="ga_provider_details">
								<?php
										if (!class_exists('ga_work_schedule')) {
											require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
										}
										$schedule = new ga_work_schedule('no_provider');
										echo $schedule->display_schedule('ga_appointments_work_schedule', $work);

										?>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<td>
						<label for="clear_time"><b>Breaks</b></label>
						<div class="work_schedule_container">
							<div id="ga_provider_details">
								<?php
										echo $schedule->display_breaks('ga_appointments_schedule_breaks', $breaks);
										?>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<td>
						<label for="clear_time"><b>Holidays</b></label>
						<p class="description">Format: year, month, day</p>
						<div class="work_schedule_container">
							<div id="ga_provider_details">
								<?php
										echo $schedule->display_holidays('ga_appointments_holidays', $holidays);
										?>
							</div>
						</div>
					</td>
				</tr>
			</table>


			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>

	<?php }



		public function notifications_tab_markup()
		{
			$options = get_option('ga_appointments_notifications');

			require_once('includes/ga_emails.php');
			$emails = new ga_appointment_emails();
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_notifications_options'); // needed to save the data
					?>
			<p>Appointment notifications are sent to the client/service provider when an appointment is pending, confirmed or cancelled.</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">From name</label>
					</th>
					<td>
						<?php
								$from_name = isset($options['from_name']) ? $options['from_name'] : get_bloginfo();
								?>
						<input type="text" name="ga_appointments_notifications[from_name]" class="regular-text" value="<?php echo $from_name; ?>">
						<p class="description">Change sender name</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">From email</label>
					</th>
					<td>
						<?php
								$from_email = isset($options['from_email']) ? $options['from_email'] : get_bloginfo('admin_email');
								?>
						<input type="text" name="ga_appointments_notifications[from_email]" class="regular-text" value="<?php echo $from_email; ?>">
						<p class="description">Change sender email</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Email logo</label>
					</th>
					<td>
						<?php
								$logo = isset($options['logo']) ? $options['logo'] : '';
								?>
						<input type="text" name="ga_appointments_notifications[logo]" class="regular-text" value="<?php echo $logo; ?>">
						<p class="description">Logo image url</p>
					</td>
				</tr>
			</table>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Add calendar links in the client notification emails</label>
					</th>
					<td>
						<?php
								$add_to_cal = isset($options['add_to_cal']) ? $options['add_to_cal'] : 'yes';
								?>
						<select name="ga_appointments_notifications[add_to_cal]" id="add_to_cal">
							<option value="yes" <?php selected('yes', $add_to_cal); ?>>Yes</option>
							<option value="no" <?php selected('no', $add_to_cal); ?>>No</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Add calendar links in the provider notification emails</label>
					</th>
					<td>
						<?php
								$provider_add_to_cal = isset($options['provider_add_to_cal']) ? $options['provider_add_to_cal'] : 'yes';
								?>
						<select name="ga_appointments_notifications[provider_add_to_cal]" id="provider_add_to_cal">
							<option value="yes" <?php selected('yes', $provider_add_to_cal); ?>>Yes</option>
							<option value="no" <?php selected('no', $provider_add_to_cal); ?>>No</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Show calendar links</label>
					</th>
					<td>
						<?php
								$add_to_google   = isset($options['google'])  ? $options['google'] : 'yes';
								$add_to_yahoo    = isset($options['yahoo'])   ? $options['yahoo'] : 'yes';
								$add_to_outlook  = isset($options['outlook']) ? $options['outlook'] : 'yes';
								?>

						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[google]" id="add_to_calendar_google" value="yes" <?php checked('yes', $add_to_google); ?>><span class="ga_checkbox_slider"></span></label> Google Calendar</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[yahoo]" id="add_to_calendar_yahoo" value="yes" <?php checked('yes', $add_to_yahoo); ?>><span class="ga_checkbox_slider"></span></label> Yahoo! Calendar</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[outlook]" id="add_to_calendar_outlook" value="yes" <?php checked('yes', $add_to_outlook); ?>><span class="ga_checkbox_slider"></span></label> Outlook Calendar</div>

						<p class="description">Select which links to be included</p>
					</td>
				</tr>
			</table>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Send notifications to client</label>
					</th>
					<td>
						<?php
								$client_notifications_pending      = isset($options['client_notifications_pending']) ? $options['client_notifications_pending'] : 'yes';
								$client_notifications_confirmation = isset($options['client_notifications_confirmation']) ? $options['client_notifications_confirmation'] : 'yes';
								$client_notifications_cancelled    = isset($options['client_notifications_cancelled']) ? $options['client_notifications_cancelled'] : 'yes';
								?>

						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[client_notifications_pending]" id="client_notifications_pending" value="yes" <?php checked('yes', $client_notifications_pending); ?>><span class="ga_checkbox_slider"></span></label> Pending email</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[client_notifications_confirmation]" id="client_notifications_confirmation" value="yes" <?php checked('yes', $client_notifications_confirmation); ?>><span class="ga_checkbox_slider"></span></label> Confirmation email</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[client_notifications_cancelled]" id="client_notifications_cancelled" value="yes" <?php checked('yes', $client_notifications_cancelled); ?>><span class="ga_checkbox_slider"></span></label> Cancelled email</div>

					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Send notifications to service provider</label>
					</th>
					<td>
						<?php
								$provider_notifications_pending      = isset($options['provider_notifications_pending']) ? $options['provider_notifications_pending'] : 'yes';
								$provider_notifications_confirmation = isset($options['provider_notifications_confirmation']) ? $options['provider_notifications_confirmation'] : 'yes';
								$provider_notifications_cancelled    = isset($options['provider_notifications_cancelled']) ? $options['provider_notifications_cancelled'] : 'yes';
								?>

						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[provider_notifications_pending]" id="provider_notifications_pending" value="yes" <?php checked('yes', $provider_notifications_pending); ?>><span class="ga_checkbox_slider"></span></label> Pending email</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[provider_notifications_confirmation]" id="provider_notifications_confirmation" value="yes" <?php checked('yes', $provider_notifications_confirmation); ?>><span class="ga_checkbox_slider"></span></label> Confirmation email</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_notifications[provider_notifications_cancelled]" id="provider_notifications_cancelled" value="yes" <?php checked('yes', $provider_notifications_cancelled); ?>><span class="ga_checkbox_slider"></span></label> Cancelled email</div>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="submit" value="Save All Changes" class="button-primary">
			</p>

			<!-- Client notifications -->
			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$pending_subject       = isset($options['pending_subject']) ? $options['pending_subject'] : $emails->pending_subject();
								$pending_title         = isset($options['pending_title']) ? $options['pending_title'] : $emails->pending_title();
								$pending_body          = isset($options['pending_body']) ? $options['pending_body'] : $emails->pending_body();
								?>
						<h2 class="ga_email_template_title">Client Appointment Pending Email</h2>
						<label>Subject<input name="ga_appointments_notifications[pending_subject]" class="large-text" value="<?php echo $pending_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[pending_title]" class="large-text" value="<?php echo $pending_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[pending_body]" class="large-text"><?php echo $pending_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$confirmation_subject       = isset($options['confirmation_subject']) ? $options['confirmation_subject'] : $emails->confirmation_subject();
								$confirmation_title         = isset($options['confirmation_title']) ? $options['confirmation_title'] : $emails->confirmation_title();
								$confirmation_body          = isset($options['confirmation_body']) ? $options['confirmation_body'] : $emails->confirmation_body();
								?>
						<h2 class="ga_email_template_title">Client Appointment Confirmation Email</h2>
						<label>Subject<input name="ga_appointments_notifications[confirmation_subject]" class="large-text" value="<?php echo $confirmation_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[confirmation_title]" class="large-text" value="<?php echo $confirmation_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[confirmation_body]" class="large-text"><?php echo $confirmation_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$cancelled_subject = isset($options['cancelled_subject']) ? $options['cancelled_subject'] : $emails->cancelled_subject();
								$cancelled_title   = isset($options['cancelled_title']) ? $options['cancelled_title'] : $emails->cancelled_title();
								$cancelled_body    = isset($options['cancelled_body']) ? $options['cancelled_body'] : $emails->cancelled_body();
								?>
						<h2 class="ga_email_template_title">Client Appointment Cancelled Email</h2>
						<label>Subject<input name="ga_appointments_notifications[cancelled_subject]" class="large-text" value="<?php echo $cancelled_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[cancelled_title]" class="large-text" value="<?php echo $cancelled_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[cancelled_body]" class="large-text"><?php echo $cancelled_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %appointment_date% | %message% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<!-- Provider notifications -->
			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$provider_pending_subject       = isset($options['provider_pending_subject']) ? $options['provider_pending_subject'] : $emails->provider_pending_subject();
								$provider_pending_title         = isset($options['provider_pending_title']) ? $options['provider_pending_title'] : $emails->provider_pending_title();
								$provider_pending_body          = isset($options['provider_pending_body']) ? $options['provider_pending_body'] : $emails->provider_pending_body();
								?>
						<h2 class="ga_email_template_title">Provider Appointment Pending Email</h2>
						<label>Subject<input name="ga_appointments_notifications[provider_pending_subject]" class="large-text" value="<?php echo $provider_pending_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[provider_pending_title]" class="large-text" value="<?php echo $provider_pending_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[provider_pending_body]" class="large-text"><?php echo $provider_pending_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$provider_confirmation_subject       = isset($options['provider_confirmation_subject']) ? $options['provider_confirmation_subject'] : $emails->provider_confirmation_subject();
								$provider_confirmation_title         = isset($options['provider_confirmation_title']) ? $options['provider_confirmation_title'] : $emails->provider_confirmation_title();
								$provider_confirmation_body          = isset($options['provider_confirmation_body']) ? $options['provider_confirmation_body'] : $emails->provider_confirmation_body();
								?>
						<h2 class="ga_email_template_title">Provider Appointment Confirmation Email</h2>
						<label>Subject<input name="ga_appointments_notifications[provider_confirmation_subject]" class="large-text" value="<?php echo $provider_confirmation_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[provider_confirmation_title]" class="large-text" value="<?php echo $provider_confirmation_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[provider_confirmation_body]" class="large-text"><?php echo $provider_confirmation_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$provider_cancelled_subject = isset($options['provider_cancelled_subject']) ? $options['provider_cancelled_subject'] : $emails->provider_cancelled_subject();
								$provider_cancelled_title   = isset($options['provider_cancelled_title']) ? $options['provider_cancelled_title'] : $emails->provider_cancelled_title();
								$provider_cancelled_body    = isset($options['provider_cancelled_body']) ? $options['provider_cancelled_body'] : $emails->provider_cancelled_body();
								?>
						<h2 class="ga_email_template_title">Provider Appointment Cancelled Email</h2>
						<label>Subject<input name="ga_appointments_notifications[provider_cancelled_subject]" class="large-text" value="<?php echo $provider_cancelled_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[provider_cancelled_title]" class="large-text" value="<?php echo $provider_cancelled_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[provider_cancelled_body]" class="large-text"><?php echo $provider_cancelled_body; ?></textarea></label>

						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %message% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>


			<!-- MULTIPLE BOOKINGS -->
			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$pending_multi_subject = isset($options['pending_multi_subject']) ? $options['pending_multi_subject'] : $emails->pending_multi_subject();
								$pending_multi_title   = isset($options['pending_multi_title']) ? $options['pending_multi_title'] : $emails->pending_multi_title();
								$pending_multi_body    = isset($options['pending_multi_body']) ? $options['pending_multi_body'] : $emails->pending_multi_body();
								?>
						<h2 class="ga_email_template_title"><b>Multiple Bookings</b> - Client Pending Email</h2>
						<label>Subject<input name="ga_appointments_notifications[pending_multi_subject]" class="large-text" value="<?php echo $pending_multi_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[pending_multi_title]" class="large-text" value="<?php echo $pending_multi_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[pending_multi_body]" class="large-text"><?php echo $pending_multi_body; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$confirmation_multi_subject = isset($options['confirmation_multi_subject']) ? $options['confirmation_multi_subject'] : $emails->confirmation_multi_subject();
								$confirmation_multi_title   = isset($options['confirmation_multi_title']) ? $options['confirmation_multi_title'] : $emails->confirmation_multi_title();
								$confirmation_multi_body    = isset($options['confirmation_multi_body']) ? $options['confirmation_multi_body'] : $emails->confirmation_multi_body();
								?>
						<h2 class="ga_email_template_title"><b>Multiple Bookings</b> - Client Confirmation Email</h2>
						<label>Subject<input name="ga_appointments_notifications[confirmation_multi_subject]" class="large-text" value="<?php echo $confirmation_multi_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[confirmation_multi_title]" class="large-text" value="<?php echo $confirmation_multi_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[confirmation_multi_body]" class="large-text"><?php echo $confirmation_multi_body; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$provider_pending_multi_subject = isset($options['provider_pending_multi_subject']) ? $options['provider_pending_multi_subject'] : $emails->provider_pending_multi_subject();
								$provider_pending_multi_title   = isset($options['provider_pending_multi_title']) ? $options['provider_pending_multi_title'] : $emails->provider_pending_multi_title();
								$provider_pending_multi_body    = isset($options['provider_pending_multi_body']) ? $options['provider_pending_multi_body'] : $emails->provider_pending_multi_body();
								?>
						<h2 class="ga_email_template_title"><b>Multiple Bookings</b> - Provider Pending Email</h2>
						<label>Subject<input name="ga_appointments_notifications[provider_pending_multi_subject]" class="large-text" value="<?php echo $provider_pending_multi_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[provider_pending_multi_title]" class="large-text" value="<?php echo $provider_pending_multi_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[provider_pending_multi_body]" class="large-text"><?php echo $provider_pending_multi_body; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php
								$provider_confirmation_multi_subject = isset($options['provider_confirmation_multi_subject']) ? $options['provider_confirmation_multi_subject'] : $emails->provider_confirmation_multi_subject();
								$provider_confirmation_multi_title   = isset($options['provider_confirmation_multi_title']) ? $options['provider_confirmation_multi_title'] : $emails->provider_confirmation_multi_title();
								$provider_confirmation_multi_body    = isset($options['provider_confirmation_multi_body']) ? $options['provider_confirmation_multi_body'] : $emails->provider_confirmation_multi_body();
								?>
						<h2 class="ga_email_template_title"><b>Multiple Bookings</b> - Provider Confirmation Email</h2>
						<label>Subject<input name="ga_appointments_notifications[provider_confirmation_multi_subject]" class="large-text" value="<?php echo $provider_confirmation_multi_subject; ?>" type="text"></label>
						<label class="ga_email_template">Body heading title<input name="ga_appointments_notifications[provider_confirmation_multi_title]" class="large-text" value="<?php echo $provider_confirmation_multi_title; ?>" type="text"></label>
						<label>Body<textarea cols="90" rows="10" name="ga_appointments_notifications[provider_confirmation_multi_body]" class="large-text"><?php echo $provider_confirmation_multi_body; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>

			</table>

			<p class="submit">
				<input type="submit" name="submit" value="Save All Changes" class="button-primary">
			</p>
		</form>

	<?php }



		public function sms_notifications_tab_markup()
		{
			$options = get_option('ga_appointments_sms_notifications');

			require_once('includes/ga_emails.php');
			$emails = new ga_appointment_emails();
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_sms_notifications_options'); // needed to save the data

					if (in_array('wp-twilio-core/core.php', apply_filters('active_plugins', get_option('active_plugins')))) {
						$page_url = admin_url('admin.php?page=twilio-options');
						echo '<p><b>WP Twilio Core</b> is installed. Go to <a href="' . $page_url . '">WP Twilio Settings</a> and make sure everything is configured correctly.</p>';
					} else {
						echo '<p><b>WP Twilio Core</b> is not installed. <a href="https://wordpress.org/plugins/wp-twilio-core">Install it now.</a></p>';
					}
					?>

			<p>Sms notifications are sent to the client/service provider when an appointment is pending, confirmed or cancelled.</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Send notifications to client</label>
					</th>
					<td>
						<?php
								$client_sms_pending      = isset($options['client_sms_pending']) ? $options['client_sms_pending'] : 'no';
								$client_sms_confirmation = isset($options['client_sms_confirmation']) ? $options['client_sms_confirmation'] : 'no';
								$client_sms_cancelled    = isset($options['client_sms_cancelled']) ? $options['client_sms_cancelled'] : 'no';
								?>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[client_sms_pending]" id="client_sms_pending" value="yes" <?php checked('yes', $client_sms_pending); ?>><span class="ga_checkbox_slider"></span></label> Pending sms</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[client_sms_confirmation]" id="client_sms_confirmation" value="yes" <?php checked('yes', $client_sms_confirmation); ?>><span class="ga_checkbox_slider"></span></label> Confirmation sms</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[client_sms_cancelled]" id="client_sms_cancelled" value="yes" <?php checked('yes', $client_sms_cancelled); ?>><span class="ga_checkbox_slider"></span></label> Cancelled sms</div>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Send notifications to service provider</label>
					</th>
					<td>
						<?php
								$provider_sms_pending      = isset($options['provider_sms_pending']) ? $options['provider_sms_pending'] : 'no';
								$provider_sms_confirmation = isset($options['provider_sms_confirmation']) ? $options['provider_sms_confirmation'] : 'no';
								$provider_sms_cancelled    = isset($options['provider_sms_cancelled']) ? $options['provider_sms_cancelled'] : 'no';
								?>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[provider_sms_pending]" id="provider_sms_pending" value="yes" <?php checked('yes', $provider_sms_pending); ?>><span class="ga_checkbox_slider"></span></label> Pending sms</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[provider_sms_confirmation]" id="provider_sms_confirmation" value="yes" <?php checked('yes', $provider_sms_confirmation); ?>><span class="ga_checkbox_slider"></span></label> Confirmation sms</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_sms_notifications[provider_sms_cancelled]" id="provider_sms_cancelled" value="yes" <?php checked('yes', $provider_sms_cancelled); ?>><span class="ga_checkbox_slider"></span></label> Cancelled sms</div>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="submit" value="Save All Changes" class="button-primary">
			</p>

			<!-- CLIENT SMS NOTIFICATIONS -->
			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $pending = isset($options['pending_body']) ? $options['pending_body'] : $emails->pending_sms_body(); ?>
						<h2 class="ga_email_template_title">Client Appointment Pending Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[pending_body]" class="large-text"><?php echo $pending; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $confirmation = isset($options['confirmation_body']) ? $options['confirmation_body'] : $emails->confirmation_sms_body(); ?>
						<h2 class="ga_email_template_title">Client Appointment Confirmation Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[confirmation_body]" class="large-text"><?php echo $confirmation; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %appointment_date% | %service_name% | %provider_name% | %provider_email%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $cancelled = isset($options['cancelled_body']) ? $options['cancelled_body'] : $emails->cancelled_sms_body(); ?>
						<h2 class="ga_email_template_title">Client Appointment Cancelled Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[cancelled_body]" class="large-text"><?php echo $cancelled; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %appointment_date% | %message% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<!-- PROVIDER SMS NOTIFICATIONS -->
			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $provider_pending = isset($options['provider_pending_body']) ? $options['provider_pending_body'] : $emails->provider_pending_sms_body(); ?>
						<h2 class="ga_email_template_title">Provider Appointment Pending Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[provider_pending_body]" class="large-text"><?php echo $provider_pending; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $provider_confirmation = isset($options['provider_confirmation_body']) ? $options['provider_confirmation_body'] : $emails->provider_confirmation_sms_body(); ?>
						<h2 class="ga_email_template_title">Provider Appointment Confirmation Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[provider_confirmation_body]" class="large-text"><?php echo $provider_confirmation; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<table class="ga_email_template_container form-table">
				<tr>
					<td>
						<?php $provider_cancelled = isset($options['provider_cancelled_body']) ? $options['provider_cancelled_body'] : $emails->provider_cancelled_sms_body(); ?>
						<h2 class="ga_email_template_title">Provider Appointment Cancelled Sms</h2>
						<label><textarea cols="90" rows="5" name="ga_appointments_sms_notifications[provider_cancelled_body]" class="large-text"><?php echo $provider_cancelled; ?></textarea></label>
						<p>Shortcodes to use: %client_name% | %client_email% | %client_phone% | %appointment_date% | %message% | %service_name% | %provider_name%</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="submit" value="Save All Changes" class="button-primary">
			</p>
		</form>
	<?php }



		public function policies_tab_markup()
		{
			$options = get_option('ga_appointments_policies');
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_policies_options'); // needed to save the data
					?>
			<table class="form-table">

				<tr>
					<th scope="row">
						<label for="clear_time">Auto confirm appointments</label>
					</th>
					<td>
						<?php
								$auto_confirm = isset($options['auto_confirm']) ? $options['auto_confirm'] : 'yes';
								?>
						<select name="ga_appointments_policies[auto_confirm]" id="auto_confirm">
							<option value="no" <?php selected('no', $auto_confirm); ?>>No</option>
							<option value="yes" <?php selected('yes', $auto_confirm); ?>>Yes</option>
						</select>
						<br>
						<p class="description">Setting this as Yes will automatically confirm all appointments, except the ones that are pending payment, those ones will be automatically confirmed after the payment was received.</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Provider confirms own appointments</label>
					</th>
					<td>
						<?php
								$provider_confirms = isset($options['provider_confirms']) ? $options['provider_confirms'] : 'no';
								?>
						<select name="ga_appointments_policies[provider_confirms]" id="provider_confirms">
							<option value="no" <?php selected('no', $provider_confirms); ?>>No</option>
							<option value="yes" <?php selected('yes', $provider_confirms); ?>>Yes</option>
						</select>
						<br>
						<p class="description">Allow service providers to confirm pending appointments assigned to them.</p>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Cancellation Policy</label>
					</th>
					<td>
						<select name="ga_appointments_policies[cancellation_notice]" id="cancellation_notice">
							<?php
									$cancellation_notice = isset($options['cancellation_notice']) ? $options['cancellation_notice'] : 'no';
									?>
							<option value="yes" <?php selected('yes', $cancellation_notice); ?>>Cancel any time</option>
							<option value="no" <?php selected('no', $cancellation_notice); ?>>Client not allowed to cancel</option>
							<option value="custom" <?php selected('custom', $cancellation_notice); ?>>Client allowed to cancel within custom timeframe</option>
						</select>
						<br>
						<p class="description">Allow customers to cancel pending appointments</p>
					</td>
				</tr>

				<tr id="tr_cancelllation_notice_timeframe" class="<?php if ($cancellation_notice != 'custom') echo 'hide_cancellation_notice_timeframe' ?>">
					<th scope="row">
						<label for="clear_time">Cancellation Policy Timeframe</label>
					</th>
					<td>
						<?php
								$cancellation_notice_timeframe = isset($options['cancellation_notice_timeframe']) ? $options['cancellation_notice_timeframe'] : '10';
								?>
						<input type="number" min="1" name="ga_appointments_policies[cancellation_notice_timeframe]" id="cancellation_notice_timeframe" value="<?php echo $cancellation_notice_timeframe; ?>">
						<br>
						<p class="description">Allow user to cancel before x hours before appointment</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Provider Cancellation Policy</label>
					</th>
					<td>
						<?php
								$provider_cancellation_notice = isset($options['provider_cancellation_notice']) ? $options['provider_cancellation_notice'] : 'no'; ?>

						<select name="ga_appointments_policies[provider_cancellation_notice]" id="provider_cancellation_notice">
							<option value="yes" <?php selected('yes', $provider_cancellation_notice); ?>>Cancel any time</option>
							<option value="no" <?php selected('no', $provider_cancellation_notice); ?>>Provider not allowed to cancel</option>
						</select>
						<br>
						<p class="description">Allow service providers to cancel pending appointments assigned to them</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Provider Manages Schedule</label>
					</th>
					<td>
						<?php
								$provider_manages_schedule = isset($options['provider_manages_schedule']) ? $options['provider_manages_schedule'] : 'yes'; ?>

						<select name="ga_appointments_policies[provider_manages_schedule]" id="provider_manages_schedule">
							<option value="yes" <?php selected('yes', $provider_manages_schedule); ?>>Yes</option>
							<option value="no" <?php selected('no', $provider_manages_schedule); ?>>No</option>
						</select>
						<br>
						<p class="description">Allow service providers to manage they're schedule on the front-end.</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Allow reschedule appointments</label>
					</th>

					<td>
						<?php
								$appointment_reschedule = isset($options['appointment_reschedule']) ? $options['appointment_reschedule'] : 'no'; ?>

						<select name="ga_appointments_policies[appointment_reschedule]" id="appointment_reschedule">
							<option value="yes" <?php selected('yes', $appointment_reschedule); ?>>Yes</option>
							<option value="no" <?php selected('no', $appointment_reschedule); ?>>No</option>
						</select>
						<br>
						<p class="description">Allow clients to reschedule their appointments.</p>

					</td>

				</tr>
				<tr id="tr_reschedule_appointment_pending" class="<?php if ($appointment_reschedule != 'yes') echo 'hide_reschedule_appointment_pending' ?>">
					<th scope="row">
						<label for="clear_time">Set rescheduled appointments to pending</label>
					</th>
					<td>
						<?php
						$appointment_reschedule_pending = isset( $options['appointment_reschedule_pending'] ) ? $options['appointment_reschedule_pending'] : 'no'; ?>
                        <select name="ga_appointments_policies[appointment_reschedule_pending]" id="appointment_reschedule_pending">
                            <option value="yes" <?php selected( 'yes', $appointment_reschedule_pending ); ?>>Yes</option>
                            <option value="no" <?php selected( 'no', $appointment_reschedule_pending ); ?>>No</option>
                        </select>
                        <br>
                        <p class="description">Set appointment status to pending after user reschedules.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="clear_time">Allow user to set appointment status to pending</label>
                    </th>
                    <td>
						<?php
								$user_set_appointment_pending = isset($options['user_set_appointment_pending']) ? $options['user_set_appointment_pending'] : 'no'; ?>

                        <select name="ga_appointments_policies[user_set_appointment_pending]" id="user_set_appointment_pending">
                            <option value="yes" <?php selected( 'yes', $user_set_appointment_pending ); ?>>Yes</option>
                            <option value="no" <?php selected( 'no', $user_set_appointment_pending ); ?>>No</option>
                        </select>
                        <br>
                        <p class="description">Allow users to set their appointment status to pending</p>
                    </td>
                </tr>

                <tr id="tr_reschedule_pending_to_confirmed" <?php if($user_set_appointment_pending === 'no')echo 'class="hide_user_set_appointment_confirmed_from_pending"' ?>>
                    <th scope="row">
                        <label for="clear_time">Set appointment from pending to confirmed after user reschedules</label>
                    </th>
                    <td>
						<?php
						$user_set_appointment_confirmed_from_pending = isset( $options['user_set_appointment_confirmed_from_pending'] ) ? $options['user_set_appointment_confirmed_from_pending'] : 'no'; ?>
                        <select name="ga_appointments_policies[user_set_appointment_confirmed_from_pending]" id="user_set_appointment_confirmed_from_pending">
                            <option value="yes" <?php selected( 'yes', $user_set_appointment_confirmed_from_pending ); ?>>Yes</option>
                            <option value="no" <?php selected( 'no', $user_set_appointment_confirmed_from_pending ); ?>>No</option>
                        </select>
                        <br>
                        <p class="description">After user sets their appointment to "pending" when they reschedule the appointment status
                        will be set to confirmed</p>
                    </td>
                </tr>

			</table>
			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>
	<?php }


		public function gcal_tab_markup()
		{
			$options = get_option('ga_appointments_gcal');
			$sync    = new ga_gcal_sync($post_id = 0, $provider_id = 0);
        ?>
		<form method="POST" action="options.php">
			<?php settings_fields('ga_appointments_gcal_options'); ?>

			<!-- Enable API Sync -->
			<table class="form-table">
				<tr>
					<th scope="row">
						<label>Enable API Sync</label>
					</th>
					<td>
						<?php
								$api_sync = isset($options['api_sync']) ? $options['api_sync'] : 'no';
								?>
						<select class="form-control" name="ga_appointments_gcal[api_sync]" id="api_sync">
							<option value="yes" <?php selected('yes', $api_sync); ?>>Yes</option>
							<option value="no" <?php selected('no', $api_sync); ?>>No</option>
						</select>
						<p class="description">Enable api sync for no provider case.</p>
					</td>
				</tr>
			</table>


			<!-- Step 1 -->
			<h3>Client authentication</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label>Client ID</label>
					</th>
					<td>
						<?php
								$client_id = isset($options['client_id']) ? $options['client_id'] : '';
								$readonly  = !empty($client_id) ? 'readonly="readonly"' : '';
								echo '<input type="text" class="large-text" id="client_id" name="ga_appointments_gcal[client_id]" value="' . esc_html($client_id) . '" ' . $readonly . '>';
								?>
						<p class="description">The client ID obtained from the Developers Console</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label>Client Secret</label>
					</th>
					<td>
						<?php
								$client_secret = isset($options['client_secret']) ? $options['client_secret'] : '';
								$readonly  = !empty($client_secret) ? 'readonly="readonly"' : '';
								echo '<input type="text" class="large-text" id="client_secret" name="ga_appointments_gcal[client_secret]" value="' . esc_html($client_secret) . '" ' . $readonly . '>';
								?>
						<p class="description">The client secret obtained from the Developers Console</p>
					</td>
				</tr>
			</table>


			<!-- Step 2 -->
			<h3>Authorize access</h3>
			<p>You can generate an access code after you set the Client ID.</p>
			<a href="" class="button-secondary" id="access_link" target="_blank">Generate access code</a>
			<script src="<?php echo GA_PATH_URL . '/assets/access_link.js'; ?>"></script>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label>Access Code</label>
					</th>
					<td>
						<?php
								$access_code = isset($options['access_code']) ? $options['access_code'] : '';
								$readonly  = !empty($access_code) ? 'readonly="readonly"' : '';
								echo '<input type="text" class="large-text" id="access_code" name="ga_appointments_gcal[access_code]" value="' . esc_html($access_code) . '" ' . $readonly . '>';

								?>
						<p class="description">The access code obtained from the consent screen.</p>

						<?php
								$log = get_option('ga_appointments_gcal_debug');
								if ($log == 'enabled') { ?>
							<div class="ga_access_tokens">
								<?php
											$token = get_option('ga_appointments_gcal_token');
											echo 'Access token: ', isset($token['access_token']) ? $token['access_token'] : 'not set';
											echo '<br>';
											echo 'Refresh token: ', isset($token['refresh_token']) ? $token['refresh_token'] : 'not set';
											?>
							</div>
						<?php } ?>
					</td>
				</tr>
			</table>

			<!-- Step 3 -->
			<table class="form-table">
				<tr>
					<th scope="row">
						<label>Calendar ID</label>
					</th>
					<td>
						<?php
								$calendar_id = isset($options['calendar_id']) ? $options['calendar_id'] : '';
								?>
						<select class="form-control" name="ga_appointments_gcal[calendar_id]" id="calendar_id">
							<?php
									$calendars = $sync->get_calendars();
									if (isset($calendars->items)) {
										foreach ($calendars->items as $calendar) {
											if ($calendar->accessRole == 'owner') {
												$primary = isset($calendar->primary) && $calendar->primary == '1' ? ' (primary)' : '';
												$selected = selected($calendar->id, $calendar_id, false);
												echo '<option value="' . $calendar->id . '"' . $selected . '>' . $calendar->summary . $primary . '</option>';
											}
										}
									}
									?>
						</select>
						<p class="description">Select the calendar to sync.</p>
					</td>
				</tr>
			</table>

			<h3>Settings</h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label>Location</label>
					</th>
					<td>
						<?php
								$location = isset($options['location']) ? $options['location'] : '';
								?>
						<input type="text" class="large-text" id="location" name="ga_appointments_gcal[location]" value="<?php echo esc_html($location); ?>">
						<p class="description">If left empty, your website description is sent instead.</p>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label>Synchronization mode</label>
					</th>
					<td>
						<?php
								$sync_mode = isset($options['sync_mode']) ? $options['sync_mode'] : 'one_way';
								?>

						<select class="form-control" name="ga_appointments_gcal[sync_mode]" id="sync_mode">
							<option value="one_way" <?php selected('one_way', $sync_mode); ?>>One-way sync</option>
							<option value="two_way_front" <?php selected('two_way_front', $sync_mode); ?>>Two-way front-end</option>
						</select>
						<p class="description">
							1. One-way sync pushes new appointments and any further changes to Google Calendar.<br>
							2. Two-way front-end sync will fetch events from Google Calendar and remove corresponding time slots before displaying them in the calendar availability (this will lead to form loading delay).
						</p>
					</td>
				</tr>

                <tr id="two_way_sync_time_max" style="display: none;">
                    <th scope="row">
                        <label for="time_max_number">Max bound</label>
                    </th>
                    <td>
                        <?php
                        $time_max_number   = $options['time_max_number']   ?? 1;
                        $time_max_selector = $options['time_max_selector'] ?? 'month';
                        ?>
                        <input id="time_max_number" class="form-control" type="number" name="ga_appointments_gcal[time_max_number]" min="1" max="99" value="<?php echo absint($time_max_number); ?>">
                        <select id="time_max_selector" class="form-control" name="ga_appointments_gcal[time_max_selector]" style="margin: -4px 0 0 0">
                            <option value="day"   <?php selected('day', $time_max_selector); ?>   >Days</option>
                            <option value="week"  <?php selected('week', $time_max_selector); ?>  >Weeks</option>
                            <option value="month" <?php selected('month', $time_max_selector); ?> >Months</option>
                            <option value="year"  <?php selected('year', $time_max_selector); ?>  >Years</option>
                        </select>
                        <p class="description">Define upper bound for how far into future events should be fetched from Google Calendar to gAppointments. (By default, events are fetched from today to the next month)</p>
                        <script>
                            jQuery( document ).ready(function() {
                                let sync_mode_select = jQuery('#sync_mode');
                                let time_max_field   = jQuery('#two_way_sync_time_max');
                                let time_max_select  = jQuery('#time_max_selector');
                                let time_max_number  = jQuery('#time_max_number');

                                // Show max bound option if two_way_front option is selected, hide otherwise.
                                sync_mode_select.on('change', function() {
                                    let selected = jQuery(this).children("option:selected").val();
                                    switch (selected) {
                                        case 'one_way':
                                            time_max_field.hide();
                                            break;
                                        case 'two_way_front':
                                            time_max_field.show();
                                            break;
                                    }
                                });
                                sync_mode_select.trigger("change");

                                // Dynamically adjust max bound limit. Limit to 10 years.
                                time_max_select.on('change', function() {
                                    let selected = jQuery(this).children("option:selected").val();
                                    switch (selected) {
                                        case 'day':
                                        case 'week':
                                        case 'month':
                                            time_max_number.attr('max', '99');
                                            break;
                                        case 'year':
                                            time_max_number.attr('max', '10');
                                            break;
                                    }
                                });
                                time_max_select.trigger("change");

                                // Dynamically change text formatting. Singular to plural, and vice versa.
                                time_max_number.on('change', function() {
                                    let options = time_max_select.children("option");
                                    let number  = time_max_number.val();

                                    options.each(function() {
                                        let option = jQuery(this);
                                        let regex  = new RegExp( "s{1}$" );
                                        if( parseInt( number ) === 1 ) {
                                            if( regex.exec( option.html() ) != null ) {
                                                option.text( option.html().slice(0,-1) );
                                            }
                                        } else {
                                            if( regex.exec( option.html() ) == null ) {
                                                option.text( option.html() + 's' );
                                            }
                                        }
                                    });
                                });
                                time_max_number.trigger("change");
                            });
                        </script>
                    </td>
                </tr>

				<tr>
					<th scope="row">
						<label>Event summary</label>
					</th>
					<td>
						<?php
								$summary = isset($options['summary']) ? $options['summary'] : '[{service_name}] with {client_name}';
								?>
						<input type="text" class="large-text" id="summary" name="ga_appointments_gcal[summary]" value="<?php echo esc_html($summary); ?>">
						<p>Shortcodes to use: {service_name} | {client_name} | {provider_name}</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label>Event description</label>
					</th>
					<td>
						<?php
								$description = isset($options['description']) ? $options['description'] : '';
								?>
						<textarea rows="5" class="large-text" id="description" name="ga_appointments_gcal[description]"><?php echo esc_html($description); ?></textarea>
						<p>Shortcodes to use: {service_name} | {appointment_date} | {client_name} | {client_email} | {client_phone} | {provider_name}</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label>Add attendee</label>
					</th>
					<td>
						<?php
								$attendee = isset($options['attendee']) ? $options['attendee'] : 'no';
								?>
						<select class="form-control" name="ga_appointments_gcal[attendee]" id="attendee">
							<option value="yes" <?php selected('yes', $attendee); ?>>Yes</option>
							<option value="no" <?php selected('no', $attendee); ?>>No</option>
						</select>
						<p>Add client name & email to event. Google blocks creating too many events from same client email at the same. We recommend setting to No, and add the client information to event description.</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label>Colors</label>
					</th>
					<td>
						<h5 style="margin: 0px 0px 7px 0px;">Confirmed Event Color</h5>
						<?php
								$confirmed_color = isset($options['confirmed_color']) ? $options['confirmed_color'] : '1';
								foreach (range(1, 11) as $id) { ?>
							<label class="gcal-color gcal-color-<?php echo $id; ?>"><input type="radio" value="<?php echo $id; ?>" name="ga_appointments_gcal[confirmed_color]" <?php checked($id, $confirmed_color); ?>><span></span></label>
						<?php } ?>

						<h5 style="margin: 20px 0px 7px 0px;">Pending Event Color</h5>
						<?php
								$pending_color = isset($options['pending_color']) ? $options['pending_color'] : '4';
								foreach (range(1, 11) as $id) { ?>
							<label class="gcal-color gcal-color-<?php echo $id; ?>"><input type="radio" value="<?php echo $id; ?>" name="ga_appointments_gcal[pending_color]" <?php checked($id, $pending_color); ?>><span></span></label>
						<?php } ?>

					</td>
				</tr>

			</table>

			<p class="submit">
				<input type="submit" name="save_settings" value="Save Settings" class="button-primary">
				<input type="submit" class="button-secondary" name="ga_appointments_gcal[reset_api]" id="reset_api" value="Reset API Credentials">
				<script>
					jQuery('body').on('click', '#reset_api', function() {
						return confirm('Reset API Credentials?');
					});
				</script>
			</p>
		</form>

		<hr>


		<!-- Server Information -->
		<h3>Server information</h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label>cURL support</label>
				</th>
				<td>
					<?php
							echo function_exists('curl_version') ? '<span style="color: #0B8043;">Enabled</span>' : '<span style="color: #D50000;">Disabled</span>';
							?>
				</td>
			</tr>
		</table>

		<!-- LOGS -->
		<form method="POST" action="options.php">
			<?php
					$debug = get_option('ga_appointments_gcal_debug');
					settings_fields('ga_appointments_gcal_debug_options'); // needed to save the data
					?>
			<h3>Debug <small>(developer mode)</small></h3>
			<?php if ($debug == 'enabled') { ?>

				<p>Logging is enabled</p>
				<button type="submit" name="ga_appointments_gcal_debug" class="button-secondary" value="disabled">Disable Logging</button>

				<div class="ga_log_data">
					<?php
								$log_data = (array) get_option('ga_appointments_gcal_log');
								echo '<div class="ga_log_entry">' . implode('</div><div class="ga_log_entry">', $log_data) . '</div>';
								?>
				</div>

			<?php } else { ?>

				<p>Logging is disabled</p>
				<button type="submit" name="ga_appointments_gcal_debug" class="button-secondary" value="enabled">Enable Logging</button>

			<?php } ?>
		</form>

	<?php }


		public function add_to_calendar_tab_markup()
		{
			$options = get_option('ga_appointments_add_to_calendar');
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_add_to_calendar_options'); // needed to save the data
					?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Enable add to calendar links</label>
					</th>
					<td>
						<?php
								$show_links = isset($options['show_links']) ? $options['show_links'] : 'yes';
								?>
						<select name="ga_appointments_add_to_calendar[show_links]">
							<option value="yes" <?php selected('yes', $show_links); ?>>Yes</option>
							<option value="no" <?php selected('no', $show_links); ?>>No</option>
						</select>
						<p class="description">Links are inserted in the front-end shortcodes for pending & confirmed appointments.</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Show calendar links</label>
					</th>
					<td>
						<?php
								$add_to_apple    = isset($options['apple'])   ? $options['apple'] : 'yes';
								$add_to_google   = isset($options['google'])  ? $options['google'] : 'yes';
								$add_to_outlook  = isset($options['outlook']) ? $options['outlook'] : 'yes';
								$add_to_yahoo    = isset($options['yahoo'])   ? $options['yahoo'] : 'yes';
								?>

						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_add_to_calendar[apple]" id="add_to_calendar_apple" value="yes" <?php checked('yes', $add_to_apple); ?>><span class="ga_checkbox_slider"></span></label> Apple Calendar</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_add_to_calendar[google]" id="add_to_calendar_google" value="yes" <?php checked('yes', $add_to_google); ?>><span class="ga_checkbox_slider"></span></label> Google Calendar</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_add_to_calendar[outlook]" id="add_to_calendar_outlook" value="yes" <?php checked('yes', $add_to_outlook); ?>><span class="ga_checkbox_slider"></span></label> Outlook Calendar</div>
						<div class="ga_setting"><label class="ga_checkbox_switch"><input type="checkbox" name="ga_appointments_add_to_calendar[yahoo]" id="add_to_calendar_yahoo" value="yes" <?php checked('yes', $add_to_yahoo); ?>><span class="ga_checkbox_slider"></span></label> Yahoo! Calendar</div>

						<p class="description">Select which links to be included</p>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Calendar Location</label>
					</th>
					<td>
						<?php
								$location = isset($options['location']) ? $options['location'] : '';
								?>
						<input type="text" class="regular-text" name="ga_appointments_add_to_calendar[location]" value="<?php echo esc_html($location); ?>">
						<p class="description">Enter the text that will be used as location field in Calendar. You can use google maps location. If left empty, your website description is sent instead.</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Description</label>
					</th>
					<td>
						<?php
								$description = isset($options['description']) ? $options['description'] : '';
								?>
						<textarea cols="90" rows="10" name="ga_appointments_add_to_calendar[description]" class="large-text"><?php echo $description; ?></textarea>
						<p class="description">Enter the text that will be used as appointment description field in Calendar. If left empty, Appointment time is sent instead.</p>
						<p>Shortcodes to use: %client_name%, %client_email%, %client_phone%, %service_name%</p>
					</td>
				</tr>

			</table>
			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>
	<?php }



		public function translation_tab_markup()
		{
			$options = get_option('ga_appointments_translation');
			?>
		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_translation_options'); // needed to save the data
					?>
			<p>Translate calendar into your language. You can also translate every form individually by going to a form, <b>Settings</b> > <b>gAppointments</b></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Calendar week days short names</label>
					</th>
					<td>
						<?php
								$weeks  = ga_get_translated_data('weeks');
								?>
						<span class="ga_translation_title">Sunday</span> <input type="text" name="ga_appointments_translation[weeks][sun]" value="<?php echo $weeks['sun']; ?>"><br>
						<span class="ga_translation_title">Monday</span> <input type="text" name="ga_appointments_translation[weeks][mon]" value="<?php echo  $weeks['mon']; ?>"><br>
						<span class="ga_translation_title">Tueday</span> <input type="text" name="ga_appointments_translation[weeks][tue]" value="<?php echo  $weeks['tue']; ?>"><br>
						<span class="ga_translation_title">Wednesday</span> <input type="text" name="ga_appointments_translation[weeks][wed]" value="<?php echo  $weeks['wed']; ?>"><br>
						<span class="ga_translation_title">Thursday</span> <input type="text" name="ga_appointments_translation[weeks][thu]" value="<?php echo  $weeks['thu']; ?>"><br>
						<span class="ga_translation_title">Friday</span> <input type="text" name="ga_appointments_translation[weeks][fri]" value="<?php echo  $weeks['fri']; ?>"><br>
						<span class="ga_translation_title">Saturday</span> <input type="text" name="ga_appointments_translation[weeks][sat]" value="<?php echo  $weeks['sat']; ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Calendar week days long names</label>
					</th>
					<td>
						<?php
								$long_weeks  = ga_get_translated_data('long_weeks');
								?>
						<span class="ga_translation_title">Sunday</span> <input type="text" name="ga_appointments_translation[long_weeks][sunday]" value="<?php echo $long_weeks['sunday']; ?>"><br>
						<span class="ga_translation_title">Monday</span> <input type="text" name="ga_appointments_translation[long_weeks][monday]" value="<?php echo  $long_weeks['monday']; ?>"><br>
						<span class="ga_translation_title">Tueday</span> <input type="text" name="ga_appointments_translation[long_weeks][tuesday]" value="<?php echo  $long_weeks['tuesday']; ?>"><br>
						<span class="ga_translation_title">Wednesday</span> <input type="text" name="ga_appointments_translation[long_weeks][wednesday]" value="<?php echo  $long_weeks['wednesday']; ?>"><br>
						<span class="ga_translation_title">Thursday</span> <input type="text" name="ga_appointments_translation[long_weeks][thursday]" value="<?php echo  $long_weeks['thursday']; ?>"><br>
						<span class="ga_translation_title">Friday</span> <input type="text" name="ga_appointments_translation[long_weeks][friday]" value="<?php echo  $long_weeks['friday']; ?>"><br>
						<span class="ga_translation_title">Saturday</span> <input type="text" name="ga_appointments_translation[long_weeks][saturday]" value="<?php echo  $long_weeks['saturday']; ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Calendar heading month/year</label>
					</th>
					<td>
						<?php
								$january   = ga_get_translated_data('january');
								$february  = ga_get_translated_data('february');
								$march     = ga_get_translated_data('march');
								$april     = ga_get_translated_data('april');
								$may       = ga_get_translated_data('may');
								$june      = ga_get_translated_data('june');
								$july      = ga_get_translated_data('july');
								$august    = ga_get_translated_data('august');
								$september = ga_get_translated_data('september');
								$october   = ga_get_translated_data('october');
								$november  = ga_get_translated_data('november');
								$december  = ga_get_translated_data('december');
								?>
						<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="ga_appointments_translation[january]" value="<?php echo $january; ?>"><br>
						<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="ga_appointments_translation[february]" value="<?php echo $february; ?>"><br>
						<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="ga_appointments_translation[march]" value="<?php echo $march; ?>"><br>
						<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="ga_appointments_translation[april]" value="<?php echo $april; ?>"><br>
						<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="ga_appointments_translation[may]" value="<?php echo $may; ?>"><br>
						<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="ga_appointments_translation[june]" value="<?php echo $june; ?>"><br>
						<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="ga_appointments_translation[july]" value="<?php echo $july; ?>"><br>
						<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="ga_appointments_translation[august]" value="<?php echo $august; ?>"><br>
						<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="ga_appointments_translation[september]" value="<?php echo $september; ?>"><br>
						<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="ga_appointments_translation[october]" value="<?php echo $october; ?>"><br>
						<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="ga_appointments_translation[november]" value="<?php echo $november; ?>"><br>
						<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="ga_appointments_translation[december]" value="<?php echo $december; ?>">

						<p class="description">Shortcode to use: [year]</p>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Calendar slots date</label>
					</th>
					<td>
						<?php
								$slots_january   = ga_get_translated_data('slots_january');
								$slots_february  = ga_get_translated_data('slots_february');
								$slots_march     = ga_get_translated_data('slots_march');
								$slots_april     = ga_get_translated_data('slots_april');
								$slots_may       = ga_get_translated_data('slots_may');
								$slots_june      = ga_get_translated_data('slots_june');
								$slots_july      = ga_get_translated_data('slots_july');
								$slots_august    = ga_get_translated_data('slots_august');
								$slots_september = ga_get_translated_data('slots_september');
								$slots_october   = ga_get_translated_data('slots_october');
								$slots_november  = ga_get_translated_data('slots_november');
								$slots_december  = ga_get_translated_data('slots_december');
								?>
						<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_january]" value="<?php echo $slots_january; ?>"><br>
						<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_february]" value="<?php echo $slots_february; ?>"><br>
						<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_march]" value="<?php echo $slots_march; ?>"><br>
						<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_april]" value="<?php echo $slots_april; ?>"><br>
						<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_may]" value="<?php echo $slots_may; ?>"><br>
						<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_june]" value="<?php echo $slots_june; ?>"><br>
						<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_july]" value="<?php echo $slots_july; ?>"><br>
						<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_august]" value="<?php echo $slots_august; ?>"><br>
						<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_september]" value="<?php echo $slots_september; ?>"><br>
						<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_october]" value="<?php echo $slots_october; ?>"><br>
						<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_november]" value="<?php echo $slots_november; ?>"><br>
						<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="ga_appointments_translation[slots_december]" value="<?php echo $slots_december; ?>">

						<p class="description">Shortcodes to use: [day], [year]</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Date & Time</label>
					</th>
					<td>
						<?php
								$date_time_january   = ga_get_translated_data('date_time_january');
								$date_time_february  = ga_get_translated_data('date_time_february');
								$date_time_march     = ga_get_translated_data('date_time_march');
								$date_time_april     = ga_get_translated_data('date_time_april');
								$date_time_may       = ga_get_translated_data('date_time_may');
								$date_time_june      = ga_get_translated_data('date_time_june');
								$date_time_july      = ga_get_translated_data('date_time_july');
								$date_time_august    = ga_get_translated_data('date_time_august');
								$date_time_september = ga_get_translated_data('date_time_september');
								$date_time_october   = ga_get_translated_data('date_time_october');
								$date_time_november  = ga_get_translated_data('date_time_november');
								$date_time_december  = ga_get_translated_data('date_time_december');
								?>
						<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_january]" value="<?php echo $date_time_january; ?>"><br>
						<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_february]" value="<?php echo $date_time_february; ?>"><br>
						<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_march]" value="<?php echo $date_time_march; ?>"><br>
						<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_april]" value="<?php echo $date_time_april; ?>"><br>
						<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_may]" value="<?php echo $date_time_may; ?>"><br>
						<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_june]" value="<?php echo $date_time_june; ?>"><br>
						<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_july]" value="<?php echo $date_time_july; ?>"><br>
						<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_august]" value="<?php echo $date_time_august; ?>"><br>
						<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_september]" value="<?php echo $date_time_september; ?>"><br>
						<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_october]" value="<?php echo $date_time_october; ?>"><br>
						<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_november]" value="<?php echo $date_time_november; ?>"><br>
						<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="ga_appointments_translation[date_time_december]" value="<?php echo $date_time_december; ?>">

						<p class="description">Shortcodes to use: [week_long], [day], [year], [time]</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">AM/PM</label>
					</th>
					<td>
						<?php
								$am  = ga_get_translated_data('am');
								$pm  = ga_get_translated_data('pm');
								?>
						<span class="ga_translation_title">Am time</span> <input type="text" name="ga_appointments_translation[am]" value="<?php echo $am; ?>"><br>
						<span class="ga_translation_title">Pm time</span> <input type="text" name="ga_appointments_translation[pm]" value="<?php echo $pm; ?>"><br>
					</td>
				</tr>



				<tr>
					<th scope="row">
						<label for="clear_time">Capacity</label>
					</th>
					<td>
						<?php
								$space   = ga_get_translated_data('space');
								$spaces  = ga_get_translated_data('spaces');
								?>
						<span class="ga_translation_title">Is one</span> <input type="text" class="regular-text" name="ga_appointments_translation[space]" value="<?php echo $space; ?>"><br>
						<span class="ga_translation_title">Is greater than one</span> <input type="text" class="regular-text" name="ga_appointments_translation[spaces]" value="<?php echo $spaces; ?>"><br>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Front-end shortcodes</label>
					</th>
					<td>
						<?php
								$manage_text         = ga_get_translated_data('manage_text');
								$schedule            = ga_get_translated_data('schedule');
								$breaks              = ga_get_translated_data('breaks');
								$holidays            = ga_get_translated_data('holidays');
								$schedule_updated    = ga_get_translated_data('schedule_updated');
								$upcoming            = ga_get_translated_data('upcoming');
								$past                = ga_get_translated_data('past');
								$no_appointments	 = ga_get_translated_data('no_appointments');
								$client_service      = ga_get_translated_data('client_service');
								$provider_service    = ga_get_translated_data('provider_service');
								$add_to_calendar     = ga_get_translated_data('add_to_calendar');
								$apple_calendar      = ga_get_translated_data('apple_calendar');
								$google_calendar     = ga_get_translated_data('google_calendar');
								$outlook_calendar    = ga_get_translated_data('outlook_calendar');
								$yahoo_calendar      = ga_get_translated_data('yahoo_calendar');
								$bookable_date       = ga_get_translated_data('bookable_date');
								$status_completed    = ga_get_translated_data('status_completed');
								$status_publish      = ga_get_translated_data('status_publish');
								$status_payment      = ga_get_translated_data('status_payment');
								$status_pending      = ga_get_translated_data('status_pending');
								$status_cancelled    = ga_get_translated_data('status_cancelled');
								$confirm_button      = ga_get_translated_data('confirm_button');
								$update_button       = ga_get_translated_data('update_button');
								$cancel_button       = ga_get_translated_data('cancel_button');
								$confirm_text        = ga_get_translated_data('confirm_text');
								$cancel_text         = ga_get_translated_data('cancel_text');
								$close_button        = ga_get_translated_data('close_button');
								$optional_text       = ga_get_translated_data('optional_text');
								$app_confirmed       = ga_get_translated_data('app_confirmed');
								$app_cancelled       = ga_get_translated_data('app_cancelled');
								$error               = ga_get_translated_data('error');
								?>
						<p>Manage Schedule Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[manage_text]" value="<?php echo $manage_text; ?>"><br>

						<p>Schedule Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[schedule]" value="<?php echo $schedule; ?>"><br>

						<p>Breaks Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[breaks]" value="<?php echo $breaks; ?>"><br>

						<p>Holidays Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[holidays]" value="<?php echo $holidays; ?>"><br>

						<p>Schedule Updated</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[schedule_updated]" value="<?php echo $schedule_updated; ?>"><br>

						<p>Upcoming tab</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[upcoming]" value="<?php echo $upcoming; ?>"><br>

						<p>Upcoming tab</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[upcoming]" value="<?php echo $upcoming; ?>"><br>

						<p>Past tab</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[past]" value="<?php echo $past; ?>"><br>

						<p>No appointments</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[no_appointments]" value="<?php echo $no_appointments; ?>"><br>

						<p>Client service title. Shortcodes to use: [service_name], [provider_name]</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[client_service]" value="<?php echo $client_service; ?>"><br>

						<p>Provider service title. Shortcodes to use: [service_name], [client_name]</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[provider_service]" value="<?php echo $provider_service; ?>"><br>

						<p>Add to calendar</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[add_to_calendar]" value="<?php echo $add_to_calendar; ?>"><br>

						<p>Apple Calendar</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[apple_calendar]" value="<?php echo $apple_calendar; ?>"><br>

						<p>Google Calendar</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[google_calendar]" value="<?php echo $google_calendar; ?>"><br>

						<p>Outlook Calendar</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[outlook_calendar]" value="<?php echo $outlook_calendar; ?>"><br>

						<p>Yahoo Calendar</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[yahoo_calendar]" value="<?php echo $yahoo_calendar; ?>"><br>

						<p>Bookable date</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[bookable_date]" value="<?php echo $bookable_date; ?>"><br>

						<p>Completed Status</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[status_completed]" value="<?php echo $status_completed; ?>"><br>

						<p>Confirmed Status</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[status_publish]" value="<?php echo $status_publish; ?>"><br>

						<p>Payment Status</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[status_payment]" value="<?php echo $status_payment; ?>"><br>

						<p>Pending Status</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[status_pending]" value="<?php echo $status_pending; ?>"><br>

						<p>Cancelled Status</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[status_cancelled]" value="<?php echo $status_cancelled; ?>"><br>

						<p>Confirm Button</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[confirm_button]" value="<?php echo $confirm_button; ?>"><br>

						<p>Cancel Button</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[cancel_button]" value="<?php echo $cancel_button; ?>"><br>

						<p>Update Button</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[update_button]" value="<?php echo $update_button; ?>"><br>

						<p>Cancel Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[cancel_text]" value="<?php echo $cancel_text; ?>"><br>

						<p>Confirm Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[confirm_text]" value="<?php echo $confirm_text; ?>"><br>

						<p>Close Button</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[close_button]" value="<?php echo $close_button; ?>"><br>

						<p>Optional Text</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[optional_text]" value="<?php echo $optional_text; ?>"><br>

						<p>Appointment Confirmed</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[app_confirmed]" value="<?php echo $app_confirmed; ?>"><br>

						<p>Appointment Cancelled</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[app_cancelled]" value="<?php echo $app_cancelled; ?>"><br>

						<p>Error message</p>
						<input type="text" class="regular-text" name="ga_appointments_translation[error]" value="<?php echo $error; ?>"><br>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Appointment Cost</label>
					</th>
					<td>
						<?php
								$app_cost_text = ga_get_translated_data('app_cost_text');
								?>
						<input type="text" class="regular-text" name="ga_appointments_translation[app_cost_text]" value="<?php echo $app_cost_text; ?>"><br>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Validation messages</label>
					</th>
					<td>
						<?php
								$error_required           = ga_get_translated_data('error_required');
								$error_reached_max        = ga_get_translated_data('error_reached_max');
								$error_required_date      = ga_get_translated_data('error_required_date');
								$error_max_bookings       = ga_get_translated_data('error_max_bookings');
								$error_required_service   = ga_get_translated_data('error_required_service');
								$error_booked_date        = ga_get_translated_data('error_booked_date');
								$error_date_valid         = ga_get_translated_data('error_date_valid');
								$error_slot_valid         = ga_get_translated_data('error_slot_valid');
								$error_required_slot      = ga_get_translated_data('error_required_slot');
								$error_services_form      = ga_get_translated_data('error_services_form');
								$error_service_valid      = ga_get_translated_data('error_service_valid');
								$error_required_provider  = ga_get_translated_data('error_required_provider');
								$error_providers_service  = ga_get_translated_data('error_providers_service');
								$error_no_services        = ga_get_translated_data('error_no_services');
								?>
						<p># Field required</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_required]" value="<?php echo $error_required; ?>"><br>

						<p># Date maximum bookings reached. Shortcode to use: [date]</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_reached_max]" value="<?php echo $error_reached_max; ?>"><br>

						<p># Date not selected</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_required_date]" value="<?php echo $error_required_date; ?>"><br>

						<p># Date max bookings. Shortcode to use: [total], [date]</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_max_bookings]" value="<?php echo $error_max_bookings; ?>"><br>

						<p># Service not selected</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_required_service]" value="<?php echo $error_required_service; ?>"><br>

						<p># Client already booked date. Shortcode to use: [date]</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_booked_date]" value="<?php echo $error_booked_date; ?>"><br>

						<p># Date not valid. Shortcode to use: [date]</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_date_valid]" value="<?php echo $error_date_valid; ?>"><br>

						<p># Time slot not valid. Shortcode to use: [date]</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_slot_valid]" value="<?php echo $error_slot_valid; ?>"><br>

						<p># Time slot required</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_required_slot]" value="<?php echo $error_required_slot; ?>"><br>

						<p># Services field not added to form</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_services_form]" value="<?php echo $error_services_form; ?>"><br>

						<p># Service is not valid</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_service_valid]" value="<?php echo $error_service_valid; ?>"><br>

						<p># Provider not selected</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_required_provider]" value="<?php echo $error_required_provider; ?>"><br>

						<p># Providers service not valid</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_providers_service]" value="<?php echo $error_providers_service; ?>"><br>

						<p># No services found</p>
						<input type="text" class="large-text" name="ga_appointments_translation[error_no_services]" value="<?php echo $error_no_services; ?>"><br>

					</td>
				</tr>


			</table>

			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>
	<?php }

		public function display_tab_markup()
		{
			$ga_colors         = get_option('ga_appointments_colors');

			//print_r($ga_colors);

			$main_color        = isset($ga_colors['main']) ? $ga_colors['main'] : $this->calendar_colors['main'];
			$secondary_color   = isset($ga_colors['secondary']) ? $ga_colors['secondary'] : $this->calendar_colors['secondary'];
			$header_color      = isset($ga_colors['header_color']) ? $ga_colors['header_color'] : $this->calendar_colors['header_color'];
			$cal_bg            = isset($ga_colors['bg']) ? $ga_colors['bg'] : $this->calendar_colors['bg'];
			$cal_border        = isset($ga_colors['border']) ? $ga_colors['border'] : $this->calendar_colors['border'];
			$cal_color         = isset($ga_colors['color']) ? $ga_colors['color'] : $this->calendar_colors['color'];
			$hover_color       = isset($ga_colors['hover_color']) ? $ga_colors['hover_color'] : $this->calendar_colors['hover_color'];
			$cal_bg_available  = isset($ga_colors['bg_available']) ? $ga_colors['bg_available'] : $this->calendar_colors['bg_available'];
			$cal_color_available  = isset($ga_colors['color_available']) ? $ga_colors['color_available'] : $this->calendar_colors['color_available'];
			$loading_overlay   = isset($ga_colors['loading_overlay']) ? $ga_colors['loading_overlay'] : $this->calendar_colors['loading_overlay'];
			$spinner_color     = isset($ga_colors['spinner_color']) ? $ga_colors['spinner_color'] : $this->calendar_colors['spinner_color'];
			$cal_slots_bg      = isset($ga_colors['slots_bg']) ? $ga_colors['slots_bg'] : $this->calendar_colors['slots_bg'];
			$slot_selected_bg  = isset($ga_colors['slot_selected_bg']) ? $ga_colors['slot_selected_bg'] : $this->calendar_colors['slot_selected_bg'];
			$slot_selected_color = isset($ga_colors['slot_selected_color']) ? $ga_colors['slot_selected_color'] : $this->calendar_colors['slot_selected_color'];
			$cal_slots_title   = isset($ga_colors['slots_title']) ? $ga_colors['slots_title'] : $this->calendar_colors['slots_title'];
			$cal_slots_border  = isset($ga_colors['slots_border']) ? $ga_colors['slots_border'] : $this->calendar_colors['slots_border'];
			$cal_ajax_spinner  = isset($ga_colors['ajax_spinner']) ? $ga_colors['ajax_spinner'] : $this->calendar_colors['ajax_spinner'];
			?>
		<h3>Calendar Colors</h3>

		<form method="POST" action="options.php">
			<?php
					settings_fields('ga_appointments_colors_options'); // needed to save the data
					?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clear_time">Main Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[main]" class="small-text color-field" value="<?php echo $main_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Secondary Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[secondary]" class="small-text color-field" value="<?php echo $secondary_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Calendar Header Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[header_color]" class="small-text color-field" value="<?php echo $header_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Background Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[bg]" class="small-text color-field" value="<?php echo $cal_bg; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Border Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[border]" class="small-text color-field" value="<?php echo $cal_border; ?>" type="text"></label>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Font Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[color]" class="small-text color-field" value="<?php echo $cal_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Hover Font Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[hover_color]" class="small-text color-field" value="<?php echo $hover_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Available Background</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[bg_available]" class="small-text color-field" value="<?php echo $cal_bg_available; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Available Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[color_available]" class="small-text color-field" value="<?php echo $cal_color_available; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Loading Overlay</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[loading_overlay]" class="small-text color-field" value="<?php echo $loading_overlay; ?>" type="text"></label>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Loading Spinner Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[spinner_color]" class="small-text color-field" value="<?php echo $spinner_color; ?>" type="text"></label>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Slots Background</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[slots_bg]" class="small-text color-field" value="<?php echo $cal_slots_bg; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Slot Selected Background</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[slot_selected_bg]" class="small-text color-field" value="<?php echo $slot_selected_bg; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Slot Selected Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[slot_selected_color]" class="small-text color-field" value="<?php echo $slot_selected_color; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Slots Title Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[slots_title]" class="small-text color-field" value="<?php echo $cal_slots_title; ?>" type="text"></label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="clear_time">Slots Border Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[slots_border]" class="small-text color-field" value="<?php echo $cal_slots_border; ?>" type="text"></label>
					</td>
				</tr>


				<tr>
					<th scope="row">
						<label for="clear_time">Ajax Spinner Color</label>
					</th>
					<td>
						<label><input name="ga_appointments_colors[ajax_spinner]" class="small-text color-field" value="<?php echo $cal_ajax_spinner; ?>" type="text"></label>
					</td>
				</tr>

			</table>
			<p class="submit">
				<input type="submit" name="submit" value="Save Changes" class="button-primary">
			</p>
		</form>
	<?php }


		/**
		 * Generate CSS
		 */
		public function generate_styles()
		{
			$ga_colors         = get_option('ga_appointments_colors');
			$main_color        = isset($ga_colors['main']) ? $ga_colors['main'] : $this->calendar_colors['main'];
			$secondary_color   = isset($ga_colors['secondary']) ? $ga_colors['secondary'] : $this->calendar_colors['secondary'];
			$header_color      = isset($ga_colors['header_color']) ? $ga_colors['header_color'] : $this->calendar_colors['header_color'];
			$cal_bg            = isset($ga_colors['bg']) ? $ga_colors['bg'] : $this->calendar_colors['bg'];
			$cal_border        = isset($ga_colors['border']) ? $ga_colors['border'] : $this->calendar_colors['border'];
			$cal_color         = isset($ga_colors['color']) ? $ga_colors['color'] : $this->calendar_colors['color'];
			$hover_color       = isset($ga_colors['hover_color']) ? $ga_colors['hover_color'] : $this->calendar_colors['hover_color'];
			$cal_bg_available  = isset($ga_colors['bg_available']) ? $ga_colors['bg_available'] : $this->calendar_colors['bg_available'];
			$cal_color_available  = isset($ga_colors['color_available']) ? $ga_colors['color_available'] : $this->calendar_colors['color_available'];
			$loading_overlay   = isset($ga_colors['loading_overlay']) ? $ga_colors['loading_overlay'] : $this->calendar_colors['loading_overlay'];
			$spinner_color     = isset($ga_colors['spinner_color']) ? $ga_colors['spinner_color'] : $this->calendar_colors['spinner_color'];
			$cal_slots_bg      = isset($ga_colors['slots_bg']) ? $ga_colors['slots_bg'] : $this->calendar_colors['slots_bg'];
			$slot_selected_bg  = isset($ga_colors['slot_selected_bg']) ? $ga_colors['slot_selected_bg'] : $this->calendar_colors['slot_selected_bg'];
			$slot_selected_color = isset($ga_colors['slot_selected_color']) ? $ga_colors['slot_selected_color'] : $this->calendar_colors['slot_selected_color'];
			$cal_slots_title   = isset($ga_colors['slots_title']) ? $ga_colors['slots_title'] : $this->calendar_colors['slots_title'];
			$cal_slots_border  = isset($ga_colors['slots_border']) ? $ga_colors['slots_border'] : $this->calendar_colors['slots_border'];

			$cal_ajax_spinner  = isset($ga_colors['ajax_spinner']) ? $ga_colors['ajax_spinner'] : $this->calendar_colors['ajax_spinner'];
			?>
		<style>
			<?php if (!is_user_logged_in_a_provider()) { ?>.ga_provider_page {
				display: none !important;
			}

			<?php } ?><?php if (!is_user_logged_in()) { ?>.ga_customer_page {
				display: none !important;
			}

			<?php } ?><?php if (!empty($main_color)) { ?>.ga_appointments_calendar_header,
			.ga_monthly_schedule_wrapper table thead,
			.ga_monthly_schedule_wrapper table thead th {
				background: <?php echo esc_html($main_color); ?> !important;
			}

			.ga_wrapper_small .ga_monthly_schedule_wrapper td.selected,
			.ga_wrapper_small .ga_monthly_schedule_wrapper td.day_available:hover {
				color: <?php echo esc_html($main_color); ?> !important;
			}

			<?php } ?><?php if (!empty($secondary_color)) { ?>.ga_wrapper_small .ga_monthly_schedule_wrapper td.day_available:hover span,
			.ga_wrapper_small .ga_monthly_schedule_wrapper td.selected span,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.day_available:hover span,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.selected span,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.day_available:hover span,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.selected span {
				background: <?php echo esc_html($secondary_color); ?> !important;
				border: 2px solid <?php echo esc_html($secondary_color); ?> !important;
			}

			.ga_wrapper_small .ga_monthly_schedule_wrapper td.ga_today span,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.ga_today span,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.ga_today span {
				border: 2px solid <?php echo esc_html($secondary_color); ?> !important;
			}

			#no_time_slots i {
				color: <?php echo esc_html($secondary_color); ?> !important;
			}

			<?php } ?><?php if (!empty($header_color)) { ?>.ga_appointments_calendar_header h3,
			.ga_appointments_calendar_header .arrow-left,
			.ga_appointments_calendar_header .arrow-right,
			.ga_monthly_schedule_wrapper thead th {
				color: <?php echo esc_html($header_color); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_bg)) { ?>.ga_monthly_schedule_wrapper td {
				background: <?php echo esc_html($cal_bg); ?> !important;
			}

			<?php } ?><?php if (!empty($loading_overlay)) { ?>.ga_monthly_schedule_wrapper.ga_spinner:before {
				background: <?php echo esc_html($loading_overlay); ?> !important;
			}

			<?php } ?><?php if (!empty($spinner_color)) { ?>.ga_monthly_schedule_wrapper.ga_spinner:after {
				border-color: <?php echo esc_html($spinner_color); ?> transparent transparent !important;
			}

			<?php } ?><?php if (!empty($cal_border)) { ?>.ga_monthly_schedule_wrapper td {
				border-color: <?php echo esc_html($cal_border); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_color)) { ?>.ga_monthly_schedule_wrapper td {
				color: <?php echo esc_html($cal_color); ?> !important;
			}

			<?php } ?><?php if (!empty($hover_color)) { ?>.ga_wrapper_small .ga_monthly_schedule_wrapper td.selected,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.selected,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.selected {
				color: <?php echo esc_html($hover_color); ?> !important;
			}

			.ga_wrapper_small .ga_monthly_schedule_wrapper td.day_available:hover,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.day_available:hover,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.day_available:hover {
				color: <?php echo esc_html($hover_color); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_bg_available)) { ?>.ga_wrapper_small .ga_monthly_schedule_wrapper td.day_available,
			.ga_wrapper_medium .ga_monthly_schedule_wrapper td.day_available,
			.ga_wrapper_large .ga_monthly_schedule_wrapper td.day_available {
				background: <?php echo esc_html($cal_bg_available); ?> !important;
			}

			.ga_monthly_schedule_wrapper td.selected:after {
				border-color: <?php echo esc_html($cal_bg_available); ?> transparent transparent transparent !important;
			}

			<?php } ?><?php if (!empty($cal_color_available)) { ?>#gappointments_calendar_slots label.time_slot {
				color: <?php echo esc_html($cal_color_available); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_slots_bg)) { ?>.ga_monthly_schedule_wrapper td.calendar_slots {
				background: <?php echo esc_html($cal_slots_bg); ?> !important;
			}

			<?php } ?><?php if (!empty($slot_selected_bg)) { ?>#gappointments_calendar_slots label.time_selected div {
				background: <?php echo esc_html($slot_selected_bg); ?> !important;
			}

			<?php } ?><?php if (!empty($slot_selected_color)) { ?>#gappointments_calendar_slots label.time_selected div {
				color: <?php echo esc_html($slot_selected_color); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_slots_title)) { ?>#gappointments_calendar_slots .calendar_time_slots .slots-title,
			#no_time_slots span {
				color: <?php echo esc_html($cal_slots_title); ?> !important;
			}


			<?php } ?><?php if (!empty($cal_slots_border)) { ?>.ga_monthly_schedule_wrapper td.calendar_slots {
				border: 1px solid <?php echo esc_html($cal_slots_border); ?> !important;
			}

			<?php } ?><?php if (!empty($cal_ajax_spinner)) { ?>.ajax-spinner-bars>div {
				background-color: <?php echo esc_html($cal_ajax_spinner); ?> !important;
			}

			<?php } ?>
		</style>

<?php }



	/**
	 * Admin Dashboard Menu
	 */
	public function admin_menu()
	{
		add_menu_page(
			'gAppointments', // page title
			'gAppointments', // admin settings title
			'manage_options',
			'ga_appointments_settings', // page url
			array($this, 'settings_page'), // output the content for this page
			// image icon
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiBwcmVzZXJ2ZUFzcGVjdFJhdGlvPSJ4TWlkWU1pZCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIiB2aWV3Qm94PSIwIDAgMjAgMjAiPiAgPGRlZnM+ICAgIDxzdHlsZT4gICAgICAuY2xzLTEgeyAgICAgICAgZmlsbDogIzljYTFhNjsgICAgICAgIGZpbGwtcnVsZTogZXZlbm9kZDsgICAgICB9ICAgIDwvc3R5bGU+ICA8L2RlZnM+ICA8cGF0aCBkPSJNMTUuNjM0LDQuMzMzIEMxMi41MTMsMS4yMTIgNy40NTMsMS4yMTIgNC4zMzMsNC4zMzMgQzEuMjEyLDcuNDUzIDEuMjEyLDEyLjUxMyA0LjMzMywxNS42MzQgQzcuNDUzLDE4Ljc1NCAxMi41MTMsMTguNzU0IDE1LjYzNCwxNS42MzQgQzE4Ljc1NCwxMi41MTMgMTguNzU0LDcuNDUzIDE1LjYzNCw0LjMzMyBaTTEzLjIzNSwxMy44OTkgQzEyLjU4OSwxMy41MjYgOS4wOTIsMTEuNTExIDkuMDkyLDExLjUxMSBMOS4wNjEsMTEuNTExIEM5LjA2MSwxMS41MTEgOS4wNjEsNS42NzggOS4wNjEsNC44NzMgQzkuMDYxLDQuMDY3IDEwLjMyNiw0LjAzOCAxMC4zMjYsNC44NzMgQzEwLjMyNiw1LjcwNyAxMC4zMjYsMTAuNzU0IDEwLjMyNiwxMC43NTQgQzEwLjMyNiwxMC43NTQgMTMuMDgyLDEyLjMxOSAxMy44NjcsMTIuNzk5IEMxNC42NTIsMTMuMjgwIDEzLjg4MCwxNC4yNzIgMTMuMjM1LDEzLjg5OSBaIiBjbGFzcz0iY2xzLTEiLz48L3N2Zz4=',
			'16.10' // position after gravity forms
		);
	}
} // end class
