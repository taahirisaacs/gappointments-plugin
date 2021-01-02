<?php

defined('ABSPATH') or exit; // Exit if accessed directly

if (class_exists('GFForms')) {
	class GF_Appointment_Booking_Providers extends GF_Field
	{
		public $type = 'appointment_providers';

		public function get_form_editor_field_title()
		{
			return esc_attr__('Booking Providers', 'gravityforms');
		}


		/*
		* Where to assign this widget
		*/
		public function get_form_editor_button()
		{
			return array(
				'group' => 'appointment_calendar',
				'text'  => $this->get_form_editor_field_title()
			);
		}
		/*
		* Add button to the group
		*/
		public function add_button($field_groups)
		{
			$field_groups = $this->ga_appointment_providers_gf_group($field_groups);
			return parent::add_button($field_groups);
		}
		/*
		* Add our group
		*/
		public function ga_appointment_providers_gf_group($field_groups)
		{
			foreach ($field_groups as $field_group) {
				if ($field_group['name'] == 'appointment_calendar') {
					return $field_groups;
				}
			}
			$field_groups[] = array(
				'name'   => 'appointment_calendar',
				'label'  => __('Appointment Booking', 'simplefieldaddon'),
				'fields' => array()
			);

			return $field_groups;
		}

		/*
		* Widget settings
		*/
		function get_form_editor_field_settings()
		{
			return array(
				'enable_enhanced_ui_setting',
				'label_setting',
				'error_message_setting',
				'label_placement_setting',
				'admin_label_setting',
				'size_setting',
				'description_setting',
				'css_class_setting',
				'rules_setting',
				'conditional_logic_field_setting',
				'visibility_setting'
			);
		}

		public function is_conditional_logic_supported()
		{
			return true;
		}

		/**
		 * Field Markups
		 */
		public function get_field_input( $form, $value = '', $entry = null )
		{
			$form_id            = absint( $form['id'] );
			$is_entry_detail    = $this->is_entry_detail();
			$is_form_editor     = $this->is_form_editor();

			$id                 = $this->id;
			$field_id           = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

			//			$logic_event        = $this->get_conditional_logic_event( 'change' );
			$size               = $this->size;
			$class_suffix       = $is_entry_detail ? '_admin' : '';
			$chosenUI           = $this->enableEnhancedUI ? ' chosen-select' : '';
			$providers_class    = ' appointment_provider_id';
			$class              = $size . $class_suffix . $chosenUI . $providers_class;
			$css_class          = trim(esc_attr($class) . ' gfield_select');
			$tabindex           = $this->get_tabindex();
			$disabled_text      = $is_form_editor ? 'disabled="disabled"' : '';
			$required_attribute = $this->isRequired ? 'aria-required="true"' : '';
			$invalid_attribute  = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
            $style              = 'width: 99%;';

			$select_providers = '';

			if( $this->is_entry_edit() ) {
				$select_providers .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%d' id='%s' class='%s' $tabindex %s %s %s style='%s' >%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $style, $this->get_providers_entry_choices( $form, $value ) );
			} elseif( !$this->is_form_editor() ) {
			    $new_service_id = ga_service_id( $form );
				if( !empty( $new_service_id ) && gf_field_type_exists( $form, 'appointment_services' ) ) {
					$select_providers .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%d' id='%s' class='%s' $tabindex %s %s %s form_id='%d' >%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $form_id, $this->get_providers_choices( $form, $value, $new_service_id ) );
				} else {
					$select_providers .= '<p>' . ga_get_form_translated_data( $form = false, 'error_no_services' ) . '</p>';
				}
			}

			return $select_providers;
		}

		/**
		 * Is Entry Edit
		 */
		public function is_entry_edit()
		{
			if (rgget('page') == 'gf_entries' && rgget('view') == 'entry' && rgpost('screen_mode') == 'edit') {
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

		/**
		 * Get Provider Select Options
		 */
		public function get_providers_choices( $form, $value, $new_service_id )
		{
			$options = '';
			$service_id = absint( $new_service_id );
			$provider_id = '';

			// Form submited services field value
			if( gf_field_type_exists( $form, 'appointment_services' ) ) {
				$services_field_value = gf_get_field_type_postid( $form, 'appointment_services' );
				if( is_numeric( $services_field_value ) && 'ga_services' == get_post_type( $services_field_value ) ) {
					$service_id = $services_field_value;
				}

				if( is_numeric( $value ) ) {
					$provider_id = (string) $value;
				}
			}

			$args = array( 'post_type' => 'ga_providers', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array(array('key' => 'ga_provider_services', 'value' => serialize(strval($service_id)), 'compare' => 'LIKE')) );
			$the_query = new WP_Query( $args );
			wp_reset_postdata();

			// The Loop
			if( $the_query->have_posts() ) {
				while( $the_query->have_posts() ) {
					$the_query->the_post();
                    $post = $the_query->post;
					$selected = $provider_id == $post->post_title ? ' selected="selected"' : '';
					$options .= '<option value="' . $post->post_title . '"' . $selected . '>' . $post->post_title . '</option>' . PHP_EOL;
                }
				wp_reset_postdata();
			} else {
				$options .= '<option value="0">No preference</option>' . PHP_EOL;
			}

			return $options;
		}


		/**
		 * Get Providers Entry Options
		 */
		public function get_providers_entry_choices($form, $value)
		{
			$args = array('post_type' => 'ga_providers', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC');
			$the_query = new WP_Query($args);
			wp_reset_postdata();
			$options = '<option value="0">No preference</option>' . PHP_EOL;

			// The Loop
			if ($the_query->have_posts()) {
				while ($the_query->have_posts()) {
					$the_query->the_post();
                    $post = $the_query->post;
					$selected = $value == $post->post_title ? ' selected="selected"' : '';
					$options .= '<option value="' . $post->post_title . '"' . $selected . '>' . $post->post_title . '</option>' . PHP_EOL;
				}
				wp_reset_postdata();
			}
			return $options;
		}



		/**
		 * Validation Failed
		 */
		private function validationFailed($message = '')
		{
			$this->failed_validation = true;
			$message = esc_html__($message, 'gravityforms');
			$this->validation_message = empty($this->errorMessage) ? $message : $this->errorMessage;
		}

		/**
		 * Validation
		 */
		public function validate($value, $form)
		{
			$form_id   = absint($form['id']);
            $form_lang = get_form_translations( $form );

            $provider_id = get_page_by_title( esc_html( $value ), OBJECT, 'ga_providers' );
            if( !is_null( $provider_id ) && isset( $provider_id->ID ) ) {
                $provider_id = $provider_id->ID;
            }

			// Check if services widget is found
			if (gf_field_type_exists($form, 'appointment_services') && 'ga_services' == get_post_type(gf_get_field_type_postid( $form, 'appointment_services' ))) {
				# service exists
			} else {
				$this->validationFailed(ga_get_form_translated_error_message($form_lang, 'error_required_service'));
				return;
			}

			// Service field value
			$service_id  = gf_get_field_type_postid( $form, 'appointment_services' );


			// Selected service exists in form category term
            $form_cat_slug = rgar($form, 'ga_service_category');
            $cat           = ga_get_service_category( $form_cat_slug );

			if ($cat) {
				if (has_term($cat, 'ga_service_cat', $service_id)) {
					# valid
				} else {
					$this->validationFailed(ga_get_form_translated_error_message($form_lang, 'error_required_service'));
					return;
				}
			}


			// Service has a provider assigned, this function will get first provider ID
			if (ga_get_provider_id($service_id) && is_numeric($provider_id)) {

				// Provider has the selected service
				if (ga_provider_has_service($service_id, $provider_id)) { } else {
					$this->validationFailed(ga_get_form_translated_error_message($form_lang, 'error_providers_service'));
					return;
				}
			} elseif (!ga_get_provider_id($service_id) && $value == 0) {
				# Service doesn't have any providers assigned but still valid as 0 for no provider
				//$this->validationFailed( 'Service doesn\'t have providers' );
				//return;
			} else {
				$this->validationFailed(ga_get_form_translated_error_message($form_lang, 'error_required_provider'));
				return;
			}
		}



		/**
		 * Save value
		 */
		public function get_value_save_entry($value, $form, $input_name, $entry_id, $entry)
        {
            $value = $this->format_entry_field( $value );

			return esc_html( $value );
		}


		/**
		 * Show provider title on entry single & GP Preview Plugin
		 */
		public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
		{
            // Support for old entry save method
            $value = $this->format_entry_field( $value );

            return esc_html( $value );
		}

		/*
		* Show provider title on entry list
		* To sanitize values before being output in the entry list page
		*/
        /*
		public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
			$post_id = absint( $value );
			if( 'ga_providers' == get_post_type($post_id) ) {
				$value = get_the_title( $post_id );
				return esc_html( $value );
			}

			if( $value == 0 ) {
				return 'No preference';
			}
		}
        */

		/**
		 * Merge tag, on notifications, confirmations
		 */
		public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br)
		{
            // Support for old entry save method
            $value = $this->format_entry_field( $value );

            return esc_html( $value );
		}

        /**
         * Format entry value to return title of post.
         */
        public static function format_entry_field($value ) {
            $post_id = absint( $value );

            if( 'ga_providers' == get_post_type( $post_id ) ) {
                $value = get_the_title( $post_id );
            } else if( is_numeric( $value ) && (int)$value === 0 ) {
                $value = 'No preference';
            }

            return $value;
        }


		public function get_form_editor_inline_script_on_page_render()
		{
			return "
			gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
				if (type == 'appointment_providers') {
					if (GetFieldsByType(['appointment_providers']).length > 0) {
						alert(" . json_encode(esc_html__('Only one Booking Providers field can be added to the form', 'gravityformscoupons')) . ");
						return false;
					}
				}
				return canFieldBeAdded;
			});";
		}
	} // end class
	GF_Fields::register(new GF_Appointment_Booking_Providers());
} // end if
