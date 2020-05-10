<?php
defined( 'ABSPATH' ) or exit; // Exit if accessed directly

class ga_gcal_sync {
    // Debug variables
    public $debug_mode = false; 

    // OAuth2 constants
    const OAUTH2_REVOKE_URI = 'https://accounts.google.com/o/oauth2/revoke';
    const OAUTH2_TOKEN_URI  = 'https://accounts.google.com/o/oauth2/token';
    const OAUTH2_TOKEN_INFO = 'https://accounts.google.com/o/oauth2/tokeninfo';	// Added by me
    const OAUTH2_AUTH_URL   = 'https://accounts.google.com/o/oauth2/auth';
    const OAUTH2_FEDERATED_SIGNON_CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';
	
    // Calendar constants
    const CAL_BASE_URL  = 'https://www.googleapis.com/calendar/v3/calendars/';
    const CAL_BATCH_URL = 'https://www.googleapis.com/batch';
   
   
    /* 
    *   Construct function, sets up the api_config with the requeired variables
    *   @param (array) array
	*       client_id (string)  The client id from Google API Console
	*       redirect_uri (string)   The redirect uri from Google API Console
	*       scope (string)  The scope of the requests, which is essentially what you are planning to  access
	*       access_type (string)   The access type is either 'online' or 'offline', 'offline' gives you a longer access period and allows you to get a refresh_token
	*       response_type (string)  The response type refers to the flow of the program
    */
	function __construct( $post_id = null, $provider = null ) {
		$this->post_id  = $post_id;
		$this->provider = absint($provider);
		
		// Debug mode
		if( get_option('ga_appointments_gcal_debug') == 'enabled' ) {
			$this->debug_mode = true;
		}
		
		// Provider API Settings
		if( $this->provider == 0 || $this->provider == false ) {
			$options = (array) get_option( 'ga_appointments_gcal' );
			$token   = (array) get_option( 'ga_appointments_gcal_token' );
		} else {
			$options   = (array) get_post_meta( $this->provider, 'ga_provider_gcal', true );
			$token     = (array) get_post_meta( $this->provider, 'ga_provider_gcal_token', true );			
		}

		// Default data
		$data = array(
			'redirect_uri'   => 'urn:ietf:wg:oauth:2.0:oob',
			'scope'          => 'https://www.googleapis.com/auth/calendar',
			'access_type'    => 'offline',
			'response_type'  => 'code',
			'grant_type'     => 'authorization_code',
			'client_id'      => '',
			'client_secret'  => '',
			'code'           => '',
			'access_token'   => '',
			'refresh_token'  => '',
		);
		
		// Add default data to Config
		$this->api_config = $data;

		if( isset($options['client_id']) && !empty($options['client_id']) && isset($options['client_secret']) && !empty($options['client_secret']) && isset($options['access_code']) && !empty($options['access_code']) ) {
			$this->api_config['client_id']     = $options['client_id'];
			$this->api_config['client_secret'] = $options['client_secret'];
			$this->api_config['access_code']   = $options['access_code'];

			if( isset($token['access_token']) && !empty($token['access_token']) && isset($token['refresh_token']) && !empty($token['refresh_token']) ) {
				// Push the new data into the api_config
				$this->api_config['access_token']  = $token['access_token'];
				$this->api_config['refresh_token'] = $token['refresh_token'];
				$this->validate_token();
			} else {
		        $this->get_first_token();
			}
		}
	}
    
    /* 
    *   Returns the url to the authorisation link, once used and a refresh token is retained, you'll never need this again
    *   @return (string) Google OAutht2 link
    */
    public function create_auth_url() {
        $params = array(
            'redirect_uri=' . urlencode($this->api_config['redirect_uri']),
            'client_id=' . urlencode($this->api_config['client_id']),
            'scope=' . urlencode($this->api_config['scope']),
            'access_type=' . urlencode($this->api_config['access_type']),
            'response_type=' .urlencode($this->api_config['response_type'])
        );
        $params = implode('&', $params);
        return self::OAUTH2_AUTH_URL . "?$params";
    }  
    
    /*
    * Is valid credentials
    */
    public function valid() {
        $events = $this->get_calendars();
        if( isset($events->error->code) && $events->error->code == '401' ) {
            return false;
        } else {
			return true;
		}
    } 	
	
    /* 
    *   Returns a new access token from the refresh token
    *   @param (string) refresh_token 
    *   @return (string) New access token
    */
    public function refresh_token() {
        $info = array(
              'refresh_token' => $this->api_config['refresh_token'],
              'grant_type'    => 'refresh_token',
              'client_id'     => $this->api_config['client_id'],
              'client_secret' => $this->api_config['client_secret']
        );
        
        // Get returned CURL request
        $request = $this->make_request(self::OAUTH2_TOKEN_URI, 'POST', 'normal', $info);

		if( isset($request->access_token) ) {
			// Push the new token into the api_config
			$this->api_config['access_token'] = $request->access_token;	

			// Push the new token into the settings
			if( $this->provider == 0 || $this->provider == false ) {
				$token = (array) get_option( 'ga_appointments_gcal_token' );
				$token['access_token'] = $request->access_token;				
				update_option( 'ga_appointments_gcal_token', $token );
			} else {
				$token = (array) get_post_meta( $this->provider, 'ga_provider_gcal_token', true );
				$token['access_token'] = $request->access_token;
				update_post_meta( $this->provider, 'ga_provider_gcal_token', $token ); 
			}			
			
			// Return the token
			return $request;
		} else {
			return false;
		}
    }
      
    /* 
    *   Returns an access token from the code given in the first request to Google
    *   @param (string) data - the actual GET code given after authorisation
    *   @param (string) grant_type - always 'authorisation_code' in this instance
    *   @return (array) Contains all the returned data inc. access_token, refresh_token(first time only)
    */
    public function get_first_token() {
        $info = array(
              'code'           => $this->api_config['access_code'],
              'client_id'      => $this->api_config['client_id'],
              'client_secret'  => $this->api_config['client_secret'],			  
              'grant_type'     => $this->api_config['grant_type'],
              'redirect_uri'   => $this->api_config['redirect_uri'],
        );
        
        // Get the returned CURL request
        $request = $this->make_request(self::OAUTH2_TOKEN_URI, 'POST', 'normal', $info);

		if( isset($request->access_token) && isset($request->refresh_token) ) {
			
			// Push the new data into the api_config
			$this->api_config['access_token']  = $request->access_token;
			$this->api_config['refresh_token'] = $request->refresh_token;
			
			// Push the new tokens into the settings
			$token = array();
			$token['access_token']  = $request->access_token;
			$token['refresh_token'] = $request->refresh_token;			
			
			if( $this->provider == 0 || $this->provider == false ) {
				update_option( 'ga_appointments_gcal_token', $token );
			} else {
				update_post_meta( $this->provider, 'ga_provider_gcal_token', $token );
			}
		} 
		 
        // Return all request data
        return $request;
    }
    
    /*
    *	Check the access_token is still valid, if not use the refresh_token to get a new one
    *   
    */
    public function validate_token() {
        // make a dummy request
        $events = $this->get_calendars();
		
        if(isset($events->error->code) && $events->error->code == '401') {
            $data = $this->refresh_token();
            return $data;
        }
    }   
    
    
    /* 
    *   CURL request function
    *   @param (string) url - Obvious
    *   @param (string) method - POST, GET, PUT, DELETE, whatever...
    *   @param (string) data - We shall see...
    *   @return (object) Returns data cleanly
    */
    public function make_request($url, $method, $type, $data) {
        // Init and build/switch methods
        $ch = curl_init();
	
        // Build basic options array	
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			
        if( $method == 'GET' ) {
			$request_url = is_null($data) ? $url : $url . "?" . http_build_query($data);
			curl_setopt($ch, CURLOPT_URL, $request_url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }		
		
        if( $method == 'POST' ) {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Content-Type: application/x-www-form-urlencoded" ));
        }
		
        if( $method == 'DELETE' ) {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }		

        if( $method == 'PUT' ) {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        }				
		
        if( $type == 'json' ) {
            $post_fields = json_encode($data);
            $header = array( "Authorization: Bearer " .  $this->api_config['access_token'] , "Host: www.googleapis.com", "Content-Type: application/json" );

            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }		
			
        // Make CURL reponse
        $response = json_decode(curl_exec($ch));
     
        // Check for errors ** DEV MODE **
        if( $this->debug_mode == true ) {
			if( !is_object( $response ) ) {
				$response = new stdClass();
			}
			
			// Update Log
			$log_data = (array) get_option( 'ga_appointments_gcal_log' );
			$log_date = ga_current_date_with_timezone()->format('Y-m-j g:i a');
			array_unshift( $log_data, "<span>{$log_date}</span>" . ' - ' . print_r($response, true) );
			update_option( 'ga_appointments_gcal_log', array_slice($log_data, 0, 25) );
			
        }	   
	   
        // Close CURL
        curl_close($ch);
        
        return $response;
    }
  
 
	/* 
    *   @return (object) Returns a list of Calendars
    */
	public function get_calendars() {
        $url = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
        $events = $this->make_request($url, 'GET', 'normal', array('access_token' => $this->api_config['access_token'])); 
        return $events;	
	}  
   
	/* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @return (object) Returns all calendar events for this calendar
    */
	public function get_events( $calendar_id = NULL, $startMin = false, $startMax = false ) {
        $calendar_id = $calendar_id == NULL ? $this->get_calendar_id() : $calendar_id;
        $url = self::CAL_BASE_URL . $calendar_id . '/events';
		$data = array( 'maxResults' => '2500' );

		if( $startMin && $startMax ) {
			$data = array_merge( $data, array('timeMin' => $startMin, 'timeMax' => $startMax) );
		}

		$events = $this->make_request($url, 'GET', 'normal', array_merge( $data, array('access_token' => $this->api_config['access_token']) ));
		$items = array();
		
		do {
			if( isset( $events->nextSyncToken ) ) {
				$data = array_merge( $data, array('syncToken' => $events->nextSyncToken) );
			} else {
				$data = array_merge( $data, array('access_token' => $this->api_config['access_token']) );
			}
			
			if( isset( $events->nextPageToken ) && !empty( $events->nextPageToken ) ) {
				$events = $this->make_request($url, 'GET', 'normal', array_merge( $data, array('pageToken' => $events->nextPageToken) ));
			}

			if( isset( $events->items ) ) {
				$items = array_merge( $items, (array) $events->items );
			}
		} while( isset( $events->nextPageToken ) && !empty( $events->nextPageToken ) );		
		 
        return $items;
	}
    
	/* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @param (string) event_id - the event id, must be present or the request is useless
    *   @return (object) Returns a calendar event for this calendar
    */
	public function get_event($calendar_id = NULL, $event_id) {
        if(!$event_id) return array('error' => 'No Event ID specified');
        $calendar_id = ($calendar_id == NULL ? 'primary' : $calendar_id);
        $url = self::CAL_BASE_URL . $calendarID . '/events/' . $event_id;
        $events = $this->make_request($url, 'GET', 'normal', array('access_token' => $this->api_config['access_token'])); 
        return $events;	
	}
	
    /* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
                summary (string)
                description (string)   
    *   @return (object) Returns a calendar event for this calendar
    */
	public function create_event() {
		$date     = get_post_meta( $this->post_id, 'ga_appointment_date', true );
		$time     = get_post_meta( $this->post_id, 'ga_appointment_time', true );
		$time_end = get_post_meta( $this->post_id, 'ga_appointment_time_end', true );		
		
		if( ga_valid_date_format( $date ) ) {
			$start_date = $date;
			$end_date   = $date;
			if( ga_valid_time_format($time) && ga_valid_time_format($time_end) ) {
				$start_date .= "T{$time}:00";
				$end_date   .= "T{$time_end}:00";
			}
		} else {
			return;
		}
		
		
		$options  = get_option( 'ga_appointments_gcal' );	
		$location = isset( $options['location'] ) ? $options['location'] : get_bloginfo();	
		$calendar_id = $this->get_calendar_id();	
		$time_zone = ga_time_zone();
		
        $data = array(
            "access_token" => $this->api_config['access_token'],
            "kind"         => "calendar#event",
            "status"       => "tentative",
			"location"     => $location,
			"summary"     => $this->get_event_title( $this->post_id ),
			"description" => $this->get_event_description( $this->post_id ),
            "start"        => array( "dateTime" => $start_date, "timeZone" => $time_zone ),
            "end"          => array( "dateTime" => $end_date, "timeZone" => $time_zone ),
            "colorId"      => $this->event_color(),			
        );
		
		// Add attendee
		$data['attendees'] = $this->add_attendee( $this->post_id );
		
        $url = self::CAL_BASE_URL . $calendar_id . '/events'; 
        $event = $this->make_request($url, 'POST', 'json', $data);
		
		if( is_object($event) && isset( $event->id ) ) {
			update_post_meta( $this->post_id, 'ga_appointment_gcal', $calendar_id );
			update_post_meta( $this->post_id, 'ga_appointment_gcal_id', $event->id );
			update_post_meta( $this->post_id, 'ga_appointment_gcal_provider', $this->provider );
		}
		
        return $event;
	}
    
    /* 
    *  Batch Events
    */
	public function create_batch_events() {
		if( is_array( $this->post_id ) ) {
			$posts        = $this->post_id;
			$options      = get_option( 'ga_appointments_gcal' );
			$location     = isset( $options['location'] ) ? $options['location'] : get_bloginfo();
			//$event_title  = $this->get_event_title();
			$time_zone    = ga_time_zone();
			$access_token = $this->api_config['access_token'];
			$colorId      = $this->event_color();
			$calendar_id  = $this->get_calendar_id();

			// Curl Init
			$ch = curl_init();	
			$boundary = 'batch_' . uniqid();
			$data = '';			
			
			// Create the Events
			foreach( $posts as $postID ) {
				$date     = get_post_meta($postID, 'ga_appointment_date', true);
				$time     = get_post_meta($postID, 'ga_appointment_time', true);
				$time_end = get_post_meta($postID, 'ga_appointment_time_end', true);
				
				if( ga_valid_date_format( $date ) ) {
					$start_date = $date;
					$end_date   = $date;
					if( ga_valid_time_format($time) && ga_valid_time_format($time_end) ) { 
						$start_date .= "T{$time}:00";
						$end_date   .= "T{$time_end}:00"; 
					}
				} else {
					continue; 
				}
	
				$event = array(
					"access_token" => $access_token,
					"kind"         => "calendar#event",
					"status"       => "tentative",
					"location"     => $location,
					"summary"     => $this->get_event_title( $postID ),
					"description" => $this->get_event_description( $postID ),
					"description"  => "",
					"start"        => array( "dateTime" => $start_date, "timeZone" => $time_zone ),
					"end"          => array( "dateTime" => $end_date, "timeZone" => $time_zone ),
					"colorId"      => $colorId,
					"post_id"      => $postID,
				);

				// Add attendee
				$event['attendees'] = $this->add_attendee( $postID );
				
				$data .= "--" . $boundary . "\r\n";
				$data .= 'Content-Type: application/http' . "\r\n";
				$data .= 'Content-Transfer-Encoding: binary' . "\r\n";
				$data .= 'MIME-Version: 1.0' . "\r\n";
				$data .= 'Content-ID: '. $postID . '-post' . "\r\n\r\n";
				// empty line
				$data .= 'POST /calendar/v3/calendars/' . $calendar_id . '/events' . "\r\n";
				$data .= 'Authorization: Bearer ' .  $access_token	. "\r\n";
				$data .= 'Content-Type: application/json' . "\r\n\r\n";
				$data .= json_encode($event) . "\r\n\r\n";				
			} // end foreach
			
			// End boundary
			$data .= "--" . $boundary . "--";
			
			// Headers
			$headers = array(
				"Host: www.googleapis.com",
				"Content-Type: multipart/mixed; boundary={$boundary}",
			);	
				
			// Curl Setup
			curl_setopt($ch, CURLOPT_URL, self::CAL_BATCH_URL);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POST, true);	
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLINFO_HEADER_OUT, false);
			curl_setopt($ch, CURLOPT_VERBOSE, true);	
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

			// Make CURL reponse
			$response = curl_exec($ch);

			// Close CURL
			curl_close($ch);			
			
			// Decode response
			$response_data = preg_split("/(--batch_.*?:)+/s", $response, -1, PREG_SPLIT_NO_EMPTY);
			$pattern = '/({(?>[^{}]|(?0))*?})/';

			foreach( $response_data as $r_data ) {
				if( preg_match( $pattern, $r_data, $json ) == 1 ) {
					$event = json_decode(reset($json));
					
					if( json_last_error() == JSON_ERROR_NONE ) {
						if( preg_match('/response-(.*?)-post/', $r_data, $post_id) == 1 ) {
							$event->post_id = end($post_id);	
						}
						
						if( isset($event->id) && isset($event->post_id) ) {
							update_post_meta( $event->post_id, 'ga_appointment_gcal', $calendar_id );
							update_post_meta( $event->post_id, 'ga_appointment_gcal_id', $event->id );
							update_post_meta( $event->post_id, 'ga_appointment_gcal_provider', $this->provider );							
						}
					}
				}
			}			
			
			return $response;		
		}
	} // end func
	
	
    /* 
    *   Deletes an Event
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
    *   @return (object) Returns a calendar event for this calendar
    */
	public function delete_event() {
		$calendar_id = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal', true );
		$calendar_id = empty($calendar_id) ? 'primary' : $calendar_id;
		$event_id    = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal_id', true );
		
		if( !empty($event_id) ) {
			$url = self::CAL_BASE_URL . $calendar_id . '/events/' . $event_id; 
			$event = $this->make_request( $url, 'DELETE',  'json', null );
			return $event;
		}
	}
    
    /* 
    *   Deletes an Event
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
    *   @return (object) Returns a calendar event for this calendar
    */
    public function update_event() {
		$options     = get_option( 'ga_appointments_gcal' );	
		$location    = isset( $options['location'] ) ? $options['location'] : get_bloginfo();		
		$time_zone   = ga_time_zone();
		
		$calendar_id = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal', true );		
		$calendar_id = empty($calendar_id) ? 'primary' : $calendar_id;

		$event_id    = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal_id', true );
       
		$date     = get_post_meta( $this->post_id, 'ga_appointment_date', true );
		$time     = get_post_meta( $this->post_id, 'ga_appointment_time', true );
		$time_end = get_post_meta( $this->post_id, 'ga_appointment_time_end', true );		

		if( ga_valid_date_format( $date ) ) {
			$start_date = $date;
			$end_date   = $date;
			if( ga_valid_time_format($time) && ga_valid_time_format($time_end) ) {
				$start_date .= "T{$time}:00";
				$end_date   .= "T{$time_end}:00";
			}
		} else {
			return;
		}	   
		
	    if( !empty($event_id) ) {
			$data = array(
				"access_token" => $this->api_config['access_token'],
				"kind"        => "calendar#event",
				"status"      => "tentative",
				"location"    => $location,
				"summary"     => $this->get_event_title( $this->post_id ),
				"description" => $this->get_event_description( $this->post_id ),
				"start"       => array( "dateTime" => $start_date, "timeZone" => $time_zone ),
				"end"         => array( "dateTime" => $end_date, "timeZone" => $time_zone ),
				"colorId"     => $this->event_color(),					
			);			
			
			// Add attendee
			$data['attendees'] = $this->add_attendee( $this->post_id );					
			
		   $url = self::CAL_BASE_URL . $calendar_id .'/events/' . $event_id;
		   $events = $this->make_request($url, 'PUT', 'json', $data);
		   return $events;		
		}
    }

    /* 
    *   Sync events 2 way
    */	
	public function sync_events( $calendar_id ) {
		$events = $this->get_events($calendar_id);
		
		if( isset($events) ) {
			wp_defer_term_counting(true);
			wp_defer_comment_counting(true);
			
			foreach( $events as $event ) {
				if( $this->event_exists($calendar_id, $event->id) ) {
					continue;
				}

				if( isset($event->visibility) && $event->visibility == 'private' ) {
					continue;
				}
				
				
				if( isset($event->start->dateTime) && isset($event->end->dateTime) ) {
					
					// Start Date/Time
					$startTime  = new DateTime( $event->start->dateTime );
					$start_date = $startTime->format('Y-m-j');
					$time       = $startTime->format('H:i');
					
					// End Date/Time
					$endTime    = new DateTime( $event->end->dateTime );
					$end_date   = $endTime->format('Y-m-j');
					$time_end   = $endTime->format('H:i');
					
					$app_type   = 'time_slot';
					$duration   = $this->get_slot_duration( $startTime, $endTime );
					
				} elseif( isset($event->start->date) && isset($event->end->date) ) {
					
					// Start Date/Time
					$startTime  = new DateTime( $event->start->date );
					$start_date = $startTime->format('Y-m-j');
					$time       = '00:00';
					
					// End Date/Time
					$endTime    = new DateTime( $event->end->date );
					$end_date   = $endTime->format('Y-m-j');
					$time_end   = '23:59';
					
					$app_type   = 'date';
					$duration   = $this->get_slot_duration( $startTime, $endTime );
					
				} else {
					continue;
				}
								
				// Gather post data.
				$ga_appointment = array(
					'post_title'    => 'Appointment',
					'post_status'   => $this->appointment_status(),
					'post_type'     => 'ga_appointments',
				);
				
				// Check for Service Name
				$service_id = '';
				if( isset($event->summary) && preg_match('/\\[(.*?)\\]/', $event->summary, $match) ) {
					if( $page = get_page_by_title( $match[1], OBJECT, 'ga_services' ) ) {
						$service_id = $page->ID;
					}
				}
				
				if( $postID = wp_insert_post($ga_appointment) ) {
					update_post_meta($postID, 'ga_appointment_type', $app_type);
					update_post_meta($postID, 'ga_appointment_duration', $duration); // Duration
					update_post_meta($postID, 'ga_appointment_service', $service_id); // Provider
					update_post_meta($postID, 'ga_appointment_provider', $this->provider); // Provider
					update_post_meta($postID, 'ga_appointment_date', $start_date); //	Date		
					update_post_meta($postID, 'ga_appointment_time', $time); //	Time
					update_post_meta($postID, 'ga_appointment_time_end', $time_end); //	Time							
					update_post_meta($postID, 'ga_appointment_gcal', $calendar_id);
					update_post_meta($postID, 'ga_appointment_gcal_id', $event->id);
					update_post_meta($postID, 'ga_appointment_gcal_provider', $this->provider);
				}
			}
			
			wp_defer_term_counting(false);
			wp_defer_comment_counting(false);	

		} // end if				
	}
	
    /* 
    *   Event exists
    */		
	public function event_exists( $calendar_id, $event_id ) {
		$appointments = new WP_Query( 
			array(
				'post_type'      => 'ga_appointments',
				'post_status'    => array('completed', 'publish', 'pending', 'payment', 'cancelled', 'draft', 'trash', 'auto-draft', 'future', 'private', 'inherit'),				
				'posts_per_page' => -1, 
				'orderby'        => 'meta_value',
				'order'          => 'ASC',			
				'fields'         => 'ids',
				'meta_query'        => array( 'relation' => 'AND',					
					array(
						'key'     => 'ga_appointment_gcal',
						'value'   => $calendar_id,
						'compare' => '='
					), 	
					array(
						'key'     => 'ga_appointment_gcal_id',
						'value'   => $event_id,
						'compare' => '='
					)						
				)
			) 
		);

		wp_reset_postdata();
		return $appointments->posts ? true : false;
	}
	
	
	public function get_calendar_id() {
		// Get calendar ID
		if( $this->provider == 0 || $this->provider == false ) {
			$options = get_option( 'ga_appointments_gcal' );
			$calendar_id = isset($options['calendar_id']) && !empty($options['calendar_id']) ? $options['calendar_id'] : 'primary';	
		} else {
			$provider_options = (array) get_post_meta( $this->provider, 'ga_provider_gcal', true );
			$calendar_id = isset($provider_options['calendar_id']) && !empty($provider_options['calendar_id']) ? $provider_options['calendar_id'] : 'primary';
		}
		
		return $calendar_id;
	}	
	
	public function appointment_status() {
		$options = get_option('ga_appointments_policies');
		$auto_confirm = isset( $options['auto_confirm'] ) ? $options['auto_confirm'] : 'yes';
		return $auto_confirm == 'yes' ? 'publish' : 'pending';	
	}
	
	public function get_slot_duration( $startTime, $endTime ) {
		$diff    = $startTime->diff( $endTime );
		$minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
		return $minutes;
	}	
	
	
	public function event_color() {
		$status = $this->appointment_status();
		$options = get_option( 'ga_appointments_gcal' );	
		$confirmed_color = isset( $options['confirmed_color'] ) ? $options['confirmed_color'] : '2';
		$pending_color = isset( $options['pending_color'] ) ? $options['pending_color'] : '6';

		return $status == 'publish' ? $confirmed_color : $pending_color;
	}
	
	// Event Summary
	public function get_event_title( $post_id ) {
		$find = array(
			'{service_name}',			
			'{client_name}',				
			'{provider_name}',
		);
		
		$replace = array(
			$this->get_service_name($post_id),
			$this->get_client_name($post_id),		
			$this->get_provider_name($post_id),	
		);		
		
		
		$gcal    = get_option( 'ga_appointments_gcal' );
		$summary = isset( $gcal['summary'] ) ? $gcal['summary'] : '[{service_name}] with {client_name}';
		return str_ireplace( $find, $replace, $summary );
	}
	
	// Event description
	public function get_event_description( $post_id ) {
		$find = array(
			'{service_name}',	
			'{appointment_date}',			
			'{client_name}',
			'{client_email}',	
			'{client_phone}',				
			'{provider_name}',
		);
		
		$replace = array(
			$this->get_service_name($post_id),	
			$this->get_date_time($post_id),
			$this->get_client_name($post_id),
			$this->get_client_email($post_id),
			$this->get_client_phone($post_id),			
			$this->get_provider_name($post_id),	
		);
		
		$gcal        = get_option( 'ga_appointments_gcal' );
		$description = isset( $gcal['description'] ) ? $gcal['description'] : '';
		return str_ireplace( $find, $replace, $description );
	}			
	
	public function get_service_id( $appointment_id ) {
		return (int) get_post_meta( $appointment_id, 'ga_appointment_service', true );
	}		
	
	public function get_service_name( $appointment_id ) {
		$service_id = $this->get_service_id( $appointment_id );
		return 'ga_services' == get_post_type($service_id) ? esc_html( get_the_title( $service_id ) ) : '(Not defined)';
	}

	public function get_client_id( $appointment_id ) {
		return get_post_meta( $appointment_id, 'ga_appointment_client', true );
	}		

	public function get_client_name( $appointment_id ) {
		$client_id = get_post_meta( $appointment_id, 'ga_appointment_client', true );
	
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

	public function get_client_email( $appointment_id ) {	
		$client_id = $this->get_client_id($appointment_id);
	
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
	
	private function get_client_phone($post_id) {
		$new_client = get_post_meta( $post_id, 'ga_appointment_new_client', true ); // array
		$phone      = isset( $new_client['phone'] ) && !empty( $new_client['phone'] ) ? $new_client['phone'] : '';
		return $phone;
	}		
	
	private function get_provider_name($post_id) {
		$provider_id = (int) get_post_meta( $post_id, 'ga_appointment_provider', true );
		$provider_name = 'ga_providers' == get_post_type($provider_id) ? esc_html( get_the_title( $provider_id ) ) : 'No provider';		
		return $provider_name;
	}		
	
	private function get_date_time($post_id) {
		// Date
		$app_date            = (string) get_post_meta( $post_id, 'ga_appointment_date', true );			
		$date                = ga_valid_date_format($app_date) ? new DateTime($app_date) : false;
		$app_date_text       = $date ? $date->format('l, F j Y') : '(Date not defined)';

		// Time
		$app_time            = (string) get_post_meta( $post_id, 'ga_appointment_time', true );
		$time                = ga_valid_time_format($app_time) ? new DateTime($app_time) : false;
		$app_time_text       = $time ? $time->format('g:i a') : '(Time not defined)';
		
		// Date Slots Mode
		$service_id = (int) get_post_meta( $post_id, 'ga_appointment_service', true );
		
		// Time Format Display
		$time_display = ga_service_time_format_display($service_id);			

		// Service Mode
		$available_times_mode = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );			
		
		// Form id
		$form_id = $this->get_form_id($post_id);
		
		// Translation Support					
		if( $available_times_mode == 'no_slots' )  {
			if( $date ) {
				$month = $date->format('F');
				$day   = $date->format('j');
				$year  = $date->format('Y');
				$appointment_date = ga_get_form_translated_slots_date($form_id, $month, $day, $year);
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
				
				$appointment_date = ga_get_form_translated_date_time($form_id, $month, $week, $day, $year, $_time);
			} else {
				$appointment_date = "{$app_date_text} at {$app_time_text}";;
			}					
		}		
		
		
		return $appointment_date;
	}		
	
	public function add_attendee( $post_id ) {
		$options      = get_option( 'ga_appointments_gcal' );
		$add_attendee = isset( $options['attendee'] ) ? $options['attendee'] : 'no';
		if( $add_attendee == 'yes' ) {
			$email = $this->get_client_email( $post_id );
			if( filter_var($email, FILTER_VALIDATE_EMAIL) ) {
				$name  = $this->get_client_name($post_id);
				return array( array( "displayName" => $name, "email" => $email ) );
			}
		}
		
		return '';
	}		

	
	private function get_form_id($post_id) {
		$entry_id = get_post_meta($post_id, 'ga_appointment_gf_entry_id', true  );

		if( class_exists('RGFormsModel') && RGFormsModel::get_lead($entry_id) ) {
			$entry_obj      = RGFormsModel::get_lead($entry_id);
			$form_id        = $entry_obj['form_id'];				
			return $form_id;
		} else {
			return false;
		}			
	}

	
		
} // end class