<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

new ga_providers_post_type();
class ga_providers_post_type {
	public function __construct() {
		// Post type
		add_action('wp_loaded', array($this, 'ga_providers_init') );
	
		// Providers Details Options
		add_action( 'cmb2_admin_init', array($this,'cmb2_ga_providers_details_metaboxes') );			
		
		// Provider Google Calendar
		add_action( 'cmb2_admin_init', array($this,'cmb2_ga_providers_gcal_metaboxes') );			
		
		
		// Providers post type new submit widget
		add_action( 'cmb2_admin_init', array($this, 'cmb2_ga_providers_submitdiv_metabox' ) );

		// Appointment row columns
		add_filter( "manage_edit-ga_providers_columns", array($this,'manage_ga_providers_columns') ); // column names
		add_action( "manage_ga_providers_posts_custom_column", array($this,'manage_ga_providers_posts_custom_column'), 10, 2 ); // html column			
		
	}	
	
	/**
	 * Providers post type
	 */	
	public function ga_providers_init() {
		$questions_labels = array(
			'name' => _x('Providers', 'post type general name'),
			'singular_name' => _x('Provider', 'post type singular name'),
			'all_items' => __('Providers'),
			'add_new' => _x('Add provider', 'ga_providers'),
			'add_new_item' => __('Add new providers'),
			'edit_item' => __('Edit provider'),
			'new_item' => __('New provider'),
			'view_item' => __('View provider'),
			'search_items' => __('Search providers'),
			'not_found' =>  __('You do not have any providers'),
			'not_found_in_trash' => __('Nothing found in trash'), 
			'parent_item_colon' => ''
		);

		$args = array(
			'labels' => $questions_labels,
			'public' => true,
			'publicly_queryable' => false,
			'has_archive' => false,
			'show_ui' => true, 
			'show_in_menu' => 'ga_appointments_settings',
			'query_var' => true,
			'rewrite' => array('slug' => 'ga_contact'),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array('title'),
		); 
		register_post_type('ga_providers',$args);
	}	

	/**
	 * Post Type Custom Columns
	 */	
	public function manage_ga_providers_columns( $columns ) {

		$columns = array(
			'cb'            => '<input type="checkbox" />', // needed for checking/selecting multiple rows
			'title'         => __( 'Provider Name' ),
			'services'      => __( 'Services Provided' ),
			'schedule'      => __( 'Work Schedule' ),	
			'user'          => __( 'User' ),
		);

		return $columns;
	}
	
	/**
	 * Providers Custom Columns HTML
	 */		
	public function manage_ga_providers_posts_custom_column( $column, $post_id ) {
		//global $post;

		switch( $column ) {	
			case 'services' :
			
				$services = get_post_meta( $post_id, 'ga_provider_services', true );
				
				if( $services ) {
					foreach( $services as $key => $service ) {
						$pipe = $key + 1 == count($services) ? ' ' : ' | ';	
						echo get_the_title( $service ) . '<br>';	
					}					
				}
				//echo $services;	
				
				break;		
								
			case 'schedule' :
				$schedule = get_post_meta( $post_id, 'ga_provider_work_schedule', true );	

				echo $this->get_work_schedule( $schedule );
				//echo '<pre>'; print_r($schedule); echo '</pre>';
				
				break;				

			case 'user' :
				$user_id = get_post_meta( $post_id, 'ga_provider_user', true );	
				$user_info = get_userdata($user_id);
				
				if( $user_info ) {
					echo '<a href="'.get_edit_user_link( $user_info->ID ).'" target="_blank">'.$user_info->user_login.'</a>';
				} else {
					echo 'No user assigned';
				}

				break;	
				
			default :
				break;
		}
	}		
	
	/**
	 * Get Work Schedule
	 */		
	public function get_work_schedule( $array ) {
		$week_days = array('sunday','monday','tuesday','wednesday','thursday','friday','saturday');	
		
		$schedule = (array) $array;
		
		foreach( $week_days as $day ) {
			if( isset( $schedule[$day]['begin'] ) && isset( $schedule[$day]['end'] ) ) {
				if( $schedule[$day]['begin'] == 'out' ) {
					echo '<b>' . ucfirst( substr($day, 0, 3) ) . '</b>: Out' . '<br>';	
				} else {
					$begin = new DateTime($schedule[$day]['begin']);
					$end = new DateTime($schedule[$day]['end']);
					echo '<b>' . ucfirst( substr($day, 0, 3) ) . '</b>: ' . $begin->format('g:i a') .' - '. $end->format('g:i a') . '<br>';					
				}
			}			
		}

	}
	
	/**
	 * Providers Details Options
	 */
	public function cmb2_ga_providers_details_metaboxes() {
		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_provider_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'                    => 'ga_provider_details',
			'title'                 => __( 'Provider Details', 'cmb2' ),
			'object_types'          => array( 'ga_providers' ), // Post type
			'context'               => 'normal',
			'priority'              => 'high',
			'show_names'            => true, // Show field names on the left
		) );
		
		
		// User
		$cmb->add_field( array(
			'name'                  => 'Assign User',
			'desc'                  => 'You can assign only one user per provider, if same user is assigned, this field will not be saved.',
			'id'                    => $prefix . 'user',
			'type'                  => 'select',
			'show_option_none'      => 'Select user',
			'options_cb'            => 'get_ga_provider_users',
			'sanitization_cb'       => 'sanitize_get_ga_provider_users', // function should return a sanitized value
		) );


		// Services
		$cmb->add_field( array(
			'name'                  => 'Services',
			'desc'                  => 'Select between time slots or bookable dates services.',
			'id'                    => $prefix . 'services',
			'type'                  => 'multicheck_inline',
			'select_all_button'     => false,
			'sanitization_cb'       => 'sanitize_ga_provider_services', // function should return a sanitized value	
			'select_all_button'     => false,
			'render_row_cb'         => 'get_ga_provider_services_render_row',				
		) );
	
		// Calendar
		$cmb->add_field( array(
			'name'               => 'Calendar',
			'desc'               => 'Provider gets his own calendar schedule.',
			'id'                 => $prefix . 'calendar',
			'type'               => 'checkbox',
			'render_row_cb'      => 'get_ga_provider_calendar_render_row',			
		) );

        // Availability
        $cmb->add_field( array(
            'name'               => 'Availability',
            'desc'               => '1. Global - all appointments from all services assigned to the provider will be hidden in the booking calendar form field.<br>
                                     2. Service-based - only specific service appointments will be hidden (based on booking service form field value).<br>
                                     PS All Google Calendar two-way sync appointments will also be hidden automatically.',
            'id'                 => $prefix . 'appointment_availability',
            'type'               => 'select',
            'render_row_cb'      => 'get_ga_provider_appointment_availability_row',
        ) );
		
		// Work Schedule
		$cmb->add_field( array(
			'name'               => 'Work Schedule',
			'desc'               => 'Define week day schedule for time slots or bookable dates. If you are using for bookable dates, put any times, just don\'t select <b>"Out"</b>',
			'id'                 => $prefix . 'work_schedule',
			'type'               => 'select',
			'render_row_cb'      => 'get_ga_provider_work_schedule_render_row',
			'sanitization_cb'    => 'sanitize_get_ga_provider_work_schedule', // function should return a sanitized value
		) );
		
		// Break Schedule
		$cmb->add_field( array(
			'name'               => 'Breaks',
			'desc'               => 'Define time breaks',
			'id'                 => $prefix . 'breaks',
			'type'               => 'select',
			'render_row_cb'      => 'get_ga_provider_breaks_render_row',		
			'sanitization_cb'    => 'sanitize_get_ga_provider_breaks', // function should return a sanitized value
		) );		
		
		// Non working days
		$cmb->add_field( array(
			'name'               => 'Holidays',
			'desc'               => 'Define non working days for this service provider. Format: year, month, day',
			'id'                 => $prefix . 'holidays',
			'render_row_cb'      => 'get_ga_provider_holidays_render_row',
			'sanitization_cb'    => 'sanitize_get_ga_provider_holidays', // function should return a sanitized value			
			'type'               => 'text',
		) );			
		

	}
	

	/**
	 * Provider Google Calendar API
	 */
	public function cmb2_ga_providers_gcal_metaboxes() {
		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_provider_';
		
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'                    => 'ga_provider_gcal',
			'title'                 => __( 'Google Calendar', 'cmb2' ),
			'object_types'          => array( 'ga_providers' ), // Post type
			'context'               => 'normal',
			'priority'              => 'high',
			'show_names'            => true, // Show field names on the left
		) );		 


		$cmb->add_field( array(
			'id'                   => $prefix . 'gcal',
			'type'                 => 'custom',
			'render_row_cb'        => 'ga_provider_gcal_render_row',
			'sanitization_cb'      => 'sanitize_ga_provider_gcal',		
		) );

	}

	/**
	 * Providers New Submit Widget
	 */
	public function cmb2_ga_providers_submitdiv_metabox() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_providers_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'            => 'ga_provider_submitdiv',
			'title'         => __( 'Save Provider', 'cmb2' ),
			'object_types'  => array( 'ga_providers' ), // Post type
			'context'       => 'side',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		) );
		
		// New Submit Widget
		$cmb->add_field( array(
			'name'         => 'Save Provider',
			'id'           => $prefix . 'submitdiv',
			'type'         => 'select',	
			'render_row_cb' => array($this, 'ga_providers_submitdiv_render_row'),
		) );	
	}		
	
	/**
	 * Providers Submit Widget HTML
	 */	
	public function ga_providers_submitdiv_render_row() {
		global $post;
		$post_status = isset($post->post_status) ? $post->post_status : '';
		
		$statuses = array('publish' => 'Published', 'pending' => 'Pending', 'draft' => 'Draft');
		
		echo '<div class="cmb-submitdiv">';	
			echo '<select id="post-status" class="" name="post_status">';
			
			foreach($statuses as $key => $status) {
				$selected = $post_status == $key ? ' selected' : '';
				echo '<option value="'.$key.'"'.$selected.'>'.$status.'</option>';
			}
				
			echo '</select>';
			echo '<button type="submit" class="button button-ga right">Update</button>'; // button-primary

        echo '<div class="clear"></div>';
		echo '</div>';
		
	}
	
}
