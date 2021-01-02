<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ga_work_schedule {
	private $week_days  = array('sunday','monday','tuesday','wednesday','thursday','friday','saturday');
	private $begin      = array('sunday' => 'out', 'monday' => '09:00', 'tuesday' => '09:00', 'wednesday' => '09:00', 'thursday' => '09:00', 'friday' => 'out', 'saturday' => 'out');
	private $end        = array('sunday' => 'out', 'monday' => '17:00', 'tuesday' => '17:00', 'wednesday' => '17:00', 'thursday' => '17:00', 'friday' => 'out', 'saturday' => 'out');
	private $post_id;
	private $field_name;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	public function display_schedule( $field_name, $value ) {
		$this->field_name = $field_name;
			$output = '';
			foreach( $this->week_days as $week_day ) {
				$output .= '<div class="grid-row schedule_'. $week_day .' schedule_day">';
					$output .= '<div class="grid-lg-2 grid-md-3 grid-sm-12 grid-xs-12">';
						$output .= '<span class="day-description">'.ucfirst($week_day).'</span>';
					$output .= '</div>';

					$output .= '<div class="grid-lg-10 grid-md-9 grid-sm-12 grid-xs-12 schedule_day_container">';
						$output .= $this->generate_begin_select( $week_day, $value );
						$output .= '<span class="day_to">to</span>';
						$output .= $this->generate_end_select( $week_day, $value );
					$output .= "</div>"; //.grid-lg
				$output .= '</div>'; //.schedule_day
			}

		return $output;
	}

	private function generate_begin_select( $week_day, $value ) {
		$field_name   = $this->field_name;
		$field_id     = $field_name;

		$val = isset( $value[$week_day]['begin'] ) ? $value[$week_day]['begin'] : $this->begin[$week_day];
		$name = "{$field_name}[{$week_day}][begin]";
		$output = '<select name="'.$name.'" id="'.$field_id.'">';

		$interval = get_ga_appointment_time('schedule');
		foreach( $interval as $time => $text ) {
			$selected = $val == $time ? ' selected="selected"' : '';
			$output .= '<option value='. $time .' '.$selected.'>'. $text .'</option>';
		}

		$output .= "</select>";
		return $output;
	}

	private function generate_end_select( $week_day, $value ) {
		$field_name   = $this->field_name;
		$field_id     = $field_name;

		$val = isset( $value[$week_day]['end'] ) ? $value[$week_day]['end'] : $this->end[$week_day];
		$name = "{$field_name}[{$week_day}][end]";
		$output = '<select name="'.$name.'" id="'.$field_id.'">';

		$interval = get_ga_appointment_time('schedule', 'midnight');
		foreach( $interval as $time => $text ) {
			$selected = $val == $time ? ' selected="selected"' : '';
			$output .= '<option value='. $time .' '.$selected.'>'. $text .'</option>';
		}

		$output .= "</select>";
		return $output;
	}

	public function validate_work_schedule( $value ) {

		$begin_defaults     = $this->begin;
		$end_defaults       = $this->end;

		foreach( $this->week_days as $day ) {

			if( isset( $value[$day]['begin'] ) && array_key_exists( $value[$day]['begin'], get_ga_appointment_time('schedule') ) && $value[$day]['begin'] != 'out' ) {

				if( isset( $value[$day]['end'] ) && array_key_exists( $value[$day]['end'], get_ga_appointment_time('schedule', 'midnight') ) && $value[$day]['end'] != 'out' ) {

					if ( new DateTime( $value[$day]['end'] ) > new DateTime( $value[$day]['begin'] ) ) {
						# do nothing
					} else {
						$value[$day]['end'] = $value[$day]['begin'];
					}

				} else {
					$value[$day]['end'] = $value[$day]['begin'];
				}

			} else {

				$value[$day]['begin'] = 'out';
				$value[$day]['end'] = 'out';

			}

		}

		return $value;
	}

	/**
	 * Breaks Markup
	 */
	public function display_breaks( $field_name, $value ) {
		$this->field_name = $field_name;

		$output = '';
		foreach( $this->week_days as $week_day ) {
			$output .= '<div class="grid-row schedule_'. $week_day .' schedule_day">';
				$output .= '<div class="grid-lg-2 grid-md-3 grid-sm-12 grid-xs-12">';
					$output .= '<span class="day-description">'.ucfirst($week_day).'</span>';
				$output .= '</div>';

				$output .= '<div class="grid-lg-10 grid-md-9 grid-sm-12 grid-xs-12 schedule_day_container">';
					$output .= '<div class="schedule_week_breaks">';
					$output .= $this->generate_hidden_break( $week_day );
					$output .= $this->get_provider_breaks( $week_day );
					$output .= '</div>';
					$output .= '<span class="ga_add_break button button-ga">Add break</span>';
				$output .= "</div>"; //.grid-lg
			$output .= '</div>'; //.schedule_day
		}

		return $output;
	}

	private function generate_breaks_select( $week_day, $array ) {
		$field_id = $this->field_name;

		$output = '';
		foreach( $array as $begin => $end ) {
			$output .= '<div class="break_time">';
				// Begin
				$val  = $begin;
				$name = "{$this->field_name}[{$week_day}][begin][]";

				$output .= '<select name="'.$name.'" id="'.$field_id.'">';
				foreach( get_ga_appointment_time() as $time => $text ) {
					$selected = $val == $time ? ' selected' : '';
					$output .= '<option value='. $time .' '.$selected.'>'. $text .'</option>';
				}
				$output .= "</select>";

				$output .= '<span class="break_to">-</span>'; // Separator

				// End
				$val = $end;
				$name = "{$this->field_name}[{$week_day}][end][]";
				$output .= '<select name="'.$name.'" id="'.$field_id.'">';
				foreach( get_ga_appointment_time() as $time => $text ) {
					$selected = $val == $time ? ' selected' : '';
					$output .= '<option value='. $time .' '.$selected.'>'. $text .'</option>';
				}
				$output .= "</select>";
			$output .= '<span class="break-delete"></span>';
			$output .= '</div>';
		}

		return $output;
	}

	private function generate_hidden_break( $week_day ) {
		$field_id = $this->field_name;

		$output = '<div class="break_time" style="display:none;">';

			// Begin
			$name = "{$this->field_name}[{$week_day}][begin][]";
			$output .= '<select name="'.$name.'" id="'.$field_id.'">';
			foreach( get_ga_appointment_time() as $time => $text ) {
				$output .= '<option value='. $time .'>'. $text .'</option>';
			}
			$output .= "</select>";

			$output .= '<span class="break_to">-</span>'; // Separator

			// End
			$name = "{$this->field_name}[{$week_day}][end][]";
			$output .= '<select name="'.$name.'" id="'.$field_id.'">';
			foreach( get_ga_appointment_time() as $time => $text ) {
				$output .= '<option value='. $time .'>'. $text .'</option>';
			}
			$output .= "</select>";


		$output .= '<span class="break-delete"></span>';
		$output .= '</div>';

		return $output;
	}

	public function sort_breaks($a, $b) {
		return new DateTime($a) > new DateTime($b);
	}

	private function get_provider_breaks( $week_day ) {
		if( $this->post_id == 'no_provider' ) {
			$breaks_array  = get_option( 'ga_appointments_schedule_breaks' );
			$breaks        = (array) $breaks_array;
		} else {
			$breaks = (array) get_post_meta( $this->post_id, 'ga_provider_breaks', true );
		}

		if( array_key_exists($week_day, $breaks) && is_array( $breaks[ $week_day ] ) && count($breaks[$week_day]) > 0 ) {
			//$print = '<pre>' . print_r($breaks, true);
			//wp_die( $print );

			$output = $this->generate_breaks_select($week_day, $breaks[$week_day]);

			return $output;

		} else {
			return '';
		}
	}


	/**
	 * Validation: Breaks
	 */
	public function validate_breaks( $breaks ) {
		$breaks = (array) $breaks;

		foreach( $breaks as $key => $val ) {
			if( in_array($key, $this->week_days) && isset($val['begin']) && isset($val['end']) ) {
				// If no breaks are set, we need to check if Begin & End arrays exist, if not will trigger errors

				$breaks[$key] = array_combine( $val['begin'], $val['end'] );

				foreach( $breaks[$key] as $start => $end ) {
					if( new DateTime($end) <= new DateTime($start) ) {
						unset( $breaks[$key][$start] );
					}
				}

				uasort($breaks[$key], array($this, 'sort_breaks') );
			} else {
				unset($breaks[$key]);
			}
		}
		return $breaks;
	}


	/**
	 * Holidays Markup
	 */
	public function display_holidays( $name, $holidays ) {
		$out = '<div class="provider_holidays" style="margin-top:5px;"><div id="provider_holidays">
					<div class="holiday" style="display: none;">
						<input type="text" class="cmb2-text-small ga-date-picker" value="" name="'. $name .'[]" placeholder="Select date">
						<span class="holiday-delete"></span>
					</div>';

					if( $holidays ) {
						foreach( $holidays as $holiday ) {
							$out .= '<div class="holiday">
								<input type="text" class="cmb2-text-small ga-date-picker" value="'. $holiday .'" name="'. $name .'[]" placeholder="Select date">
								<span class="holiday-delete"></span>
							</div>';
						}
					}

				$out .= '</div><span class="ga_add_holiday button button-ga">Add a holiday</span></div>';
		return $out;
	}

	/**
	 * Validation: Holidays
	 */
	public function validate_holidays($value) {
		$value = (array) $value;

		foreach( $value as $key => $holiday ) {
			if( !ga_valid_date_format( $holiday ) ) {
				unset( $value[$key] );
			}
		}
		return array_unique($value);
	}
} // end class
