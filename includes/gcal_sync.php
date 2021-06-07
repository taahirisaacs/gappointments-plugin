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
    const CAL_BATCH_URL = 'https://www.googleapis.com/batch/calendar/v3';
    const CAL_LIST_URL  = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    /**
     * @var int
     */
    private $post_id;

    /**
     * @var int
     */
    private $provider_id;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $token;

    /**
     * @var mixed
     */
    private $sync_options;

    /**
     * @var string[]
     */
    private $api_config;


    /**
     * ga_gcal_sync constructor. Sets up the api_config with the required variables
     *
     * @param null $post_id
     * @param null $provider_id
     * @set array $data
     *       (string) client_id     The client id from Google API Console
     *       (string) redirect_uri  The redirect uri from Google API Console
     *       (string) scope         The scope of the requests, which is essentially what you are planning to  access
     *       (string) access_type   The access type is either 'online' or 'offline', 'offline' gives you a longer access period and allows you to get a refresh_token
     *       (string) response_type The response type refers to the flow of the program
     */
    function __construct( $post_id = null, $provider_id = null ) {
        $this->post_id     = $post_id;
        $this->provider_id = absint($provider_id);

        // Debug mode
        if( get_option('ga_appointments_gcal_debug') == 'enabled' ) {
            $this->debug_mode = true;
        }

        // Get Google Calendar sync settings
        if( $this->provider_id === 0 ) {
            $this->options      = (array) get_option( 'ga_appointments_gcal' );
            $this->token        = (array) get_option( 'ga_appointments_gcal_token' );
            $this->sync_options = get_option( 'ga_appointments_gcal_sync_options' );
        } else {
            $this->options      = (array) get_post_meta( $this->provider_id, 'ga_provider_gcal', true );
            $this->token        = (array) get_post_meta( $this->provider_id, 'ga_provider_gcal_token', true );
            $this->sync_options = get_post_meta( $this->provider_id, 'ga_provider_gcal_sync_options', true );
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

        if( isset( $this->options['client_id'] )
            && !empty( $this->options['client_id'] )
            && isset( $this->options['client_secret'] )
            && !empty( $this->options['client_secret'] )
            && isset( $this->options['access_code'] )
            && !empty( $this->options['access_code'] ) )
        {
            $this->api_config['client_id']     = $this->options['client_id'];
            $this->api_config['client_secret'] = $this->options['client_secret'];
            $this->api_config['access_code']   = $this->options['access_code'];

            if( isset( $this->token['access_token'] )
                && !empty( $this->token['access_token'] )
                && isset( $this->token['refresh_token'] )
                && !empty( $this->token['refresh_token'] ) )
            {
                // Push the new data into the api_config
                $this->api_config['access_token']  = $this->token['access_token'];
                $this->api_config['refresh_token'] = $this->token['refresh_token'];
                $this->validate_token();
            } else {
                $this->get_first_token();
            }
        }
    }

    /**
     * Returns a new access token from the refresh token
     *
     * @return string New access token
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
            $this->token['access_token'] = $request->access_token;

            if( $this->provider_id === 0 ) {
                $this->update_gcal_option('ga_appointments_gcal_token', $this->token );
            } else {
                $this->update_gcal_option('ga_provider_gcal_token', $this->token );
            }

            // Return the token
            return $request;
        } else {
            return false;
        }
    }

    /**
     * Returns an access token from the code given in the first request to Google
     *
     * @return object Contains all the returned data inc. access_token, refresh_token(first time only)
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
            $this->token['access_token']  = $request->access_token;
            $this->token['refresh_token'] = $request->refresh_token;

            if( $this->provider_id === 0 ) {
                $this->update_gcal_option( 'ga_appointments_gcal_token', $this->token );
            } else {
                $this->update_gcal_option( 'ga_provider_gcal_token', $this->token );
            }
        }

        // Return all request data
        return $request;
    }

    /**
     *	Check the access_token is still valid, if not use the refresh_token to get a new one
     */
    public function validate_token() {
        // make a dummy request
        $events = $this->get_calendars();

        if(isset($events->error->code) && $events->error->code == '401') {
            $data = $this->refresh_token();
            return $data;
        }
    }

    /**
     * Update Google Calendar options
     *
     * @param $option
     * Name of the option to update
     * @param $value
     * Option value
     */
    private function update_gcal_option( $option, $value ) {
        if( $this->provider_id === 0 ) {
            update_option( $option, $value );
        } else {
            update_post_meta( $this->provider_id, $option, $value );
        }
        return true;
    }

    /**
     * CURL request function
     *
     * @param $url
     * @param $method
     * @param $type
     * @param $data
     * @return object Returns event data
     */
    public function make_request( $url, $method, $type, $data ) {
        // Init and build/switch methods
        $ch = curl_init();

        // Build basic options array
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ( $method == 'GET' ) {
            $request_url = is_null($data) ? $url : $url . "?" . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $request_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        } else if ( $method == 'POST' ) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Content-Type: application/x-www-form-urlencoded" ));
        } else if ( $method == 'DELETE' ) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else if ( $method == 'PUT' ) {
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

    /**
     * Get a list of calendars
     *
     * @return object returns calendars
     */
    public function get_calendars() {
        return $this->make_request(self::CAL_LIST_URL, 'GET', 'normal', array('access_token' => $this->api_config['access_token']));
    }

    /**
     * Fetch all events from a given calendar and return them
     *
     * @param string calendar_id The calendar id
     * @return array Returns all calendar events
     */
    public function get_events( string $calendar_id ) {
        $url       = self::CAL_BASE_URL . $calendar_id . '/events';
        $max_bound = $this->get_max_bound();
        $items     = array();
        $time_now  = (new DateTime( 'now' ))->setTime(0, 0, 0);
        $time_min_bound = date(DateTime::RFC3339_EXTENDED);
        $time_max_bound = date(DateTime::RFC3339_EXTENDED, strtotime( $max_bound, strtotime($time_min_bound) ) );
        $regenerate     = false;
        $sync_options   = $this->sync_options;

        // Prepare sync options
        if( $sync_options === false || empty( $sync_options ) ) {
            $sync_options = false;
        }

        if( $sync_options ) {
            if( isset( $sync_options['created'] ) ) {
                // Regenerate sync token if more than one day has passed.
                $created_old = new DateTime( $sync_options['created'] );
                $diff = $created_old->diff( $time_now )->format('%a');
                if( $diff >= 1 ) {
                    $regenerate = true;
                }
            }

            // Modify sync options if sync token is present
            if( isset( $sync_options['syncToken'] ) ) {
                if( isset( $sync_options['timeMin'], $sync_options['timeMax'] ) ) {
                    unset( $sync_options['timeMin'], $sync_options['timeMax'] );
                }
            } else {
                // Update timeMin and timeMax values
                $sync_options['timeMin'] = $time_min_bound;
                $sync_options['timeMax'] = $time_max_bound;
            }
        } else {
            $regenerate = true;
        }

        if( $regenerate ) {
            // Get default sync options
            $sync_options = $this->get_sync_options( $time_min_bound, $time_max_bound );
        }

        $events = $this->make_request($url, 'GET', 'normal', array_merge( $sync_options, array('access_token' => $this->api_config['access_token']) ));

        // Validate for expired sync token
        if( isset( $events->error->code ) && $events->error->code == '410' ) {
            $sync_options = $this->get_sync_options( $time_min_bound, $time_max_bound );
            $events       = $this->make_request($url, 'GET', 'normal', array_merge( $sync_options, array('access_token' => $this->api_config['access_token']) ));
        }
        $next_sync_token = $events->nextSyncToken ?? '';

        do {
            if( isset( $events->nextPageToken ) && !empty( $events->nextPageToken ) ) {
                $events = $this->make_request($url, 'GET', 'normal', array_merge( $sync_options, array('pageToken' => $events->nextPageToken) ));
            }
            if( isset( $events->items ) ) {
                $items = array_merge( $items, (array) $events->items );
            }
            $next_sync_token = $events->nextSyncToken ?? '';
        } while( isset( $events->nextPageToken ) && !empty( $events->nextPageToken ) );

        // Update syncToken value
        if( !empty( $next_sync_token ) ) {
            $sync_options['syncToken'] = $next_sync_token;
        }

        // Update or create 'created' timestamp value
        if( $regenerate || !isset( $sync_options['created'] ) ) {
            $sync_options['created'] = $time_now->format("Y-m-d");
        }

        // Update sync options
        if( $this->provider_id === 0 ) {
            $this->update_gcal_option( 'ga_appointments_gcal_sync_options', $sync_options );
        } else {
            $this->update_gcal_option( 'ga_provider_gcal_sync_options', $sync_options );
        }

        return $items;
    }

    /**
     * Create a single Google Calendar event
     */
    public function create_event() {
        if( is_array( $date_params = $this->set_date_params( $this->post_id ) ) ) {
            list( $start_date, $end_date ) = $date_params;
        } else {
            return false;
        }

        $location    = isset( $this->options['location'] ) ? esc_html( $this->options['location'] ) : '';
        $time_zone   = ga_time_zone();
        // get calendar ID from provider settings
        $calendar_id = $this->get_calendar_id();
        if( $calendar_id === 'primary' ) {
            return false;
        }

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
        $event = $this->make_request( $url, 'POST', 'json', $data );

        if( is_object( $event ) && isset( $event->id ) ) {
            update_post_meta( $this->post_id, 'ga_appointment_gcal_calendar_id', $calendar_id );
            update_post_meta( $this->post_id, 'ga_appointment_gcal_id', $event->id );
            update_post_meta( $this->post_id, 'ga_appointment_gcal_last_updated', $event->updated );
            update_post_meta( $this->post_id, 'ga_appointment_gcal_provider', $this->provider_id );
        }

        return true;
    }

    /**
     * Create multiple Google Calendar events via batch
     */
    public function create_batch_events() {
        if( !is_array( $this->post_id ) ) {
            return false;
        }

        $posts        = $this->post_id;
        $location     = isset( $this->options['location'] ) ? esc_html( $this->options['location'] ) : '';
        $time_zone    = ga_time_zone();
        $access_token = $this->api_config['access_token'];
        $colorId      = $this->event_color();
        // get calendar ID from provider settings
        $calendar_id  = $this->get_calendar_id();
        if( $calendar_id === 'primary' ) {
            return false;
        }

        // Curl Init
        $ch = curl_init();
        $boundary = 'batch_' . uniqid();
        $data = '';

        // Create the Events
        foreach( $posts as $post_id ) {
            if( is_array( $date_params = $this->set_date_params( $post_id ) ) ) {
                list( $start_date, $end_date ) = $date_params;
            } else {
                continue;
            }

            $event = array(
                "access_token" => $access_token,
                "kind"         => "calendar#event",
                "status"       => "tentative",
                "location"     => $location,
                "summary"      => $this->get_event_title( $post_id ),
                "description"  => $this->get_event_description( $post_id ),
                "start"        => array( "dateTime" => $start_date, "timeZone" => $time_zone ),
                "end"          => array( "dateTime" => $end_date, "timeZone" => $time_zone ),
                "colorId"      => $colorId,
                "post_id"      => $post_id,
            );

            // Add attendee
            $event['attendees'] = $this->add_attendee( $post_id );

            $data .= "--" . $boundary . "\r\n";
            $data .= 'Content-Type: application/http' . "\r\n";
            $data .= 'Content-Transfer-Encoding: binary' . "\r\n";
            $data .= 'MIME-Version: 1.0' . "\r\n";
            $data .= 'Content-ID: '. $post_id . '-post' . "\r\n\r\n";

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

        // Make CURL response
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
                    if( preg_match('/response-(.*?)-post/', $r_data, $post_id ) == 1 ) {
                        $event->post_id = end($post_id);
                    }

                    if( isset( $event->id ) && isset( $event->post_id ) ) {
                        update_post_meta( $event->post_id, 'ga_appointment_gcal_calendar_id', $calendar_id );
                        update_post_meta( $event->post_id, 'ga_appointment_gcal_id', $event->id );
                        update_post_meta( $event->post_id, 'ga_appointment_gcal_last_updated', $event->updated);
                        update_post_meta( $event->post_id, 'ga_appointment_gcal_provider', $this->provider_id );
                    }
                }
            }
        }
        return $response;
    }

    /**
     * Delete event from Google Calendar
     *
     */
    public function delete_event() {

        // get event ID from appointment settings
        $event_id     = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal_id', true );
        // get calendar ID from appointment settings
        $calendar_id  = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal_calendar_id', true );

        if( !empty( $calendar_id ) && !empty( $event_id ) ) {
            $url = self::CAL_BASE_URL . $calendar_id . '/events/' . $event_id;
            $this->make_request( $url, 'DELETE',  'json', array("access_token" => $this->api_config['access_token']) );
        }

        return true;
    }

    /**
     * Update event on Google Calendar
     */
    public function update_event()
    {
        $status      = (string) get_post_status( $this->post_id );
        if( $status === 'cancelled' ) {
            $this->delete_event();
            return false;
        }

        // Appointment created from the back-end (add new appointment feature)
        if ( $status === 'draft' ) {
            return false;
        }

        $location    = isset( $this->options['location'] ) ? esc_html( $this->options['location'] )  : '';
        $time_zone   = ga_time_zone();
        // get event ID from appointment settings
        $event_id    = (string) get_post_meta( $this->post_id, 'ga_appointment_gcal_id', true );
        // get calendar ID from provider settings
        $calendar_id = $this->get_calendar_id();
        if( $calendar_id === 'primary' ) {
            return false;
        }

        if( !empty( $calendar_id ) && !empty( $event_id ) ) {
            if( is_array( $date_params = $this->set_date_params( $this->post_id ) ) ) {
                list( $start_date, $end_date ) = $date_params;
            } else {
                return false;
            }

            $service_id    = (int) get_post_meta( $this->post_id, 'ga_appointment_service', true );
            $gcal_service_id = get_default_sync_service();

            $data = array(
                "access_token" => $this->api_config['access_token'],
                "kind"         => "calendar#event",
                "status"       => "tentative",
                "location"     => $location,
                "summary"      => $service_id === $gcal_service_id ? 'Event synced with gAppointments' : $this->get_event_title( $this->post_id ),
                "description"  => $this->get_event_description( $this->post_id ),
                "start"        => array( "dateTime" => $start_date, "timeZone" => $time_zone ),
                "end"          => array( "dateTime" => $end_date, "timeZone" => $time_zone ),
                "colorId"      => $this->event_color( $status ),
            );

            // Add attendee
            $data['attendees'] = $this->add_attendee( $this->post_id );

            $url = self::CAL_BASE_URL . $calendar_id .'/events/' . $event_id;
            $event = $this->make_request( $url, 'PUT', 'json', $data );

            if( is_object( $event ) && isset( $event->id ) ) {
                update_post_meta( $this->post_id, 'ga_appointment_gcal_last_updated', $event->updated );
            }

            // Appointment provider was switched; event has been created in the past, but it does not exist in provider's calendar
            if( is_object( $event ) && isset( $event->error->code ) && $event->error->code === 404 ) {
                $this->create_event();
            }

        } else if( !empty( $calendar_id ) && empty( $event_id ) ) {
            // New appointment or appointment exists, but it was never synced to Google Calendar
            $this->create_event();
        }

        return true;
    }

    /**
     * Execute two way synchronization
     */
    public function sync_events() {

        if( !$this->is_two_way_sync() ) {
            return false;
        }

        $calendar_id  = $this->get_calendar_id();
        if( $calendar_id === 'primary' ) {
            return false;
        }

        $events_new  = $this->get_events( $calendar_id );

        if( isset( $events_new ) && !empty( $events_new ) ) {
            wp_defer_term_counting(true);
            wp_defer_comment_counting(true);

            $events_old = $this->get_events_by_calendar_id( $calendar_id );
            $events_to_update = $this->event_difference( $events_new, $events_old );

            // Prepare default two-way syncronization service
            $gcal_service_id = get_default_sync_service();
            $gcal_service_id = is_null($gcal_service_id) ? $this->create_default_sync_service() : $gcal_service_id;

            foreach( $events_to_update as $event ) {

                // Get service ID by event's summary
                if( isset( $event->summary ) && preg_match('/\\[(.*?)\\]/', $event->summary, $match) ) {
                    if( $page = get_page_by_title( $match[1], OBJECT, 'ga_services' ) ) {
                        $service_id = $page->ID;
                    }
                }

                // Set default two-way syncronization service as service ID
                if ( !isset( $service_id ) ) {
                    $service_id = $gcal_service_id;
                }

                // Delete syncronization event if it was cancelled
                if ( isset( $event->status, $event->post_id ) && $event->status === 'cancelled' && $service_id === $this->get_service_id( $event->post_id ) ) {
                    wp_delete_post( $event->post_id, true );
                    continue;
                }

                if( isset( $event->start->dateTime ) && isset( $event->end->dateTime ) ) {

                    // Start Date/Time
                    $startTime  = new DateTime( $event->start->dateTime, new DateTimeZone( ga_time_zone() ) );

                    if( isset( $event->start->timeZone ) ) {
                        $startTime = new DateTime($event->start->dateTime, new DateTimeZone($event->start->timeZone));
                        $startTime->setTimeZone(new DateTimeZone(ga_time_zone()));
                    }

                    $start_date = $startTime->format('Y-m-j');
                    $time       = $startTime->format('H:i');

                    // End Date/Time
					$endTime    = new DateTime( $event->end->dateTime, new DateTimeZone( ga_time_zone() ) );

                    if( isset( $event->end->timeZone ) ) {
                        $endTime  = new DateTime( $event->end->dateTime, new DateTimeZone( $event->end->timeZone ) );
                        $endTime->setTimeZone(new DateTimeZone( ga_time_zone() ));
                    }

                    $end_date   = $endTime->format('Y-m-j');
                    $time_end   = $endTime->format('H:i');

                    $app_type   = 'time_slot';
                    $duration   = $this->get_slot_duration( $startTime, $endTime );

                } elseif( isset( $event->start->date ) && isset( $event->end->date ) ) {

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

                // Prepare post for update
                if( isset( $event->post_id ) ) {
                    $ga_appointment['ID'] = $event->post_id;
                }

                $postID = wp_insert_post( $ga_appointment );
                if( !empty( $postID ) ) {
                    update_post_meta($postID, 'ga_appointment_type', $app_type);
                    update_post_meta($postID, 'ga_appointment_duration', $duration); // Duration
                    update_post_meta($postID, 'ga_appointment_service', $service_id); // Service
                    update_post_meta($postID, 'ga_appointment_date', $start_date); //	Date
                    update_post_meta($postID, 'ga_appointment_time', $time); //	Time
                    update_post_meta($postID, 'ga_appointment_time_end', $time_end); //	Time
                    update_post_meta($postID, 'ga_appointment_gcal_calendar_id', $calendar_id);
                    update_post_meta($postID, 'ga_appointment_gcal_id', $event->id);
                    update_post_meta($postID, 'ga_appointment_gcal_last_updated', $event->updated);
                    update_post_meta($postID, 'ga_appointment_gcal_provider', $this->provider_id);
                    if( $service_id === $gcal_service_id ) {
                        $providers = $this->get_provider_ids( $calendar_id );
                        if ( !empty( $providers ) ) {
                            // Assign all Google Calendar two-way sync events to first provider (if more than one provider has the same calendar id)
                            update_post_meta($postID, 'ga_appointment_provider', $providers[0]['provider']); // Provider
                        } else {
                            update_post_meta($postID, 'ga_appointment_provider', $this->provider_id); // Provider
                        }
                    } else {
                        update_post_meta($postID, 'ga_appointment_provider', $this->provider_id); // Provider
                    }
                }
            }
            wp_defer_term_counting(false);
            wp_defer_comment_counting(false);
        }

        return true;
    }

    /**
     * Retrieve stored events by calendar id from database
     *
     * @param int $calendar_id
     * @return array|object|null an array containing all
     * event data that were stored in the database
     */
    public function get_events_by_calendar_id( $calendar_id ) {
        global $wpdb;

        return $wpdb->get_results("
            SELECT gcal_id.post_id, 
                   gcal_id.meta_value as event_id,
                   gcal_last_updated.meta_value as event_last_updated
            FROM   
                   {$wpdb->postmeta} AS gcal_id
            INNER JOIN 
                   {$wpdb->postmeta} AS gcal_last_updated 
                   ON gcal_last_updated.post_id = gcal_id.post_id 
            INNER JOIN 
                   {$wpdb->postmeta} AS gcal_calendar_id 
                   ON gcal_calendar_id.post_id = gcal_id.post_id 
            WHERE gcal_id.meta_key = 'ga_appointment_gcal_id'
            AND   gcal_last_updated.meta_key = 'ga_appointment_gcal_last_updated'
            AND   gcal_calendar_id.meta_key = 'ga_appointment_gcal_calendar_id'
            AND   gcal_calendar_id.meta_value = '{$calendar_id}'
        ");
    }

    /**
     * Computes the difference of event arrays
     *
     * @param array|object $new_events
     * The array to compare from
     * @param array|object $old_events
     * An array to compare against
     * @return array an array containing all entries from
     * new events that were updated or are not present in the old events array.
     */
    public function event_difference( $new_events, $old_events ) {
        $map = $difference = array();

        // Create map of new sync events
        foreach( $new_events as $new_event ) {
            $map[$new_event->id] = $new_event;
        }

        foreach( $old_events as $old_event ) {
            $map_key = $old_event->event_id;

            if( isset( $map[$map_key] ) ) {
                $last_updated_old = date(DateTime::RFC3339_EXTENDED, strtotime($old_event->event_last_updated));
                $last_updated_new = date(DateTime::RFC3339_EXTENDED, strtotime($map[$map_key]->updated));

                if( $last_updated_new === $last_updated_old ) {
                    // Event already exists in gAppointments.
                    $map[$map_key] = 0;
                } else {
                    // Event was updated or deleted. Append stdClass object with id of post.
                    $map[$map_key] = (object) array_merge( (array) $map[$map_key], array( 'post_id' => $old_event->post_id ) );
                }
            }
        }

        foreach( $map as $event_id => $event ) {
            if( isset( $event->status) && $event->status === 'cancelled' && !isset( $event->post_id ) ) {
                // Event does not exists and/or already deleted from gAppointments.
                continue;
            } else {
                // Event was created. It will be added to gAppointments.
            }

            if( !is_int( $event ) ) $difference[] = $event;
        }

        return $difference;
    }

    /**
     * Create default two way sync service
     *
     * @return int The post ID on success. The value 0 on failure.
     */
    public function create_default_sync_service() {
        $sync_service = array(
            'post_title'    => 'two_way_sync',
            'post_status'   => 'publish',
            'post_type'     => 'ga_services',
        );

        return wp_insert_post( $sync_service );
    }

    public function get_calendar_id() {
        // Get calendar ID
        return isset($this->options['calendar_id']) && !empty($this->options['calendar_id']) ? $this->options['calendar_id'] : 'primary';
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


    public function event_color( $status = '' ) {
        if( empty($status) ) {
            $status = $this->appointment_status();
        }
        $confirmed_color = isset( $this->options['confirmed_color'] ) ? $this->options['confirmed_color'] : '2';
        $pending_color = isset( $this->options['pending_color'] ) ? $this->options['pending_color'] : '6';

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

        $summary = isset( $this->options['summary'] ) ? $this->options['summary'] : '[{service_name}] with {client_name}';

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

        return isset( $new_client['phone'] ) && !empty( $new_client['phone'] ) ? $new_client['phone'] : '';
    }

    private function get_provider_name($post_id) {
        $provider_id = (int) get_post_meta( $post_id, 'ga_appointment_provider', true );

        return 'ga_providers' == get_post_type($provider_id) ? esc_html( get_the_title( $provider_id ) ) : 'No provider';
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
        $form_lang = get_form_translations( null, null, $post_id );

        // Translation Support
        if( $available_times_mode == 'no_slots' )  {
            if( $date ) {
                $month = $date->format('F');
                $day   = $date->format('j');
                $year  = $date->format('Y');
                $appointment_date = ga_get_form_translated_slots_date($form_lang, $month, $day, $year);
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

                $appointment_date = ga_get_form_translated_date_time($form_lang, $month, $week, $day, $year, $_time);
            } else {
                $appointment_date = "{$app_date_text} at {$app_time_text}";;
            }
        }

        return $appointment_date;
    }

    /**
     * Get upper bound for an event's start time to filter by
     *
     * @return string max bound value in string format
     */
    public function get_max_bound() {
        $default_bound = array( 1, 'month' );
        $time_max_number   = $this->options['time_max_number']   ?? $default_bound[0];
        $time_max_selector = $this->options['time_max_selector'] ?? $default_bound[1];
        $time_max_number = absint( $time_max_number );

        if( $time_max_number === 0 ) {
            list( $time_max_number, $time_max_selector ) = $default_bound;
        }

        return "+{$time_max_number} {$time_max_selector}";
    }

    /**
     * Return default options for two-way sync
     *
     * @param $time_min
     * @param $time_max
     * @return array
     */
    private function get_sync_options( $time_min, $time_max ) {
        return array(
            'maxResults' => '2500',
            'singleEvents' => 'true',
            'showDeleted' => 'true',
            'timeMin' => $time_min,
            'timeMax' => $time_max,
            'timeZone' => ga_time_zone()
        );
    }

    /**
     * Check if synchronization mode is set to two way front
     *
     * @return bool
     */
    public function is_two_way_sync() {
        if( isset( $this->options['sync_mode'] ) )
            return $this->options['sync_mode'] === 'two_way_front';
        else
            return false;
    }

    /**
     * Get synchronization on/off state
     *
     * @return bool
     */
    public function is_sync_enabled() {
        if( isset( $this->options['api_sync'] ) )
            return $this->options['api_sync'] === 'yes';
        else
            return false;
    }

    public function add_attendee( $post_id ) {
        $add_attendee = isset( $this->options['attendee'] ) ? $this->options['attendee'] : 'no';
        if( $add_attendee == 'yes' ) {
            $email = $this->get_client_email( $post_id );
            if( filter_var($email, FILTER_VALIDATE_EMAIL) ) {
                $name  = $this->get_client_name($post_id);
                return array( array( "displayName" => $name, "email" => $email ) );
            }
        }

        return '';
    }

    /**
     * Set and return date and time parameters of an appointment.
     *
     * @param $post_id
     * @return array|bool return date parameters if date is of valid format, false otherwise
     */
    private function set_date_params( $post_id ) {
        $post_meta  = get_post_meta( $post_id );
        $date       = $post_meta['ga_appointment_date'][0];
        $time       = $post_meta['ga_appointment_time'][0];
        $time_end   = $post_meta['ga_appointment_time_end'][0];

        if( ga_valid_date_format( $date ) ) {
            $start_date = $date;
            $end_date   = $date;
            if( ga_valid_time_format( $time ) && ga_valid_time_format( $time_end ) ) {
                $start_date .= "T{$time}:00";
                $end_date   .= "T{$time_end}:00";
            }
            return [$start_date, $end_date];
        } else {
            return false;
        }
    }

    /**
     * Get unique list of provider ids that have the same Google Calendar ID
     *
     * @param $calendar_id
     * @return array|object|null
     */
    private function get_provider_ids( $calendar_id ) {
        global $wpdb;
        $providers_query = "
            SELECT p.ID,
            provider.meta_value AS provider
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta AS gcal_calendar on gcal_calendar.post_id = p.ID
            LEFT JOIN $wpdb->postmeta AS provider on provider.post_id = p.ID
            WHERE p.post_type = 'ga_appointments'
            and p.post_status IN ('completed', 'publish', 'payment', 'pending', 'cancelled', 'private')
            and gcal_calendar.meta_key = 'ga_appointment_gcal_calendar_id'
            and provider.meta_key = 'ga_appointment_provider'
            and gcal_calendar.meta_value = '{$calendar_id}'
            group by provider
            order by provider ASC;
        ";
        return $wpdb->get_results( $providers_query, ARRAY_A );
    }
} // end class