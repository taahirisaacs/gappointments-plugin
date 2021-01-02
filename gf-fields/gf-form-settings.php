<?php 
defined( 'ABSPATH' ) or exit; // Exit if accessed directly

/**
 * Add gappointments custom menu item to the Form Settings page menu
 */
add_filter( 'gform_form_settings_menu', 'gappointments_form_menu_item' );
function gappointments_form_menu_item( $menu_items ) {
    $menu_items[] = array(
        'name' => 'gappointments_form_page',
        'label' => __( 'gAppointments' )
        );
 
    return $menu_items;
}
 
/**
 * Handle displaying content for our custom menu when selected
 */
add_action( 'gform_form_settings_page_gappointments_form_page', 'gappointments_form_page' );
function gappointments_form_page() {
    GFFormSettings::page_header(); 	
	
	$form_id = rgget( 'id' );
	save_gappointments_form_page($form_id);
	
	$form = GFAPI::get_form( $form_id );
    $form_lang = get_form_translations( $form );
?>
	
	<form action="" method="post" id="gappointments_form_page">
		<h3><span>gAppointments Settings</span></h3>
		<table class="form-table">	 
			<tr>
				<?php 
				$services_cats = get_terms( 'ga_service_cat', array( 'parent' => '',  'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );				
				$options       = '<option value="0">All services</option>';
				$value         = rgar($form, 'ga_service_category');
				
				foreach ( $services_cats as $cat ) {
					$options .= '<option value="' .$cat->slug. '" ' .selected($value, $cat->slug, false). '>'. $cat->name .'</option>';
				}
				?>			
				<th><label for="ga_service_category">Form booking category</label></th>
				<td>
					<select name="ga_service_category" id="ga_service_category" class="regular-text" >
						<?php echo $options; ?>
					</select>	
				</td>
			</tr>
		</table>

        <table class="form-table">
            <tr>
                <?php
                $value    = rgar($form, 'ga_form_review_page');
                $options  = '<option value="0" ' .selected(0, $value, false). '>No</option>';
                $options .= '<option value="1" ' .selected(1, $value, false). '>Yes</option>';
                ?>
                <th><label for="ga_form_review_page">Show form review page</label></th>
                <td>
                    <select name="ga_form_review_page" id="ga_form_review_page" class="regular-text" >
                        <?php echo $options; ?>
                    </select>
                    <p class="description">If enabled, review page is shown before the final form submission.</p>
                </td>
            </tr>
        </table>

		<h3 class="gform_ga_heading"><span>Form Translation</span></h3>
	
		<table class="form-table">		
			<tr>
				<th scope="row">
					<label for="clear_time">Calendar week days short names</label>
				</th>
				<td>
					<?php
						$weeks  = ga_get_form_translated_data($form_lang, 'weeks');
					?>
					<span class="ga_translation_title">Sunday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][sun]" value="<?php echo $weeks['sun']; ?>"><br>
					<span class="ga_translation_title">Monday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][mon]" value="<?php echo  $weeks['mon']; ?>"><br>
					<span class="ga_translation_title">Tueday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][tue]" value="<?php echo  $weeks['tue']; ?>"><br>
					<span class="ga_translation_title">Wednesday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][wed]" value="<?php echo  $weeks['wed']; ?>"><br>
					<span class="ga_translation_title">Thursday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][thu]" value="<?php echo  $weeks['thu']; ?>"><br>
					<span class="ga_translation_title">Friday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][fri]" value="<?php echo  $weeks['fri']; ?>"><br>
					<span class="ga_translation_title">Saturday</span> <input class="regular-text" class="regular-text" type="text" name="gappointments_translation[weeks][sat]" value="<?php echo  $weeks['sat']; ?>">
				</td>
			</tr>



			<tr>
				<th scope="row">
					<label for="clear_time">Calendar week days long names</label>
				</th>
				<td>
					<?php
						$long_weeks  = ga_get_form_translated_data($form_lang, 'long_weeks');
					?>
					<span class="ga_translation_title">Sunday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][sunday]" value="<?php echo $long_weeks['sunday']; ?>"><br>
					<span class="ga_translation_title">Monday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][monday]" value="<?php echo  $long_weeks['monday']; ?>"><br>
					<span class="ga_translation_title">Tueday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][tuesday]" value="<?php echo  $long_weeks['tuesday']; ?>"><br>
					<span class="ga_translation_title">Wednesday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][wednesday]" value="<?php echo  $long_weeks['wednesday']; ?>"><br>
					<span class="ga_translation_title">Thursday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][thursday]" value="<?php echo  $long_weeks['thursday']; ?>"><br>
					<span class="ga_translation_title">Friday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][friday]" value="<?php echo  $long_weeks['friday']; ?>"><br>
					<span class="ga_translation_title">Saturday</span> <input class="regular-text" type="text" name="gappointments_translation[long_weeks][saturday]" value="<?php echo  $long_weeks['saturday']; ?>">
				</td>
			</tr>				
					
			<tr>
				<th scope="row">
					<label for="clear_time">Calendar heading month/year</label>
				</th>
				<td>
					<?php
						$january   = ga_get_form_translated_data($form_lang, 'january');
						$february  = ga_get_form_translated_data($form_lang, 'february');
						$march     = ga_get_form_translated_data($form_lang, 'march');
						$april     = ga_get_form_translated_data($form_lang, 'april');
						$may       = ga_get_form_translated_data($form_lang, 'may');
						$june      = ga_get_form_translated_data($form_lang, 'june');
						$july      = ga_get_form_translated_data($form_lang, 'july');
						$august    = ga_get_form_translated_data($form_lang, 'august');
						$september = ga_get_form_translated_data($form_lang, 'september');
						$october   = ga_get_form_translated_data($form_lang, 'october');
						$november  = ga_get_form_translated_data($form_lang, 'november');
						$december  = ga_get_form_translated_data($form_lang, 'december');
					?>
					<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="gappointments_translation[january]" value="<?php echo $january; ?>"><br>
					<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="gappointments_translation[february]" value="<?php echo $february; ?>"><br>
					<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="gappointments_translation[march]" value="<?php echo $march; ?>"><br>
					<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="gappointments_translation[april]" value="<?php echo $april; ?>"><br>
					<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="gappointments_translation[may]" value="<?php echo $may; ?>"><br>
					<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="gappointments_translation[june]" value="<?php echo $june; ?>"><br>
					<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="gappointments_translation[july]" value="<?php echo $july; ?>"><br>
					<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="gappointments_translation[august]" value="<?php echo $august; ?>"><br>
					<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="gappointments_translation[september]" value="<?php echo $september; ?>"><br>
					<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="gappointments_translation[october]" value="<?php echo $october; ?>"><br>
					<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="gappointments_translation[november]" value="<?php echo $november; ?>"><br>
					<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="gappointments_translation[december]" value="<?php echo $december; ?>">
					
					<p class="description">Shortcode to use: [year]</p>
				</td>
			</tr>

			
			<tr>
				<th scope="row">
					<label for="clear_time">Calendar slots date</label>
				</th>
				<td>
					<?php
						$slots_january   = ga_get_form_translated_data($form_lang, 'slots_january');
						$slots_february  = ga_get_form_translated_data($form_lang, 'slots_february');
						$slots_march     = ga_get_form_translated_data($form_lang, 'slots_march');
						$slots_april     = ga_get_form_translated_data($form_lang, 'slots_april');
						$slots_may       = ga_get_form_translated_data($form_lang, 'slots_may');
						$slots_june      = ga_get_form_translated_data($form_lang, 'slots_june');
						$slots_july      = ga_get_form_translated_data($form_lang, 'slots_july');
						$slots_august    = ga_get_form_translated_data($form_lang, 'slots_august');
						$slots_september = ga_get_form_translated_data($form_lang, 'slots_september');
						$slots_october   = ga_get_form_translated_data($form_lang, 'slots_october');
						$slots_november  = ga_get_form_translated_data($form_lang, 'slots_november');
						$slots_december  = ga_get_form_translated_data($form_lang, 'slots_december');
					?>
					<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="gappointments_translation[slots_january]" value="<?php echo $slots_january; ?>"><br>
					<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="gappointments_translation[slots_february]" value="<?php echo $slots_february; ?>"><br>
					<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="gappointments_translation[slots_march]" value="<?php echo $slots_march; ?>"><br>
					<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="gappointments_translation[slots_april]" value="<?php echo $slots_april; ?>"><br>
					<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="gappointments_translation[slots_may]" value="<?php echo $slots_may; ?>"><br>
					<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="gappointments_translation[slots_june]" value="<?php echo $slots_june; ?>"><br>
					<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="gappointments_translation[slots_july]" value="<?php echo $slots_july; ?>"><br>
					<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="gappointments_translation[slots_august]" value="<?php echo $slots_august; ?>"><br>
					<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="gappointments_translation[slots_september]" value="<?php echo $slots_september; ?>"><br>
					<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="gappointments_translation[slots_october]" value="<?php echo $slots_october; ?>"><br>
					<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="gappointments_translation[slots_november]" value="<?php echo $slots_november; ?>"><br>
					<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="gappointments_translation[slots_december]" value="<?php echo $slots_december; ?>">
					
					<p class="description">Shortcodes to use: [day], [year]</p>
				</td>
			</tr>				
			
			<tr>
				<th scope="row">
					<label for="clear_time">Date & Time</label>
				</th>
				<td>
					<?php
						$date_time_january   = ga_get_form_translated_data($form_lang, 'date_time_january');
						$date_time_february  = ga_get_form_translated_data($form_lang, 'date_time_february');
						$date_time_march     = ga_get_form_translated_data($form_lang, 'date_time_march');
						$date_time_april     = ga_get_form_translated_data($form_lang, 'date_time_april');
						$date_time_may       = ga_get_form_translated_data($form_lang, 'date_time_may');
						$date_time_june      = ga_get_form_translated_data($form_lang, 'date_time_june');
						$date_time_july      = ga_get_form_translated_data($form_lang, 'date_time_july');
						$date_time_august    = ga_get_form_translated_data($form_lang, 'date_time_august');
						$date_time_september = ga_get_form_translated_data($form_lang, 'date_time_september');
						$date_time_october   = ga_get_form_translated_data($form_lang, 'date_time_october');
						$date_time_november  = ga_get_form_translated_data($form_lang, 'date_time_november');
						$date_time_december  = ga_get_form_translated_data($form_lang, 'date_time_december');
					?>
					<span class="ga_translation_title">January</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_january]" value="<?php echo $date_time_january; ?>"><br>
					<span class="ga_translation_title">February</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_february]" value="<?php echo $date_time_february; ?>"><br>
					<span class="ga_translation_title">March</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_march]" value="<?php echo $date_time_march; ?>"><br>
					<span class="ga_translation_title">April</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_april]" value="<?php echo $date_time_april; ?>"><br>
					<span class="ga_translation_title">May</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_may]" value="<?php echo $date_time_may; ?>"><br>
					<span class="ga_translation_title">June</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_june]" value="<?php echo $date_time_june; ?>"><br>
					<span class="ga_translation_title">July</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_july]" value="<?php echo $date_time_july; ?>"><br>
					<span class="ga_translation_title">August</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_august]" value="<?php echo $date_time_august; ?>"><br>
					<span class="ga_translation_title">September</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_september]" value="<?php echo $date_time_september; ?>"><br>
					<span class="ga_translation_title">October</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_october]" value="<?php echo $date_time_october; ?>"><br>
					<span class="ga_translation_title">November</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_november]" value="<?php echo $date_time_november; ?>"><br>
					<span class="ga_translation_title">December</span> <input type="text" class="regular-text" name="gappointments_translation[date_time_december]" value="<?php echo $date_time_december; ?>">
					
					<p class="description">Shortcodes to use: [week_long], [day], [year], [time]</p>
				</td>
			</tr>				

			<tr>
				<th scope="row">
					<label for="clear_time">AM/PM</label>
				</th>
				<td>
					<?php
						$am  = ga_get_form_translated_data($form_lang, 'am');
						$pm  = ga_get_form_translated_data($form_lang, 'pm');
					?>
					<span class="ga_translation_title">Am time</span> <input type="text" name="gappointments_translation[am]" value="<?php echo $am; ?>"><br>
					<span class="ga_translation_title">Pm time</span> <input type="text" name="gappointments_translation[pm]" value="<?php echo $pm; ?>"><br>
				</td>
			</tr>				

			
			
			<tr>
				<th scope="row">
					<label for="clear_time">Capacity</label> 
				</th>
				<td>
					<?php
						$space   = ga_get_form_translated_data($form_lang, 'space');
						$spaces  = ga_get_form_translated_data($form_lang, 'spaces');
					?>					
					<span class="ga_translation_title">Is one</span> <input type="text" class="regular-text" name="gappointments_translation[space]" value="<?php echo $space; ?>"><br>
					<span class="ga_translation_title">Is greater than one</span> <input type="text" class="regular-text" name="gappointments_translation[spaces]" value="<?php echo $spaces; ?>"><br>
				</td>
			</tr>			
			
	
			<tr>
				<th scope="row">
					<label for="clear_time">Add to calendar links title</label> 
				</th>
				<td>
					<?php
						$client_service      = ga_get_form_translated_data($form_lang, 'client_service');
						$provider_service    = ga_get_form_translated_data($form_lang, 'provider_service');
					?>					
					<p>Client service title. Shortcodes to use: [service_name], [provider_name]</p>  
					<input type="text" class="regular-text" name="gappointments_translation[client_service]" value="<?php echo $client_service; ?>"><br>	
					
					<p>Provider service title. Shortcodes to use: [service_name], [client_name]</p> 
					<input type="text" class="regular-text" name="gappointments_translation[provider_service]" value="<?php echo $provider_service; ?>"><br>	
			
				</td>
			</tr>
	
			<tr>
				<th scope="row">
					<label for="clear_time">Appointment cost</label> 
				</th>
				<td>
					<?php
						$app_cost_text = ga_get_form_translated_data($form_lang, 'app_cost_text');
					?>					
					<input type="text" class="regular-text" name="gappointments_translation[app_cost_text]" value="<?php echo $app_cost_text; ?>"><br>	
				</td>
			</tr>	
	
			<tr>
				<th scope="row">
					<label for="clear_time">Validation messages</label> 
				</th>
				<td>
					<?php
						$error_required           = ga_get_form_translated_data($form_lang, 'error_required');
						$error_reached_max        = ga_get_form_translated_data($form_lang, 'error_reached_max');
						$error_required_date      = ga_get_form_translated_data($form_lang, 'error_required_date');
						$error_max_bookings       = ga_get_form_translated_data($form_lang, 'error_max_bookings');
						$error_required_service   = ga_get_form_translated_data($form_lang, 'error_required_service');
						$error_booked_date        = ga_get_form_translated_data($form_lang, 'error_booked_date');
						$error_date_valid         = ga_get_form_translated_data($form_lang, 'error_date_valid');
						$error_slot_valid         = ga_get_form_translated_data($form_lang, 'error_slot_valid');
						$error_required_slot      = ga_get_form_translated_data($form_lang, 'error_required_slot');
						$error_services_form      = ga_get_form_translated_data($form_lang, 'error_services_form');
						$error_service_valid      = ga_get_form_translated_data($form_lang, 'error_service_valid');
						$error_required_provider  = ga_get_form_translated_data($form_lang, 'error_required_provider');
						$error_providers_service  = ga_get_form_translated_data($form_lang, 'error_providers_service');
						$error_no_services        = ga_get_form_translated_data($form_lang, 'error_no_services');
					?>						
					<p># Field required</p>
					<input type="text" class="large-text" name="gappointments_translation[error_required]" value="<?php echo $error_required; ?>"><br>
					
					<p># Date maximum bookings reached. Shortcode to use: [date]</p>
					<input type="text" class="large-text" name="gappointments_translation[error_reached_max]" value="<?php echo $error_reached_max; ?>"><br>						
					
					<p># Date not selected</p>
					<input type="text" class="large-text" name="gappointments_translation[error_required_date]" value="<?php echo $error_required_date; ?>"><br>						
											
					<p># Date max bookings. Shortcodes to use: [total], [date]</p>
					<input type="text" class="large-text" name="gappointments_translation[error_max_bookings]" value="<?php echo $error_max_bookings; ?>"><br>						
					
					<p># Service not selected</p>
					<input type="text" class="large-text" name="gappointments_translation[error_required_service]" value="<?php echo $error_required_service; ?>"><br>						
				
					<p># Client already booked date. Shortcode to use: [date]</p>
					<input type="text" class="large-text" name="gappointments_translation[error_booked_date]" value="<?php echo $error_booked_date; ?>"><br>						
							
					<p># Date not valid. Shortcode to use: [date]</p>
					<input type="text" class="large-text" name="gappointments_translation[error_date_valid]" value="<?php echo $error_date_valid; ?>"><br>						
						
					<p># Time slot not valid. Shortcode to use: [date]</p>
					<input type="text" class="large-text" name="gappointments_translation[error_slot_valid]" value="<?php echo $error_slot_valid; ?>"><br>						
					
					<p># Time slot required</p>
					<input type="text" class="large-text" name="gappointments_translation[error_required_slot]" value="<?php echo $error_required_slot; ?>"><br>						
			
					<p># Services field not added to form</p>
					<input type="text" class="large-text" name="gappointments_translation[error_services_form]" value="<?php echo $error_services_form; ?>"><br>						
				
					<p># Service is not valid</p>
					<input type="text" class="large-text" name="gappointments_translation[error_service_valid]" value="<?php echo $error_service_valid; ?>"><br>						
					
					<p># Provider not selected</p>
					<input type="text" class="large-text" name="gappointments_translation[error_required_provider]" value="<?php echo $error_required_provider; ?>"><br>						
											
					<p># Providers service not valid</p>
					<input type="text" class="large-text" name="gappointments_translation[error_providers_service]" value="<?php echo $error_providers_service; ?>"><br>						
				
					<p># No services found</p>
					<input type="text" class="large-text" name="gappointments_translation[error_no_services]" value="<?php echo $error_no_services; ?>"><br>						
												
				</td>
			</tr>				
			
			
		</table>
		<input type="hidden" id="form_id" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />
		<input type="submit" id="gform_save_gappointments" name="gform_save_gappointments" value="<?php _e( 'Update Form Settings', 'gravityforms' ); ?>" class="button-primary gfbutton" />
	</form>
			
    <?php GFFormSettings::page_footer();
 
}


/**
 * Handles the saving of form fields.
 *
 * @uses GFAPI::get_form()
 * @uses GFAPI::update_form()
 *
 */
function save_gappointments_form_page($form_id) {
	//$form = GFAPI::get_form( $form_id );
	
	if( isset( $_POST['gform_save_gappointments'] ) ) {
		$form = GFAPI::get_form( $form_id );
		$form['gappointments_translation'] = rgpost( 'gappointments_translation' );
		$form['ga_service_category']       = rgpost( 'ga_service_category' );
		$form['ga_form_review_page']       = rgpost( 'ga_form_review_page' );

		GFAPI::update_form( $form, $form_id );			
		?>
		<div class="updated below-h2" id="after_update_dialog">
			<p>
				<strong><?php _e( 'Form settings updated successfully.', 'gravityforms' ); ?></strong>
			</p>
		</div> 
		
<?php }
}
