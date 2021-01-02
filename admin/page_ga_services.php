<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

new ga_services_post_type();
class ga_services_post_type {
	public function __construct() {
		// Post type
		add_action('wp_loaded', array($this, 'ga_services_init') );
		
		// Services Details Options
		add_action( 'cmb2_admin_init', array($this,'cmb2_ga_services_details_metaboxes') );		
	
		// Services row columns
		add_filter( "manage_edit-ga_services_columns", array($this,'manage_ga_services_columns') ); // column names
		add_action( "manage_ga_services_posts_custom_column", array($this,'manage_ga_services_posts_custom_column'), 10, 2 ); // html column	
		
		// Services New submit widget
		add_action( 'cmb2_admin_init', array($this, 'cmb2_ga_services_submitdiv_metabox' ) );		

		// Services categories widget
		add_action( 'cmb2_admin_init', array($this, 'cmb2_ga_services_categories_metabox' ) );	
		
		// Add post type filters
		add_action( 'restrict_manage_posts', array($this, 'add_service_cat_filter_to_posts_administration') );
		add_action( 'pre_get_posts', array($this, 'add_service_cat_filter_to_posts_query') );
		
	}

	/**
	 * Services post type
	 */	
	public function ga_services_init() {
		$questions_labels = array(
			'name' => _x('Services', 'post type general name'),
			'singular_name' => _x('Service', 'post type singular name'),
			'all_items' => __('Services'),
			'add_new' => _x('Add service', 'ga_services'),
			'add_new_item' => __('Add new services'),
			'edit_item' => __('Edit service'),
			'new_item' => __('New service'),
			'view_item' => __('View service'),
			'search_items' => __('Search services'),
			'not_found' =>  __('You do not have any services'),
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
		register_post_type('ga_services',$args);
		
		// Add new taxonomy, make it hierarchical (like categories)
		$labels = array(
			'name'              => _x( 'Service Category', 'taxonomy general name', 'textdomain' ),
			'singular_name'     => _x( 'Category', 'taxonomy singular name', 'textdomain' ),
			'search_items'      => __( 'Search Categories', 'textdomain' ),
			'all_items'         => __( 'All Categories', 'textdomain' ),
			'parent_item'       => __( 'Parent Category', 'textdomain' ),
			'parent_item_colon' => __( 'Parent Category:', 'textdomain' ),
			'edit_item'         => __( 'Edit Category', 'textdomain' ),
			'update_item'       => __( 'Update Category', 'textdomain' ),
			'add_new_item'      => __( 'Add New Category', 'textdomain' ),
			'new_item_name'     => __( 'New Category Name', 'textdomain' ),
			'menu_name'         => __( 'Category', 'textdomain' ),
		);

		$args = array(
			'public'            => false,
			'show_in_rest'      => false,
			'hierarchical'      => true,
			'parent_item'       => null,
			'parent_item_colon' => null,			
			'labels'            => $labels,
			'show_ui'           => false, // show in post type column
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'query_var'         => true,
			'rewrite'           => false,	
			//'rewrite'           => array( 'slug' => 'ga_service_cat' ), // /ga_services/form-1/
		);		
		
		register_taxonomy( 'ga_service_cat', array( 'ga_services' ), $args );
		
	}	

	/**
	 * New Submit Widget
	 */
	public function cmb2_ga_services_submitdiv_metabox() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_service_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'            => 'ga_service_submitdiv',
			'title'         => __( 'Save Service', 'cmb2' ),
			'object_types'  => array( 'ga_services' ), // Post type
			'context'       => 'side',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		) );
		
		// New Submit Widget
		$cmb->add_field( array(
			'name'          => 'Save Service',
			'id'            => $prefix . 'submitdiv',
			'type'          => 'select',	
			'render_row_cb' => array($this, 'ga_services_submitdiv_render_row'),			
		) );
	}
	
	
	/**
	 * Submit Widget HTML
	 */	
	public function ga_services_submitdiv_render_row() {
		global $post;
		$post_status = isset($post->post_status) ? $post->post_status : '';
		
		$statuses = array(
			'publish'      => 'Published', // appointment is was submited without a payment gateway
			'pending'      => 'Pending',   // pending payment or is not auto-confirmed on settings
			'draft'        => 'Draft',     // appointment draft
		);
		
		echo '<div class="cmb-submitdiv">';	
			echo '<select id="post-status" class="" name="post_status">';
			
			foreach($statuses as $key => $status) {
				$selected = $post_status == $key ? ' selected' : '';
				echo '<option value="'.$key.'"'.$selected.'>'.$status.'</option>';
			}
				
			echo '</select>';
			echo '<input type="submit" class="button button-ga right" value="Update">'; // button-primary
			echo '<div class="clear"></div>';
		echo '</div>';
	}	
	
	/**
	 * Categories MetaBox
	 */		
	public function cmb2_ga_services_categories_metabox() {
		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_service_';
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'            => 'ga_service_categories',
			'title'         => __( 'Service Category', 'cmb2' ),
			'object_types'  => array( 'ga_services' ), // Post type
			'context'       => 'side',
			'priority'      => 'low',
			'show_names'    => true, // Show field names on the left
		) );

		
		$cmb->add_field( array(
			'name'          => 'Service Category',
			'description'   => 'A category of services can be used into a form.',
			'id'            => $prefix . 'categories',			
			'type'          => 'select',	
			'render_row_cb' => 'get_ga_service_category_render_row',			
		) );
	}
	
	/**
	 * Services Post Type Options
	 */
	public function cmb2_ga_services_details_metaboxes() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = 'ga_service_';
		
		/**
		 * Initiate the metabox
		 */
		$cmb = new_cmb2_box( array(
			'id'            => 'ga_services_details',
			'title'         => __( 'Service Details', 'cmb2' ),
			'object_types'  => array( 'ga_services', ), // Post type
			'context'       => 'normal',
			'priority'      => 'high',
			'show_names'    => true, // Show field names on the left
		) );

		// Price
		$cmb->add_field( array(
			'name' => 'Price',
			'desc' => 'Price without currency symbol. Service price is displayed to your clients when they choose date & time.',
			'id'   => $prefix . 'price',
			'type' => 'text_money',
			'classes_cb'      => array($this,'show_on'),			
			'before_field'    => 'gf_get_currency_symbol',
			'sanitization_cb' => 'sanitize_ga_services_price', // function should return a sanitized value			
		) );	

		
		// Available Appointment Times
		$cmb->add_field( array(
			'name' => 'Available Appointment Times',
			'desc' => 'Clients book time slots or dates on the calendar. If you set this option to <b>Dates</b>, is recommended to add a provider offering this service.',
			'id'   => $prefix . 'available_times_mode',
			'type' => 'radio_inline',
			'default'         => 'interval',
			'render_row_cb'   => 'get_ga_service_times_mode_render_row', 
		) );
		
		// Duration
		$cmb->add_field( array(
			'name' => 'Duration',
			'desc' => 'Clients book appointments on an interval.',
			'id'   => $prefix . 'duration',
			'type'        => 'select',
			'classes_cb'      => array($this,'show_on'),			
			'default'     => '30', // 30 minutes
			'options_cb'      => 'ga_service_duration_options',			
			'sanitization_cb' => 'ga_sanitize_service_duration_options', // function should return a sanitized value				
		) );	
		
		// Cleanup
		$cmb->add_field( array(
			'name' => 'Cleanup',
			'desc' => 'The amount of free time to leave after an appointment.',
			'id'   => $prefix . 'cleanup',
			'type' => 'select',	
			'classes_cb'      => array($this,'show_on'),
			'options_cb' => 'ga_service_cleanup_options',			
			'sanitization_cb' => 'sanitize_ga_service_cleanup_options', // function should return a sanitized value
		) );			
		
		// Custom Time SLots
		$cmb->add_field( array(
			'name' => 'Custom Time Slots',
			'desc' => '',
			'id'   => $prefix . 'custom_slots',
			'type' => 'select',	
			'classes_cb'      => array($this,'show_on'),
			'render_row_cb'   => 'get_ga_service_custom_slots_render_row',
			'sanitization_cb' => 'sanitize_get_ga_service_custom_slots',
		) );		
		
		// Capacity
		$cmb->add_field( array(
			'name' => 'Max Capacity',
			'desc' => 'Capacity is the number of customers that can take the service at the same time/date. <br>Ex: 3 attendees for 9:00 AM, or 3 attendees for 22 January 2018 if you selected "Available Appointment Times" to "Dates"',
			'id'   => $prefix . 'capacity',
			'type' => 'select',	
			'classes_cb'      => array($this,'show_on'),
			'options_cb' => 'ga_services_capacity_options',
			'sanitization_cb' => 'sanitize_ga_services_capacity_options',			
		) );
		
		// Reduce Gaps
		$cmb->add_field( array(
			'name'            => 'Reduce Gaps',
			'desc'            => 'Clients can also book any time directly following an appointment to avoid free gaps after booked appointments and breaks.',
			'id'              => $prefix . 'reduce_gaps',
			'type'            => 'select',	
			'classes_cb'      => array($this,'show_on'),			
			'default'         => 'yes',
			'options'         => array(
				'yes'         => __( 'Yes', 'cmb2' ),
				'no'          => __( 'No', 'cmb2' ),
			),
		) );
				
		// Time Format
		$cmb->add_field( array(
			'name'            => 'Time Format',
			'desc'            => 'Calendar slots display time format.',
			'id'              => $prefix . 'time_format',
			'type'            => 'select',	
			'classes_cb'      => array($this,'show_on'),
			'default'         => '12h',
			'options'         => array(
				'12h'         => __( '12 hour', 'cmb2' ),
				'24h'         => __( '24 hour', 'cmb2' ),
			),
		) );
		
		// Show End Times
		$cmb->add_field( array(
			'name'            => 'Show End Times',
			'desc'            => 'Clients can view when the timeslot ends on the calendar.',
			'id'              => $prefix . 'show_end_times',
			'type'            => 'select',
			'classes_cb'      => array($this,'show_on'),			
			'default'         => 'no',
			'options'         => array(
				'yes'         => __( 'Yes', 'cmb2' ),
				'no'          => __( 'No', 'cmb2' ),
			),
		) );		
			
		// Remove am/pm text
		$cmb->add_field( array(
			'name'            => 'Remove AM/PM text',
			'desc'            => 'Remove am/pm text from calendar slots.',
			'id'              => $prefix . 'remove_am_pm',
			'type'            => 'select',
			'classes_cb'      => array($this,'show_on'),
			'default'         => 'no',
			'options'         => array(
				'yes'         => __( 'Yes', 'cmb2' ),
				'no'          => __( 'No', 'cmb2' ),
			),
		) );		

		// New Appointment Lead Time
		$cmb->add_field( array(
			'name'            => 'New Appointment Lead Time',
			'desc'            => 'How much lead time do you require for new appointments.',
			'id'              => $prefix . 'schedule_lead_time_minutes',
			'type'            => 'select',	
			'default'         => '240',
			'options_cb'      => 'ga_schedule_lead_time_minutes',
		) );			
		

		// Avability Type
		$cmb->add_field( array(
			'name' => 'Availability Period',
			'desc' => 'Period when appointments can be scheduled.',
			'id'   => $prefix . 'period_type',
			'type'             => 'select',
			'default'          => 'week_days',
			'options'          => array(
				'future_days'  => __( 'Future days', 'cmb2' ),
				'date_range'   => __( 'Date range', 'cmb2' ),
				'custom_dates' => __( 'Custom dates', 'cmb2' ),
			),
		) );

		// Prior Days To Book Appointment
		$cmb->add_field( array(
			'name'            => 'Prior Days To Book Appointments',
			'desc'            => 'How far in the future from today can clients book appointments.',
			'id'              => $prefix . 'schedule_max_future_days',
			'classes_cb'      => array($this, 'show_on_future_days'),			
			'type'            => 'select',	
			'default'         => '90',		
			'options_cb'      => 'ga_schedule_max_future_days',
		) );	

		/** Date range availability **/		
		$cmb->add_field( array(
			'name'            => 'Date Range',
			'desc'            => 'Availability within a defined range of dates. Format: year, month, day',
			'id'              => $prefix . 'date_range',
			'type'            => 'text',		
			'render_row_cb'   => 'get_ga_service_date_range_period_render_row',
			'sanitization_cb' => 'sanitize_get_ga_service_date_range',	
		) );		
		/** Date range availability **/			
				
		
		/** Custom dates availability **/
		$cmb->add_field( array(
			'name'            => 'Custom Dates',
			'desc'            => 'Schedule appointments only on custom dates. Format: year, month, day', 
			'id'              => $prefix . 'custom_dates',
			'type'            => 'text',		
			'render_row_cb'   => 'get_ga_service_custom_dates_period_render_row',
			'sanitization_cb' => 'sanitize_get_ga_service_custom_dates',
		) );		
		/** Custom dates availability **/		

		// Max Bookings
		$cmb->add_field( array(
			'name'            => 'Max Bookings',
			'desc'            => 'How many time slots can a client book per date.',
			'id'              => $prefix . 'max_bookings',
			'type'            => 'select',
			'default'         => '3',	
			'options_cb'      => array($this,'max_slots_selection_options')
		) );		
		
		// Multiple Slots
		$cmb->add_field( array(
			'name'            => 'Multiple Bookings',
			'desc'            => 'Enable/disable multiple bookings.',
			'id'              => $prefix . 'multiple_selection',
			'type'            => 'select',
			'default'         => 'no',
			'options'         => array(
				'yes'         => __( 'Yes', 'cmb2' ),
				'no'          => __( 'No', 'cmb2' ),
			),
		) );		

		// Multiple Max Selection
		$cmb->add_field( array(
			'name'            => 'Multiple Max Selection',
			'desc'            => 'How many multiple slots can a client select from the calendar at once.',
			'id'              => $prefix . 'max_selection',
			'type'            => 'select',			
			'default'         => '3',
			'options_cb'      => array($this,'max_slots_selection_options')
		) );

		// Prevent Double Bookings
		$cmb->add_field( array(
			'name'            => 'Prevent Double Bookings',
			'desc'            => 'Block clients booking same time again. Is recommended to set this field to Yes.',
			'id'              => $prefix . 'double_bookings',
			'type'            => 'select',			
			'default'         => 'yes',
			'options'         => array(
				'yes'         => __( 'Yes', 'cmb2' ),
				'no'          => __( 'No', 'cmb2' ),
			),
		) );
	}	
	
	
	public function show_on( $field_args, $field ) {
		$service_mode = (string) get_post_meta($field->object_id, 'ga_service_available_times_mode', true);
		$field_id     = $field_args['id'];

		$interval = array(
						'ga_service_price',
						'ga_service_duration',
						'ga_service_cleanup',
						'ga_service_capacity',
						'ga_service_reduce_gaps',
						'ga_service_time_format', 
						'ga_service_show_end_times', 
						'ga_service_remove_am_pm', 
					);
		
		$custom = array(
						'ga_service_custom_slots',
						'ga_service_time_format', 
						'ga_service_show_end_times', 
						'ga_service_remove_am_pm',					
					);
		
		$dates = array( 'ga_service_price', 'ga_service_capacity' );
		

		switch ($service_mode) {
			case 'interval':
				return in_array($field_id, $interval) ? '' : 'cmb2-hidden';
			case 'custom':
				return in_array($field_id, $custom)   ? '' : 'cmb2-hidden';
			case 'no_slots':
				return in_array($field_id, $dates)    ? '' : 'cmb2-hidden';
			default:
			   return '';
		}
	}

	
	// Show on future days
	public function show_on_future_days( $field_args, $field ) {
		$period_type = (string) get_post_meta($field->object_id, 'ga_service_period_type', true);
		return $period_type == 'future_days' || $period_type == '' ? '' : 'cmb2-hidden';
	}
	
	public function max_slots_selection_options() {
		$options = array();
		foreach (range(1, 150) as $value) {
			$options[$value] = $value;
		}

		return $options;
	}
	
	/**
	 * Services Custom Columns
	 */	
	public function manage_ga_services_columns( $columns ) {

		$columns = array(
			'cb'            => '<input type="checkbox" />', // needed for checking/selecting multiple rows
			'title'         => __( 'Service Name' ),
			'price'         => __( 'Price' ),	
			'times_mode'    => __( 'Available Appointment Times' ),							
			'duration'      => __( 'Duration' ),
			'cleanup'       => __( 'Cleanup' ),				
			'capacity'      => __( 'Attendees' ),
			'multiple'      => __( 'Multiple Bookings' ),			
			'service_cat'   => __( 'Categories' ),
		);

		return $columns;
	}
	
	public function times_mode( $post_id ) {
		return (string) get_post_meta( $post_id, 'ga_service_available_times_mode', true );		
	}	

	/**
	 * Services Custom Columns HTML
	 */		
	public function manage_ga_services_posts_custom_column( $column, $post_id ) {
		//global $post;

		switch( $column ) {	
			case 'price':
				$price = get_post_meta( $post_id, 'ga_service_price', true );
				if( $this->times_mode( $post_id ) == 'interval' ) {
					echo gf_to_money($price);
				} elseif( $this->times_mode( $post_id ) == 'custom' ) {
					echo '&#8212;';
				} elseif( $this->times_mode( $post_id ) == 'no_slots' ) {
					echo gf_to_money($price);
				}			
				break;		
			case 'times_mode':
				if( $this->times_mode( $post_id ) == 'interval' ) {
					echo 'Time Slots';
				} elseif( $this->times_mode( $post_id ) == 'custom' ) {
					echo 'Custom Time Slots';
				} elseif( $this->times_mode( $post_id ) == 'no_slots' ) {
					echo 'Dates';
				}					
			break;
					
			case 'duration':
				if( $this->times_mode( $post_id ) == 'interval' ) {
					$duration = (int) get_post_meta( $post_id, 'ga_service_duration', true );
					echo convertToHoursMins($duration);
				} elseif( $this->times_mode( $post_id ) == 'custom' ) {
					echo '&#8212;';
				} elseif( $this->times_mode( $post_id ) == 'no_slots' ) {
					echo '&#8212;';
				}
				break;	
	
			case 'cleanup':
				if( $this->times_mode( $post_id ) == 'interval' ) {
					$cleanup = (int) get_post_meta( $post_id, 'ga_service_cleanup', true );	
					echo $cleanup . ' minutes';	
				} elseif( $this->times_mode( $post_id ) == 'custom' ) {
					echo '&#8212;';
				} elseif( $this->times_mode( $post_id ) == 'no_slots' ) {
					echo '&#8212;';
				}				
				
				break;	
				
			case 'capacity':
				if( $this->times_mode( $post_id ) == 'interval' ) {
					echo get_post_meta( $post_id, 'ga_service_capacity', true );	
				} elseif( $this->times_mode( $post_id ) == 'custom' ) {
					echo '&#8212;';
				} elseif( $this->times_mode( $post_id ) == 'no_slots' ) {
					echo get_post_meta( $post_id, 'ga_service_capacity', true );
				}
				break;					

			case 'multiple':
				if( get_post_meta($post_id, 'ga_service_multiple_selection', true) == 'yes' ) {
					echo "Enabled";
				} else {
					echo "Disabled";
				}
				
				break;	
				
			case 'service_cat':
			
				$services_cats = wp_get_post_terms( $post_id, 'ga_service_cat', array('orderby' => 'name', 'order' => 'ASC', 'fields' => 'names') );				
				if( count($services_cats) > 0 ) {
					$categories = implode(", ", $services_cats);
					echo $categories;
				} else {
					echo '&#8212;';
				}

				break;			
			default :
				break;
		}
	}		
			
	
	
	/**
	 * 1. Add custom filters to appointments post type
	 */	
	public function add_service_cat_filter_to_posts_administration() {

		//execute only on the 'post' content type
		global $post_type, $pagenow; 

		//if we are currently on the edit screen of the post type listings
		if($pagenow == 'edit.php' && $post_type == 'ga_services') {

			$cat_args = array(
				'show_option_all'   => 'Filter by category',
				'name'              => 'service_cat',
				'selected'          => '0',
			);			
			
			// Filter by category
			if( isset($_GET['service_cat'])) {
				$cat_args['selected'] = absint( $_GET['service_cat'] );
			} 

			echo '<select name="'.$cat_args['name'].'" id="'.$cat_args['name'].'">';
				echo '<option value="0">'.$cat_args['show_option_all'].'</option>';
				
				$services_cats = get_terms( 'ga_service_cat', array( 'parent' => '',  'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );

				if( $services_cats ) {
					foreach( $services_cats as $cat ) {
						echo '<option value="' .$cat->term_id. '" ' .selected($cat_args['selected'], $cat->term_id, false). '>'. $cat->name .'</option>';			
					}
				}	
			echo '</select>';	
		}
	}
	
	/**
	 * 2. Add custom filters to appointments post type
	 */	
	public function add_service_cat_filter_to_posts_query($query){

		global $post_type, $pagenow; 

		//if we are currently on the edit screen of the post type listings
		if($pagenow == 'edit.php' && $post_type == 'ga_services' && $query->is_main_query()) {

			$filters = array();

			if(isset($_GET['service_cat'])) {
				
				//set the query variable for 'author' to the desired value
				$cat_id = sanitize_text_field($_GET['service_cat']);

				//if the author is not 0 (meaning all)
				if($cat_id != 0) {
					$filters[] = array(
						'taxonomy' => 'ga_service_cat', // taxonomy name 
						'field'    => 'term_id',           // term_id, slug or name
						'terms'    => $cat_id    // term id, term slug or term name
					);
				}

			}
			

			$query->set( 'tax_query', $filters);
			
		}
	}
}

