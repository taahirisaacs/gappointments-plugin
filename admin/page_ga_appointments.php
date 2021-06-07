<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

new ga_appointments_post_type();
class ga_appointments_post_type
{

	public function __construct()
	{
		// Post type		
		add_action('wp_loaded', array($this, 'ga_appointments_init'));

		// New Statuses
		add_action('wp_loaded', array($this, 'ga_appointments_new_statuses'));

		// Appointments Details Options
		add_action('cmb2_admin_init', array($this, 'cmb2_ga_appointments_details_metaboxes'));

		// Rename post statuses
		add_filter("views_edit-ga_appointments", array($this, 'rename_ga_appointments_post_statuses'));

		// Appointments post type new submit widget
		add_action('cmb2_admin_init', array($this, 'cmb2_ga_appointments_submitdiv_metabox'));

		// Appointment row columns
		add_filter("manage_edit-ga_appointments_columns", array($this, 'manage_ga_appointments_columns')); // column names
		add_filter("manage_edit-ga_appointments_sortable_columns", array($this, 'sortable_ga_appointments_columns')); // sortable columns

        add_filter('list_table_primary_column', array($this, 'ga_appointments_primary_column'), 10, 2 ); //change primary column
        add_filter('post_row_actions', array($this, 'ga_appointments_row_actions'), 10, 2 ); //remove default actions

		add_action("manage_ga_appointments_posts_custom_column", array($this, 'manage_ga_appointments_posts_custom_column'), 10, 2); // html column

		// Add post type filters
		add_action('restrict_manage_posts', array($this, 'add_service_provider_filter_to_posts_administration'));
		add_action('pre_get_posts', array($this, 'add_service_provider_filter_to_posts_query'));

		// Remove Date Drop Filter
		add_action('admin_head', array($this, 'remove_date_drop'));

        // Prepare review page
        add_filter( 'gform_review_page', array($this, 'ga_add_review_page'), 10, 3 );

        // Serialize Appointment Data Pre Submission
        add_action('gform_pre_submission', array($this, 'serialize_ga_appointment'), 10, 1);

		// Add Gravity Appointment
		add_action('gform_after_submission', array($this, 'add_new_ga_appointment'), 10, 2);


		// Cancel Appointment Action
		add_action('wp_ajax_ga_cancel_appointment', array($this, 'ga_update_appointment_status'));
		add_action('wp_ajax_nopriv_ga_cancel_appointment', array($this, 'ga_update_appointment_status'));

		//Reschedule Appointment
		add_action('wp_ajax_ga_reschedule_appointment', array($this, 'ga_update_appointment_status'));

		//user set the appointment status to pending
		add_action('wp_ajax_ga_user_set_appointment_pending', array($this, 'user_set_appointment_status'));

		//Change Appointment status admin
		add_action('wp_ajax_ga_change_appointment_status', array($this, 'ga_admin_update_appointment_status'));

		// Provider Cancel Appointment Action
		add_action('wp_ajax_ga_provider_cancel_appointment', array($this, 'ga_update_appointment_status'));
		add_action('wp_ajax_nopriv_ga_provider_cancel_appointment', array($this, 'ga_update_appointment_status'));

		// Provider Confirms Appointment Action
		add_action('wp_ajax_ga_provider_confirm', array($this, 'ga_update_appointment_status'));
		add_action('wp_ajax_nopriv_ga_provider_confirm', array($this, 'ga_update_appointment_status'));

		// After paid GF Entry
		add_action('transition_post_status', array($this, 'after_paid_gf_entry'), 10, 3);


		// ACTION: New Appointment and sync to gcal
		add_action('ga_new_appointment', array($this, 'ga_new_appointment'), 10, 2);

		// ACTION: Delete Appointment and gcal event
		add_action('before_delete_post', array($this, 'ga_delete_appointment'), 10);

		// ACTION: Update Appointment and gcal event
        add_action('cmb2_save_post_fields_ga_appointment_details', array($this, 'ga_update_appointment'), 10, 3);

		// ACTION: Bulk Appointments sync to gcal
		add_action('ga_bulk_appointments', array($this, 'ga_bulk_appointments'), 10, 2);

		// ACTION: Bulk Appointments sync to gcal
		add_action('ga_appointment_provider_switch', array($this, 'ga_appointment_provider_switch'));

        // ACTION: Update appointment on status_change
//		add_action('transition_post_status', array($this, 'ga_update_appointment_on_status_change'), 10, 3)
	}

    public function ga_add_review_page( $review_page, $form, $entry ) {

        // Enable the review page
        $ga_form_review_page       = rgar($form, 'ga_form_review_page');
        $review_page['is_enabled'] = !empty( $ga_form_review_page );

        if ( $entry && $review_page['is_enabled'] ) {
            $entry_clone = $entry;
            $form_clone = $form;

            $calendar_field_value = gf_get_field_type_value( $form_clone, 'appointment_calendar', $field_id );
            if( !empty( $calendar_field_value['date'] ) && !empty( $calendar_field_value['time'] ) ) {
                $form_clone = populate_services_for_conditional_logic( $form_clone );
                $form_clone = populate_providers_for_conditional_logic( $form_clone );
                $calendar_formatted     = GF_Appointment_Booking_Calendar::TranslateDateToText( $calendar_field_value, $form_clone );
                $entry_clone[$field_id] = $calendar_formatted;
            }

            $cost_field_value       = gf_get_field_type_value( $form_clone, 'appointment_cost', $field_id );
            if( $cost_field_value === '0' || !empty( $cost_field_value ) ) {
                $cost_formatted         = gf_to_money( $cost_field_value );
                $entry_clone[$field_id] = $cost_formatted;
            }

            // Populate the review page.
            $review_page['content'] = GFCommon::replace_variables( '{all_fields}', $form_clone, $entry_clone );
        }

        return $review_page;
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
	 * Remove Date Drop Filter
	 */
	public function remove_date_drop()
	{
		global $post_type, $pagenow;

		if ($pagenow == 'edit.php' && $post_type == 'ga_appointments') {
			add_filter('months_dropdown_results', '__return_empty_array');
		}
	}



	/**
	 * Appointments Post Type
	 */
	public function ga_appointments_init()
	{
		$questions_labels = array(
			'name' => _x('Appointments', 'post type general name'),
			'singular_name' => _x('Appointment', 'post type singular name'),
			'all_items' => __('Appointments'),
			'add_new' => _x('Add new', 'ga_booking_appointments'),
			'add_new_item' => __('Add new appointment'),
			'edit_item' => __('Edit appointment'),
			'new_item' => __('New appointment'),
			'view_item' => __('View appointment'),
			'search_items' => __('Search appointments'),
			'not_found' =>  __('You do not have any appointments!'),
			'not_found_in_trash' => __('Nothing found in trash'),
			'parent_item_colon' => ''
		);

		$args = array(
			'labels' => $questions_labels,
			'public' => false,
			'publicly_queryable' => false,
			'has_archive' => false,
			'show_ui' => true,
			'show_in_menu' => 'ga_appointments_settings',
			'query_var' => true,
			'rewrite' => array('slug' => 'ga_contact'),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array(''), // nothing supports
		);

		register_post_type('ga_appointments', $args);
	}

	/**
	 * Appointment Post Type New Statuses
	 */
	public function ga_appointments_new_statuses()
	{
		register_post_status('completed', array(
			'label'                     => _x('Completed', 'post'),
			'public'                    => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>'),
		));


		register_post_status('payment', array(
			'label'                     => _x('Pending Payment', 'post'),
			'public'                    => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Pending Payment <span class="count">(%s)</span>', 'Pending Payment <span class="count">(%s)</span>'),
		));

		register_post_status('cancelled', array(
			'label'                     => _x('Cancelled', 'post'),
			'public'                    => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
		));
	}


	/**
	 * 1. Add custom filters to appointments post type
	 */
	public function add_service_provider_filter_to_posts_administration()
	{

		//execute only on the 'post' content type
		global $post_type, $pagenow;

		//if we are currently on the edit screen of the post type listings
		if ($pagenow == 'edit.php' && $post_type == 'ga_appointments') {

			$services_args = array(
				'show_option_all'   => 'Filter by service',
				'name'              => 'service_filter',
				'selected'          => '0',
			);

			$providers_args = array(
				'show_option_all'   => 'Filter by provider',
				'name'              => 'provider_filter',
				'selected'          => '-1',
			);

			$date_filter_args = array(
				'name'              => 'ga_date_filter',
				'selected'          => '',
			);

            $sync_service = get_default_sync_service();
            $sync_option = is_null($sync_service) ? -1 : $sync_service;
            $sync_args = array(
                'show_option'       => 'Show sync events',
                'hide_option'       => 'Hide sync events',
                'name'              => 'ga_sync_service_filter',
                'selected'          => $sync_option,
            );

			// Filter by service
			if (isset($_GET['service_filter'])) {
				//set the selected value to the value of the author
				$services_args['selected'] = absint($_GET['service_filter']);
			}

			echo '<select name="' . $services_args['name'] . '" id="' . $services_args['name'] . '">';
			echo '<option value="0">' . $services_args['show_option_all'] . '</option>';

			if (get_ga_appointment_services()) {
				foreach (get_ga_appointment_services() as $_id => $service_title) {
					$selected = $services_args['selected'] == $_id ? ' selected' : '';
					echo '<option value="' . $_id . '"' . $selected . '>' . $service_title . '</option>';
				}
			}
			echo '</select>';

			// Filter by provider			
			if (isset($_GET['provider_filter'])) {
				// set the selected value to the value of the author
				$providers_args['selected'] = (int) $_GET['provider_filter'];
			}

			echo '<select name="' . $providers_args['name'] . '" id="' . $providers_args['name'] . '">';
			echo '<option value="-1">' . $providers_args['show_option_all'] . '</option>';

			if (get_ga_appointment_providers()) {
				foreach (get_ga_appointment_providers() as $_id => $service_title) {
					$selected = $providers_args['selected'] == $_id ? ' selected' : '';
					echo '<option value="' . $_id . '"' . $selected . '>' . $service_title . '</option>';
				}
			}

			echo '</select>';

			// Filter by input date		
			if (isset($_GET['ga_date_filter'])) {
				// set the selected value to the value of the author
				$date_filter_args['selected'] = esc_html($_GET['ga_date_filter']);
			}

			echo '<input type="search" class="ga-date-picker" name="' . $date_filter_args['name'] . '" id="' . $date_filter_args['name'] . '" value="' . $date_filter_args['selected'] . '" placeholder="Filter by date">';

			// Filter by sync service
            if( !is_null($sync_service) ) {

                if( isset($_GET['ga_sync_service_filter']) ) {
                    $sync_args['selected'] = (int) $_GET['ga_sync_service_filter'];
                }
                echo "<select name=\"{$sync_args['name']}\" id=\"{$sync_args['name']}\">";

                echo "<option value=\"-1\"              " . selected( '-1' , $sync_args['selected']) .          ">{$sync_args['show_option']}</option>";
                echo "<option value=\"{$sync_service}\" " . selected( $sync_service , $sync_args['selected']) . ">{$sync_args['hide_option']}</option>";

                echo '</select>';
            }
		}
	}

	/**
	 * 2. Add custom filters to appointments post type
	 */
	public function add_service_provider_filter_to_posts_query($query)
	{
		global $post_type, $pagenow;

		//if we are currently on the edit screen of the post type listings
		if( $pagenow == 'edit.php' && $post_type == 'ga_appointments' && $query->is_main_query() ) {
			$filters = array();

            // Appointment search
            if( isset( $_GET['s'] ) && $_GET['s'] != '' ) {
                $search_value  = sanitize_text_field($query->get('s'));
                $search_query  = array('relation' => 'OR');
                $search_fields = array(
                    'ga_appointment_client',
                    'ga_appointment_new_client',
                    'ga_appointment_time_end',
                    'ga_appointment_time',
                    'ga_appointment_date',
                    'ga_appointment_duration',
                );

                // Remove "s" parameter from the query, because it will prevent the posts from being found
                $query->query_vars['s'] = '';

                if( !empty( $search_value ) ) {
                    // search by client (username)
                    $user = get_user_by( 'login', $search_value );

                    // TODO: add additional search options for service, provider, status, etc.
                    foreach ($search_fields as $field) {
                        $value = $search_value;
                        if( $field === 'ga_appointment_client' && !empty( $user ) ) {
                            $value = $user->ID;
                        } else if( $field === 'ga_appointment_new_client' ) {
                            $value = serialize( $search_value );
                        }
                        $search_query[] = array(
                            'key' => $field,
                            'value' => $value,
                            'compare' => 'LIKE'
                        );
                    }
                    $filters[] = $search_query;
                }
            }

            // Appointment order
            $appointment_date_extra_query = array(
                'relation' => 'OR',
                array('key' => 'ga_appointment_date', 'compare' => '=', 'value' => ''),
                array('key' => 'ga_appointment_date', 'compare' => 'NOT EXISTS'),
                array('key' => 'ga_appointment_date', 'type' => 'DATE')
            );

            // Order by column
            if( isset($_GET['orderby']) && isset($_GET['order']) ) {
                $column_to_order_by = sanitize_text_field($query->get('orderby'));
                $new_order          = sanitize_text_field($query->get('order'));
                $column             = 'ga_appointment_service';
                $column_type        = 'NUMERIC';
                $column_query       = array();

                switch( $column_to_order_by ) {
                    case __('Appointment Date'):
                        $column       = 'ga_appointment_date';
                        $column_type  = 'DATE';
                        break;
                    case __('Time'):
                        $column       = 'ga_appointment_time';
                        $column_type  = 'TIME';
                        break;
                    case __('Ends'):
                        $column       = 'ga_appointment_time_end';
                        $column_type  = 'TIME';
                        break;
                    case __('Duration'):
                        $column       = 'ga_appointment_duration';
                        break;
                    case __('Provider'):
                        $column       = 'ga_appointment_provider';
                        break;
                    case __('Service'):
                        $column       = 'ga_appointment_service';
                        break;
                    case __('Client'):
                        $column       = 'ga_appointment_client';
                        break;
                }

                switch( $column_type ) {
                    case 'DATE':
                        $column_query = array(
                            'date' => array('key' => $column, 'type' => $column_type),
                            'time' => array('key' => 'ga_appointment_time', 'type' => 'TIME'),
                            $appointment_date_extra_query
                        );
                        $new_order_by = array(
                            'date'   => $new_order,
                            'time'   => 'ASC',
                        );
                        break;
                    case 'TIME':
                        $column_query = $this->ga_get_column_order_query( $column, $column_type, $new_order, $appointment_date_extra_query );
                        $new_order_by = array(
                            'date'  => 'ASC',
                            $column => $new_order,
                        );
                        break;
                    case 'NUMERIC':
                        $column_query = $this->ga_get_column_order_query( $column, $column_type, $new_order, $appointment_date_extra_query, $new_order_by );
                        break;

                }

                // Set order filter
                $filters[] = $column_query;

            } else {

                // Default Appointment Order
                $filters[] = array(
                    'relation' => 'AND',
                    'date' => array('key' => 'ga_appointment_date', 'type' => 'DATE'),
                    'time' => array('key' => 'ga_appointment_time', 'type' => 'TIME'),
                    array(
                        // A nested set of conditions for when the above condition is false.
                        $appointment_date_extra_query
                    ),
                );
                $new_order_by =  array(
                    'date'   => 'ASC',
                    'time'   => 'ASC',
                );
            }

            // Sort Appointment Order

            // Filter by provider
			if( isset($_GET['service_filter']) ) {

				//set the query variable for 'author' to the desired value
				$service_id = sanitize_text_field($_GET['service_filter']);

				//if the author is not 0 (meaning all)
				if( $service_id != 0 ) {
					$filters[] = array(
						'key'     => 'ga_appointment_service',
						'value'   => $service_id,
						'type'    => 'numeric',
					);
				}
			}

			// Filter by selected provider
			if( isset($_GET['provider_filter']) ) {
				$provider_id = sanitize_text_field($_GET['provider_filter']);

				if( $provider_id != '-1' ) {
					$filters[] = array(
						'key'     => 'ga_appointment_provider',
						'value'   => $provider_id,
						'type'    => 'numeric',
					);
				}
			}

            // Filter by date
			if( isset($_GET['ga_date_filter']) && ga_valid_date_format($_GET['ga_date_filter']) ) {
				$date = $_GET['ga_date_filter'];
				$filters[] = array(
					'key'     => 'ga_appointment_date',
					'value'   => $date,
				);
			}

			// Filter by two way sync service
            if( !isset( $_GET['ga_sync_service_filter']) ) {
                $sync_service = get_default_sync_service();
            } else {
                $sync_service = $_GET['ga_sync_service_filter'];
            }
            if( isset($sync_service) && !is_null($sync_service) ) {
                if( $sync_service != '-1' ) {
                    $filters[] = array(
                        'key'     => 'ga_appointment_service',
                        'value'   => $sync_service,
                        'compare' => '!=',
                        'type'    => 'numeric',
                    );
                }
            }

            // Set query params
			$query->set('meta_query', $filters);
			$query->set('orderby', $new_order_by);
		}
	}

	private function ga_get_column_order_query( $column, $type, $order, $appointment_date_extra_query, &$new_order_by = false ) {
        $column_query = array(
            'relation' => 'AND',
            $column => array('key' => $column, 'type' => $type),
            'date'  => array('key' => 'ga_appointment_date', 'type' => 'DATE'),
            $appointment_date_extra_query,
        );

        $new_order_by = array(
            $column => $order,
            'date'  => 'ASC'
        );

        return $column_query;
    }

	/**
	 * Appointments Details Options
	 */
	public function cmb2_ga_appointments_details_metaboxes()
	{

		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_appointment_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box(array(
			'id'            => 'ga_appointment_details',
			'title'         => __('Appointment Details', 'cmb2'),
			'object_types'  => array('ga_appointments'), // Post type
			'context'       => 'normal',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		));

		// Type
		$cmb->add_field(array(
			'name'            => 'Type',
			'desc'            => 'Interval or bookable date appointment',
			'id'              => $prefix . 'type',
			'type'            => 'select',
			'default'         => 'time_slot',
			'options'         => array(
				'time_slot'   => __('Time slot', 'cmb2'),
				'date'        => __('Bookable date', 'cmb2'),
			),
		));


		// Duration
		$cmb->add_field(array(
			'name' => 'Duration',
			'desc' => 'Duration is how long the appointment lasts',
			'id'   => $prefix . 'duration',
			'type' => 'select',
			'classes_cb'      => array($this, 'show_on_time_slot_type'),
			'default' => '30', // 30 minutes
			'options_cb' => 'ga_service_duration_options',
			'sanitization_cb' => 'ga_sanitize_service_duration_options', // function should return a sanitized value				
		));

		// Date
		$cmb->add_field(array(
			'name' => 'Date',
			'desc' => 'Format: year, month, day',
			'id'   => $prefix . 'date',
			'type' => 'text_date',
			'timezone_meta_key' => ga_time_zone(),
			'date_format' => 'Y-m-j',
			'sanitization_cb' => 'sanitize_get_ga_services_date', // function should return a sanitized value				
		));

		// Time
		$cmb->add_field(array(
			'name' => 'Time',
			'desc' => 'Start time',
			'id'   => $prefix . 'time',
			'type' => 'select',
			'classes_cb'      => array($this, 'show_on_time_slot_type'),
			'default' => '09:00', // 30 minutes			
			'options_cb'      => 'get_ga_appointment_time',
			'sanitization_cb' => 'sanitize_get_ga_appointment_time', // function should return a sanitized value
		));


		// Provider
		$cmb->add_field(array(
			'name' => 'Provider',
			'desc' => 'Select no provider if you don\'t want any provider',
			'id'   => $prefix . 'provider',
			'type' => 'select',
			'options_cb' => 'get_ga_appointment_providers',
			'sanitization_cb' => 'sanitize_get_ga_appointment_providers', // function should return a sanitized value				
		));

		// Service
		$cmb->add_field(array(
			'name' => 'Service',
			'desc' => 'Select a service',
			'id'   => $prefix . 'service',
			'type'  => 'select',
			'options_cb' => 'get_ga_appointment_services',
			'sanitization_cb' => 'sanitize_ga_appointment_services', // function should return a sanitized value			
		));

		// Client
		$cmb->add_field(array(
			'name' => 'Client',
			'desc' => 'Select a registered user or add new client',
			'id'   => $prefix . 'client',
			'type'  => 'select',
			'options_cb' => 'get_ga_appointment_users',
			'sanitization_cb' => 'sanitize_get_ga_appointment_users', // function should return a sanitized value
		));

		// Client
		$cmb->add_field(array(
			'name' => 'Client Information',
			'desc' => 'Name, Email & Phone',
			'id'   => $prefix . 'new_client',
			'type'  => 'text',
			'render_row_cb'   => 'get_ga_appointment_new_client_render_row',
			'sanitization_cb' => 'sanitize_get_ga_appointment_new_client', // function should return a sanitized value			
		));

		// GF Entry ID
		$cmb->add_field(array(
			'name' => 'Payment Order Details',
			'desc' => 'GravityForms Entry ID. This field is necessary to update appointment status after completed or failed payment. Modify this field only if you know what you\'re doing.',
			'id'   => $prefix . 'gf_entry_id',
			'type' => 'select',
			'show_option_none' => 'Select entry',
			'options_cb'      => 'get_gravity_form_entries_ids',
			'sanitization_cb' => 'sanitize_get_gravity_form_entries_ids', // function should return a sanitized value
		));

		// Appointment IP
		$cmb->add_field(array(
			'name' => 'User IP',
			'desc' => 'Clients who want to book more than 1 time per time slot will be blocked if it\'s enabled service capacity',
			'id'   => $prefix . 'ip',
			'type' => 'text',
			'sanitization_cb' => 'sanitize_ga_appointment_ip', // function should return a sanitized value			
		));


		/*
		// GCAL 
		$cmb->add_field( array(
			'name' => 'GCAL',
			'id'   => $prefix . 'gcal',
			'type' => 'text',		
		) );
		
		// GCAL Event ID
		$cmb->add_field( array(
			'name' => 'GCAL Event ID',
			'id'   => $prefix . 'gcal_id',
			'type' => 'text',			
		) );		

		// GCAL Provider
		$cmb->add_field( array(
			'name' => 'GCAL Provider',
			'id'   => $prefix . 'gcal_provider',
			'type' => 'text',				
		) );		
		*/
	}


	/**
	 * Show on time slot type
	 */
	public function show_on_time_slot_type($field_args, $field)
	{
		// Appointment Type
		$type = (string) get_post_meta($field->object_id, 'ga_appointment_type', true);

		// Time Slots Mode
		if ($type == 'time_slot' || $type == '') {
			return '';
		} else {
			return 'cmb2-hidden';
		}
	}


	/**
	 * Appointments New Submit Widget
	 */
	public function cmb2_ga_appointments_submitdiv_metabox()
	{

		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_appointment_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box(array(
			'id'            => 'ga_appointment_submitdiv',
			'title'         => __('Save Appointment', 'cmb2'),
			'object_types'  => array('ga_appointments'), // Post type
			'context'       => 'side',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		));

		// New Submit Widget
		$cmb->add_field(array(
			'name'         => 'Save Appointment',
			'id'           => $prefix . 'submitdiv',
			'type'         => 'select',
			'render_row_cb' => array($this, 'ga_appointment_submitdiv_render_row'),
		));
	}

	/**
	 * Appointments New Submit Widget
	 */
	public function rename_ga_appointments_post_statuses($views)
	{
		if (isset($views['publish']))
			$views['publish'] = str_replace('Published ', 'Confirmed ', $views['publish']);

		return $views;
	}

	/**
	 * Appointments Custom Columns
	 */
	public function manage_ga_appointments_columns($columns)
	{

		$columns = array(
			'cb'            => '<input type="checkbox" />', // needed for checking/selecting multiple rows
			//'title'         => __( 'Title' ),
			//'date'          => __( 'Date' ),			
			'date_on'       => __('Appointment Date'),
			'time'          => __('Time'),
			'time_end'      => __('Ends'),
			'duration'      => __('Duration'),
			'provider'      => __('Provider'),
			'service'       => __('Service'),
			'client'        => __('Client'),
			'payment'       => __('Payment'),
			'status'        => __('Status'),
		);

		return $columns;
	}

	/**
	columns that will have sort option in appointments page
	*/
	public function sortable_ga_appointments_columns($columns){
        $columns = array(
			'date_on'       => __('Appointment Date'),
			'time'          => __('Time'),
			'time_end'      => __('Ends'),
			'duration'      => __('Duration'),
			'provider'      => __('Provider'),
			'service'       => __('Service'),
			'client'        => __('Client'),
		);
		return $columns;
	}

    /**
     * Change appointments page default primary column
     */
    function ga_appointments_primary_column( $default, $screen ) {

        if( 'ga_appointments' === $screen ) {
            $default = 'date_on';
        }

        if( 'edit-ga_appointments' === $screen ) {
            $default = 'date_on';
        }

        return $default;
    }

    /**
     * Remove default appointments row actions
     */
    public function ga_appointments_row_actions( $actions, $post )
    {
        if( 'ga_appointments' === $post->post_type ) {
            return array();
        }
        return $actions;
    }

	 /**
	 * Appointments Custom Columns HTML
	 */
	public function manage_ga_appointments_posts_custom_column($column, $post_id)
	{	
		//global $post;

		switch ($column) {
			case 'date_on':
				$post                = get_post($post_id);

				// DATE
				$app_date            = (string) get_post_meta($post_id, 'ga_appointment_date', true);
				$date                = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
				$app_date_text       = $date ? $date->format('F j, Y') : 'Date not defined';

				// Date-Time Link
				echo '<a href="' . get_edit_post_link($post->ID, true) . '" title="' . esc_attr(__('Edit this appointment')) . '">' . $app_date_text . '</a>';


				//***** ROW ACTIONS  *******//
				// First set up some variables
				$actions             = array();
				$post_type_object    = get_post_type_object($post->post_type);
				$can_edit_post       = current_user_can($post_type_object->cap->edit_post, $post->ID);


				// Actions to edit
				if ($can_edit_post && 'trash' != $post->post_status) {
					$actions['edit'] = '<a href="' . get_edit_post_link($post->ID, true) . '" title="' . esc_attr(__('Edit this appointment')) . '">' . __('Edit') . '</a>';
				}

				// Actions to delete/trash
				if (current_user_can($post_type_object->cap->delete_post, $post->ID)) {
					if ('trash' == $post->post_status) {
						$_wpnonce = wp_create_nonce('untrash-post_' . $post_id);
						$actions['untrash'] = "<a title='" . esc_attr(__('Restore this item from the Trash')) . "' href='" . admin_url('post.php?post=' . $post->ID . '&action=untrash&_wpnonce=' . $_wpnonce) . "'>" . __('Restore') . "</a>";
					} elseif (EMPTY_TRASH_DAYS) {
						$actions['trash'] = "<a class='submitdelete' title='" . esc_attr(__('Move this item to the Trash')) . "' href='" . get_delete_post_link($post->ID) . "'>" . __('Delete') . "</a>";
					}
					if ('trash' == $post->post_status || !EMPTY_TRASH_DAYS) {
						$actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Delete this item permanently')) . "' href='" . get_delete_post_link($post->ID, '', true) . "'>" . __('Delete Permanently') . "</a>";
					}
				}

				//***** END - ROW ACTIONS *******//		

				echo '<div class="row-actions">';
				foreach ($actions as $key => $action) {
					$sep = $key == 'edit' || $key == 'untrash' ? ' | ' : '';
					echo '<span class="' . $key . '">' . $action . $sep . '<span>';
				}
				echo '</div>';

				echo '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>';
				break;

			case 'time':
				// Time
				$app_time            = (string) get_post_meta($post_id, 'ga_appointment_time', true);
				$time                = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
				$app_time_text       = $time ? $time->format('g:i a') : 'Time not defined';

				// Date Slots Mode
				$app_type = get_post_meta($post_id, 'ga_appointment_type', true);
				if ($app_type == 'date') {
					echo 'Full day';
				} else {
					// Time Slots Mode
					echo $app_time_text;
				}

				break;

			case 'time_end':
				// Time
				$app_time            = (string) get_post_meta($post_id, 'ga_appointment_time_end', true);
				$time                = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
				$app_time_text       = $time ? $time->format('g:i a') : 'Time not defined';

				// Date Slots Mode
				$app_type = get_post_meta($post_id, 'ga_appointment_type', true);
				if ($app_type == 'date') {
					echo 'Full day';
				} else {
					// Time Slots Mode
					if ($time && $time->format('H:i') == '23:59' || $app_time == '24:00') {
						echo '12:00 am';
					} else {
						echo $app_time_text;
					}
				}
				break;

			case 'duration':
				// Date Slots Mode
				$app_type = get_post_meta($post_id, 'ga_appointment_type', true);
				if ($app_type == 'date') {
					echo 'Full day';
				} else {
					// Time Slots Mode
					$duration = (int) get_post_meta($post_id, 'ga_appointment_duration', true);
					echo convertToHoursMins($duration);
				}

				break;

			case 'client':

				$client = (string) get_post_meta($post_id, 'ga_appointment_client', true);

				if ($client == 'new_client') {
					$new_client = get_post_meta($post_id, 'ga_appointment_new_client', true);

					$client = isset($new_client['name']) ? esc_html($new_client['name']) : '';

					if (isset($new_client['email']) && !empty($new_client['email'])) {
						$client .= '<div class="ga_client_email">(' . esc_html($new_client['email']) . ')</div>';
					}

					if (isset($new_client['phone']) && !empty($new_client['phone'])) {
						$client .= '<div class="ga_client_phone">(' . esc_html($new_client['phone']) . ')</div>';
					}

					echo $client;
				} else {

					$user_info = get_userdata($client);

					if ($user_info) {
						echo '<a href="' . get_edit_user_link($user_info->ID) . '" target="_blank">' . $user_info->user_login . '</a>';
					} else {
						echo 'Not defined';
					}
				}

				break;

			case 'service':
				$service_id = get_post_meta($post_id, 'ga_appointment_service', true);

				if ('ga_services' == get_post_type($service_id)) {
					echo esc_html(get_the_title($service_id));
				} else {
					echo 'Not defined';
				}
				break;

			case 'provider':
				$provider_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);

				if ('ga_providers' == get_post_type($provider_id)) {
					echo esc_html(get_the_title($provider_id));
				} else {
					echo 'No provider';
				}

				break;

			case 'payment':

				$app_entry_id = (int) get_post_meta($post_id, 'ga_appointment_gf_entry_id', true);
				if (RGFormsModel::get_lead($app_entry_id)) {
					$entry_obj      = RGFormsModel::get_lead($app_entry_id);
					$form_id        = $entry_obj['form_id'];
					$entry_id       = $entry_obj['id'];
					$entry_url      = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id;
					$text           = str_replace('{entry_url}', urlencode($entry_url), $entry_url);

					echo '<a href="' . esc_url($text) . '" target="_blank">View Order Details</a>';
				} else {
					echo '<span>Not Available</span>';
				}
				break;

			case 'status':
				$post_obj = get_post($post_id);
				echo '<select class="appointment_status_select" app-id="' . $post_id . '">';

				if (isset($post_obj->post_status)) {
                    $statuses = ga_appointment_statuses();
                    if (array_key_exists($post_obj->post_status, ga_appointment_statuses())) {
						echo '<option value="' . $post_obj->post_status . '" selected>' . $statuses[$post_obj->post_status] . '</option>';
					} elseif ($post_obj->post_status == 'trash') {
						echo '<option value="trash" selected>' . 'In trash' . '</option>';
					} else {
						echo '<option value="" selected>' . 'Unknown' . '</option>';
					}
					foreach ($statuses as $status_value => $status) {
						if ($status != ucfirst($post_obj->post_status)) {
							if ($status != 'Pending Payment') {
								if ($status != 'Confirmed') {
									echo '<option value="' . $status_value . '">' . $status . '</option>';
								}
							}
						}
					}
					if ($post_obj->post_status != 'publish') {
						echo '<option value="publish">Confirmed</option>';
					}
				}
				echo '</select>';
				break;

				/* Just break out of the switch statement for everything else. */
			default:
				break;
		}
	}

	/**
	 * Appointments Submit Widget HTML
	 */
	public function ga_appointment_submitdiv_render_row()
	{
		global $post;
        global $pagenow;

		$post_status = isset($post->post_status) ? $post->post_status : '';

        if( in_array( $pagenow, array( 'post-new.php' ) ) ) {
            $post_status = 'draft';
        }

		$appointment_statuses = ga_appointment_statuses();

		echo '<div class="cmb-submitdiv">';
		echo '<select id="post-status" class="" name="post_status">';

		foreach ($appointment_statuses as $key => $status) {
			$selected = $post_status == $key ? ' selected' : '';
			echo '<option value="' . $key . '"' . $selected . '>' . $status . '</option>';
		}

		echo '</select>';
		echo '<input type="submit" class="button button-ga right" value="Update">'; // button-primary
		echo '<div class="clear"></div>';

		echo '<textarea name="ga_cancel_message" class="ga_cancel_message cmb2-hidden" placeholder="Optional message"></textarea>';

		echo '</div>';
	}

	/**
	 * Update appointment post status by ADMIN //AJAX
	 */
	public function ga_admin_update_appointment_status()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error();
			wp_die();
		}

		// Data
		$posted     = isset($_POST) ? $_POST : '';
		$statuses   = ga_appointment_statuses();
        $post_id    = $_POST['app_id'];
        $new_status = $posted['status'];
        $old_status = get_post_status( $post_id );

		if( 'ga_appointments' != get_post_type($post_id) || !array_key_exists($new_status, $statuses) ) {
			wp_die();
		}

		if( $new_status === '' || $new_status === 'trash' ) {
            $new_status = 'draft';
		}

		// Update Status
		wp_update_post( array('ID' => $post_id, 'post_status' => $new_status) );

        $provider_id  = $this->get_gcal_provider( $post_id );
        $sync         = new ga_gcal_sync( $post_id, $provider_id );

        if( $sync->is_sync_enabled() ) {
            // Delete a Google Calendar event when appointment is canceled.
            if( $new_status === 'cancelled' && $old_status !== 'cancelled' ) {
                $sync->delete_event();
            }
            // Update a Google Calendar event when appointment is not canceled or draft.
            else if( $new_status !== 'draft' && $new_status !== 'cancelled') {
                $sync->update_event();
            }
        }

		// Send success response
		wp_send_json_success();
		wp_die();
	}

	public function user_set_appointment_status(){
		$error = array('message' => '<div class="ga_alert ga_alert_danger">' . ga_get_translated_data('error') . '</div>');
		$options = get_option( 'ga_appointments_policies' );
		$user_set_appointment_pending = isset( $options['user_set_appointment_pending'] ) ? $options['user_set_appointment_pending'] : 'no';
		if($user_set_appointment_pending === 'no'){
			wp_send_json_error($error);
			wp_die();
		}
		if (!is_user_logged_in()) {
			wp_send_json_error($error);
			wp_die();
		}
		//Allowed status
		$statuses = array('publish');
		// Data
		$posted = isset($_POST) ? $_POST : '';
		$post_id = isset($posted['app-id']) ? (int) $posted['app-id'] : 0;

		if ('ga_appointments' != get_post_type($post_id) || !in_array(get_post_status($post_id), $statuses)) {
			wp_send_json_error($error);
			wp_die();
		}
		// User ID
		$user_id = $this->get_user_id();
		// Client ID
		$appointment_client_id = (int) get_post_meta($post_id, 'ga_appointment_client', true);
		if($appointment_client_id != $user_id){
			wp_send_json_error($error);
			wp_die();
		}
		// Appointment changed to pending
		$success = array(
			'message'    => '<div class="ga_alert ga_alert_success">' . ga_get_translated_data('app_set_pending') . '</div><div class="hr"></div><div pending-string="'.ga_get_translated_data('status_pending').'" app-id="'.$post_id.'" class="ga_btn_close">' . ga_get_translated_data('close_button') . '</div>',
		);

		// Update Status
		wp_update_post(array('ID' => $post_id, 'post_status' => 'pending'));
		// Update meta that user has set their own appointment to pending
		update_post_meta($post_id, 'user_set_to_pending', 'yes');
		// Send success response
		wp_send_json_success($success);
		wp_die();

	}

	/**
	 * Update appointment post status
	 */
	public function ga_update_appointment_status()
	{

		// Message Templates
		$warning = '<div class="ga_alert ga_alert_warning">Something went wrong.</div>';
		$error = array('message' => '<div class="ga_alert ga_alert_danger">' . ga_get_translated_data('error') . '</div>');

		// Appointment cancelled.
		$success = array(
			'message'    => '<div class="ga_alert ga_alert_success">' . ga_get_translated_data('app_cancelled') . '</div><div class="hr"></div><div class="ga_btn_close">' . ga_get_translated_data('close_button') . '</div>',
			'app_status' => '<span class="appointment-status-red">' . ga_get_translated_data('status_cancelled') . '</span>',
		);

		if (!is_user_logged_in()) {
			wp_send_json_error($error);
			wp_die();
		}

		// User ID
		$user_id = $this->get_user_id();

		// Data
		$posted = isset($_POST) ? $_POST : '';

		$statuses = array('publish', 'pending');

		$post_id = isset($posted['app-id']) ? (int) $posted['app-id'] : 0;
		$message = isset($posted['ga_cancel_message']) ? sanitize_textarea_field($posted['ga_cancel_message']) : '';

		if ('ga_appointments' != get_post_type($post_id) || !in_array(get_post_status($post_id), $statuses)) {
			wp_send_json_error($error);
			wp_die();
		}

		$update_status = 'cancelled';

		// Appointment Policies Options
		$ga_policies = get_option('ga_appointments_policies');

		// Client Cancellation
		$cancellation_notice = isset($ga_policies['cancellation_notice']) ? $ga_policies['cancellation_notice'] : 'no';

		//Client Reschedule
		$appointment_reschedule = isset($ga_policies['appointment_reschedule']) ? $ga_policies['appointment_reschedule'] : 'no';
		//Client Reschedule set to pending
		$appointment_reschedule_pending = isset($ga_policies['appointment_reschedule_pending']) ? $ga_policies['appointment_reschedule_pending'] : 'no';
		//Get Client Cancellation custom timeframe
		$cancellation_notice_timeframe = isset($ga_policies['cancellation_notice_timeframe']) ? $ga_policies['cancellation_notice_timeframe'] : 10;
		//Set to confirm from user-set pending policy
		$user_set_appointment_confirmed_from_pending = isset( $ga_policies['user_set_appointment_confirmed_from_pending'] ) ? $ga_policies['user_set_appointment_confirmed_from_pending'] : 'no';
		// Provider Cancellation
		$provider_cancellation_notice = isset($ga_policies['provider_cancellation_notice']) ? $ga_policies['provider_cancellation_notice'] : 'no';

		// Provider Confirms
		$provider_confirms = isset($ga_policies['provider_confirms']) ? $ga_policies['provider_confirms'] : 'no';

        // Appointment Provider ID
        $appointment_provider_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);
        $appointment_provider_gcal_id = (int) get_post_meta($post_id, 'ga_appointment_provider', true);

		// Client ID
		$appointment_client_id = (int) get_post_meta($post_id, 'ga_appointment_client', true);
        $can_continue = false;

        // init Google Calendar sync
        $sync = new ga_gcal_sync( $post_id, $appointment_provider_id );

        # client can cancel
		if( isset($posted['action']) && $posted['action'] == 'ga_cancel_appointment' && $cancellation_notice == 'yes' && $appointment_client_id == $user_id ) {
            $can_continue = true;
		}
        # check if client can cancel
		elseif( isset($posted['action']) && $posted['action'] == 'ga_cancel_appointment' && $cancellation_notice == 'custom' && $appointment_client_id == $user_id ) {
		    $shortcode    = new ga_appointment_shortcodes();
            $can_continue = user_can_cancel_appointment( $cancellation_notice_timeframe, $shortcode->ga_date( $post_id, $translation = false ), $shortcode->ga_time( $post_id, $translation = false ) );
		}
        # check if client can reschedule
        elseif( isset($posted['action']) && $posted['action'] == 'ga_reschedule_appointment' && $appointment_reschedule == 'yes' && $appointment_client_id == $user_id ) {
		    $error = array( 'message' => '<div class="ga_alert ga_alert_danger">' . ga_get_translated_data('unselected_time_date') . '</div>' );
            $type  = get_post_meta( $post_id, 'ga_appointment_type', true );

            if( $posted['input_']['time'] !== "" && $posted['input_']['date'] !== "" ) {
                $can_continue = true;
                $update_status = get_post_status( $post_id );

                if( $type === 'time_slot' ) {
                    $time_inputs = explode('-', $posted['input_']['time'] );
                    update_post_meta( $post_id, 'ga_appointment_time', $time_inputs[0] );
                    update_post_meta( $post_id, 'ga_appointment_time_end', $time_inputs[1] );
                }
                update_post_meta( $post_id, 'ga_appointment_date', $posted['input_']['date'] );

                if( $appointment_reschedule_pending === 'yes' ) {
                    $update_status = 'pending';
                }
                if( $user_set_appointment_confirmed_from_pending === 'yes' ) {
                    if( get_post_meta( $post_id, 'user_set_to_pending', true) === 'yes' ) {
                        $update_status = 'publish';
                        delete_post_meta( $post_id, 'user_set_to_pending' );
                    }
                }

                $success = array(
                    'message'    => '<div class="ga_alert ga_alert_success">' . ga_get_translated_data('app_rescheduled') . '</div><div class="hr"></div><div class="ga_btn_close">' . ga_get_translated_data('close_button') . '</div>',
                    /*'app_status' => '<span class="appointment-status-red">' .ga_get_translated_data('status_cancelled'). '</span>',*/
                );
			}
		}
        # check if provider can cancel
		elseif( isset($posted['action']) && $posted['action'] == 'ga_provider_cancel_appointment' && $provider_cancellation_notice == 'yes' ) {
			$providers    = ga_provider_query( $user_id );
            $can_continue = $providers->post_count === 1 && $providers->post->ID === $appointment_provider_id;
		}
        # check if provider can confirm
		elseif( isset($posted['action']) && $posted['action'] == 'ga_provider_confirm' && $provider_confirms == 'yes' ) {
            $can_continue = false;
			$providers    = ga_provider_query( $user_id );
			if( $providers->post_count === 1 && $providers->post->ID === $appointment_provider_id ) {
                $can_continue  = true;
                $update_status = 'publish';

                // Appointment confirmed.
                $success       = array(
                    'message'    => '<div class="ga_alert ga_alert_success">' . ga_get_translated_data('app_confirmed')  . '</div><div class="hr"></div><div class="ga_btn_close">' . ga_get_translated_data('close_button') . '</div>',
                    'app_status' => '<span class="appointment-status-green">' . ga_get_translated_data('status_publish') . '</span>',
                );
            }
		}

		if( $can_continue ) {
            wp_update_post( array('ID' => $post_id, 'post_status' => $update_status) );
            switch ($update_status) {
                case 'cancelled':
                    if( $sync->is_sync_enabled() ) {
                        $sync->delete_event();
                    }
                    break;
                case 'publish':
                case 'pending':
                    if( $sync->is_sync_enabled() ) {
                        $sync->update_event();
                    }
                    break;
            };
        }

		// Send success response
		wp_send_json_success($success);
		wp_die();
	}


	/**
	 * After paid GF Entry
	 */
	public function after_paid_gf_entry($new_status, $old_status, $post)
	{

		if (isset($post->ID) && $post->post_type == 'ga_appointments') {
            require_once('includes/ga_emails.php');
            $ga_emails = new ga_appointment_emails($post->ID);

			// Appointment is confirmed from admin dashboard or front-end.
			if ($old_status == 'pending' && $new_status == "publish") {
				// EMAILING
				$ga_emails->confirmation($post->ID);
				$ga_emails->provider_confirmation($post->ID);
			}

			// Appointment is payed and confirmation is set to auto
			if ($old_status == 'payment' && $new_status == "publish") {
				// EMAILING
				$ga_emails->confirmation($post->ID);
				$ga_emails->provider_confirmation($post->ID);
			}

			// Appointment is payed and confirmation is set to pending
			if ($old_status == 'payment' && $new_status == "pending") {
				// EMAILING
				$ga_emails->pending( $post->ID );
				$ga_emails->provider_pending( $post->ID );
			}

			// Appointment is cancelled from admin dashboard or front-end.
			if ($old_status == "publish" && $new_status == "cancelled") {

				$posted = isset($_POST) ? $_POST : '';
				if (is_admin()) {
					$message = isset($posted['ga_cancel_message']) ? sanitize_textarea_field($posted['ga_cancel_message']) : '';
				} else {
					if (isset($posted['action']) && ($posted['action'] == 'ga_cancel_appointment' || $posted['action'] == 'ga_provider_cancel_appointment')) {
						$message = isset($posted['ga_cancel_message']) ? sanitize_textarea_field($posted['ga_cancel_message']) : '';
					} else {
						$message = '';
					}
				}

				// EMAILING
				$ga_emails->cancellation($post->ID, $message);
				$ga_emails->provider_cancellation($post->ID, $message);
			}
		}
	}

    /**
     * Serialize appointment post data
     */
    public function serialize_ga_appointment( $form ) {

        // Serialize data if calendar field exists
        if ( gf_field_type_exists( $form, 'appointment_calendar' ) ) {
            $field_id    = "";
            $field_value = gf_get_field_type_value( $form, 'appointment_calendar', $field_id );
            $field_input = gf_generate_field_input( $field_id );

            if ( empty( $field_value ) || empty ( $field_id ) ) {
                return;
            }

            // Serialize field
            $field_value = serialize( $field_value );

            // Override calendar post field
            $_POST[$field_input] = $field_value;
        }
    }

	/**
	 * Add new appointment post
	 */
	public function add_new_ga_appointment($entry, $form)
	{
		// Appointment fields are set
		if (gf_field_type_exists($form, 'appointment_services') && gf_field_type_exists($form, 'appointment_calendar')) {
			$form_id = absint($form['id']);

			ini_set('max_execution_time', 300); // 5 minutes
			set_time_limit(300); // 5 minutes

			$options = get_option('ga_appointments_policies');
			$auto_confirm = isset($options['auto_confirm']) ? $options['auto_confirm'] : 'yes';
			$auto_confirm_status = $auto_confirm == 'yes' ? 'publish' : 'pending';

			$status = isset($entry['payment_status']) && $entry['payment_status'] == 'Processing' ? 'payment' : $auto_confirm_status; // publish is confirmed


			// Service & Provider ID
			$service_id   = gf_get_field_type_postid( $form, 'appointment_services' );
			$provider_id  = gf_get_field_type_postid( $form, 'appointment_providers' );
			$provider_id  = gf_field_type_exists($form, 'appointment_providers')
				&& is_numeric($provider_id)
				&& 'ga_providers' == get_post_type($provider_id)
				? $provider_id
				: 0;

			if (ga_get_provider_id($service_id) && $provider_id == 0) {
				$provider_id = ga_get_provider_id($service_id);
			}

			// Client information
			$user_info   = array();
			$name_value  = gf_field_type_exists($form, 'name')  ? gf_get_name_field_value($form) : '';
			$email_value = gf_field_type_exists($form, 'email') ? ga_get_field_type_value($form, 'email') : '';
			$phone_value = gf_field_type_exists($form, 'phone') ? ga_get_field_type_value($form, 'phone') : '';
			$user_info['name']  = $name_value;
			$user_info['email'] = $email_value;
			$user_info['phone'] = $phone_value;
			// Client information

			// Date & Time
			$date_array = gf_get_field_type_value($form, 'appointment_calendar');
			$date       = isset($date_array['date']) ? $date_array['date'] : '';
			$timeArray  = isset($date_array['time']) ? explode("-", $date_array['time']) : array();
			$time       = reset($timeArray);
			$timeID     = isset($date_array['time']) ? $date_array['time'] : '';

			// Check if calendar field is hidden
			if( empty( $date_array ) ) {
                return;
            }

			// Service duration
			$duration = (int) get_post_meta($service_id, 'ga_service_duration', true); // entry id


			/**
			 * Multiple Bookings
			 */
			// Service mode
			$available_times_mode = (string) get_post_meta($service_id, 'ga_service_available_times_mode', true);

			// Service multiple slots
			$multiple_slots = (string) get_post_meta($service_id, 'ga_service_multiple_selection', true);

			// Get bookings
			$bookings = ga_get_multiple_bookings($date_array, $service_id, $provider_id);

			// Time Format Display
			$time_display  = ga_service_time_format_display($service_id);
			$format        = $available_times_mode == 'no_slots' ? 'F j, Y' : 'l, F j, Y \a\t ' . $time_display;

			if ($multiple_slots == 'yes') {
				if (count($bookings) > 0) {
					wp_defer_term_counting(true);
					wp_defer_comment_counting(true);

					// Add to calendar links in email
					$notifications       = get_option('ga_appointments_notifications');
					$add_to_cal          = isset($notifications['add_to_cal']) ? $notifications['add_to_cal'] : 'yes';
					$provider_add_to_cal = isset($notifications['provider_add_to_cal']) ? $notifications['provider_add_to_cal'] : 'yes';

					$booking_dates  = array();
					$sms_dates      = array();
					$provider_dates = array();
					$bulk_ids       = array();

					foreach ($bookings as $key => $booking) {
						$dateTime = new DateTime(sprintf('%s %s', $booking['date'], $booking['time']), new DateTimeZone(ga_time_zone()));
						// Date Slots Mode
						if ($available_times_mode == 'no_slots') {
							$app_type     = 'date';
							$date         = $dateTime->format('Y-m-j');
							$time         = '00:00';
							$time_end     = '23:59';
						} else {
							$app_type     = 'time_slot';
							$date         = $dateTime->format('Y-m-j');
							$time         = $dateTime->format('H:i');
							$timeId       = $booking['time_id'];
							$_time	      = $dateTime->format($time_display);
							$time_end     = ga_get_time_end($time, $service_id);

							if ($available_times_mode == 'custom') {
								$time_end     = $booking['end'];
								$duration     = $booking['duration'];
							}
						}

						// Gather post data.
						$ga_appointment = array(
							'post_title'    => 'Appointment',
							'post_status'   => $status,
							'post_type'     => 'ga_appointments',
						);

						// Insert the post into the database.
						if ($postID = wp_insert_post($ga_appointment)) {
							update_post_meta($postID, 'ga_appointment_type', $app_type);
							update_post_meta($postID, 'ga_appointment_duration', $duration); // Duration
							update_post_meta($postID, 'ga_appointment_service', $service_id); // Service
							update_post_meta($postID, 'ga_appointment_provider', $provider_id); // Provider
							update_post_meta($postID, 'ga_appointment_new_client', $user_info); // Client Data
							update_post_meta($postID, 'ga_appointment_date', $date); //	Date
							update_post_meta($postID, 'ga_appointment_time', $time); //	Time
                            update_post_meta($postID, 'ga_appointment_gcal_calendar_id', null); //Default Google Calendar id value

							// Time slot end
							update_post_meta($postID, 'ga_appointment_time_end', $time_end); //	End Time

							// Client is logged in
							if (is_user_logged_in()) {
								$user_id = $this->get_user_id();
								update_post_meta($postID, 'ga_appointment_client', $user_id); // entry id
							} else {
								update_post_meta($postID, 'ga_appointment_client', 'new_client');
							}

							$entry_id = $entry['id'];
							$entry_ip = $entry['ip'];

							update_post_meta($postID, 'ga_appointment_gf_entry_id', $entry_id); // entry id
							update_post_meta($postID, 'ga_appointment_ip', $entry_ip); // entry IP

							// Add the post id to bulk array
							$bulk_ids[] = $postID;

                            $form_lang = get_form_translations( $form );

							// Translation Support
							if ($available_times_mode == 'no_slots') {
								$month = $dateTime->format('F');
								$day   = $dateTime->format('j');
								$year  = $dateTime->format('Y');
								$appointment_date = ga_get_form_translated_slots_date($form_lang, $month, $day, $year);
							} else {
								$month = $dateTime->format('F');
								$week  = $dateTime->format('l');
								$day   = $dateTime->format('j');
								$year  = $dateTime->format('Y');

								$appointment_date = ga_get_form_translated_date_time($form_lang, $month, $week, $day, $year, $_time);
							}

							$sms_dates[] = $appointment_date;
                            require_once(ga_base_path . '/admin/includes/ga_emails.php');
                            $ga_emails   = new ga_appointment_emails();

                            if ($add_to_cal == 'yes') {

								// Client Links
								$client_links      = $ga_emails->get_client_calendar_links($postID, $form_lang);
								$booking_dates[]   = '<div>' . $appointment_date . $client_links . '</div>';
							} else {
								$booking_dates[]   = '<div>' . $appointment_date . '</div>';
							}

							if ($provider_add_to_cal == 'yes') {

								// Provider Links
								$provider_links    = $ga_emails->get_provider_calendar_links($postID, $form_lang);
								$provider_dates[]  = '<div>' . $appointment_date . $provider_links . '</div>';
							} else {
								$provider_dates[]  = '<div>' . $appointment_date . '</div>';
							}
						}
					} // end foreach

					if (count($bulk_ids) == 1) {
						do_action('ga_new_appointment', reset($bulk_ids), $provider_id);
					} else {
						do_action('ga_bulk_appointments', $bulk_ids, $provider_id);
					}

					wp_defer_term_counting(false);
					wp_defer_comment_counting(false);

					/******** BULK EMAILING ********/
					$booking_dates  = implode("", $booking_dates);
					$sms_dates      = implode(PHP_EOL, $sms_dates);
					$provider_dates = implode("", $provider_dates);
                    require_once('includes/ga_emails.php');
                    $ga_emails = new ga_appointment_emails( $postID );

                    if ($status == 'publish') {
						$ga_emails->bulk_confirmation($postID, $booking_dates, $bookings, $sms_dates);
						$ga_emails->provider_bulk_confirmation($postID, $provider_dates, $bookings, $sms_dates);
					}
					if ($status == 'pending') {
						$ga_emails->bulk_pending($postID, $booking_dates, $bookings, $sms_dates);
						$ga_emails->provider_bulk_pending($postID, $provider_dates, $bookings, $sms_dates);
					}
					/******** BULK EMAILING *******  */
				}
				return;
			}


			/**
			 * Single Bookings
			 */
			// Date Slots Mode
			if ($available_times_mode == 'no_slots') {
				$app_type  = 'date';
				$time      = '00:00';
				$time_end  = '23:59';
			} else {
				// Time slot end
				$app_type  = 'time_slot';
				$time_end  = ga_get_time_end($timeID, $service_id);
				$dateTime  = new DateTime($date, new DateTimeZone(ga_time_zone()));
				if ($available_times_mode == 'custom') {
					if (!class_exists('GA_Calendar')) {
						require_once(ga_base_path . '/gf-fields/ga-calendar.php');
					}

					$ga_calendar  = new GA_Calendar($form_id, $dateTime->format('n'), $dateTime->format('Y'), $service_id, $provider_id);
					$slots        = $ga_calendar->get_slots($dateTime);
					$time_end     = $slots[$timeID]['end'];
					$duration     = $slots[$timeID]['duration'];
				}
			}

			// Gather post data.
			$ga_appointment = array(
				'post_title'    => 'Appointment',
				'post_status'   => $status,
				'post_type'     => 'ga_appointments',
			);

			wp_defer_term_counting(true);
			wp_defer_comment_counting(true);

			// Insert the post into the database.
			if ($postID = wp_insert_post($ga_appointment)) {
				update_post_meta($postID, 'ga_appointment_type', $app_type);
				update_post_meta($postID, 'ga_appointment_duration', $duration); //
				update_post_meta($postID, 'ga_appointment_service', $service_id); //
				update_post_meta($postID, 'ga_appointment_provider', $provider_id); //
				update_post_meta($postID, 'ga_appointment_new_client', $user_info); // Client Data
				update_post_meta($postID, 'ga_appointment_date', $date); //	Date
				update_post_meta($postID, 'ga_appointment_time', $time); //	Time
                update_post_meta($postID, 'ga_appointment_gcal_calendar_id', null); // Default Google Calendar id value

				// Time slot end
				update_post_meta($postID, 'ga_appointment_time_end', $time_end); //	End Time

				// Client is logged in
				if (is_user_logged_in()) {
					$user_id = $this->get_user_id();
					update_post_meta($postID, 'ga_appointment_client', $user_id); // entry id
				} else {
					update_post_meta($postID, 'ga_appointment_client', 'new_client');
				}

				$entry_id = $entry['id'];
				$entry_ip = $entry['ip'];

				update_post_meta($postID, 'ga_appointment_gf_entry_id', $entry_id); // entry id
				update_post_meta($postID, 'ga_appointment_ip', $entry_ip); // entry IP

				do_action('ga_new_appointment', $postID, $provider_id);
                require_once('includes/ga_emails.php');
                $ga_emails = new ga_appointment_emails( $postID );

				if ($status == 'publish') {
					// EMAILING
					$ga_emails->confirmation($postID);
					$ga_emails->provider_confirmation($postID);
				}

				if ($status == 'pending') {
					// EMAILING
					$ga_emails->pending($postID);
					$ga_emails->provider_pending($postID);
				}
			}
			wp_defer_term_counting(false);
			wp_defer_comment_counting(false);
		}
	}


	/**
	 * ACTION: New appointment sync to gcal
	 */
	public function ga_new_appointment($post_id, $provider_id)
	{
		if (!function_exists('curl_version')) {
			return;
		}

		$options = get_option('ga_appointments_calendar');
		$auto_complete = isset($options['auto_complete']) ? $options['auto_complete'] : 'no';
		if ($auto_complete === 'custom') {
			if (get_post_status($post_id) === 'publish') {
				$auto_complete_custom = isset($options['auto_complete_custom']) ? $options['auto_complete_custom'] : 10;
				$shortcode = new ga_appointment_shortcodes();

				$date = $shortcode->ga_date($post_id);
				$time =  $shortcode->ga_time($post_id);
				if ($time === false) {
					$time = '12:00 AM';
				}
				$pass = false;
				$type = get_post_meta(get_the_ID(), 'ga_appointment_type', true);
				if ($type === 'date') {
					$appointment_time = date_create_from_format('F d, Y', $date);
					$pass = true;
				}
				if($type === 'time_slot') {
					$appointment_time = date_create_from_format('F d, Y g:i A', $date . ' ' . $time);
					$appointment_time->sub(new DateInterval(('PT' . $auto_complete_custom . 'H')));
					$pass = true;
				}
				if($pass){
					wp_schedule_single_event(strtotime($appointment_time->format('d M Y H:i')), 'complete_appointment_cronjob');
				}
			}
		}

		// Prepare sync settings
        $sync = new ga_gcal_sync( $post_id, $provider_id );

		if( $sync->is_sync_enabled() ) {
			$sync->create_event();
		}
	}

	/**
	 * ACTION: Bulk Appointments Sync
	 */
	public function ga_bulk_appointments( $post_ids, $provider_id )
	{
		if (!function_exists('curl_version')) {
			return;
		}

        // Prepare sync settings
        $sync = new ga_gcal_sync( $post_ids, $provider_id );

        if( $sync->is_sync_enabled() ) {
			$sync->create_batch_events();
		}
	}

	/**
	 * ACTION: Delete gcal event
	 */
	public function ga_delete_appointment( $post_id )
	{
		if( !function_exists('curl_version') ) {
			return;
		}

        if( isset($post_id) && get_post_type( $post_id ) == 'ga_appointments' ) {
            $provider_id  = $this->get_gcal_provider( $post_id );
            $sync         = new ga_gcal_sync( $post_id, $provider_id );

            if( $sync->is_sync_enabled() ) {
                $sync->delete_event();
            }
        }
	}

	/**
	 * ACTION: Update gcal event on status change
	 */
	public function ga_update_appointment_on_status_change( $new_status, $old_status, $post )
	{
	    $post_id = $post->ID;

        if (!is_admin()) {
            return false;
        }

		if( isset($post_id) && $post->post_type == 'ga_appointments' ) {
            $provider_id  = $this->get_gcal_provider( $post_id );
            $sync         = new ga_gcal_sync( $post_id, $provider_id );

            if( $sync->is_sync_enabled() ) {
                // Delete a Google Calendar event when appointment is canceled.
                if( $new_status === 'cancelled' && $old_status !== 'cancelled' ) {
                    $sync->delete_event();
                }
                // Create a Google Calendar event when appointment is not canceled or draft.
                else if( $new_status !== 'draft' && $new_status !== 'cancelled') {
                    $sync->update_event();
                }
			}
		}
	}

	/**
	 * ACTION: Update gcal event
	 */
	public function ga_update_appointment( $post, $updated, $cmb2 )
	{
		$options = get_option('ga_appointments_calendar');
		$auto_complete = isset($options['auto_complete']) ? $options['auto_complete'] : 'no';
		if ($auto_complete === 'custom') {
			if (get_post_status($post) === 'publish') {
				$auto_complete_custom = isset($options['auto_complete_custom']) ? $options['auto_complete_custom'] : 10;
				$shortcode = new ga_appointment_shortcodes();

				$date = $shortcode->ga_date($post);
				$time =  $shortcode->ga_time($post);
				if ($time === false) {
					$time = '12:00 AM';
				}
				$pass = false;
				$type = get_post_meta(get_the_ID(), 'ga_appointment_type', true);
				if ($type === 'date') {
					$appointment_time = date_create_from_format('F d, Y', $date);
					$pass = true;
				}
				if($type === 'time_slot') {
					$appointment_time = date_create_from_format('F d, Y g:i A', $date . ' ' . $time);
					$appointment_time->sub(new DateInterval(('PT' . $auto_complete_custom . 'H')));
					$pass = true;
				}
				if($pass){
					wp_schedule_single_event(strtotime($appointment_time->format('d M Y H:i')), 'complete_appointment_cronjob');
				}
			}
		}

		$post_id = $post;
		$post = get_post($post);
		if (!is_admin()) {
			return;
		}

		if (!function_exists('curl_version')) {
			return;
		}

		if ($post->post_status == 'trash') {
			return;
		}

		if( 'ga_appointments' == $post->post_type ) {
		    // Get new provider id in case it was switched
            $provider_id = $this->get_provider( $post_id );
            $sync        = new ga_gcal_sync( $post_id, $provider_id );

            if( $sync->is_sync_enabled() ) {
				$sync->update_event();
			}
            return;
		}
	}

	/**
	 * ACTION: Appointment provider switch
	 */
	public function ga_appointment_provider_switch( $post_id )
	{
        $provider_id = $this->get_gcal_provider( $post_id );
        $sync        = new ga_gcal_sync( $post_id, $provider_id );

        if( $sync->is_sync_enabled() ) {
            $sync->delete_event();
        }
        return;
	}

	public function get_gcal_provider($post_id)
	{
		return (int) get_post_meta($post_id, 'ga_appointment_gcal_provider', true);
	}

	public function get_provider($post_id)
	{
		return (int) get_post_meta($post_id, 'ga_appointment_provider', true);
	}
} // end class
