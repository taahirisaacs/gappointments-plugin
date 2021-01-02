( function($) {

	/**
	 * Appointment status change AJAX
	 */
	jQuery('.appointment_status_select').on('change', function(){
		// wp ajax
		var data = {
			'action': 'ga_change_appointment_status',
			'app_id': jQuery(this).attr('app-id'),
			'status': this.value,
		};
		var tr = '#post-'+jQuery(this).attr('app-id');
		jQuery.post(ga_change_appointment_status_obj.ajax_url, data, function() {
		}).fail(function() {
			alert('There was a problem changing the appointment status');
		}).done(function(){
			jQuery(tr).addClass('successful_change_tr');
			setTimeout(function(){
				jQuery(tr).removeClass('successful_change_tr');
			}, 100);
		});


	});

	/**
	 * Appointment Reschedule pending hide
	 */
	jQuery('body').on('change', '#appointment_reschedule', function() {
		//alert( jQuery(this).val() );
		var type = jQuery(this).val();
		if( type === 'yes' ) {
			jQuery('#tr_reschedule_appointment_pending').removeClass('hide_reschedule_appointment_pending')
		} else if( type === 'no' ) {
			jQuery('#tr_reschedule_appointment_pending').addClass('hide_reschedule_appointment_pending');
		}

	});


	/**
	 * Appointment Reschedule pending to confirmed user hide
	 */
	jQuery('body').on('change', '#user_set_appointment_pending', function() {
		//alert( jQuery(this).val() );
		var type = jQuery(this).val();
		if( type === 'yes' ) {
			jQuery('#tr_reschedule_pending_to_confirmed').removeClass('hide_user_set_appointment_confirmed_from_pending')
		} else if( type === 'no' ) {
			jQuery('#tr_reschedule_pending_to_confirmed').addClass('hide_user_set_appointment_confirmed_from_pending');
		}

	});


	/**
	 * Disable appointment set to pending after user reschedules
	 */
	jQuery('body').on('change', '#user_set_appointment_confirmed_from_pending', function() {
		//alert( jQuery(this).val() );
		var type = jQuery(this).val();
		if( type === 'yes' ) {
			jQuery('#appointment_reschedule_pending').val('no')
		} else if( type === 'no' ) {
			jQuery('#appointment_reschedule_pending').val('yes');
		}

	});


	/**
	 * Disable user set to confirmed if appointment reschedule to pending is turned on
	 */
	jQuery('body').on('change', '#appointment_reschedule_pending', function() {
		var type = jQuery(this).val();
		if( type === 'yes' ) {
			jQuery('#user_set_appointment_confirmed_from_pending').val('no')
		} else if( type === 'no' ) {
			jQuery('#user_set_appointment_confirmed_from_pending').val('yes');
		}

	});




	/**
	 * Appointment Type
	 */
	jQuery('body').on('change', '#ga_appointment_type', function() {
		//alert( jQuery(this).val() );
		var type = jQuery(this).val();

		if( type == 'time_slot' ) {
			jQuery('.cmb2-id-ga-appointment-duration, .cmb2-id-ga-appointment-time').show();
		} else if( type == 'date' ) {
			jQuery('.cmb2-id-ga-appointment-duration, .cmb2-id-ga-appointment-time').hide();
		}

	});

	/**
	 *  Cancellation Policy Custom Timeframe
	 */
	jQuery('body').on('change', '#cancellation_notice', function(){
		var type = jQuery(this).val();
		if( type == 'custom' ) {
			jQuery('#tr_cancelllation_notice_timeframe').show();
		}else{
			jQuery('#tr_cancelllation_notice_timeframe').hide();
		}
	});

	/**
	 * Appointment Cancel Message
	 */
	jQuery('body').on('change', '#cmb2-metabox-ga_appointment_submitdiv #post-status', function() {
		//alert( jQuery(this).val() );
		var postStatus = jQuery(this).val();

		if( postStatus == 'cancelled' ) {
			jQuery('#cmb2-metabox-ga_appointment_submitdiv .ga_cancel_message').removeClass('cmb2-hidden');
		} else {
			jQuery('#cmb2-metabox-ga_appointment_submitdiv .ga_cancel_message').addClass('cmb2-hidden');
		}
	});

	/**
	 *  Autocomplete appointment custom show/hide
	 */
	jQuery('body').on('change', '#auto_complete', function(){
		var type = jQuery(this).val();
		if( type == 'custom' ) {
			jQuery('#tr_auto_complete_custom').show();
		}else{
			jQuery('#tr_auto_complete_custom').hide();
		}
	});

	/**
	 * Available Times Mode
	 */
	jQuery('body').on('change', '.ga_service_available_times_mode', function() {
		var timeMode = jQuery(this).val();

		var excluded = [
			'cmb2-id-ga_service_available_times_mode',
			'cmb2-id-ga-service-schedule-lead-time-minutes',
			'cmb2-id-ga-service-period-type',
			'cmb2-id-ga-service-max-bookings',
			'cmb2-id-ga-service-multiple-selection',
			'cmb2-id-ga-service-max-selection',
			'cmb2-id-ga-service-double-bookings',
		];

		var interval = [
			'cmb2-id-ga-service-price',
			'cmb2-id-ga-service-duration',
			'cmb2-id-ga-service-cleanup',
			'cmb2-id-ga-service-capacity',
			'cmb2-id-ga-service-reduce-gaps',
			'cmb2-id-ga-service-time-format',
			'cmb2-id-ga-service-show-end-times',
			'cmb2-id-ga-service-remove-am-pm',
		];

		var custom = [
			'cmb2-id-ga_service_custom_slots',
			'cmb2-id-ga-service-time-format',
			'cmb2-id-ga-service-show-end-times',
			'cmb2-id-ga-service-remove-am-pm',
		];

		var dates = [
			'cmb2-id-ga-service-price',
			'cmb2-id-ga-service-capacity',
		];

		jQuery('#cmb2-metabox-ga_services_details .cmb-row').each(function() {
			var field_class = jQuery(this).attr('class').match(/cmb2-id-[^ ]+/);
			var data = [];

			if( timeMode == 'interval' ) {
				data = interval;
			} else if( timeMode == 'custom' ) {
				data = custom;
			} else if( timeMode == 'no_slots' ) {
				data = dates;
			}

			if( $.inArray(field_class[0], data) >= 0 ) {
				jQuery(this).removeClass('cmb2-hidden');
			} else {
				if( $.inArray(field_class[0], excluded) >= 0 ) {
					jQuery(this).removeClass('cmb2-hidden');
				} else {
					jQuery(this).addClass('cmb2-hidden');
				}
			}
		});
	});

	/**
	 * Calendar Availability Type
	 */
	jQuery('body').on('change', '#ga_service_period_type', function() {
		var period_type = jQuery(this).val();

		if( period_type == 'future_days' ) {
			jQuery('.cmb2-id-ga_service_date_range, .cmb2-id-ga_service_custom_dates').hide(); // Hide other field
			jQuery('.cmb2-id-ga-service-schedule-max-future-days').show();
		} else if( period_type == 'date_range' ) {
			jQuery('.cmb2-id-ga_service_date_range').show();
			jQuery('.cmb2-id-ga-service-schedule-max-future-days, .cmb2-id-ga_service_custom_dates').hide(); // Hide other field
		} else if( period_type == 'custom_dates' ) {
			jQuery('.cmb2-id-ga_service_custom_dates').show();
			jQuery('.cmb2-id-ga-service-schedule-max-future-days, .cmb2-id-ga_service_date_range').hide(); // Hide other field
		}

	});

	/**
	 * Add Color Picker to all inputs that have 'color-field' class
	 */
	jQuery('.color-field').wpColorPicker();

	/**
	 * Delete Services Term AJAX
	 */
	jQuery('body').on('click', '.ga_service_cat_delete', function() {
		if ( confirm("Are you sure?") ) {
			var term_id = jQuery(this).attr('term-id');
			var parent_li = jQuery(this).parent('li');

			// wp ajax
			var data = {
				'action': 'ga_service_delete_term',
				'term_id': term_id,
			};

			jQuery.post(ga_service_delete_term_obj.ajax_url, data, function(response) {
				if ( typeof response !== 'undefined' ) {
					if( response.success == true ) {
						parent_li.fadeOut(150, function() {
							jQuery(this).remove();
						});
					}
				}
			});
		}

	});

	/**
	 * Deselect Services Type
	 */
	jQuery('body').on('change', '.ga_provider_service_type', function() {
		var service_type = jQuery(this).parent('label').attr('class');

		if( service_type == 'ga_provider_service_slots' ) {
			jQuery('#ga_dates_services input:checkbox').removeAttr("checked");
		}

		if( service_type == 'ga_provider_service_dates' ) {
			jQuery('#ga_time_slots_services input:checkbox').removeAttr("checked");
		}

	});

	/**
	 * ADD NEW CUSTOM DATE
	 */
	jQuery('body').on('click', '.custom_dates_period .ga_add_custom_date', function() {
		var cloned = jQuery(this).parent('.custom_dates_period').find('#custom_dates_period .custom-date').first().clone().removeAttr( 'style' );
		jQuery(this).parent('.custom_dates_period').find('#custom_dates_period').append( cloned );
	});


	/**
	 * REMOVE CUSTOM DATE
	 */
	jQuery('body').on('click', '#custom_dates_period .custom-date .custom-date-delete', function() {
		jQuery(this).parent('.custom-date').fadeOut(150, function() {
			jQuery(this).remove();
		});
	});

	/**
	 * ADD NEW CUSTOM SLOT
	 */
	jQuery('body').on('click', '#ga_custom_slots .add-slot', function() {
		var data = {
			'action': 'ga_service_add_slot',
		};

		var $this = jQuery(this);

		jQuery.post(ga_service_add_slot_obj.ajax_url, data, function(response) {
			$this.closest('#ga_custom_slots').find('tbody').append( response );
		});
	});


	/**
	 * REMOVE CUSTOM SLOT
	 */
	jQuery('body').on('click', '#ga_custom_slots .slot-delete', function() {
		if( confirm('Are you sure?') ) {
			jQuery(this).closest('tr').fadeOut(150, function() {
				jQuery(this).remove();
			});
		}
	});

	// validate input of provider name
	jQuery('body').on('click', 'div#ga_provider_submitdiv  button.button.button-ga.right', function(e) {

		e.preventDefault();

		const button = jQuery(this);
		const title = jQuery("input[name='post_title']").val();
		const postId = jQuery('input[name="post_ID"]').val();
		const boundNumber = jQuery('input#time_max_number').val();
		const boundSelect = jQuery( "select#time_max_selector" ).val();
		let message = '';

		// Disable the button
		button.attr( 'disabled', true );
		button.html( '<i class="fa fa-spinner fa-spin"></i> Updating' );

		console.log(boundSelect);

		switch( boundSelect ) {
			case "":
				break;
			case 'day':
			case 'week':
			case 'month':
				if( 0 > boundNumber || boundNumber > 99 ) {
					message = "'Max bound' value must be between 0 and 99.";
					enable_button(button, message);
					return false;
				}
				break;
			case 'year':
				if( 0 > boundNumber || boundNumber > 10 ) {
					message = "'Max bound' value must be between 0 and 10.";
					enable_button(button, message);
					return false;
				}
				break;
		}

		if(!jQuery.isNumeric(postId) && Math.floor(postId) !== parseInt(postId)) {
			message = "Incorrect post id";
			enable_button(button, message);
			return false;
		}

		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "sanitize_ga_provider_title",
				title: title,
			},
			success: function (data, textStatus) {
				if (textStatus === "success") {
					const postData = parseInt(data);
					if (postData === 0 || postData === parseInt(postId)) {
						jQuery('#post').submit();
					} else if (postData > 0) {
						message = "Title already exists, please try a different value";
						enable_button(button, message);
					} else if (postData === -1) {
						message = "Title cannot be empty";
						enable_button(button, message);
					} else {
						message = "Something went wrong, please try again";
						enable_button(button, message);
					}
				}
			},
			error: function () {
				alert("Unexpected error occurred, please try again.");
				window.location.reload();
				return false;
			},
		});
	});

	function enable_button(button, message) {
		alert(message);
		button.attr( 'disabled', false );
		button.html( 'Update' );
	}

} ) ( jQuery );

