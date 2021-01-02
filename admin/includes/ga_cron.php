<?php 

defined( 'ABSPATH' ) or exit; // Exit if accessed directly


new ga_cronjobs();
class ga_cronjobs {
	// We need to do our custom cronjob function
	// Some websites have wp cronjob disabled

	private $cancel_count    = -1;
	private $complete_count  = -1;
	private $payment_count   = 6;
	
	public function __construct() {
		// Cancel unpaid appointments
		add_action( 'wp', array($this, 'ga_cancel_unpaid_appointments') );
		
		// Auto complete appointments
		add_action( 'wp', array($this, 'ga_auto_complete_appointments') );
		
		// Update appointment after payment  
		add_action( 'wp', array($this, 'ga_after_payment') );

		// PayPal Fulfillment
		//add_action( 'gform_paypal_fulfillment', array($this, 'gform_paypal_fulfillment'), 10, 4 );

        // Sync one way
        // add_action( 'wp', array($this, 'ga_cron_sync_one_way') );
	}
	
	
	/*
	 * Cancel unpaid appointments
	 */ 
	public function ga_cancel_unpaid_appointments() {
		$options = get_option('ga_appointments_calendar');
		
		// clear_appointment	
		$clear_appointment = isset($options['clear_appointment']) ? (int) $options['clear_appointment'] : 30;

		if( $clear_appointment == 0 ) {
			return;
		}

		$appointments = new WP_QUERY(
			array(
				'post_type'         => 'ga_appointments',
				'posts_per_page'    => $this->cancel_count,
				'post_status'       => array( 'payment' ),
				'orderby'           => 'modified',
				'order'             => 'DESC',				
				'date_query' => array(
					array(
						'before'    => "{$clear_appointment} minutes ago",
						'inclusive' => true,
						'column'    => 'post_modified',
					),
				),
			)	
		);
		wp_reset_postdata();
		if ( $appointments->have_posts() ) {
			while ( $appointments->have_posts() ) : $appointments->the_post();
				$post_id = get_the_ID();
				$entry_id = get_post_meta($post_id, 'ga_appointment_gf_entry_id', true  );

				if( !is_wp_error( GFAPI::get_entry($entry_id) ) ) {
					$entry = GFAPI::get_entry( $entry_id );

					$options = get_option('ga_appointments_policies');
					$auto_confirm = isset( $options['auto_confirm'] ) ? $options['auto_confirm'] : 'yes';
					$auto_confirm_status = $auto_confirm == 'yes' ? 'publish' : 'pending';

					if( isset($entry['payment_status']) ) {	
						$status = $this->auto_confirm_status( $entry['payment_status'] );
					} else {
						$status = $auto_confirm_status;
					}				
				
					if( $status == 'payment' ) {
						# cancel appointment
						wp_update_post( array( 'ID' => $post_id, 'post_status' => 'cancelled') );
					} else {
						# update new status
						wp_update_post( array( 'ID' => $post_id, 'post_status' => $status) );
					}						

				} else {
					//echo get_the_date('Y-m-j H:i:s') . '<br>';
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'cancelled' ) );					
				}
				
			endwhile; 
			wp_reset_postdata();
		}
	}
	
	/*
	 * Auto complete appointments after duration ended
	 */ 	
	public function ga_auto_complete_appointments() {
		$options = get_option('ga_appointments_calendar');
		$auto_complete = isset( $options['auto_complete'] ) ? $options['auto_complete'] : 'no';
		
		if( $auto_complete == 'no') {
			return;
		}

		$date_now = ga_current_date_with_timezone();
		$date     = $date_now->format("Y-m-j");
		$time     = $date_now->format('H:i');

		$appointments = new WP_QUERY(
			array(
				'post_type'         => 'ga_appointments',
				'posts_per_page'    => $this->complete_count,
				'post_status'       => array( 'publish' ),
				'orderby'           => 'meta_value',
				'order'             => 'ASC',					
				'meta_query'        => array( 'relation' => 'AND',
				
					array( 'key'    => 'ga_appointment_date',     'value' => $date, 'type' => 'DATE', 'compare' => '<='),
					array( 'key'    => 'ga_appointment_time_end', 'value' => $time, 'type' => 'TIME', 'compare' => '<' ) // this works only if appointment is less than time end, doesn't matter if date is less than today
					
				),
			)	
		);		

		wp_reset_postdata(); 
		
		if ( $appointments->have_posts() ) {
			while ( $appointments->have_posts() ) : $appointments->the_post();
				//echo get_post_meta( get_the_id(), 'ga_appointment_date', true ) . ' - ' . get_post_meta( get_the_id(), 'ga_appointment_time_end', true ) . '<br>';
                wp_update_post( array( 'ID' => get_the_ID(), 'post_status' => 'completed' ) );

			endwhile; 
			wp_reset_postdata();
		}			
	}
	
	/*
	 * Update appointment status after payment
	 */ 
	public function ga_after_payment() {
		// The Query
		$args = array(
					'post_type'      => 'ga_appointments',
					'post_status'    => array('payment'),
					'posts_per_page' => $this->payment_count,
					'orderby'        => 'date',
					//'order'          => 'DESC' // newest
					'order'          => 'ASC' // oldest					
				);		
		
		
		$the_query = new WP_Query( $args );
		wp_reset_postdata();
		// Update Appointment If Payment was Completed
		if ( $the_query->have_posts() ) {

			while( $the_query->have_posts() ) : $the_query->the_post();
				$post_id = get_the_ID();
				$entry_id = get_post_meta($post_id, 'ga_appointment_gf_entry_id', true  );

				if( is_wp_error( GFAPI::get_entry($entry_id) ) ) {
					return;
				}
				
				$entry = GFAPI::get_entry( $entry_id );

				$options = get_option('ga_appointments_policies');
				$auto_confirm = isset( $options['auto_confirm'] ) ? $options['auto_confirm'] : 'yes';
				$auto_confirm_status = $auto_confirm == 'yes' ? 'publish' : 'pending';

				if( isset($entry['payment_status']) ) {	
					$status = $this->auto_confirm_status( $entry['payment_status'] );				
				} else {
					$status = $auto_confirm_status;
				}				
			
				if( $status == 'payment' ) {
					# do nothing
				} else {
					wp_update_post( array( 'ID' => $post_id, 'post_status' => $status) );						
				}
			endwhile;
			wp_reset_postdata();
		}			
	}
	
	
	private function auto_confirm_status( $entry_status ) {
		$options = get_option('ga_appointments_policies');
		$auto_confirm = isset( $options['auto_confirm'] ) ? $options['auto_confirm'] : 'yes';
		$auto_confirm_status = $auto_confirm == 'yes' ? 'publish' : 'pending';
		
		switch ( $entry_status ) {
			case 'Paid':
				$status = $auto_confirm_status;
				break;
			case 'Completed':
				$status = $auto_confirm_status;
				break;					
			case 'Processing':
				$status = 'payment';
				break;
			case 'Pending':
				$status = $auto_confirm_status;
				break;	
			case 'Cancelled':
				$status = 'cancelled';
				break;	
			case 'Expired':
				$status = 'cancelled';
				break;				
			case 'Active':
				$status = $auto_confirm_status;
				break;	
			case 'Failed':
				$status = 'cancelled';
				break;	
			case 'Authorized':
				$status = $auto_confirm_status;
				break;	
			case 'Refunded':
				$status = 'cancelled';
				break;	
			case 'Voided':
				$status = 'cancelled';
				break;				
			default:
				$status = $auto_confirm_status;
		}

		return $status;
		
	}

	// TODO: disabled until further notice. Make sure this call is necessary.
    public function ga_cron_sync_one_way()
    {
        $last_run = get_option('ga_appointments_gcal_cron');
        $timestamp = ga_current_date_with_timezone()->getTimestamp();

        $dt = new DateTime();
        $dt->setTimestamp($last_run);

        if (!$last_run || $last_run == false) {
            $valid = true;
        } elseif ($timestamp - $last_run > 5 * 60) {
            $valid = true;
        } else {
            $valid = false;
        }

        if ($valid) {
            $providers_data = get_ga_appointment_providers();
            // TODO: combine query calls, simplify validation.
            foreach ($providers_data as $provider_id => $provider_name) {
                $args = array(
                    'post_type' => 'ga_appointments',
                    'post_status' => array('completed', 'publish', 'pending'),
                    'numberposts' => 25,
                    'orderby' => 'date',
                    'fields' => 'ids',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array('key' => 'ga_appointment_provider', 'value' => $provider_id, 'type' => 'numeric'),
                        array(
                            'relation' => 'OR',
                            array('key' => 'ga_appointment_gcal_id', 'value' => '', 'compare' => '='),
                            array('key' => 'ga_appointment_gcal_id', 'compare' => 'NOT EXISTS'),
                        ),
                    )
                );

                // Get the posts
                $posts = get_posts($args);
                $post_count = count($posts);

                // Check if sync is enabled
                $options = get_option('ga_appointments_gcal');
                if ($provider_id == 0 || $provider_id == false) {
                    $api_sync = isset($options['api_sync']) ? $options['api_sync'] : 'no';
                } else {
                    $provider = (array)get_post_meta($provider_id, 'ga_provider_gcal', true);
                    $api_sync = isset($provider['api_sync']) ? $provider['api_sync'] : 'no';
                    $sync_mode = isset($provider['sync_mode']) ? $provider['sync_mode'] : 'main';

                    // Sync mode
                    if ($sync_mode == 'main') {
                        $provider_id = 0;
                        $api_sync = isset($options['api_sync']) ? $options['api_sync'] : 'no';
                    }
                }

                if ($post_count > 0 && $api_sync == 'yes') {

                    if ($post_count > 1) {
                        do_action('ga_bulk_appointments', $posts, $provider_id);
                    } else {
                        $post_id = reset($posts);
                        do_action('ga_new_appointment', $post_id, $provider_id);
                    }
                }
            }

            // Update cronjob
            update_option('ga_appointments_gcal_cron', ga_current_date_with_timezone()->getTimestamp());
        }
    }

	//public function gform_paypal_fulfillment( $entry, $feed, $transaction_id, $amount ) {
	//}

} // end class





?>