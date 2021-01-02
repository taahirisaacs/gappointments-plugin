/**
 * RENDER CHOSEN JS ON LOAD
 */
jQuery(window).on('load', function() {
	jQuery(".chosen-select").chosen();
});

/**
 * GF AJAX RENDER CHOSEN
 */
jQuery(document).bind('gform_post_render', function(){
	jQuery(".chosen-select").chosen();
});

/*jQuery('.appointment_status_change_span').on('click',function(){
	jQuery(this).hide();
	jQuery(this).parent().find('.user_appointment_status_set').removeClass('user_appointment_status_set_hide');
});*/
jQuery('.user_appointment_status_set').on('click', function(e){
	e.preventDefault();
	var appID          = jQuery(this).attr('app-id');
	var title          = jQuery(this).attr('title');
	var action         = 'ga_user_set_appointment_pending';
	var message 	   = '';
	//var message        = '<textarea name="ga_cancel_message" placeholder="' + optional_text + '"></textarea><div class="hr"></div>';
	var btn_title      = jQuery(this).attr('button-text');

	var modal_output = '<div id="ga_model" class="ga_modal_bg"><div class="ga_dialog"><div class="ga_dialog_wrapper"><div class="ga_modal_wrapper"><div class="ga_modal_container"><span title="Close" class="ga_close ga_remove cancel_user_app_pending"></span><h3 class="modal-title">'+title+'</h3><div class="hr"></div><form id="ga_user_set_appointment_pending" action="" method="post" class="clearfix"><div class="ajax-response"></div><input type="hidden" name="app-id" value="'+appID+'"><input type="hidden" name="action" value="'+action+'">'+message+'<div class="ga_modal_footer"><button type="submit" class="ga-button">'+btn_title+'</button></div></form><div class="modal_overlay"></div></div></div></div></div></div>';

	jQuery('#ga_appointment_modal').hide().html(modal_output).fadeIn(100, function() {
		jQuery('html, body').addClass('ga_modal_open');
	});

});

jQuery( document ).ready(function() {
	/**
	 * Select Service Event - AJAX
	 */
	jQuery('body').on('change', '.appointment_service_id', function() {
		// Remove value to gform input
		jQuery(".appointment_booking_date, .appointment_booking_time").val( '' );
		jQuery("#ga_selected_bookings").fadeOut(300, function() { jQuery(this).html('').removeAttr("style"); });

		// Add spinner to calendar
		jQuery('.ga_monthly_schedule_wrapper').addClass('ga_spinner');

		jQuery('.appointment_provider_id').each(function() {
			//jQuery(this).hide();
			jQuery(this).html('<option value="">Please wait...</option>');

			if( jQuery(this).hasClass('chosen-select') ) {
				jQuery(this).trigger("chosen:updated");
			}

		});

		// service id
		var service = jQuery(this).val();
		var form_id    = jQuery(this).attr('form_id');

		// wp ajax
		var data = {
			'action': 'ga_calendar_select_service',
			'service': service,
			'form_id': form_id,
		};

		jQuery.post(ga_calendar_services_obj.ajax_url, data, function(response) {
				if ( typeof response !== 'undefined' ) {

					if( response.success == true ) {

						jQuery('.appointment_provider_id').each(function() {

							jQuery(this).html( response.data['providers'] );

							if( jQuery(this).hasClass('chosen-select') ) {
								jQuery(this).trigger("chosen:updated");
							}

						});


						jQuery('#gappointments_calendar').find('.ga_monthly_schedule_wrapper').html( response.data['calendar'] );

						// Remove spinner from calendar
						jQuery('.ga_monthly_schedule_wrapper').removeClass('ga_spinner');

						gform_appointment_cost_filter();
					}

				}

			//console.log(response);
			//ga_ajax_response(response);
		});
		// wp ajax
	});

	/**
	 * Select Provider Event - AJAX
	 */
	jQuery('body').on('change', '.appointment_provider_id', function() {
		// Remove value to gform input
		jQuery(".appointment_booking_date, .appointment_booking_time").val( '' );
		jQuery("#ga_selected_bookings").fadeOut(300, function() { jQuery(this).html('').removeAttr("style"); });

		// Add spinner to calendar
		jQuery('.ga_monthly_schedule_wrapper').addClass('ga_spinner');

		// service id
		var provider   = jQuery(this).val();
		var service       = jQuery('.appointment_service_id').val();
		var form_id       = jQuery(this).attr('form_id');

		//alert( service_id );

		// wp ajax
		var data = {
			'action': 'ga_calendar_select_provider',
			'service': service,
			'provider': provider,
			'form_id': form_id,
		};

		jQuery.post(ga_calendar_providers_obj.ajax_url, data, function(response) {
			ga_ajax_response(response);


			// Remove spinner from calendar
			jQuery('.ga_monthly_schedule_wrapper').removeClass('ga_spinner');

			gform_appointment_cost_filter();
		});
	});

	/**
	 * Calendar Previous Month Event - AJAX
	 */
	jQuery('body').on('click', '#ga_calendar_prev_month', function() {
		// Remove value to gform input
		jQuery(".appointment_booking_date, .appointment_booking_time").val( '' );

		// Add spinner to calendar
		jQuery('.ga_monthly_schedule_wrapper').addClass('ga_spinner');

		var current_month = jQuery(this).attr('date-go');
		var service_id    = jQuery(this).attr('service_id');
		var provider_id   = jQuery(this).attr('provider_id');
		var form_id       = jQuery('#ga_appointments_calendar').attr('form_id');

		var data = {
			'action': 'ga_calendar_prev_month',
			'current_month': current_month,
			'service_id': service_id,
			'provider_id': provider_id,
			'form_id': form_id,
		};

		jQuery.post(ga_calendar_prev_month_obj.ajax_url, data, function(response) {
			ga_ajax_response(response);

			// Remove spinner from calendar
			jQuery('.ga_monthly_schedule_wrapper').removeClass('ga_spinner');
		});
	});

	/**
	 * Calendar Next Month Event - AJAX
	 */
	jQuery('body').on('click', '#ga_calendar_next_month', function() {
		// Remove value to gform input
		jQuery(".appointment_booking_date, .appointment_booking_time").val( '' );

		// Add spinner to calendar
		jQuery('.ga_monthly_schedule_wrapper').addClass('ga_spinner');

		var current_month = jQuery(this).attr('date-go');
		var service_id    = jQuery(this).attr('service_id');
		var provider_id   = jQuery(this).attr('provider_id');
		var form_id       = jQuery('#ga_appointments_calendar').attr('form_id');

		var data = {
			'action': 'ga_calendar_next_month',
			'current_month': current_month,
			'service_id': service_id,
			'provider_id': provider_id,
			'form_id': form_id,
		};

		jQuery.post(ga_calendar_next_month_obj.ajax_url, data, function(response) {
			ga_ajax_response(response);

			// Remove spinner from calendar
			jQuery('.ga_monthly_schedule_wrapper').removeClass('ga_spinner');
		});
	});

	/**
	 * Time Slots Event - AJAX
	 */
	jQuery('body').on('click', '#service-working-days td.day_available', function(e) {
		e.preventDefault();
		var dateGo = jQuery(this).attr('date-go');

		// Remove value to gform input
		jQuery(".appointment_booking_date, .appointment_booking_time").val( '' );

		if ( jQuery(this).hasClass("selected") ) {
			var $_this = jQuery(this);
			jQuery("#gappointments_calendar_slots").slideUp(0, function() {
				jQuery(this).html('');
			});

			// Remove selected class
			jQuery($_this).removeClass('selected');
			gform_appointment_cost_filter();
			return;
		}

		// Removing all available siblings
		jQuery('#service-working-days td').each(function() {
			jQuery(this).removeClass('selected');
		});

		// Adding selected class
		jQuery(this).addClass('selected');

		// Add value to gform input
		jQuery(".appointment_booking_date").val( jQuery(this).attr('date-go') );

		// Disable time slots if date slots class found
		if ( jQuery(this).hasClass("ga_date_slots") ) {
			jQuery(".appointment_booking_time").val( jQuery(this).attr('date-go') );


			// Multiple Dates Selection
			if( jQuery(this).attr('multi-select') == 'enabled' ) {
				var valid       = true;
				var inputs      = jQuery("#ga_selected_bookings .ga_selected_booking");
				var total       = jQuery(this).attr('select-total');
				var capacity    = jQuery(this).attr('capacity');
				var no_double   = jQuery(this).attr('no_double');
				var slot_cost   = jQuery(this).attr('service_cost');
				var date        = jQuery(".appointment_booking_date").val();
				var input_date  = date;
				var input_time  = jQuery(".appointment_booking_time").val();
				var input_value = input_date + ' ' + input_time;

				var d = 0; // Doubles counter
				jQuery(inputs).each(function() {
					var dateField = jQuery(this).find( '.ga_selected_booking_date' ).val();
					var timeField = jQuery(this).find( '.ga_selected_booking_time' ).val();
					var dateTime  = dateField + ' ' + timeField;
					if( inputs.length >= total ) {
						valid = false;
						return;
					}

					// Prevent Double Selections
					if( no_double == 'yes' ) {
						if( dateTime == input_value ) {
							valid = false;
						}
					} else {
						// Doubles Count
						if( dateTime == input_value ) {
							d++;
						}

						if( d >= capacity ) {
							valid = false;
						}

					}

				});

				if(valid) {
					var human_date  = jQuery(this).attr('lang_slot');
					var name_date   = jQuery(".appointment_booking_date").attr('name').replace("[date]", "[bookings][date][]");
					var name_time   = jQuery(".appointment_booking_date").attr('name').replace("[date]", "[bookings][time][]");
					var input_html  = '<div class="ga_selected_booking"><div class="ga_delete_booking"><i class="fa fa-times-circle"></i></div><input type="hidden" class="ga_hidden_input ga_selected_booking_date" name="' + name_date + '" value="' + input_date + '" slot_cost="' + slot_cost + '"><input type="hidden" class="ga_hidden_input ga_selected_booking_time" name="' + name_time + '" value="' + input_time + '" slot_cost="' + slot_cost + '">' + human_date + '</div></div>';
					jQuery(input_html).hide().appendTo("#ga_selected_bookings").fadeIn(700);
				}
			}
			// Multiple Dates Selection

			gform_appointment_cost_filter();

			return;
		}

		jQuery("#gappointments_calendar_slots").each( function() {
			jQuery( this ).remove();
		});

		jQuery(this).parent('tr').after('<tr id="gappointments_calendar_slots"><td colspan="7" class="calendar_slots"><div class="calendar_time_slots"><div class="app_hours_loading"><div class="ajax-spinner-bars"> <div class="bar-1"></div><div class="bar-2"></div><div class="bar-3"></div><div class="bar-4"></div><div class="bar-5"></div><div class="bar-6"></div><div class="bar-7"></div><div class="bar-8"></div><div class="bar-9"></div><div class="bar-10"></div><div class="bar-11"></div><div class="bar-12"></div><div class="bar-13"></div><div class="bar-14"></div><div class="bar-15"></div><div class="bar-16"></div></div></div></div></td></tr>');

		setTimeout( function() {
			var timeSlots = jQuery( '#' + dateGo ).html();
			jQuery('#gappointments_calendar_slots .calendar_time_slots').html(timeSlots);
		}, 500 );

	});

	/**
	 * Ajax Response
	 */
	function ga_ajax_response(response) {
		jQuery('#gappointments_calendar').find('.ga_monthly_schedule_wrapper').html(response);
	}

	/**
	 * Close Modal Button
	 */
	jQuery('body').on('click', '#ga_model .ga_modal_wrapper .ga_remove, form#ga_cancel_appointment .ga_btn_close, form#ga_reschedule_appointment .ga_btn_close', function(e) {
		e.preventDefault();

		jQuery(this).parents('#ga_model').fadeIn(100, function() {
			jQuery(this).remove();
			jQuery('html, body').removeClass('ga_modal_open');
		});

	});
	/*
	* Close modal for status change
	* */
	jQuery('body').on('click', '#ga_user_set_appointment_pending .ga_btn_close', function(e){
		e.preventDefault();
		jQuery(this).parents('#ga_model').fadeIn(100, function() {
			jQuery(this).remove();
			jQuery('html, body').removeClass('ga_modal_open');
		});
		var select =jQuery('#sel-'+jQuery(this).attr('app-id'));
		select.addClass('user_appointment_status_set_hide');
		select.unbind();
		var span = jQuery(select).parent().find('span');
		span.removeClass('appointment_status_change_span');
		span.html(jQuery(this).attr('pending-string'));
		span.removeClass('appointment-status-green');
		span.addClass('appointment-status-yellow');
        span.show();
		jQuery('#sel-'+jQuery('.ga_btn_close').attr('app-id')).remove();
		select.remove();
	});

	/**
	 * Cancel/Confirm Appointment Modal
	 */
	jQuery('body').on('click', '.appointment-status .appointment-action', function(e) {
		e.preventDefault();
		var appID          = jQuery(this).attr('app-id');
		var title          = jQuery(this).attr('title');
		var optional_text  = jQuery(this).attr('optional_text');
		var action         = 'ga_cancel_appointment';
		var message        = '<textarea name="ga_cancel_message" placeholder="' + optional_text + '"></textarea><div class="hr"></div>';
		var btn_title      = jQuery(this).text();

		if( jQuery(this).hasClass('provider-cancel') ) {
			var action = 'ga_provider_cancel_appointment';
		}

		if( jQuery(this).hasClass('provider-confirm') ) {
			var title     = jQuery(this).attr('title');
			var action    = 'ga_provider_confirm';
			var message   = '';
			var btn_title = jQuery(this).text();
		}

		var modal_output = '<div id="ga_model" class="ga_modal_bg"><div class="ga_dialog"><div class="ga_dialog_wrapper"><div class="ga_modal_wrapper"><div class="ga_modal_container"><span title="Close" class="ga_close ga_remove"></span><h3 class="modal-title">'+title+'</h3><div class="hr"></div><form id="ga_cancel_appointment" action="" method="post" class="clearfix"><div class="ajax-response"></div><input type="hidden" name="app-id" value="'+appID+'"><input type="hidden" name="action" value="'+action+'">'+message+'<div class="ga_modal_footer"><button type="submit" class="ga-button">'+btn_title+'</button></div></form><div class="modal_overlay"></div></div></div></div></div></div>';

		jQuery('#ga_appointment_modal').hide().html(modal_output).fadeIn(100, function() {
			jQuery('html, body').addClass('ga_modal_open');
		});

		window.appointmentID = jQuery(this).parent('.appointment-status');

	});

	/**
	 * Reschedule Appointment Modal
	 */
	jQuery('body').on('click', '.appointment-status .reschedule-appointment-action', function(e) {
		e.preventDefault();
		var appID          = jQuery(this).attr('app-id');
		var title          = jQuery(this).attr('title');
		var action         = 'ga_reschedule_appointment';
		var message 	   = '';
		var btn_title      = jQuery(this).text();

		// wp ajax
		var data = {
			'action': 'ga_get_calendar',
			'app_id': appID,
		};

		jQuery.post(ga_get_calendar_obj.ajax_url, data, function(response) {
			if ( typeof response !== 'undefined' ) {
				message = response;
			}
		}).fail(function() {
			message = '<div>Error</div>';
			action = false;
		}).done(function(){

			var modal_output = '<div id="ga_model" class="ga_modal_bg"><div class="ga_dialog"><div class="ga_dialog_wrapper"><div class="ga_modal_wrapper"><div class="ga_modal_container"><span title="Close" class="ga_close ga_remove"></span><h3 class="modal-title">'+title+'</h3><div class="hr"></div><form id="ga_reschedule_appointment" action="" method="post" class="clearfix"><div class="ajax-response"></div><input type="hidden" name="app-id" value="'+appID+'"><input type="hidden" name="action" value="'+action+'">'+message+'<div class="ga_modal_footer"><button type="submit" class="ga-button">'+btn_title+'</button></div></form><div class="modal_overlay"></div></div></div></div></div></div>';
			jQuery('#ga_appointment_modal').hide().html(modal_output).fadeIn(100, function() {
				jQuery('html, body').addClass('ga_modal_open');
			});
		});


		window.appointmentID = jQuery(this).parent('.appointment-status');
	});

	/**
	 * Cancel/Confirm Appointment - Ajax
	 */
	jQuery(document).on("submit", "form#ga_cancel_appointment", function(e) {
		e.preventDefault();
		var modal_wrapper = jQuery(this);
		var action = modal_wrapper.find('input[name=action]').val();

		modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').show();

		var data = jQuery("form#ga_cancel_appointment").serialize();

		jQuery.post(ga_update_appointment_status_obj.ajax_url, data, function(response) {
			if ( typeof response !== 'undefined' ) {

				modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();

				if( response.success == false ) {
	                jQuery("form#ga_cancel_appointment").html(response.data['message']);
				}

				if( response.success == true ) {
	                jQuery("form#ga_cancel_appointment").html(response.data['message']);
					jQuery( window.appointmentID ).html(response.data['app_status']);
				}

			}
		}).fail(function() {
			jQuery("form#ga_cancel_appointment .ajax-response").html('<div class="ga_alert ga_alert_danger">Error sending form!</div>');
			modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();
		});
	});

	/**
	 * User set appointment status to pending - Ajax
	 */
	jQuery(document).on("submit", "form#ga_user_set_appointment_pending", function(e) {
		e.preventDefault();
		var modal_wrapper = jQuery(this);
		var action = modal_wrapper.find('input[name=action]').val();

		modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').show();

		var data = jQuery("form#ga_user_set_appointment_pending").serialize();

		jQuery.post(ga_user_set_appointment_pending_obj.ajax_url, data, function(response) {
			if ( typeof response !== 'undefined' ) {

				modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();

				if( response.success == false ) {
					jQuery("form#ga_user_set_appointment_pending").html(response.data['message']);
				}

				if( response.success == true ) {
					console.log('nustatytas');
					jQuery("form#ga_user_set_appointment_pending").html(response.data['message']);
					jQuery( window.appointmentID ).html(response.data['app_status']);

					jQuery('.cancel_user_app_pending').unbind();
					jQuery('.cancel_user_app_pending').on('click', function(){
						var select =jQuery('#sel-'+jQuery('.ga_btn_close').attr('app-id'));
						select.addClass('user_appointment_status_set_hide');
						select.unbind();

						var span = jQuery(select).parent().find('span');
						span.removeClass('appointment_status_change_span');
						span.html(jQuery('.ga_btn_close').attr('pending-string'));
						span.removeClass('appointment-status-green');
						span.addClass('appointment-status-yellow');
						span.show();
						jQuery('#sel-'+jQuery('.ga_btn_close').attr('app-id')).remove();
					})
				}

			}
		}).fail(function() {
			jQuery("form#ga_user_set_appointment_pending .ajax-response").html('<div class="ga_alert ga_alert_danger">Error sending form!</div>');
			modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();
		});
	});

	/**
	 * Reschedule Appointment - Ajax
	 */
	jQuery(document).on("submit", "form#ga_reschedule_appointment", function(e) {
		e.preventDefault();
		var modal_wrapper = jQuery(this);
		var action = modal_wrapper.find('input[name=action]').val();

		modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').show();

		var data = jQuery("form#ga_reschedule_appointment").serialize();

		jQuery.post(ga_update_appointment_status_obj.ajax_url, data, function(response) {
			if ( typeof response !== 'undefined' ) {

				modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();

				if( response.success == false ) {
					jQuery("form#ga_reschedule_appointment").html(response.data['message']);
				}

				if( response.success == true ) {
					jQuery("form#ga_reschedule_appointment").html(response.data['message']);
					jQuery( window.appointmentID ).html(response.data['app_status']);
				}

			}
		}).fail(function() {
			jQuery("form#ga_reschedule_appointment .ajax-response").html('<div class="ga_alert ga_alert_danger">Error sending form!</div>');
			modal_wrapper.parent('.ga_modal_container').find('.modal_overlay').hide();
		});
	});



	/**
	 * Init Service Cost
	 */
	jQuery('body').on('click', '#gappointments_calendar_slots label.time_slot', function() {
		var time_slot = jQuery(this).attr('time_slot');
		jQuery(".appointment_booking_time").val( time_slot );

		// Removing all available siblings
		jQuery('#gappointments_calendar_slots label.time_slot').each(function() {
			jQuery(this).removeClass('time_selected');
		});

		// Adding selected class
		jQuery(this).addClass('time_selected');

		// Multiple Selection
		if( jQuery(this).attr('multi-select') == 'enabled' ) {
			var valid         = true;
			var inputs        = jQuery("#ga_selected_bookings .ga_selected_booking");
			var max           = jQuery(this).attr('select-max');
			var total         = jQuery(this).attr('select-total');
			var time_format   = jQuery(this).attr('time_format');
			var remove_am_pm  = jQuery(this).attr('remove_am_pm');
			var capacity      = jQuery(this).attr('capacity');
			var no_double     = jQuery(this).attr('no_double');
			var slot_cost     = jQuery(this).attr('slot_cost');
			var date          = jQuery(".appointment_booking_date").val();
			var input_date    = date;
			var input_time    = jQuery(".appointment_booking_time").val();
			var input_value   = input_date + ' ' + input_time;

			var x = 0;
			var d = 0; // Doubles counter
			jQuery(inputs).each(function() {
				var dateField = jQuery(this).find( '.ga_selected_booking_date' ).val();
				var timeField = jQuery(this).find( '.ga_selected_booking_time' ).val();
				var dateTime  = dateField + ' ' + timeField;

					//console.log( dateTime );
					//console.log( input_value );

				// Total max selection is reached
				if( inputs.length >= total ) {
					valid = false;
					return;
				}

				// Same date is found
				if( jQuery(this).val().match(date) ) {
					x++;
				}

				// Prevent Double Selections
				if( no_double == 'yes' ) {
					if( dateTime == input_value || x >= max ) {
						valid = false;
					}
				} else {
					// Doubles Count
					if( dateTime == input_value ) {
						d++;
					}

					if( d >= capacity ) {
						valid = false;
					}
				}
			});

			//console.log( valid );

			if(valid) {
				var human_date  = jQuery(this).attr('lang_slot');
				var name_date  = jQuery(".appointment_booking_date").attr('name').replace("[date]", "[bookings][date][]");
				var name_time  = jQuery(".appointment_booking_date").attr('name').replace("[date]", "[bookings][time][]");
				var input_html  = '<div class="ga_selected_booking"><div class="ga_delete_booking"><i class="fa fa-times-circle"></i></div><input type="hidden" class="ga_hidden_input ga_selected_booking_date" name="' + name_date + '" value="' + input_date + '" slot_cost="' + slot_cost + '"><input type="hidden" class="ga_hidden_input ga_selected_booking_time" name="' + name_time + '" value="' + input_time + '" slot_cost="' + slot_cost + '">' + human_date + '</div></div>';
				jQuery(input_html).hide().appendTo("#ga_selected_bookings").fadeIn(700);
			}
		}
		// Multiple Selection

	    gform_appointment_cost_filter();
	});



	/**
	 * Remove a Selected Booking
	 */
	jQuery('body').on('click', '#ga_selected_bookings .ga_selected_booking .ga_delete_booking', function() {
		jQuery(this).parent('.ga_selected_booking').fadeOut(150, function() {
			jQuery(this).remove();
			gform_appointment_cost_filter();

			if( !jQuery( '#ga_selected_bookings .ga_selected_booking .ga_delete_booking' ).lenght )
				jQuery( '.appointment_booking_time' ).val( '' );
		});
	});

	/**
	 * Calculate appointment cost
	 */
	function gform_appointment_cost_filter() {
		var $costFields  = jQuery( '.ginput_appointment_cost_input' );
		var total        = 0;
		var service_cost = jQuery("#service-working-days td.day_available").attr('service_cost');

		// Date Slot Selected
		var dateSelected = jQuery("#service-working-days td.ga_date_slots.selected");

		// Time Slot Selected
		var slotSelected = jQuery('#gappointments_calendar_slots label.time_selected');

		// Mulitple Bookings
		var bookings     = jQuery("#ga_selected_bookings .ga_selected_booking input.ga_selected_booking_date");

		if( bookings && bookings.length ) {
			jQuery("#ga_selected_bookings .ga_selected_booking input.ga_selected_booking_date").each(function() {
				total += Math.max( 0, parseFloat( jQuery(this).attr('slot_cost') ) );
			});
		} else if( dateSelected && dateSelected.length && jQuery.isNumeric(service_cost) ) {
			total = service_cost;
		} else if( slotSelected && slotSelected.length ) {
			total = slotSelected.attr('slot_cost');
		}

		// Add appointment total to cost inputs
		$costFields.each( function() {
			jQuery( this ).val( total ).change();
		});

		// Trigger gf total
		if( typeof gformInitPriceFields == 'function' ) {
			gformInitPriceFields(); // will trigger "gform_product_total" filter
		}
	}

}); // end doc ready




/**
 * Trigger appointment cost & add cost to total
 */
( function( $ ) {
	/**
	 * Init Service Cost On AJAX Form Trigger
	 */
	jQuery(document).bind('gform_post_render', function() {
		var $costFields = $( '.ginput_appointment_cost_input' ).change();
		$costFields.each( function() {
			$( this ).change();
		});
	});

} )( jQuery );
