<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( class_exists( 'GFForms' ) ) {
	class GF_Appointment_Booking_Cost extends GF_Field {
		public $type = 'appointment_cost';
		
		public function get_form_editor_field_title() {
			return esc_attr__( 'Booking Cost', 'gravityforms' );
		}
		

		/*
		* Where to assign this widget
		*/
		public function get_form_editor_button() {
			return array(
				//'group' => 'advanced_fields',
				'group' => 'appointment_calendar',
				'text'  => $this->get_form_editor_field_title()
			);
		}	
		
		/*
		* Add button to the group
		*/
		public function add_button( $field_groups ) {
			$field_groups = $this->ga_appointment_services_gf_group( $field_groups );
			return parent::add_button( $field_groups );
		}
		
		/*
		* Add our group
		*/	
		public function ga_appointment_services_gf_group( $field_groups ) {
			foreach ( $field_groups as $field_group ) {
				if ( $field_group['name'] == 'appointment_calendar' ) {
					return $field_groups;
				}
			}		
			$field_groups[] = array(
				'name'   => 'appointment_calendar',
				'label'  => __( 'Appointment Booking', 'simplefieldaddon' ),
				'fields' => array(
				)
			);

			return $field_groups;
		}	
		
		/*
		* Widget settings
		*/			
		function get_form_editor_field_settings() {
			return array(
				'label_setting',
				'error_message_setting',			
				'label_placement_setting',
				'admin_label_setting',
				'description_setting',
				'css_class_setting',
				'conditional_logic_field_setting',				
			);
		}

		public function is_conditional_logic_supported() {
			return true;
		}

		/**
		 * Field Markup
		 */			
		public function get_field_input( $form, $value = '', $entry = null ) {
			$form_id         = absint( $form['id'] );
			$is_entry_detail = $this->is_entry_detail();
			$is_form_editor  = $this->is_form_editor();

			$id       = $this->id;
			$field_id           = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
//            $logic_event        = ! $is_form_editor && ! $is_entry_detail ? 'gfield' : '';
			$size               = $this->size;
			$class_suffix       = $is_entry_detail ? '_admin' : '';
			$class              = $size . $class_suffix;
			$css_class          = trim( esc_attr( $class ) . ' gfield_select' );
			$tabindex           = $this->get_tabindex();
			
			
			$output = '<div class="ginput_container">';
			
			if( $this->is_entry_edit() ) {
				$output .= 'Pricing fields are not editable';
				$output .= "<input type='hidden' name='input_{$id}' id='{$field_id}' value='{$value}'/>";
			} elseif( !$this->is_form_editor() ) {
				$output .= '<script>jQuery("body").on("change", ".ginput_appointment_cost_input", function() { jQuery( this ).prev( "span" ).text( gformFormatMoney( this.value, true ) ); });</script>';
				$output .= "<span class='ginput_{$this->type} ginput_product_price ginput_{$this->type}_{$form_id}_{$field_id}'>" . GFCommon::to_money( '0' ) . "</span>";
                $output .= "<input type='hidden' name='input_{$id}' id='{$field_id}' style='{$this->get_inline_price_styles()}' class='{$class} ginput_{$this->type}_input gform_hidden' value='{$value}' {$tabindex}/>";
			}
			
			$output .= '</div>';
			
			return $output;
		}

		/**
		 * Is Entry Edit
		 */			
		public function is_entry_edit() {
			if ( rgget( 'page' ) == 'gf_entries' && rgget( 'view' ) == 'entry' && rgpost( 'screen_mode' ) == 'edit' ) {
				return true;
			}

			return false;
		}		
		
		/**
		 * Returns TRUE if the current page is the form editor page. Otherwise, returns FALSE
		 */		
		// public function is_form_editor() {
		// 	if ( rgget( 'page' ) == 'gf_edit_forms' && ! rgempty( 'id', $_GET ) && rgempty( 'view', $_GET ) ) {
		// 		return true;
		// 	}
		// 	return false;
		// }				
		
		public function get_inline_price_styles() {
			return '';
		}		
		
		/**
		 * Validation Failed Message
		 */			
		private function validationFailed( $message = '' ) {
			$this->failed_validation = true;
			$message = esc_html__( $message, 'gravityforms' );
			$this->validation_message = empty( $this->errorMessage ) ? $message : $this->errorMessage;
		}		

		/**
		 * Validation
		 */			
		public function validate( $value, $form ) {
			$form_id = absint( $form['id'] );
            $form_lang = get_form_translations( $form );
			
			if( !gf_field_type_exists( $form, 'appointment_services' ) ) {
				$this->validationFailed( ga_get_form_translated_error_message($form_lang, 'error_services_form') );
				return;
			}	
			
			if( 'ga_services' == get_post_type(gf_get_field_type_postid( $form, 'appointment_services' )) ) {
				// Service field value
				$service_id  = gf_get_field_type_postid( $form, 'appointment_services' );

				// Selected service exists in form category term
                $form_cat_slug = rgar($form, 'ga_service_category');
                $cat           = ga_get_service_category( $form_cat_slug );
				
				if( $cat ) {
					if( has_term( $cat, 'ga_service_cat', $service_id ) ) {
						# valid
					} else {
						$this->validationFailed( ga_get_form_translated_error_message($form_lang, 'error_required_service') );
						return;					
					}
				}
				
			} else {
				$this->validationFailed( ga_get_form_translated_error_message($form_lang, 'error_service_valid') );
				return;				
			}
			
		}	
		
		/**
		 * Save Cost with Currency Symbol
		 */
		public function get_value_save_entry( $value, $form, $input_name, $entry_id, $entry ) {
            if( empty( $entry['id'] ) ) {
                return $value;
            }

			if( gf_field_type_exists( $form, 'appointment_services' ) ) {
				$form_id = $form['id'];
				// Service ID
				$service_id    = gf_get_field_type_postid( $form, 'appointment_services' );
				
				// Provider ID
				$provider_id  = gf_get_field_type_postid( $form, 'appointment_providers' );
				$provider_id  = gf_field_type_exists($form, 'appointment_providers')
								&& 'ga_providers' == get_post_type($provider_id)
								? $provider_id
								: 0;
				if( ga_get_provider_id($service_id) && $provider_id == 0 ) {
					$provider_id = ga_get_provider_id($service_id);
				}
				
				// Service Price
				$service_price = get_post_meta($service_id, 'ga_service_price', true);
				
				
				/**
				 * Multiple Bookings
				 */
				$times_mode     = (string) get_post_meta( $service_id, 'ga_service_available_times_mode', true );	
				$multiple_slots = (string) get_post_meta( $service_id, 'ga_service_multiple_selection', true );

				if( $multiple_slots == 'yes' && gf_field_type_exists($form, 'appointment_calendar') ) {
					$calendar = gf_get_field_type_value( $form, 'appointment_calendar' );

					// Get bookings
					$bookings = ga_get_multiple_bookings($calendar, $service_id, $provider_id);
					if( $times_mode == 'custom' ) {
						$value  = gf_to_money( ga_get_slots_total( $form['id'], $service_id, $provider_id, $bookings ) );
					} else {
						$cost   = $service_price * count($bookings);
						$value  = gf_to_money( $cost );							
					}					
				} else {
					/**
					 * Single Booking
					 */		
					if( $times_mode == 'custom' ) {
						$calendar      = gf_get_field_type_value( $form, 'appointment_calendar' );
						$booking       = ga_get_multiple_bookings($calendar, $service_id, $provider_id);
						$service_price = ga_get_slots_total( $form_id, $service_id, $provider_id, $booking );
					}						 
					$value = gf_to_money( $service_price );
				}				
			}
			return $value;			
		}		
		
		/**
		* Show appointment cost on entry single & GP Preview Plugin
		*/		
		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {		
			return $value;
		}			
		
		
		
	} // end class
	GF_Fields::register( new GF_Appointment_Booking_Cost() );	
} // end if
