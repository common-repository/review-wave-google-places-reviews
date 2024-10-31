<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');

/*
 * Review Wave - Google Places Reviews Widget
 */

if (!class_exists("ReviewWaveGplaces_Widget")) {

class ReviewWaveGplaces_Widget extends WP_Widget {

	static $fields = array(
		'title'=>array(
			'title'=>'Widget Title',
			'default'=>'',
			),
		'place_id'=>array(
			'title'=>'Google Place',
			'default'=>'',
			),
		'place_name'=>array(
			'title'=>'Google Place',
			'default'=>'',
			),
		'place_hide'=>array(
			'title'=>'Show Reviews Only',
			'default'=>'',
			),
		'excerpt_len'=>array(
			'title'=>'Maximum Excerpt Length',
			'default'=>160,
			),
		'num_reviews'=>array(
			'title'=>'Show # Reviews',
			'default'=>5,
			),
		'ttl_reviews'=>array(
			'title'=>'Total # of Reviews',
			'default'=>0,
			),
		'min_rating'=>array(
			'title'=>'Minimum Review Rating',
			'default'=>0,
			),
		'disclaimer_link'=>array(
			'title'=>'Disclaimer Page',
			'default'=>'',
			'placeholder'=>'http://mysite.com/disclaimer',
			),
		);

	public function __construct() {
		$options = array(
			'classname'=>'review_wave_gplaces_reviews_widget',
			'description'=>'Display Google Places ratings and reviews on your site.',
			);
		parent::__construct('review_wave_gplaces_reviews_widget', 'Review Wave - Google Places Reviews', $options);
	}

	public function widget($args, $instance) {
		global $rw_gplaces;

		extract($args, EXTR_SKIP);

		$title = $instance['title'];

		echo $before_widget;

		$title = empty($title) ? ' ' : apply_filters('widget_title', $title);
		if (!empty($title)) { echo $before_title . $title . $after_title; };

		$data = $rw_gplaces->getWidgetData($instance['place_id'], $instance);
		if ($data) {
			echo $rw_gplaces->formatPlaceData($data['place']->place_data, $data['reviews'], $instance);
		}

		echo $after_widget;
	}

	public function form($instance) {
		global $rw_gplaces;

		$defaults = array();
		foreach (self::$fields as $field=>$fdata) {
			$defaults[$field] = $fdata['default'];
		}

		$instance = wp_parse_args((array)$instance, $defaults);

		foreach (self::$fields as $field=>$fdata) {
			$id = $this->get_field_id($field);
			$name = $this->get_field_name($field);
			$value = esc_attr(strip_tags($instance[$field]));
			$placeholder = isset($fdata['placeholder']) ? 'placeholder="'.$fdata['placeholder'].'"' : '';

			if (!in_array($field, array('place_id', 'place_name'))) {
				$html  = '<p>';
				$html .=  '<label for="'.$id.'">'.$fdata['title'].': ';
			} else {
				$html  = '';
			}

			switch ($field) {
			case 'min_rating':
				$html .= '<select class="widefat" id="'.$id.'" name="'.$name.'">';
				$selected = $value == 0 ? 'selected' : '';
				$html .=  '<option value="0" '.$selected.'>No Minimum</option>';
				for ($i = 5; $i > 0; $i--) {
					$selected = $value == $i ? 'selected' : '';
					$html .=  '<option value="'.$i.'" '.$selected.'>'.$i.' Star'.($i == 1 ? '' : 's').'</option>';
				}
				$html .=  '</select>';
				break;

			case 'place_hide':
				$html .= '<br/><input id="'.$id.'" name="'.$name.'" type="checkbox" value="1" '.($value?'checked':'').' /> ';
				$html .= 'Yes, hide the place information.';
				break;

			case 'place_id':
				$gp_id = $name;
				$gp_name = $this->get_field_name('place_name');

				if (!empty($rw_gplaces->options['api_key'])) {
					$html .= '<div class="rw-gplaces '.$id.'-wrap">';
					$html .= '<p>';
					$html .=  '<label for="'.$id.'-lookup">Place Lookup: ';
      				$html .=   '<input type="text" id="'.$id.'-lookup" value="" class="gp-place-lookup widefat" placeholder="Enter a place name or address" />';
					$html .=  '</label>';
					$html .= '</p>';
					$html .= '<p>';
					$html .=  '<label for="'.$id.'-type">Lookup Type: ';
      				$html .=   '<select id="'.$id.'-type" class="gp-place-type widefat">';
					$html .=    '<option value="">All</option>';
					$html .=    '<option value="establishment">Establishments</option>';
					$html .=    '<option value="address">Addresses</option>';
					$html .=    '<option value="geocode">Regions</option>';
      				$html .=   '</select>';
					$html .=  '</label>';
					$html .= '</p>';
					$html .= '<p>';
					$html .=  '<label for="'.$id.'-name">Place Name: ';
      				$html .=   '<input type="text" id="'.$id.'-name" name="'.$gp_name.'" value="'.$instance['place_name'].'" class="gp-place-name widefat" readonly />';
					$html .=  '</label>';
					$html .= '</p>';
					$html .= '<p>';
					$html .=  '<label for="'.$id.'-id">Place ID: ';
      				$html .=   '<input type="text" id="'.$id.'-id" name="'.$gp_id.'" value="'.$instance['place_id'].'" class="gp-place-id widefat" readonly />';
					$html .=  '</label>';
					$html .= '</p>';
					$html .= '</div>';
					$html .= '<script type="text/javascript">review_wave_init_gplaces(".rw-gplaces");jQuery(document).on("widget-updated widget-added", function(){review_wave_init_gplaces(".rw-gplaces");});</script>';
				} else {
					$html .= '<p>';
					$html .=  '<label for="'.$id.'-id">Place ID: ';
      				$html .=   '<input type="hidden" name="place_name" value="" class="gp-place-name" />';
      				$html .=   '<input type="text" id="'.$id.'-id" name="place_id" value="" class="gp-place-id widefat" />';
					$html .=  '</label>';
					$html .= '</p>';
				}
				break;

			case 'place_name':
				/* Do nothing. Part of place_id. */
				break;

			case 'disclaimer_link':
			case 'excerpt_len':
			case 'num_reviews':
			case 'title':
			case 'ttl_reviews':
			default:
				$html .= '<input class="widefat" id="'.$id.'" name="'.$name.'" type="text" value="'.$value.'" '.$placeholder.' />';
				break;
			}

			if (!in_array($field, array('place_id', 'place_name'))) {
				$html .=  '</label>';
				$html .= '</p>';
			}

			echo $html;
		}

		$rw_path = $rw_gplaces->plugin_dir_url().'css/';
		$html  =  '<div style="margin-bottom:.5em;text-align:center">';
		$html .=   'Courtesy of <a href="http://www.reviewwave.com" target="_blank"><img src="'.$rw_path.'review_wave.png" alt="Review Wave" title="Review Wave" style="max-height:12px" /></a>';
		$html .=  '</div>';

		echo $html;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;

		foreach (self::$fields as $field=>$fdata) {
			$value = isset($new_instance[$field]) ? $new_instance[$field] : '';

			switch ($field) {
			case 'disclaimer_link':
				if (!empty($value)) {
					$scheme = parse_url($value, PHP_URL_SCHEME);
					if (empty($scheme)) $value = 'http://'.ltrim($value, ':/');
				}
				break;

			case 'min_rating':
			case 'num_reviews':
			case 'ttl_reviews':
				$value = intval($value);
				break;

			default:
				$value = strip_tags($value);
				break;
			}

			$instance[$field] = $value;
		}

		return $instance;
	}


}

}

?>
