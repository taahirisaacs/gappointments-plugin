<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

new ga_appointments_activity();
class ga_appointments_activity {
	public $perPage = 10;
	public $page;	
	public $offset;	
	
	public function __construct() {
		// submenu
		add_action('admin_menu', array($this, 'activity_submenu') );
		
		$this->page = isset($_GET['ga_page']) ? (int) $_GET['ga_page'] : 1;
		$this->offset = ( $this->page - 1 ) * $this->perPage;
	}	
	
    /**
     * Admin Submenu
     */
	public function activity_submenu() {
		add_submenu_page( 'ga_appointments_settings', 'Activity', 'Activity', 'manage_options', 'ga_appointments_activity', array($this, 'ga_appointments_activity_cb') ); 
	}	

    /**
     * Activity Page Markup
     */			
	public function ga_appointments_activity_cb() {
		
		$today_appointments   = $this->get_todays_appointments();
		$today_count          = $this->get_todays_appointments_count();
		$pending_count        = $this->get_pending_appointments_count();	
		$appointment_text     = $today_count == 1 ? 'Appointment Today' : 'Appointments Today';
		$date_now = ga_current_date_with_timezone();
		?>
		<div class="wrap"><h1>Activity</h1>
		
			<div class="grid-row">
				<div class="grid-lg-6 grid-md-10 grid-sm-12 grid-xs-12">
					<div id="ga_appointment_activity_stats">					
						<div class="box-content">
						
							<div class="ga_appointment_stats">
								<div class="container">
									<div class="grid-row grid-padding">
									
										<div class="grid-lg-6 grid-md-6 grid-sm-12 grid-xs-12">
											<div class="appointment_stats_box">
												<div class="appointments_count appointments_today">
													<?php echo $today_count; ?>
												</div>	
												<div class="appointments_description">
													<span class="t-left">Today</span>
												</div>												
											</div>
										</div>	
										<div class="grid-lg-6 grid-md-6 grid-sm-12 grid-xs-12">
											<div class="appointment_stats_box">									
												<div class="appointments_count appointments_pending">
													<?php echo $pending_count; ?>
												</div>	
												<div class="appointments_description">
													<span class="t-left">Pending Confirmation</span>
												</div>												
											</div>	
										</div>	
									</div>	
								</div>	
							</div>
										
							<?php						
								$query = $this->get_todays_appointments();
							
								if ( $query->have_posts() ) {
									echo '<div class="no_appointments">LATEST APPOINTMENTS</div>';
									echo '<div class="latest_appointments">';							
									
									while ( $query->have_posts() ) : $query->the_post(); 
										// DATE
										$app_date            = (string) get_post_meta( get_the_id(), 'ga_appointment_date', true );
										$date                = new DateTime($app_date);
										$app_date_text       = $date ? $date->format('F j Y') : '(Date not defined)';									
									
										// Time
										$app_time            = (string) get_post_meta( get_the_id(), 'ga_appointment_time', true );
										$time                = new DateTime($app_time);													
										$app_time_text       = $time && $time->format('H:i') === $app_time ? $time->format('g:i a') : '(Time not defined)';
										
										
										$appointment_date    = "{$app_date_text} at {$app_time_text}";		
				
										// Date Slots Mode
										$service_id = get_post_meta(get_the_id(), 'ga_appointment_service', true);										
										if( get_post_meta($service_id, 'ga_service_available_times_mode', true) == 'no_slots' ) {
											$appointment_date  = "{$app_date_text} - Full day";
										}											
										
										
										$provider_id         = (int) get_post_meta( get_the_id(), 'ga_appointment_provider', true );
										$provider_name       = $provider_id ? ' with ' . get_the_title($provider_id) : '';	
										
										$service_id          = (int) get_post_meta( get_the_id(), 'ga_appointment_service', true );
										$service_name        = $service_id ? get_the_title($service_id) : 'Service not defined';
										
										// STATUS
										$ga_statuses = ga_appointment_statuses();
										$post_status = get_post_status( get_the_id() );
										$app_status = isset( $ga_statuses[$post_status] ) ? strtolower($ga_statuses[$post_status]) : 'failed';
										
										
										switch ( get_post_status( get_the_id() ) ) {
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
												$class = 'status-green';
										}
										
									
									?>
									
										<div class="appointment_post">
											<a class="app_post" href="<?php echo get_edit_post_link( get_the_id() ); ?>">
												<?php echo '<span class="appointment_time">'. $appointment_date .'</span>'; ?>
												<?php echo '<span class="appointment_provider">'. $service_name . $provider_name .'</span>'; ?>
												<div class="appointment-status"><span class="appointment-<?php echo strtolower($class); ?>"> <?php echo ucfirst($app_status) ?></span></div>					
											</a>
										</div>
									
								<?php 
									endwhile;
									wp_reset_postdata();
									echo '</div>';
									
									echo '<div class="ga_pagination">' . $this->ga_numeric_posts_nav('ga_appointment_provider', $provider_id) . '</div>';									
								} else {
									echo '<div class="no_appointments">No upcoming appointments</div>';
								}
							?>
						</div>
					</div>					
				</div>
				<div class="grid-lg-6 grid-md-6 grid-sm-12 grid-xs-12">
					
				</div>
			</div>	
			
		</div>
	<?php } //end public function
	
    /**
     * Get todays appointments
     */		
	public function get_todays_appointments() {	
		$args = array(
			'post_type'        => 'ga_appointments',
			'post_status'      =>  array( 'publish', 'pending' ),
			'posts_per_page'    => $this->perPage,
			'offset'            => $this->offset,
				
			'meta_query' => array( 'relation' => 'AND',	
				'date'   => array( 'key' => 'ga_appointment_date', 'type' => 'DATE' ),
				'time'   => array( 'key' => 'ga_appointment_time', 'compare' => 'BETWEEN', 'type' => 'TIME'  ),
			),
			'orderby'    => array( 
				'date'   => 'ASC',
				'time'   => 'ASC',
			),
			
		);
		
		$query = new WP_Query( $args );
		return $query;	
	}

    /**
     * Get pending appointments count
     */		
	public function get_pending_appointments_count() {
		
		$args = array(
			'post_type'         => 'ga_appointments',
			'posts_per_page'    => -1,
			'post_status'       => array('pending'),
		);
		
		$query = new WP_Query( $args );
		return $query->post_count;
		
	}	
	
    /**
     * Get pending appointments count
     */		
	public function get_todays_appointments_count() {
		$timezone = ga_time_zone();		
		
		$date = new DateTime();
		$date->setTimezone( new DateTimeZone( $timezone ) );
		
		$args = array(
			'post_type'        => 'ga_appointments',
			'post_status'      =>  array( 'publish', 'pending' ),
			'posts_per_page'   => -1,			
			'meta_query' => array(
				array( 'key' => 'ga_appointment_date', 'value'   => $date->format('Y-m-j') ),
			)			
		);
		
		$query = new WP_Query( $args );
		return $query->post_count;
		
	}		
	
	
	/**
	 * Appointments Numeric Pagination
	 */		
	public function ga_numeric_posts_nav() {

		$appointments = $this->get_todays_appointments();

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
	
	
	
	
	
	
	
	
}



