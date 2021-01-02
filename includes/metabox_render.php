<?php 
/**
 * Includes Cmb2 Custom Html MetaBoxes Functions
 * 
 */

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

/**
 * Provider Work Schedule Markup
 */
function get_ga_provider_work_schedule_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->value();
	$description = $field->args( 'description' );
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td">
			<div class="cmb2-metabox-description"><?php echo $description; ?></div>
			<?php 
				$schedule = new ga_work_schedule( $field->object_id );
				echo $schedule->display_schedule( $name, $value );
			?>
		</div>
	</div>	
	
	<?php	
}

/**
 * Provider Breaks Markup
 * Needed for saving options
 */
function get_ga_provider_breaks_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->value();
	$description = $field->args( 'description' );
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td">
			<div class="cmb2-metabox-description"><?php echo $description; ?></div>
			<?php 
				$schedule = new ga_work_schedule( $field->object_id );
				echo $schedule->display_breaks( $name, $value );
			?>
		</div>
	</div>	
	
	<?php	
}

/**
 * Provider Holidays Markup
 */
function get_ga_provider_holidays_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->value();
	$description = $field->args( 'description' );
	
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td">
			<div class="cmb2-metabox-description"><?php echo $description; ?></div>	
			
			<?php
				if( !class_exists('ga_work_schedule') ) {
					require_once( ga_base_path . '/admin/includes/ga_work_schedule.php' );
				}				
				$schedule = new ga_work_schedule( $field->object_id );
				echo $schedule->display_holidays($name, $value); 
			?>
		</div>
	</div>	
	
	<?php	
}

/**
 * Provider appointment availability markup
 */
function get_ga_provider_appointment_availability_row( $field_args, $field ) {
    $id          = $field->args( 'id' );
    $label       = $field->args( 'name' );
    $name        = $field->args( '_name' );
    $value       = $field->escaped_value();
    $description = $field->args( 'description' );

    $availability = !empty( $value ) ? $value : 'non-global';

    ?>

    <div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

        <div class="cmb-th">
            <label>Availability</label>
        </div>

        <div class="cmb-td">
            <select class="cmb2_select" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
                <option value="global" <?php selected( 'global', $availability ); ?>>Global appointment availability</option>
                <option value="non-global" <?php selected( 'non-global', $availability ); ?>>Service-based appointment availability</option>
            </select>
            <p class="cmb2-metabox-description"><?php echo $description; ?></p>
        </div>
    </div>

    <?php
}


/**
 * Provider Calendar Schedule Markup
 */
function get_ga_provider_calendar_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->escaped_value();
	$description = $field->args( 'description' );
	
    $checked = isset( $value ) && $value == 'on' ? ' checked' : '';
	
	//print_r( $value );
	
	?>

	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
	
		<div class="cmb-th">
			<label><?php echo $label; ?></label>			
		</div>	
	
		<div class="cmb-td">
			<div class="ga_radio_switch">
				<label><input type="radio" class="cmb2-option <?php echo $id; ?>" name="<?php echo $name; ?>" value="on" <?php echo checked( 'on', $value ); ?>><span class="ga-small-text">Yes</span></label>
				<label><input type="radio" class="cmb2-option <?php echo $id; ?>" name="<?php echo $name; ?>" value="" <?php echo checked( '', $value ); ?>><span class="ga-small-text">No</span></label>
			</div>	
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
	</div>	
	
	<?php
}



/**
 * Provider Gcal Sync Markup
 */
function ga_provider_gcal_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->escaped_value();
	$description = $field->args( 'description' );	
	?>
	
	<!-- Enable API Sync -->
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

		<div class="cmb-th">
			<label>Enable API Sync</label>
		</div>	
	
		<div class="cmb-td">
			<?php
				$api_sync = isset( $value['api_sync'] ) ? $value['api_sync'] : 'no';
			?>		
			<select class="cmb2_select" id="api_sync" name="<?php echo $id; ?>[api_sync]">
				<option value="yes"<?php selected( 'yes', $api_sync ); ?>>Yes</option>
				<option value="no"<?php selected( 'no', $api_sync ); ?>>No</option>
			</select>
			<p class="cmb2-metabox-description">Enable api synchronization for this provider</p>
		</div>
	</div>


    <h3 class="ga-title">Client authentication</h3>

    <!-- Client ID -->
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
		<div class="cmb-th">
			<label>Client ID</label>
		</div>	
	
		<div class="cmb-td">
			<?php
				$client_id = isset( $value['client_id'] ) ? $value['client_id'] : '';
				$readonly  = !empty($client_id) ? 'readonly="readonly"' : '';
			?>				
			<input type="text" class="large-text" id="client_id" name="<?php echo $id; ?>[client_id]" value="<?php echo esc_html($client_id); ?>" <?php echo $readonly; ?>>
			<p class="cmb2-metabox-description">The client ID obtained from the Developers Console</p>
		</div>
		
	</div>

	<!-- Client Secret -->
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

		<div class="cmb-th">
			<label>Client Secret</label>
		</div>	
	
		<div class="cmb-td">
			<?php
				$client_secret = isset( $value['client_secret'] ) ? $value['client_secret'] : '';
				$readonly  = !empty($client_secret) ? 'readonly="readonly"' : '';				
			?>				
			<input type="text" class="large-text" id="client_secret" name="<?php echo $id; ?>[client_secret]" value="<?php echo esc_html($client_secret); ?>" <?php echo $readonly; ?>>
			<p class="cmb2-metabox-description">The client secret obtained from the Developers Console</p>
		</div>
	</div>


    <h3 class="ga-title">Authorize access</h3>
    <p>You can generate an access code after you set the Client ID.</p>
    <div class="ga_generate_btn"><a href="" class="button-secondary" id="access_link" target="_blank">Generate access code</a></div>
    <script src="<?php echo GA_PATH_URL . '/assets/access_link.js'; ?>"></script>

    <!-- Access Code -->
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

		<div class="cmb-th">
			<label>Access Code</label>
		</div>	
	
		<div class="cmb-td">
			<?php
				$access_code = isset( $value['access_code'] ) ? $value['access_code'] : '';
				$readonly  = !empty($access_code) ? 'readonly="readonly"' : '';	
			?>				
			<input type="text" class="large-text" id="access_code" name="<?php echo $id; ?>[access_code]" value="<?php echo esc_html($access_code); ?>" <?php echo $readonly; ?>>
			<p class="cmb2-metabox-description">The access code obtained from the consent screen</p>
		
			<?php
				if( get_option( 'ga_appointments_gcal_debug' ) == 'enabled' ) { ?>
					<div class="ga_access_tokens">
						<?php
							$token = (array) get_post_meta( $field->object_id, 'ga_provider_gcal_token', true );
							echo 'Access token: ', isset($token['access_token']) ? $token['access_token'] : 'not set';
							echo '<br>';
							echo 'Refresh token: ', isset($token['refresh_token']) ? $token['refresh_token'] : 'not set';
						?>
					</div>
			<?php } ?>		
		</div>
	</div>	
	
	<!-- Calendar ID -->
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

		<div class="cmb-th">
			<label>Calendar ID</label>
		</div>	
	
		<div class="cmb-td">
				<?php
					$calendar_id = isset( $value['calendar_id'] ) ? $value['calendar_id'] : '';
					$sync = new ga_gcal_sync( $post_id = 0, $field->object_id );
				?>
				<select class="cmb2_select" name="<?php echo $id; ?>[calendar_id]" id="calendar_id">
					<?php 
						$calendars = $sync->get_calendars();
						if( isset($calendars->items) ) {
							foreach( $calendars->items as $calendar ) {
								if( $calendar->accessRole == 'owner' ) {
									$primary = isset($calendar->primary) && $calendar->primary == '1' ? ' (primary)' : '';
									$selected = selected( $calendar->id, $calendar_id, false );
									echo '<option value="'. $calendar->id .'"'.$selected.'>'. $calendar->summary . $primary . '</option>';									
								}
							}
						}
					?>
				</select>	
			<p class="cmb2-metabox-description">Select calendar to sync.</p>
		</div>
	</div>


    <h3 class="ga-title">Settings</h3>

    <!-- Location input -->
    <div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
        <?php
        $location = isset( $value['location'] ) ? $value['location'] : '';
        ?>
        <div class="cmb-th">
            <label>Location</label>
        </div>

        <div class="cmb-td">
            <input type="text" class="large-text" id="location" name="<?php echo $id; ?>[location]" value = "<?php echo esc_html($location); ?>"/>
            <p class="cmb2-metabox-description">If left empty, value will be taken from general settings.</p>
        </div>
    </div>

    <!-- Provider's synchronization mode -->
    <div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">

        <?php
            $sync_mode = isset($value['sync_mode']) ? $value['sync_mode'] : 'one_way';
        ?>
		<div class="cmb-th">
			<label>Synchronization mode</label>
		</div>

		<div class="cmb-td">
            <select class="form-control" name="<?php echo $id; ?>[sync_mode]" id="sync_mode">
                <option value="one_way" <?php selected('one_way', $sync_mode); ?>>One-way sync</option>
                <option value="two_way_front" <?php selected('two_way_front', $sync_mode); ?>>Two-way front-end</option>
            </select>
            <p class="cmb2-metabox-description">
                1. One-way sync pushes new appointments and any further changes to Google Calendar.<br>
                2. Two-way front-end sync will fetch events from Google Calendar and remove corresponding time slots before displaying them in the calendar availability (this will lead to form loading delay).
            </p>
		</div>
	</div>

    <!-- Provider's synchronization max bound -->
    <div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>" id="two_way_sync_time_max" style="display: none;">
        <div class="cmb-th">
            <label for="time_max_number">Max bound</label>
        </div>
        <div class="cmb-td">
            <?php
            $time_max_number   = $value['time_max_number']   ?? 1;
            $time_max_selector = $value['time_max_selector'] ?? 'month';
            ?>
            <input  id="time_max_number"   class="form-control" name="<?php echo $id; ?>[time_max_number]"   style="height: 30px;" type="number" min="1" max="99" value="<?php echo absint($time_max_number); ?>">
            <select id="time_max_selector" class="form-control" name="<?php echo $id; ?>[time_max_selector]" style="margin: 0 0 0 0">
                <option value="day"   <?php selected('day', $time_max_selector);   ?> >Days</option>
                <option value="week"  <?php selected('week', $time_max_selector);  ?> >Weeks</option>
                <option value="month" <?php selected('month', $time_max_selector); ?> >Months</option>
                <option value="year"  <?php selected('year', $time_max_selector);  ?> >Years</option>
            </select>
            <p class="cmb2-metabox-description">Define upper bound for how far into future events should be fetched from Google Calendar to gAppointments. (By default, events are fetched from today to the next month)</p>
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
        </div>
    </div>

	<div class="cmb-row">
		<input type="submit" class="button-secondary" name="<?php echo $id; ?>[reset_api]" id="reset_api" value="Reset API Credentials">
		<script>
			jQuery('body').on('click', '#reset_api', function() {
				return confirm('Reset API Credentials?');
			});			
		</script>
	</div>
	
<?php }	



/**
 * Validation: Appointment New Customer
 */
function get_ga_appointment_new_client_render_row($field_args, $field) {
	$id            = $field->args( 'id' );
	$label         = $field->args( 'name' );
	$name          = $field->args( '_name' );
	$value         = $field->escaped_value();
	$description   = $field->args( 'description' );
	$before_row	   = $field->args( 'before_row' );
	
	
	$client_name    = isset($value['name'])  ? $value['name']  : '';
	$client_email   = isset($value['email']) ? $value['email'] : '';	
	$client_phone   = isset($value['phone']) ? $value['phone'] : '';
	?>
	
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
		<?php if ( !empty($before_row) ) {
			$before_row = $field->args( 'before_row' );
			echo '<h5>'.esc_html($before_row).'</h5>';
		} ?>
		
		
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
	
		<div class="cmb-td">
			<div><input type="text" class="regular-text" name="<?php echo $name; ?>[name]" id="<?php echo $id; ?>" value="<?php echo $client_name; ?>" placeholder="Full Name"></div>
			<div><input type="text" class="regular-text" name="<?php echo $name; ?>[email]" id="<?php echo $id; ?>" value="<?php echo $client_email; ?>" placeholder="Email"></div>
			<div><input type="text" class="regular-text" name="<?php echo $name; ?>[phone]" id="<?php echo $id; ?>" value="<?php echo $client_phone; ?>" placeholder="Phone"></div>		
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
		
	</div>		
	
<?php }


/**
 * Services Available Appointment Times Markup
 */
function get_ga_service_times_mode_render_row( $field_args, $field ) {
	$id            = $field->args( 'id' );
	$label         = $field->args( 'name' );
	$name          = $field->args( '_name' );
	$value         = $field->escaped_value();
	$description   = $field->args( 'description' );
	$before_row	   = $field->args( 'before_row' ); 
	?>

	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?>">
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>
		<div class="cmb-td">
			<div class="ga_radio_switch">
				<label><input type="radio" class="cmb2-option <?php echo $id; ?>" name="<?php echo $name; ?>" value="interval" <?php echo checked( 'interval', $value ); ?>><span class="ga-large-text">Interval</label>
				<label><input type="radio" class="cmb2-option <?php echo $id; ?>" name="<?php echo $name; ?>" value="custom" <?php echo checked( 'custom', $value ); ?>><span class="ga-large-text">Custom</span></label>
				<label><input type="radio" class="cmb2-option <?php echo $id; ?>" name="<?php echo $name; ?>" value="no_slots" <?php echo checked( 'no_slots', $value ); ?>><span class="ga-large-text">Dates</span></label>
			</div>
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
	</div>	
	<?php 
}


function get_ga_service_category_render_row( $field_args, $field ) {
	$id            = $field->args( 'id' );
	$label         = $field->args( 'name' );
	$name          = $field->args( '_name' );
	$value         = $field->escaped_value();
	$description   = $field->args( 'description' );
	$before_row	   = $field->args( 'before_row' ); 
	?>

	<div class="custom-field-row cmb2-id-<?php echo $id; ?>">
		<p style="margin-top: 0px;"><?php echo $description; ?></p>	
		<div id="taxonomy-ga_service_cat" class="categorydiv">
			<div class="ga-service-tabs-panel">
			
				<!-- Need to if all categories are unchecked -->
				<input type="hidden" name="tax_input[ga_service_cat][]" value="0">
				<!-- Need to if all categories are unchecked -->
				
				<ul id="ga_service_catchecklist" data-wp-lists="list:ga_service_cat" class="categorychecklist form-no-clear">
					<?php 
						$services_cats   = get_terms( 'ga_service_cat', array( 'parent' => '',  'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
						$categories      = '';						
						$sel_cats        = wp_get_post_terms( $field->object_id, 'ga_service_cat', array('orderby' => 'name', 'order' => 'ASC', 'fields' => 'ids') );

						if( $services_cats ) {
							foreach ( $services_cats as $cat ) {
								$checked     =  in_array($cat->term_id, $sel_cats) ? ' checked="checked"' : '';
								$term_id     = $cat->term_id;
								$name        = $cat->name;
								$categories .= '<li><input type="checkbox" name="tax_input[ga_service_cat][]" id="ga_service_cat-' .$term_id. '" value="' .$term_id. '"'.$checked.'> <label class="selectit" for="ga_service_cat-' .$term_id. '">'. $name .'</label> <span class="ga_service_cat_delete" term-id="' .$term_id .'">Delete</span></li>';
							}							
						}
						echo $categories;
					
					?>
				</ul>	
			</div>
			
			<div id="ga_service_cat-adder">
				<div id="ga_service_cat-add">			
					<input type="text" name="newga_service_cat" id="newga_service_cat" class="form-required form-input-tip" value="" placeholder="New Category Name" aria-required="true">
					<input type="button" id="ga_service_cat-add-submit" data-wp-lists="add:ga_service_catchecklist:ga_service_cat-add" class="button button-ga" value="Add New Category">
					<?php wp_nonce_field( 'add-ga_service_cat', '_ajax_nonce-add-ga_service_cat', false ); ?>					
					<span id="ga_service_cat-ajax-response"></span>
				</div>
			</div>
		</div>
	</div>	
	<?php 	
}



/**
 * Services Available Appointment Times Markup
 */
function get_ga_provider_services_render_row( $field_args, $field ) {
	$id            = $field->args( 'id' );
	$label         = $field->args( 'name' );
	$name          = $field->args( '_name' );
	$value         = $field->escaped_value();
	$description   = $field->args( 'description' );
	$before_row	   = $field->args( 'before_row' ); 


	$provider_categories = (array) get_post_meta( $field->object_id, 'ga_provider_services', true );

	?>

	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id; ?> cmb-inline">
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
	
		<div class="cmb-td">
			<div id="ga_time_slots_services" class="ga_mb_10">	
				<?php
					$interval = ga_get_services_type_ids('interval');
					$custom   = ga_get_services_type_ids('custom');
					$services = array_unique( array_merge( $interval, $custom ) );
					if( $services ) {
						echo '<div class="ga_services_type_title">TIME SLOTS</div>';
						echo '<ul class="cmb2-checkbox-list no-select-all cmb2-list">';							
						foreach( $services as $service ) {
							$checked =  in_array($service, $provider_categories) ? ' checked="checked"' : '';
							echo '<li><label class="ga_provider_service_slots"><input type="checkbox" class="cmb2-option ga_provider_service_type" name="ga_provider_services[]" value="'.$service.'"'.$checked.'> '.get_the_title($service).'</label></li>';
						}
						echo '</ul>';
					}
				?>
			</div>

			<div id="ga_dates_services">
				<?php
					$services = ga_get_services_type_ids('no_slots');
					if( $services ) {						
						echo '<div class="ga_services_type_title">FULL DATES</div>';
						echo '<ul class="cmb2-checkbox-list no-select-all cmb2-list">';							
						foreach( $services as $service ) {
							$checked =  in_array($service, $provider_categories) ? ' checked="checked"' : '';
							echo '<li><label class="ga_provider_service_dates"><input type="checkbox" class="cmb2-option ga_provider_service_type" name="ga_provider_services[]" value="'.$service.'"'.$checked.'> '.get_the_title($service).'</label></li>';
						}
						echo '</ul>';
					}
				?>

			</div>

			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
	</div>	
	<?php 
}



/**
 * Service Custom Days Period
 */
function get_ga_service_custom_dates_period_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->value();
	$description = $field->args( 'description' );
	
	$period_type        = (string) get_post_meta( $field->object_id, 'ga_service_period_type', true );
	$class              = $period_type == 'custom_dates' ? '' : ' cmb2-hidden';
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id . $class; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td custom_dates_period">
			<div id="custom_dates_period">
				<div class="custom-date" style="display: none;">
					<input type="text" class="cmb2-text-small ga-date-picker" value="" name="<?php echo $name; ?>[]" placeholder="Select date">
					<span class="custom-date-delete"></span>				
				</div>
				
				<?php 
					$days = $value;
					if( $days ) {
						foreach( $days as $day ) { ?>

							<div class="custom-date">
								<input type="text" class="cmb2-text-small ga-date-picker" value="<?php echo $day; ?>" name="<?php echo $name; ?>[]" placeholder="Select date">
								<span class="custom-date-delete"></span>
							</div>	
						
					<?php
						}					
					} ?>

							
			</div>
			
			<span class="ga_add_custom_date button button-ga">Add date</span>		
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>
		</div>
	</div>	
	
	<?php	
}


/**
 * Service Date Range Period
 */
function get_ga_service_date_range_period_render_row( $field_args, $field ) {
	$id          = $field->args( 'id' );
	$label       = $field->args( 'name' );
	$name        = $field->args( '_name' );
	$value       = $field->value();
	$description = $field->args( 'description' );
	
	$range_from  = isset( $value['from'] ) ? $value['from'] : '';
	$range_to    = isset( $value['to'] ) ? $value['to'] : '';

	$period_type        = (string) get_post_meta( $field->object_id, 'ga_service_period_type', true );
	$class              = $period_type == 'date_range' ? '' : ' cmb2-hidden';
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id . $class; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td">
			<div class="ga_date_range_period">
				<input type="text" class="cmb2-text-small ga-date-picker" value="<?php echo $range_from; ?>" name="<?php echo $name; ?>[from]" placeholder="From">
				<span>till</span>
				<input type="text" class="cmb2-text-small ga-date-picker" style="margin-left:2px;" value="<?php echo $range_to; ?>" name="<?php echo $name; ?>[to]" placeholder="To">
			</div>	
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
	</div>	
	
	<?php	
}


/**
 * Service Custom Time Slots
 */
function get_ga_service_custom_slots_render_row( $field_args, $field ) {
	$id            = $field->args( 'id' );
	$label         = $field->args( 'name' );
	$name          = $field->args( '_name' );
	$value         = $field->value();
	$description   = $field->args( 'description' );
	$service_mode  = (string) get_post_meta($field->object_id, 'ga_service_available_times_mode', true);;
	$class         = $service_mode == 'custom' ? '' : ' cmb2-hidden';
	
	?>
	<div class="cmb-row custom-field-row cmb2-id-<?php echo $id . $class; ?>">
	
		<div class="cmb-th">
			<label for="<?php echo $id; ?>"><?php echo $label; ?></label>
		</div>	
	
		<div class="cmb-td">
			<table id="ga_custom_slots">
				<thead>
					<tr>
						<th>Start Time</th>
						<th>End Time</th>
						<th>Availability</th>	
						<th>Capacity</th>								
						<th>Price</th>		
						<th></th>							
					</tr>
				</thead>	
				<tbody>						
					<?php 
						if( is_array($value) && count( $value ) > 0 ) { 
							foreach( $value as $id => $data ) { ?>
								<tr>
									<td>
										<select name="<?php echo "{$name}[{$id}][start]"; ?>">
											<?php 
												foreach( get_ga_appointment_time() as $time => $text ) {
													$selected = selected( $data['start'], $time, false);
													echo "<option value='{$time}' {$selected}>{$text}</option>"; 
												} 
											?>
										</select>
									</td>
									
									<td>
										<select name="<?php echo "{$name}[{$id}][end]"; ?>">
											<?php 
												foreach( get_ga_appointment_time($out = false, $_24 = true) as $time => $text ) {
													$selected = selected( $data['end'], $time, false);
													echo "<option value='{$time}' {$selected}>{$text}</option>"; 
												} 
											?>
										</select>
									</td>
									
									<td>
										<?php 
											foreach( array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $week ) { 
												$week_short = substr( ucfirst($week), 0, 1 );
												$checked    = isset( $data['availability'] ) && in_array($week, $data['availability']) ? 'checked="checked"' : '';
												echo "<div class='ga_week_day'>
														<label>
															<input type='checkbox' name='{$name}[{$id}][availability][]' value='{$week}' {$checked}>
															<span>{$week_short}</span>
														</label>
													</div>"; 
											} 
										?>
									</td>								
									
									<td>
										<select name="<?php echo "{$name}[{$id}][capacity]"; ?>">
											<?php 
												foreach( ga_services_capacity_options() as $num => $text ) {
													$selected = selected( $data['capacity'], $num, false);
													echo "<option value='{$num}' {$selected}>{$text}</option>"; 
												} 
											?>
										</select>
									</td>
									
									<td>
										<?php echo gf_get_currency_symbol(); ?> <input type="text" class="cmb2-text-small" name="<?php echo "{$name}[{$id}][price]"; ?>" id="" value="<?php echo $data['price']; ?>">
									</td>
									
									<td>
										<div class="slot-delete">Delete</div>
									</td>						
								</tr>								
					<?php  	} 
						} ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="6"><div class="add-slot button button-ga">Add new</div></td>
					<tr>
				</tfoot>							
			</table>
			<p class="cmb2-metabox-description"><?php echo $description; ?></p>				
		</div>
	</div>	
	
	<?php	
}