<?php
defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( class_exists( 'GFForms' ) ) {
	class GF_Appointment_Booking_Services extends GF_Field {
		public $type = 'appointment_services';

		public function get_form_editor_field_title() {
			return esc_attr__( 'Booking Services', 'gravityforms' );
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
				'visibility_setting',
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
			$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

//			$logic_event        = $this->get_conditional_logic_event( 'change' );
			$size               = $this->size;
			$class_suffix       = $is_entry_detail ? '_admin' : '';
			$chosenUI           = $this->enableEnhancedUI ? ' chosen-select' : '';
			$field_class        = ' appointment_service_id';
			$class              = $size . $class_suffix . $chosenUI . $field_class;
            $css_class          = trim( esc_attr( $class ) . ' gfield_select' );
			$tabindex           = $this->get_tabindex();
			$disabled_text      = $is_form_editor ? 'disabled="disabled"' : '';
			$required_attribute = $this->isRequired ? 'aria-required="true"' : '';
			$invalid_attribute  = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

			$choices = '';

			if( $this->is_entry_edit() ) {

//                $choices .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%s' id='%s' $logic_event class='%s' $tabindex %s %s %s>%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $this->get_services_entry_choices($value, $form) );
                $choices .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%s' id='%s'  class='%s' $tabindex %s %s %s>%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $this->get_services_entry_choices($value, $form) );
			} elseif( !$this->is_form_editor() ) {
//				$choices .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%s' id='%s' $logic_event class='%s' $tabindex %s %s %s form_id='%d'>%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $form_id, $this->get_services_choices($value, $form) );
                $choices .= sprintf( "<div class='ginput_container ginput_container_select'><select name='input_%s' id='%s'  class='%s' $tabindex %s %s %s form_id='%d'>%s</select></div>", $id, $field_id, $css_class, $disabled_text, $required_attribute, $invalid_attribute, $form_id, $this->get_services_choices( $value, $form ) );
			}

			return $choices;
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

		/**
		 * Get Services Select Options
		 */
		public function get_services_choices($value, $form) {
			$options = '';

			$form_cat_slug = rgar($form, 'ga_service_category');
			$cat           = term_exists( $form_cat_slug, 'ga_service_cat' );

			// The Query
			if( $cat ) {
				$args = array('post_type' => 'ga_services', 'ignore_custom_sort' => true, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'desc', 'tax_query' => array( array(
						'taxonomy' => 'ga_service_cat', // taxonomy name
						'field'    => 'slug',           // term_id, slug or name
						'terms'    => $form_cat_slug,    // term id, term slug or term name
					))
					); // end array

			} else {
				$args = array('post_type' => 'ga_services', 'ignore_custom_sort' => true, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' );
			}


			$the_query = new WP_Query( $args );
			wp_reset_postdata();
			// The Loop
			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$post = get_post( get_the_id() );
					$selected = $value == get_the_id() ? ' selected="selected"' : '';
					$options .= '<option value="'.get_the_id().'"'.$selected.'>'.$post->post_title.'</option>' . PHP_EOL;
				}
				wp_reset_postdata();
			} else {
				// no services found
			}

			return $options;
		}

		/**
		 * Get Services Entry Options
		 */
		public function get_services_entry_choices($value, $form) {
			$options = '';

			$args = array('post_type' => 'ga_services', 'ignore_custom_sort' => true, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' );

			$the_query = new WP_Query( $args );
			wp_reset_postdata();

			// The Loop
			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$post = get_post( get_the_id() );
					$selected = $value == get_the_id() ? ' selected="selected"' : '';
					$options .= '<option value="'.get_the_id().'"'.$selected.'>'.$post->post_title.'</option>' . PHP_EOL;
				}
				wp_reset_postdata();
			} else {
				// no services found
			}

			return $options;
		}


		/**
		 * Validation Failed
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

			if( 'ga_services' == get_post_type($value) && get_post_status( $value ) == 'publish' ) {
				# valid field
				$service_id  = $value;

				// Selected service exists in form category term
				$form_cat_slug = rgar($form, 'ga_service_category');
				$cat           = term_exists( $form_cat_slug, 'ga_service_cat' );

				if( $cat ) {
					if( has_term( $cat, 'ga_service_cat', $service_id ) ) {
						# valid
					} else {
						$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_service') );
						return;
					}
				}

			} else {
				$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_service') );
				return;
			}

			// Check if calendar widget is found
			if( !gf_field_type_exists( $form, 'appointment_calendar' ) ) {
				$this->validationFailed( ga_get_form_translated_error_message($form_id, 'error_required_date') );
				return;
			}

		}

		/**
		 * Save value
		 */
		public function get_value_save_entry( $value, $form, $input_name, $entry_id, $entry ) {
			//$post_id = absint( $_POST['appointment_booking_service'] );
			//$value = get_the_title( $post_id );
			return $value;
		}

		/**
		* Show service title entry single
		*/
		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

			$post_id = absint( $value );
			if( 'ga_services' == get_post_type($post_id) ) {

				// $value = '<a href="'.get_edit_post_link( $post_id ).'">' .get_the_title( $post_id ). '</a>';	 //

				$value = get_the_title( $post_id );
				return esc_html( $value );
			} else {

				return esc_html( $value );

			}
		}

		/**
		* Merge tag, on notifications, confirmations
		*/
		public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
			$post_id = absint( $value );
			if( 'ga_services' == get_post_type($post_id) ) {
				$value = get_the_title( $post_id );
				return esc_html( $value );
			} else {

				return esc_html( $value );

			}
		}


		/*
		* Show service title on entry list
		*/
		/*
		public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
			$post_id = absint( $value );
			if( 'ga_services' == get_post_type($post_id) ) {
				$value = get_the_title( $post_id );
				return esc_html( $value );
			} else {
				return esc_html( $value );
			}

		}
		*/


		public function get_form_editor_inline_script_on_page_render() {
			return "
			gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
				if (type == 'appointment_services') {
					if (GetFieldsByType(['appointment_services']).length > 0) {
						alert(" . json_encode( esc_html__( 'Only one Booking Services field can be added to the form', 'gravityformscoupons' ) ) . ");
						return false;
					}
				}
				return canFieldBeAdded;
			});";
		}



	} // end class
	GF_Fields::register( new GF_Appointment_Booking_Services() );
} // end if
