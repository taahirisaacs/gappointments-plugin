<?php

/**
 * Plugin Name: gAppointments
 * Description: Appointment booking addon for Gravity Forms.
 * Author: WpCrunch
 * Version: 1.9.5.2
 * Author URI: https://codecanyon.net/user/wpcrunch
 */

defined('ABSPATH') or exit; // Exit if accessed directly

if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}


define('GA_PLUGIN',      __FILE__);
define('GA_PLUGIN_NAME', get_plugin_data(GA_PLUGIN)['Name']);
define('GA_VERSION',     get_plugin_data(GA_PLUGIN)['Version']);
define('GA_AUTHOR',      get_plugin_data(GA_PLUGIN)['Author']);

define('ga_base_path',   dirname(GA_PLUGIN));
define('GA_PATH_URL',    plugins_url('', GA_PLUGIN));
define('GA_ASSETS_URL',  GA_PATH_URL . '/assets/');

register_activation_hook( __FILE__, array( 'ga_appointments_addon', 'ga_plugin_activate' ) );

new ga_appointments_addon();
class ga_appointments_addon
{
    private $assets_ver = '1.2.6';

    // CONSTANTS //
    public function define_constants()
    {
        define('GA_GFORM_AFFILIATE_URL',  'https://www.gravityforms.com/');
    }

    public static function ga_plugin_activate()
    {
        add_option( 'ga_plugin_activated', 'activated' );

        // Ensure compatibility with older versions of gAppointments (1.9.4 and older).
        $ga_appointments = new WP_Query( ['post_type'=> 'ga_appointments'] );
        if( $ga_appointments->have_posts() ) {
            while( $ga_appointments->have_posts() ) {
                $ga_appointments->the_post();
                $meta = get_metadata( 'post', get_the_ID(), '', true );
                // Add a default Google Calendar id meta value to all appointments that do not have it. Required due to get_appointments_query() changes.
                if( !isset( $meta['ga_appointment_gcal_calendar_id'] ) ) {
                    update_post_meta(get_the_ID(), 'ga_appointment_gcal_calendar_id', null);
                }
            }
            wp_reset_postdata();
        }
    }

    function __construct()
    {
        if ( get_option( 'ga_plugin_activated' ) == 'activated' ) {
            add_action('init', array($this, 'init'));
        }
    }

    // INITIALIZE //
    public function init()
    {

        $this->define_constants();

        if ($this->is_gravity_forms_active()) {

            // Helper Functions
            require_once('includes/functions.php');
            // Ajax validations
            require_once('includes/ajax.php');
            // Admin settings/pages/post-types
            require_once('includes/gcal_sync.php');               // gCal Sync
            require_once('admin/page_settings.php');              // Settings page
            require_once('admin/page_ga_appointments.php');       // Appointments Post Type
            require_once('admin/page_ga_services.php');              // Services Post Type
            require_once('admin/page_ga_providers.php');          // Providers Post Type
            require_once('admin/page_activity.php');              // Activity Page
            require_once('admin/includes/ga_work_schedule.php');  // Work Schedule HTML Class
            require_once('admin/includes/ga_cron.php');           // Ga Cron

            // Styles & Scripts
            if (is_admin()) {
                add_action('admin_enqueue_scripts', array($this, 'admin_styles_scripts'));
            } else {
                add_action('wp_loaded', array($this, 'frontend_styles_scripts'));
                add_action('gform_enqueue_scripts', array($this, 'gform_enqueue_form_scripts'), 10, 2);
            }

            // Ajax Actions
            $this->ga_ajax_actions();

            // CMB2 framework
            require_once('includes/cmb2/init.php');

            // Appointment Shortcodes
            require_once('includes/shortcodes.php');

            // GF CUSTOM FIELDS
            require_once('gf-fields/gf-form-settings.php');
            require_once('gf-fields/ga-calendar.php');
            require_once('gf-fields/gf-booking-services.php');
            require_once('gf-fields/gf-booking-providers.php');
            require_once('gf-fields/gf-booking-calendar.php');
            require_once('gf-fields/gf-booking-cost.php');
        } else {
            add_action('admin_notices', array($this, 'gravity_not_active_message'));
        }
    }

    // IS GF ACTIVE
    public function is_gravity_forms_active()
    {
        return class_exists('GFCommon');
    }

    // GF NOT ACTIVE MESSAGE
    public function gravity_not_active_message()
    {
        $message = sprintf(
            __('Gravity Forms is required in order to use gAppointments. Activate it now or %1$spurchase it today!%2$s', 'ga_appointments'),
            '<a href="' . GA_GFORM_AFFILIATE_URL . '" target="_blank">',
            '</a>'
        );
        echo '<div id="ga_error" class="error is-dismissible"><p>' . $message . '</p></div>';
    }

    /**
     * Admin scripts & styles
     */
    public function admin_styles_scripts($hook)
    {
        global $post_type;
        $valid_post_types = array('ga_appointments', 'ga_services', 'ga_providers');

        if ($hook == 'toplevel_page_ga_appointments_settings' || in_array($post_type, $valid_post_types)) {
            // Datepicker UI
            wp_enqueue_script('jquery-ui-datepicker');

            // Datepicker Css
            wp_enqueue_style('jquery-ui-datepicker-base-theme', plugins_url('assets/datepicker.css', __FILE__), false, $this->assets_ver);

            // Add the color picker css file
            wp_enqueue_style('wp-color-picker');

            // Font awesome
            wp_enqueue_style('ga-font-awesome', plugins_url('assets/font-awesome.min.css', __FILE__), false, $this->assets_ver);
        }

        if (in_array($post_type, $valid_post_types)) {
            // Auto-save disabled to fix popup alert
            wp_dequeue_script('autosave');
        }

        // Admin css
        wp_enqueue_style('ga_appointments_admin_css',  plugins_url('assets/admin.css', __FILE__), false, $this->assets_ver);

        // Schedule css
        wp_enqueue_style('ga_appointments_schedule_css',  plugins_url('assets/schedule.css', __FILE__), false, $this->assets_ver);

        // Grid css
        wp_enqueue_style('ga_appointments_calendar_css_grid', plugins_url('assets/grid.css', __FILE__), false, $this->assets_ver);

        // Admin scripts
        wp_enqueue_script('ga_appointments_calendar_script', plugins_url('assets/admin.js', __FILE__), array('wp-color-picker'), $this->assets_ver, true);

        // Schedule scripts
        wp_enqueue_script('ga_appointments_schedule_script', plugins_url('assets/schedule.js', __FILE__), false, $this->assets_ver, true);

        // AJAX: Service delete term
        wp_localize_script('ga_appointments_calendar_script', 'ga_service_delete_term_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // bb_appointments_calendar_obj = for ajax reference

        // AJAX: Service delete term
        wp_localize_script('ga_appointments_calendar_script', 'ga_service_add_slot_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // bb_appointments_calendar_obj = for ajax reference

        //For admin ajax appointment status change
        wp_localize_script('ga_appointments_calendar_script', 'ga_change_appointment_status_obj', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    /**
     * Frontend scripts & styles
     */
    public function frontend_styles_scripts()
    {
        // GF Chosen
        wp_enqueue_script('gform_chosen');

        // Frontend css
        wp_enqueue_style('ga_appointments_calendar_css', plugins_url('assets/calendar.css', __FILE__), false, $this->assets_ver);

        // Schedule css
        wp_enqueue_style('ga_appointments_schedule_css',  plugins_url('assets/schedule.css', __FILE__), false, $this->assets_ver);

        // Frontend grid css
        wp_enqueue_style('ga_appointments_calendar_css_grid', plugins_url('assets/grid.css', __FILE__), false, $this->assets_ver);

        // WP Dashicons
        wp_enqueue_style('dashicons');

        // Font awesome
        wp_enqueue_style('ga-font-awesome', plugins_url('assets/font-awesome.min.css', __FILE__), false, $this->assets_ver);

        // Main javascipt file
        wp_enqueue_script('ga_appointments_calendar_script', plugins_url('assets/main.js', __FILE__), false, $this->assets_ver, true);

        // Schedule scripts
        wp_enqueue_script('ga_appointments_schedule_script', plugins_url('assets/schedule.js', __FILE__), false, $this->assets_ver, true);

        // AJAX: Services
        wp_localize_script('ga_appointments_calendar_script', 'ga_calendar_services_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_services_obj = for ajax reference

        // AJAX: Providers
        wp_localize_script('ga_appointments_calendar_script', 'ga_calendar_providers_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_services_obj = for ajax reference

        // AJAX: Next Month
        wp_localize_script('ga_appointments_calendar_script', 'ga_calendar_next_month_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_next_month_obj = for ajax reference

        // AJAX: Prev Month
        wp_localize_script('ga_appointments_calendar_script', 'ga_calendar_prev_month_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_prev_month_obj = for ajax reference

        // AJAX: Time Slots
        wp_localize_script('ga_appointments_calendar_script', 'ga_calendar_time_slots_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_time_slots_obj = for ajax reference

        // AJAX: Update Schedule
        wp_localize_script('ga_appointments_schedule_script', 'ga_calendar_schedule_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // ga_calendar_time_slots_obj = for ajax reference

        // AJAX: Cancel/Update Appointment
        wp_localize_script('ga_appointments_calendar_script', 'ga_update_appointment_status_obj', array('ajax_url' => admin_url('admin-ajax.php'))); // bb_appointments_calendar_obj = for ajax reference

        //AJAX : Get calendar
        wp_localize_script('ga_appointments_calendar_script', 'ga_get_calendar_obj', array('ajax_url' => admin_url('admin-ajax.php')));

	    //For user ajax set appointment status to pending
	    wp_localize_script('ga_appointments_calendar_script', 'ga_user_set_appointment_pending_obj', array('ajax_url' => admin_url('admin-ajax.php')) );
    }

    /**
     * Add cost to total field
     */
    public function gform_enqueue_form_scripts($form, $is_ajax)
    {

        if (gf_field_type_exists($form, 'appointment_calendar') && gf_field_type_exists($form, 'total')) {
            // Appointment Total Script
            wp_enqueue_script('ga_appointments_calendar_total', plugins_url('assets/total.js', __FILE__), false, $this->assets_ver, true);
        }

        if (gf_field_type_exists($form, 'appointment_cost')) {
            // Main javascipt file
            wp_enqueue_script('gform_gravityforms');
        }
    }


    /**
     * Ajax Actions
     */
    public function ga_ajax_actions()
    {
        // Select Service
        add_action('wp_ajax_ga_calendar_select_service', array($this, 'ga_calendar_select_service'));
        add_action('wp_ajax_nopriv_ga_calendar_select_service', array($this, 'ga_calendar_select_service'));

        // Select Provider
        add_action('wp_ajax_ga_calendar_select_provider', array($this, 'ga_calendar_select_provider'));
        add_action('wp_ajax_nopriv_ga_calendar_select_provider', array($this, 'ga_calendar_select_provider'));

        // Next month
        add_action('wp_ajax_ga_calendar_next_month', array($this, 'ga_calendar_next_month'));
        add_action('wp_ajax_nopriv_ga_calendar_next_month', array($this, 'ga_calendar_next_month'));

        // Prev month
        add_action('wp_ajax_ga_calendar_prev_month', array($this, 'ga_calendar_prev_month'));
        add_action('wp_ajax_nopriv_ga_calendar_prev_month', array($this, 'ga_calendar_prev_month'));

        // Generate Time Slots
        add_action('wp_ajax_ga_calendar_time_slots', array($this, 'ga_calendar_time_slots'));
        add_action('wp_ajax_nopriv_ga_calendar_time_slots', array($this, 'ga_calendar_time_slots')); //

        // Provider Schedule Update
        add_action('wp_ajax_ga_provider_schedule_update', array($this, 'ga_provider_schedule_update'));

        // Service delete category term
        add_action('wp_ajax_ga_service_delete_term', array($this, 'ga_service_delete_term'));

        // Service add custom slot
        add_action('wp_ajax_ga_service_add_slot', array($this, 'ga_service_add_slot'));

        // Get calendar
        add_action('wp_ajax_ga_get_calendar', array($this, 'ga_get_calendar'));
    }

    /**
     * Ajax: Delete service term
     */
    public function ga_service_delete_term()
    {
        if (!is_admin()) {
            return;
        }

        $term_id = isset($_POST['term_id']) ? $_POST['term_id'] : false;

        if ($term_id) {

            if (wp_delete_term($term_id, 'ga_service_cat')) {
                $response = array('success' => true);
                wp_send_json_success($response);
                wp_die();
            } else {
                $response = array('success' => false);
                wp_send_json_error($response);
                wp_die();
            }
        } else {
            $response = array('success' => false);
            wp_send_json_error($response);
            wp_die();
        }
    }

    /**
     * AJAX: Select Service
     */
    public function ga_calendar_select_service()
    {
        $response = array(
            'providers'   => '<option value="">Providers not found</option>',
            'calendar'    => '<p>Something went wrong.</p>'
        );

        $service = isset($_POST['service']) ? $_POST['service'] : 0;
        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;

        $service_id = get_page_by_title( esc_html( $service ), OBJECT, 'ga_services' );
        if( !is_null( $service_id ) && isset( $service_id->ID ) ) {
            $service_id = $service_id->ID;
        }

        if ('ga_services' == get_post_type($service_id)) {
            $response['providers'] =  GF_Appointment_Booking_Providers::get_providers_choices( null, null, $service_id );
            $provider_id = ga_get_provider_id($service_id) ? ga_get_provider_id($service_id) : 0;

            $current_date = ga_current_date_with_timezone();

            // Service period type
            $period_type = (string) get_post_meta($service_id, 'ga_service_period_type', true);
            if ($period_type == 'date_range') {
                $range = (array) get_post_meta($service_id, 'ga_service_date_range', true);
                if (isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to'])) {
                    $current_date = new DateTime($range['from'], new DateTimeZone(ga_time_zone()));
                }
            }
            if ($period_type == 'custom_dates') {
                $custom_dates = (array) get_post_meta($service_id, 'ga_service_custom_dates', true);
                if (is_array($custom_dates) && count($custom_dates) > 0 && ga_valid_date_format(reset($custom_dates))) {
                    $current_date = new DateTime(reset($custom_dates), new DateTimeZone(ga_time_zone()));
                }
            }

            $ga_calendar = new GA_Calendar($form_id, $current_date->format('n'), $current_date->format('Y'), $service_id, $provider_id);
            $response['calendar'] = $ga_calendar->show();


            wp_send_json_success($response);
            wp_die();
        } else {
            wp_send_json_success($response);
            wp_die();
        }
    }


    /**
     * AJAX: Select Provider
     */
    public function ga_calendar_select_provider()
    {
        $service    = $_POST['service'] ?? 0;
        $service_id = get_page_by_title( esc_html( $service ), OBJECT, 'ga_services' );
        $service_id = $service_id->ID ?? null;

        $provider    = $_POST['provider'] ?? 0;
        $provider_id = get_page_by_title( esc_html( $provider ), OBJECT, 'ga_providers' );
        if( isset( $provider_id->ID ) && 'ga_providers' == get_post_type( $provider_id ) ) {
            $provider_id = $provider_id->ID;
        } else {
            $provider_id = null;
        }

        $form_id     = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;

        if ('ga_services' == get_post_type($service_id)) {

            $current_date = ga_current_date_with_timezone();

            // Service period type
            $period_type = (string) get_post_meta($service_id, 'ga_service_period_type', true);
            if ($period_type == 'date_range') {
                $range = (array) get_post_meta($service_id, 'ga_service_date_range', true);
                if (isset($range['from']) && ga_valid_date_format($range['from']) && isset($range['to']) && ga_valid_date_format($range['to'])) {
                    $current_date = new DateTime($range['from'], new DateTimeZone(ga_time_zone()));
                }
            }
            if ($period_type == 'custom_dates') {
                $custom_dates = (array) get_post_meta($service_id, 'ga_service_custom_dates', true);
                if (is_array($custom_dates) && count($custom_dates) > 0 && ga_valid_date_format(reset($custom_dates))) {
                    $current_date = new DateTime(reset($custom_dates), new DateTimeZone(ga_time_zone()));
                }
            }

            $ga_calendar = new GA_Calendar($form_id, $current_date->format('n'), $current_date->format('Y'), $service_id, $provider_id);
            echo $ga_calendar->show();
        } else {
            wp_die('Something went wrong.');
        }

        wp_die(); // Don't forget to stop execution afterward.
    }


    /**
     * AJAX: Calendar Previous Month
     */
    public function ga_calendar_prev_month()
    {
        $current_date   = isset($_POST['current_month']) ? esc_html($_POST['current_month']) : '';
        $service_id     = isset($_POST['service_id'])    ? (int) $_POST['service_id']        : 0;
        $provider_id    = isset($_POST['provider_id']) && 'ga_providers' == get_post_type($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;

        if ('ga_services' == get_post_type($service_id) && ga_valid_year_month_format($current_date)) {
            $timezone = ga_time_zone();

            $date = new DateTime($current_date, new DateTimeZone($timezone));
            $date->modify('-1 month');

            $ga_calendar = new GA_Calendar($form_id, $date->format('n'), $date->format('Y'), $service_id, $provider_id);
            echo $ga_calendar->show();
        } else {
            wp_die("Something went wrong.");
        }

        wp_die(); // Don't forget to stop execution afterward.
    }

    /**
     * AJAX: Calendar Next Month
     */
    public function ga_calendar_next_month()
    {
        $current_date   = isset($_POST['current_month']) ? esc_html($_POST['current_month']) : '';
        $service_id     = isset($_POST['service_id'])    ? (int) $_POST['service_id']        : 0;
        $provider_id    = isset($_POST['provider_id']) && 'ga_providers' == get_post_type($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;

        if ('ga_services' == get_post_type($service_id) && ga_valid_year_month_format($current_date)) {
            $timezone = ga_time_zone();

            $date = new DateTime($current_date, new DateTimeZone($timezone));
            $date->modify('+1 month');

            $ga_calendar = new GA_Calendar($form_id, $date->format('n'), $date->format('Y'), $service_id, $provider_id);
            echo $ga_calendar->show();
        } else {
            wp_die("Something went wrong.");
        }

        wp_die(); // Don't forget to stop execution afterward.
    }

    /**
     * AJAX: Generate Time Slots
     */
    public function ga_calendar_time_slots()
    {
        // Timezone
        $timezone = ga_time_zone();

        // Service & Provider ID
        $current_date = isset($_POST['current_month']) ? esc_html($_POST['current_month']) : '';
        $service_id   = isset($_POST['service_id'])    ? (int) $_POST['service_id']        : 0;
        $provider_id  = isset($_POST['provider_id']) && 'ga_providers' == get_post_type($_POST['provider_id']) ? (int) $_POST['provider_id'] : 0;
        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;

        if ('ga_services' == get_post_type($service_id) && ga_valid_date_format($current_date)) {
            # ok
        } else {
            wp_die('Something went wrong.');
        }

        // Date Caption
        $date = new DateTime($current_date, new DateTimeZone($timezone));

        // Generate Slots
        $ga_calendar = new GA_Calendar($form_id, $date->format('n'), $date->format('Y'), $service_id, $provider_id);
        echo $ga_calendar->calendar_time_slots($date);
        wp_die();
    }

    /**
     * Ajax: Update Provider Schedule
     */
    public function ga_provider_schedule_update()
    {
        $error    = array('success' => false, 'message' => '<div class="ga_alert ga_alert_danger">' . ga_get_translated_data('error') . '</div>');

        if (!is_user_logged_in_a_provider()) {
            wp_send_json_error($error);
            wp_die();
        }

        // Data
        $posted = isset($_POST) ? $_POST : array();

        // Provider Post ID
        $provider_id = get_logged_in_provider_id();

        // Policies options
        $policies = get_option('ga_appointments_policies');

        // Provider own calendar schedule
        $calendar = get_post_meta($provider_id, 'ga_provider_calendar', true);

        // Provider manages its schedule on the front-end
        $manage = isset($policies['provider_manages_schedule']) && in_array($policies['provider_manages_schedule'], array('yes', 'no')) ? $policies['provider_manages_schedule'] : 'yes';

        if (isset($posted['action']) && $posted['action'] == 'ga_provider_schedule_update' && $calendar == 'on' && $manage == 'yes') {
            if (!class_exists('ga_work_schedule')) {
                require_once(ga_base_path . '/admin/includes/ga_work_schedule.php');
            }
            $schedule = new ga_work_schedule($provider_id);
            $work_schedule = isset($_POST['ga_provider_work_schedule']) ? $_POST['ga_provider_work_schedule'] : array();
            $breaks        = isset($_POST['ga_provider_breaks'])        ? $_POST['ga_provider_breaks']        : array();
            $holidays      = isset($_POST['ga_provider_holidays'])      ? $_POST['ga_provider_holidays']      : array();

            update_post_meta($provider_id, 'ga_provider_work_schedule', $schedule->validate_work_schedule($work_schedule));
            update_post_meta($provider_id, 'ga_provider_breaks', $schedule->validate_breaks($breaks));
            update_post_meta($provider_id, 'ga_provider_holidays', $schedule->validate_holidays($holidays));

            $success = array('success' => true, 'html' => ga_provider_schedule_form($provider_id), 'message' => '<div class="ga_alert ga_alert_success">' . ga_get_translated_data('schedule_updated') . '</div>');
            wp_send_json_success($success);
            wp_die();
        } else {
            wp_send_json_error($error);
            wp_die();
        }
    }

    /**
     * Ajax get calendar
     */
    public function ga_get_calendar()
    {
        $ga_policies = get_option('ga_appointments_policies');
        $appointment_reschedule = isset($ga_policies['appointment_reschedule']) ? $ga_policies['appointment_reschedule'] : 'no';
        if ($appointment_reschedule === 'no') {
            wp_die();
        }
        $app_id   = isset($_POST['app_id']) ? esc_html($_POST['app_id']) : false;
        if ($app_id === false) {
            wp_die();
        }
        $form_id = get_post_meta($app_id, 'ga_appointment_gf_entry_id', true); // ?? not sure if it is the correct one
        $service_id = get_post_meta($app_id, 'ga_appointment_service', true);

        $shortcode = new ga_appointment_shortcodes();
        $date       = $shortcode->ga_date( $app_id );
        $time_start = $shortcode->ga_time( $app_id );
        $time_end   = $shortcode->ga_time( $app_id, $translation = true, $start_time = false );
        $type = get_post_meta($app_id, 'ga_appointment_type', true);
        if ($type === 'time_slot') {
            $time = $time_start . ' - ' . $time_end;
        } else {
            $time = '';
        }

        $calendar = new GF_Appointment_Booking_Calendar();
        $calendar = $calendar->get_field_input(array('id' => $form_id), '', null, $service_id);

        ?>
        <style>
            .ga_reschedule_calendar_container #gappointments_calendar {
                width: 100% !important;
            }
        </style>
        <div><b><?php echo ga_get_translated_data('current_date_time') ?>:</b><br> <?php echo $date . ' ' . $time; ?> </div>
        <div class="ga_reschedule_calendar_container"><?php echo $calendar; ?></div>
    <?php
            wp_die();
        }


        /**
         * Ajax: Delete service term
         */
        public function ga_service_add_slot()
        {
            $name = 'ga_service_custom_slots';
            $id   = uniqid();
            ?>
        <tr>
            <td>
                <select name="<?php echo "{$name}[{$id}][start]"; ?>">
                    <?php
                            foreach (get_ga_appointment_time() as $time => $text) {
                                echo '<option value=' . $time . '>' . $text . '</option>';
                            }
                            ?>
                </select>
            </td>

            <td>
                <select name="<?php echo "{$name}[{$id}][end]"; ?>">
                    <?php
                            foreach (get_ga_appointment_time($out = false, $_24 = true) as $time => $text) {
                                echo '<option value=' . $time . '>' . $text . '</option>';
                            }
                            ?>
                </select>
            </td>

            <td>
                <?php
                        foreach (array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $key => $week) {
                            $week_short = substr(ucfirst($week), 0, 1);
                            echo "<div class='ga_week_day'>
								<label>
									<input type='checkbox' name='{$name}[{$id}][availability][]' value='{$week}'>
									<span>{$week_short}</span>
								</label>
							</div>";
                        }
                        ?>
            </td>

            <td>
                <select name="<?php echo "{$name}[{$id}][capacity]"; ?>">
                    <?php
                            foreach (ga_services_capacity_options() as $num => $text) {
                                echo '<option value=' . $num . '>' . $text . '</option>';
                            }
                            ?>
                </select>
            </td>

            <td>
                <?php echo gf_get_currency_symbol(); ?> <input type="text" class="cmb2-text-small" name="<?php echo "{$name}[{$id}][price]"; ?>" id="" value="">
            </td>

            <td>
                <div class="slot-delete">Delete</div>
            </td>
        </tr>

<?php
        wp_die();
    }
} // end class
