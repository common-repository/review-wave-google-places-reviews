<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');
# vim:ts=4:nowrap

/**
 * @package Review Wave - Google Places Reviews
 *
 * Copyright 2017 Todd Crowe (todd@toddcrowe.com)
 */

/*
Plugin Name: Review Wave - Google Places Reviews
Description: Review Wave - Google Places Reviews
Author: MessageMetric
Version: 1.4.7
Author URI: http://www.reviewwave.com
*/

if (!defined('RW_GPLACES_DEBUG'))         define('RW_GPLACES_DEBUG', false);
if (!defined('RW_GPLACES_LIFETIME'))      define('RW_GPLACES_LIFETIME', 86400);
if (!defined('RW_GPLACES_TEST_MODE'))     define('RW_GPLACES_TEST_MODE', false);


if (!class_exists("RWGooglePlaces")) :

require_once('classes/abstract.model.php');
require_once('classes/place.model.php');
require_once('classes/review.model.php');
require_once('classes/widget.class.php');


class RWGooglePlaces {
	var $version = '1.4.7';
	var $plugin_name = 'Review Wave - Google Places Reviews';
	var $plugin_plug = 'rw_gplaces';
	var $options = array();

	var $post_error = '';
	var $post_message = '';

	private $last_error = false;


	function __construct() {
		$this->load_options();

		/* Initialize the session now after options have been loaded so that auth info is available everywhere. */
		$this->init_session();

		if (!empty($_POST['rw_gplaces_save_config'])) $this->handle_config_options_page();

		if (is_admin()) {
			/* Dashboard initialization */
			add_action('admin_menu', array(&$this, 'add_menu_pages'));
			add_action('init', array(&$this, 'enqueue_backend'));
		} else {
			add_action('init', array(&$this, 'enqueue_frontend'));
		}

		register_activation_hook(__FILE__, array(&$this, 'activate_plugin'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate_plugin'));

		add_action('widgets_init', create_function('', 'return register_widget("ReviewWaveGplaces_Widget");'));
		add_shortcode('review_wave_gplaces', array(&$this, 'shortcode_fn'));
	}


	function activate_plugin() {
		global $rw_gplaces_place;
		global $rw_gplaces_review;

		$rw_gplaces_place->activate();
		$rw_gplaces_review->activate();
	}

	function add_menu_pages() {
		global $rw_gplaces_place;

		$config_name = $this->plugin_name . " " . __('Options', $this->plugin_plug);

		add_menu_page($this->plugin_name, $this->plugin_name, 'administrator', $this->plugin_plug.'_menu',
			array(&$this, 'format_options_page'), $this->plugin_dir_url(__FILE__).'star.png');
		add_submenu_page($this->plugin_plug.'_menu', 'Settings', 'Settings', 'administrator', $this->plugin_plug.'_menu',
			array(&$this, 'format_options_page'));

		$places = $rw_gplaces_place->getAll();
		if ($places) {
			add_submenu_page($this->plugin_plug.'_menu', 'Reviews', 'Reviews', 'administrator', $this->plugin_plug.'_menu2',
				array(&$this, 'format_reviews_page'));
		}

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2);
	}

	function deactivate_plugin() {
		global $rw_gplaces_place;
		global $rw_gplaces_review;

		//$rw_gplaces_place->deactivate();
		//$rw_gplaces_review->deactivate();
	}

	/* enqueue_backend
	 */
	function enqueue_backend() {
		$url = $this->plugin_dir_url(__FILE__);

		wp_enqueue_style('rw_gplaces_css', $url.'css/rw_gplaces.css', array(), $this->version);

		if (!empty($this->options['api_key'])) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('google_places_js', 'https://maps.googleapis.com/maps/api/js?key='.$this->options['api_key'].'&libraries=places', array(), $this->version, false);
			wp_enqueue_script('rw_gplaces_js', $url.'/js/rw_gplaces.js', array('google_places_js'), $this->version, false);
		}
	}

	function enqueue_frontend() {
		$url = $this->plugin_dir_url(__FILE__);

		wp_enqueue_style('rw_gplaces_css', $url.'css/rw_gplaces.css', array(), $this->version);

		if (!empty($this->options['api_key'])) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('google_places_js', 'https://maps.googleapis.com/maps/api/js?key='.$this->options['api_key'].'&libraries=places', array(), $this->version, true);
			wp_enqueue_script('rw_gplaces_js', $url.'/js/rw_gplaces.js', array(), $this->version, true);
		}
	}

	function filter_plugin_actions($links, $file) {
		$settings_link = '<a href="/wp-admin/admin.php?page='.$this->plugin_plug.'_menu">'.__('Settings').'</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function init_session() {
		if (!session_id()) session_start();
	}

	function load_options() {
		$save = false;
		$the_options = get_option($this->plugin_plug.'_options');

		if (empty($the_options['api_key'])) { $the_options['api_key'] = ''; $save = true; }
		if (empty($the_options['place_name'])) { $the_options['place_name'] = ''; $save = true; }
		if (empty($the_options['place_id'])) { $the_options['place_id'] = ''; $save = true; }
		if (empty($the_options['version']) || $the_options['version'] != $this->version) {
			if (empty($the_options['version'])) {
				global $rw_gplaces_place, $rw_gplaces_review;
				$rw_gplaces_place->deleteAll();
				$rw_gplaces_review->deleteAll();
			}

			$this->activate_plugin();
			$the_options['version'] = $this->version;
			$save = true;
		}

		$this->options = $the_options;

		if ($save) $this->save_options();
	}

	function plugin_dir_url($file=__FILE__) {
		$plugin_dir_url = get_option('siteurl') . '/' . PLUGINDIR . '/' .  basename(dirname($file)) . '/';
		return $plugin_dir_url;
	}

	function save_options() {
		if (empty($this->options)) {
			delete_option($this->plugin_plug.'_options');
		} else {
			update_option($this->plugin_plug.'_options', $this->options);
		}
	}

	function shortcode_fn($atts, $content=null) {
		$atts = shortcode_atts(array(
			'disclaimer_link'=>'',
			'excerpt_len'=>0,
			'min_rating'=>0,
			'num_reviews'=>3,
			'place_id'=>'',
			'place_hide'=>0,
			'title'=>'',
			'ttl_reviews'=>0,
			),
			$atts);

		$html = '';

		if (!empty($atts['title'])) {
			$html .= '<h3 class="gplaces-rw-title shortcode-title">'.$atts['title'].'</h3>';
		}

		$data = $this->getWidgetData($atts['place_id'], $atts);
		if ($data) {
			$html .= $this->formatPlaceData($data['place']->place_data, $data['reviews'], $atts);
		}

		return $html;
	}


	/* format_options_page
	 *
	 * Settings configuration page
	 */
	function format_options_page() {
		if (!empty($_POST['rw_gplaces_test'])) {
			$rc = $this->test();
			if ($rc !== false) {
				$this->post_message = $rc;
			} else {
				$this->post_error = 'Test Failed.';
			}
		}
?>
<img src="https://pixel-geo.prfct.co/sseg?add=6392023&source=js_tag&a_id=57423" width="1" height="1" border="0" />
<div class="gplaces-rw-settings wrap">
  <a href="http://reviewwave.com/?utm_source=wpplugin&utm_medium=banner&utm_campaign=google" style="display:block;text-align:center">
   <img src="<?php echo $this->plugin_dir_url(__FILE__).'banner.jpg' ?>" />
  </a>
  <h2><?php echo $this->plugin_name ?></h2>
  <?php if (!empty($this->post_error)) { ?>
  <p style="color: #C00; margin: 4px 0px 4px 10px">Error: <?php echo $this->post_error ?></p>
  <?php } else if (!empty($this->post_message)) { ?>
  <p style="color: #0A0; margin: 4px 0px 4px 10px"><?php echo $this->post_message ?></p>
  <?php } ?>

  <form name="<?php echo $this->plugin_plug ?>'_options_form" method="post">
    <table class="gplaces-rw-form-table rw-gplaces form-table">

    <tr valign="top">
      <th scope="row" colspan="2"><h3 style="margin:0"><?php _e("Plugin Settings:", $this->plugin_plug); ?></h3></th>
    </tr>
    <tr valign="top">
      <th scope="row"><?php _e('API Key:', $this->plugin_plug); ?></th>
      <td>
        <input type="text" name="api_key" value="<?php echo $this->options['api_key'] ?>" class="widefat" autocomplete="off" />
      </td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>
        <input type="submit" class="button-primary" name="rw_gplaces_save_config" value="<?php _e('Save Changes') ?>" />
        <?php if (RW_GPLACES_TEST_MODE && !empty($this->options['api_key'])) { ?>
        <input type="submit" class="button-secondary" name="rw_gplaces_test" value="Test" />
        <?php } ?>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row">&nbsp;</th>
      <td>
        <div>Follow these steps to create a Google API key:</div>
          <ol>
          <li>Go to the <a href="https://console.developers.google.com/flows/enableapi?apiid=places_backend&keyType=SERVER_SIDE&reusekey=true" target="_blank">Google API Console.</a></li>
          <li>Create or select a project.</li>
          <li>On the API Manager Credentials page at <a href="https://console.developers.google.com/apis/credentials" target="_blank">
            https://console.developers.google.com/apis/credentials</a>, create an API key (Server key).</li>
          <li>Copy the key Google returns into the API Key field above.</li>
          <li>We now need to enable 2 Google API's for this to work for you:
            <ol>
            <li>Please go to:<br/>
              <a href="https://console.developers.google.com/apis/library" target="_blank">https://console.developers.google.com/apis/library</a><br/>
              BOTH APIs to enable are under the title "Google Maps APIs"</li>
            <li>Click Google Maps JavaScript API (click link) or go to
              <a href="https://console.developers.google.com/apis/api/maps_backend/overview" target="_blank">https://console.developers.google.com/apis/api/maps_backend/overview</a></li>
            <li>Click (Enable)</li>
            <li>Go back to <a href="https://console.developers.google.com/apis/library">https://console.developers.google.com/apis/library</a></li>
            <li>Again under the title "Google Maps APIs" click "More" to drop down additional API list.</li>
            <li>Find Google Places API Web Service (click) or go directly to
              <a href="https://console.developers.google.com/apis/api/places_backend/overview" target="_blank">https://console.developers.google.com/apis/api/places_backend/overview</a></li>
            <li>Click (Enable)</li>
            </ol>
          </li>
          </ol>
        <div>Note: If you have an existing Server key, you may use that key.</div>
        <br/>
        <div><em>Reference: <a href="https://developers.google.com/places/web-service/get-api-key" target="_blank">
           https://developers.google.com/places/web-service/get-api-key</a></em></div>
      </td>
      <td>&nbsp;</td>
    </tr>
    <?php if (!empty($this->options['api_key'])) { ?>
    <tr valign="top"><td colspan="2"><hr/></td></tr>
    <tr valign="top"><th colspan="2"><h3 style="margin:0">Instructions</h3></th></tr>
    <tr valign="top">
      <td colspan="2">
        Use the Review Wave - Google Places Reviews widget or shortcode to display reviews. Use the field below to find place ID for use in shortcodes:
      </td>
    </tr>
    <tr valign="top">
      <th scope="row"><?php _e('Place Lookup:', $this->plugin_plug); ?></th>
      <td><input type="text" value="" class="gp-place-lookup widefat" placeholder="Enter a place name or address" /></td>
    </tr>
    <tr valign="top">
      <th scope="row"><?php _e('Lookup Type:', $this->plugin_plug); ?></th>
      <td>
        <select class="gp-place-type widefat">
          <option value="">All</option>
          <option value="establishment">Establishment</option>
          <option value="address">Address</option>
          <option value="geocode">Geocodes</option>
        </select>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row"><?php _e('Place Name:', $this->plugin_plug); ?></th>
      <td><input type="text" name="place_name" value="<?php echo $this->options['place_name'] ?>" class="gp-place-name widefat" autocomplete="off" readonly /></td>
    </tr>
    <tr valign="top">
      <th scope="row"><?php _e('Place ID:', $this->plugin_plug); ?></th>
      <td><input type="text" name="place_id" value="<?php echo $this->options['place_id'] ?>" class="gp-place-id widefat" autocomplete="off" readonly /></td>
    </tr>
    <tr valign="top">
      <td colspan="2">
        <p style="font-family:monospace;font-size:.875em;margin:0;white-space:pre;">[review_wave_gplaces title="<span class="gp-place-name">{title}</span>" place_id="<span class="gp-place-id">{place id}</span>"]
[review_wave_gplaces title="<span class="gp-place-name">{title}</span>" place_id="<span class="gp-place-id">{place id}</span>" excerpt_len="160" min_rating="4" num_reviews="5" place_hide="0" ttl_reviews="10"]
        <p>
        <div class="shortcode-info">The following shortcode options are supported:<br/>
        <dl>
        <dt>place_id</dt><dd>Place ID for the business you want to show reviews for.  Use the Place Lookup above to find a Place ID.</dd>
        <dt>disclaimer_link</dt><dd>Optional URL of disclaimer page to link to for each review.</dd>
        <dt>excerpt_len</dt><dd>Set the length of the excerpt to show for reviews.  Reviews longer than this will be truncated and have a link to show the full review.
          You may also set the excerpt length to 0 to show full reviews regardless of their length.</dd>
        <dt>min_rating</dt><dd>Set the minimum rating level to prevent low ratings from being displayed. The default is 4.</dd>
        <dt>num_reviews</dt><dd>Set the number of reviews to be displayed. The default is 3.</dd>
        <dt>place_hide</dt><dd>Use place_hide=1 to show only reviews and to hide business address and other information.</dd>
        <dt>title</dt><dd>Optional title to display.</dd>
        <dt>ttl_reviews</dt><dd>Set the total number of reviews available for the business.  Useful if the review schema is important to your site.</dd>
        <dl>
        </div>
      </td>
    </tr>
	<script type="text/javascript">review_wave_init_gplaces('.gplaces-rw-form-table')</script>
    <?php } ?>

    </table>
  </form>

</div>

<?php
	}

	function format_reviews_page() {
		global $rw_gplaces_place;
		global $rw_gplaces_review;

		$options = array('promote'=>'Promote', 'hide'=>'Hide');

		$places = $rw_gplaces_place->getAll();
		$place_id = isset($_POST['place_id']) ? $_POST['place_id'] : $places[0]->place_id;

		if (!empty($_POST['do_action']) && !empty($_POST['bulk_action'])) {
			switch ($_POST['bulk_action']) {
			case 'hide':
			case 'promote':
				$weight = $_POST['bulk_action'] == 'hide' ? -1 : 10;
				foreach ($_POST['reviews'] as $rwr_id) {
					$rw_gplaces_review->updateByReviewWaveID($rwr_id, array('review_weight'=>$weight));
				}
				break;
			}
		}

		$reviews = $rw_gplaces_review->getByPlaceID($place_id, array('promoted'=>1));

		echo '<div class="wrap">';
		echo '<a href="http://reviewwave.com/?utm_source=wpplugin&utm_medium=banner&utm_campaign=google" style="display:block;text-align:center">';
		echo  '<img src="'.$this->plugin_dir_url(__FILE__).'banner.jpg'.'" />';
		echo '</a>';
  		echo '<h2>Google Places Reviews</h2>';
  		if (!empty($this->post_error)) {
  			echo '<p style="color: #C00; margin: 4px 0px 4px 10px">Error: '.$this->post_error.'</p>';
  		} else if (!empty($this->post_message)) {
  			echo '<p style="color: #0A0; margin: 4px 0px 4px 10px">'.$this->post_message.'</p>';
		}

  		echo '<form name="'.$this->plugin_plug.'_reviews_form" method="post">';

		echo '<div class="tablenav top">';
		echo  'Place: <select name="place_id">';
		foreach ($places as $place) {
			$selected = $place_id == $place->place_id ? 'selected': '';
			echo '<option value="'.$place->place_id.'" '.$selected.'>'.$place->place_data['name'].'</option>';
		}
		echo  '</select>';
		echo  '<input type="submit" class="button action" value="Go">';
		echo  '&nbsp; &nbsp;';
		echo  'Action: <select name="bulk_action" class="bulk-actions">';
		foreach ($options as $oval=>$otitle) {
			$selected = in_array($oval, array('promote')) ? 'selected' : '';
			echo '<option value="'.$oval.'" '.$selected.'>'.$otitle.'</option>';
		}
		echo  '</select>';
		echo  '<input type="submit" name="do_action" id="doaction" class="button action" value="Apply">';
		echo '</div>';

		echo '<table class="wp-list-table widefat fixed">';
		echo '<thead>';
		echo '<tr valign="top">';
		echo  '<th width="5%" scope="col"><input type="checkbox" class="bulk-action-all" style="margin:0" /></th>';
		echo  '<th width="10%" scope="col">Status</th>';
		echo  '<th width="10%" scope="col">Rating</th>';
		echo  '<th width="15%" scope="col">Reviewer</th>';
		echo  '<th width="50%" scope="col">Review</th>';
		echo  '<th width="15%" scope="col">Date</th>';
		echo '</thead>';

		$alt = true;
		echo '<tbody id="the-list">';
		if (!empty($reviews)) {
			foreach ($reviews as $review) {
				$date = date('M jS, Y', strtotime($review->review_date));

				echo '<tr valign="top" class="'.($alt?'alternate':'').'">';
				echo  '<td scope="col"><input type="checkbox" name="reviews[]" class="bulk-action-one" value="'.$review->rwr_id.'" /></td>';
				echo  '<td scope="col">'.$this->formatStatus($review->review_weight).'</td>';
				echo  '<td scope="col">'.$this->formatRating($review->review_rating).'</td>';
				echo  '<td scope="col">'.$review->review_data['user_name'].'</td>';
				echo  '<td scope="col">'.$review->review_data['excerpt'].'</td>';
				echo  '<td scope="col">'.$date.'</td>';
				echo '</tr>';

				$alt = !$alt;
			}
		}
		echo '<tr valign="top">';
		if (!empty($reviews)) {
			echo '<td scope="col" colspan="12" style="border-top:1px solid #E1E1E1;text-align:center">'.count($reviews).' review(s) found.</td>';
		} else {
			echo '<td scope="col" colspan="12" style="text-align:center">No reviews found.</td>';
		}
		echo  '</td>';
		echo '</tr>';
		echo '</tbody>';

		echo '</table>';
  		echo '</form>';
		echo '</div>';
	}

	function handle_config_options_page() {
		$err = false;

		if (!empty($_POST['rw_gplaces_save_config'])) {
			$fields = array(
				'api_key', 
				);
			if (RW_GPLACES_TEST_MODE) {
				$fields[] = 'place_name';
				$fields[] = 'place_id';
			}

			foreach ($fields as $field) {
				$value = isset($_POST[$field]) ? $_POST[$field] : '';

				$this->options[$field] = $value;
			}

			$this->save_options();

			if ($err) {
				$this->post_error = $err;
			} else {
				$this->post_message = 'Your changes were sucessfully saved. ';
			}
		}
	}


	/* Public/Exported functions
	 */

	public function formatPlaceData($place, $reviews, $options) {
		$hidden = !empty($options['place_hide']) ? 'gplaces-rw-hidden' : '';
		$rw_path = $this->plugin_dir_url().'css/';

		$itemReviewed =
			'<div itemprop="itemReviewed" itemscope itemtype="http://schema.org/LocalBusiness" class="gplaces-rw-hidden">'.
			 '<span itemprop="name">'.$place['name'].'</span>'.
			'</div>';
		$disclaimer = !empty($options['disclaimer_link'])
			? ' <a href="'.$options['disclaimer_link'].'" target="_blank">*</a>'
			: '';

		$html  = '<div class="gplaces-rw-wrap">';

		$html .=  '<div class="gplaces-rw-place-wrap gplaces-rw-clear '.$hidden.'">';
		$html .=   '<div class="gplaces-rw-place-img">';
		$html .=    '<img src="'.$place['image_url'].'" alt="'.$place['name'].'" />';
		$html .=   '</div>';
		$html .=   '<div class="gplaces-rw-place-info">';
		$html .=    '<div class="place-name-wrap">';
		$html .=     '<a class="place-name" href="'.$place['url'].'" title="'.$place['name'].'" rel="nofollow">'.$place['name'].'</a>';
		$html .=    '</div>';
		$html .=    '<div class="place-rating-wrap" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
		$html .=     $itemReviewed;
		$html .=     $this->formatRating($place['rating']);
		$html .=     '<div class="place-review-count">';
		$html .=      '<div class="place-rating-value">';
		$html .=       '<span itemprop="ratingValue">'.$place['rating'].'</span> out of 5 stars';
		$html .=      '</div>';
		$html .=      '<span itemprop="reviewCount">'.$place['review_count'].'</span>';
		$html .=      ' review'.($place['review_count'] == 1 ? '' : 's');
		$html .=     '</div>';
		$html .=    '</div>';
		$html .=   '</div>';
		$html .=  '</div>';

		$html .=  '<div class="gplaces-rw-address-wrap '.$hidden.'">';
		$html .=   '<div class="address-line">'.$place['location'].'</div>';
		if (!empty($place['phone'])) {
			$html .= '<div class="phone-line">'.$place['phone'].'</div>';
		}
		$html .=  '</div>';

		$html .=  '<div class="gplaces-rw-reviews-wrap">';
		foreach ($reviews as $review) {
			$data = $review->review_data;

			$user_url = !empty($data['user_url']) ? $data['user_url'] : '#';

			$excerpt = $data['excerpt'];
			if ($options['excerpt_len'] && strlen($excerpt) > $options['excerpt_len']) {
				$break_at = strpos(wordwrap($excerpt, $options['excerpt_len']-3), "\n");
				$excerpt =
					substr($excerpt, 0, $break_at).
					'<span class="rwr-toggle-'.$review->rwr_id.'">...</span>'.
					$disclaimer.
					'<div class="rwr-toggle-'.$review->rwr_id.'"><a href="#" class="rwr-toggle" data-id="'.$review->rwr_id.'">Read Full Review</a></div>'.
					'<span class="rwr-toggle-'.$review->rwr_id.'" style="display:none">'.
					substr($excerpt, $break_at).
					'</span>';
			} else {
				$excerpt .= $disclaimer;
			}

			$user_img_url = $data['user_img_url'];
			if (substr($user_img_url, -10) === 'person.png') $user_img_url = $this->plugin_dir_url().'css/person.png';

			$html .= '<div class="gplaces-rw-review-wrap" itemprop="review" itemscope itemtype="http://schema.org/Review">';
			$html .=  $itemReviewed;
			$html .=  '<div class="gplaces-rw-review gplaces-rw-clear">';
			$html .=   '<div class="gplaces-rw-reviewer-img">';
			$html .=    '<img src="'.$user_img_url.'" alt="'.$data['user_name'].'" />';
			$html .=   '</div>';
			$html .=   '<div class="gplaces-rw-reviewer-info">';
			$html .=    '<div class="reviewer-name-wrap">';
			$html .=     '<div class="reviewer-name" itemprop="author">'.$data['user_name'].'</div>';
			$html .=    '</div>';
			$html .=    '<div class="reviewer-rating-wrap">';
			$html .=     $this->formatRating($review->review_rating);
			$html .=     '<div class="reviewer-rating-range" itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating">';
			$html .=      '<meta itemprop="worstRating" content = "1" />';
			$html .=      '<span itemprop="ratingValue">'.$review->review_rating.'</span> out of <span itemprop="bestRating">5</span> stars';
			$html .=     '</div>';
			$html .=     '<div class="reviewer-rating-date" itemprop="datePublished" content="'.date('Y-m-d', $data['created']).'">';
			$html .=      'posted '.$this->getRelativeAge($data['created']);
			$html .=     '</div>';
			$html .=    '</div>';
			$html .=   '</div>';
			$html .=  '</div>';
			$html .=  '<div class="gplaces-rw-comments-wrap">';
			$html .=   '<div itemprop="description">'.$excerpt.'</div>';
			$html .=  '</div>';
			$html .= '</div>';
		}
		$html .=  '</div>';

		$html .= '</div>';
		$html .= '<script type="text/javascript">jQuery(".rwr-toggle").click(function(e){jQuery(".rwr-toggle-"+jQuery(this).data("id")).toggle();e.preventDefault();});</script>';

		return $html;
	}

	public function formatRating($rating) {
		$html  = '<div class="gplaces-rw-rating-wrap">';
		$html .=  '<div class="gplaces-rw-rating-container">';
		$html .=   '<div class="gplaces-rw-rating-gray"></div>';
		$html .=   '<div class="gplaces-rw-rating-color" style="width:'.(($rating * 84) / 5).'px"></div>';
		$html .=  '</div>';
		$html .= '</div>';

		return $html;
	}

	public function formatStatus($weight) {
		if ($weight < 0) {
			return '<span style="color:red">Hidden</span>';
		} else if ($weight > 0) {
			return '<span style="color:green">Promoted</span>';
		} else {
			return 'Active';
		}
	}

	public function getPlace($place_id) {
		return $this->gplaces_request(array('placeid'=>$place_id));
	}

	public function getRelativeAge($time) {
		$time = time() - $time;

		$plural = '';
		$mins = 60;
		$hour = $mins * 60;
		$day = $hour * 24;
		$week = $day * 7;
		$month = $day * 30;
		$year = $day * 365;

		$segments = array();
		$segments['year']   = intval($time / $year);  $time %= $year;
		if (!$segments['year']) {
			$segments['month'] = intval($time / $month); $time %= $month;
			if (!$segments['month']) {
				$segments['day']  = intval($time / $day);   $time %= $day;
				if (!$segments['day']) {
					$segments['hour']   = intval($time / $hour);  $time %= $hour;
					if (!$segments['hour']) {
						$segments['min']  = intval($time / $mins);  $time %= $mins;
					}
				}
			}
		}

		$relTime = '';
		foreach ($segments as $unit=>$cnt) {
			if ($segments[$unit]) {
				$relTime .= "$cnt $unit";
				if ($cnt > 1) $relTime .= 's';
				$relTime .= ', ';
			}
		}
		$relTime = substr($relTime, 0, -2);
		if (!empty($relTime)) {
			return "$relTime ago";
		} else {
			return "just now";
		}
	}

	public function getWidgetData($place_id, $options=array()) {
		global $rw_gplaces_place;
		global $rw_gplaces_review;

		$options = array_merge(array(
			'active'=>1,
			'min_rating'=>4,
			'num_reviews'=>3,
			'promoted'=>1,
			'ttl_reviews'=>0,
			), $options);

		$place = $rw_gplaces_place->getByPlaceID($place_id);
		if (   !$place
			|| (time() - RW_GPLACES_LIFETIME > strtotime($place->place_date))) {
			$this->cache_place($place_id, $place === false);
			$place = $rw_gplaces_place->getByPlaceID($place_id);
		}

		if ($place) {
			$place->place_data['review_count'] = $options['ttl_reviews'];

			$reviews = $rw_gplaces_review->getByPlaceID($place_id, $options);

			$data = array(
				'place'=>$place,
				'reviews'=>$reviews,
				);
		} else {
			$data = false;
		}

		return $data;
	}


	/* Protected functions
	 */

	private function test() {
		$fn = 'get_place';

		$rc = false;
		switch ($fn) {
		case 'get_place':
			$place = $this->getPlace($this->options['place_id']);
			error_log('rw_gplaces: getPlace: '.var_export($place, 1));
			$rc = is_array($place) && !empty($place) ? 'Place information retrieved successfully.' : false;
			break;
		}

		return $rc;
	}


	/* Private functions
	 */

	private function cache_place($place_id, $add=false, $options=array()) {
		global $rw_gplaces_place;
		global $rw_gplaces_review;

		$data = $this->getPlace($place_id);
		if (!is_array($data['result']) || empty($data['result'])) return false;

		$data = $data['result'];

		$rw_path = $this->plugin_dir_url().'css/';
		$data['image_url'] = $data['photos'] ? add_query_arg(array(
				'key'=>$this->options['api_key'],
				'maxwidth'=>'300',
				'maxheight'=>'300',
				'photoreference'=>$data['photos'][0]['photo_reference'],
				), 'https://maps.googleapis.com/maps/api/place/photo') : $rw_path.'location.png';

		$params = array(
			'place_data'=>array(
				'image_url'=>$data['image_url'],
				'location'=>preg_replace('/,/', '<br/>', $data['formatted_address'], 1),
				'name'=>$data['name'],
				'phone'=>$data['formatted_phone_number'],
				'rating'=>$data['rating'],
				'review_count'=>!empty($options['ttl_reviews']) ? $options['ttl_reviews'] : 0,
				'url'=>$data['url'],
				),
			'place_date'=>date(SQL_DATE_FORMAT),
			);
			
		if ($add) {
			$params['place_id'] = $place_id;
			$rw_gplaces_place->add($params);
		} else {
			$rw_gplaces_place->updateByPlaceID($place_id, $params);
		}

		foreach ($data['reviews'] as $item) {
			$review_id = (isset($item['author_url']) && stripos($item['author_url'], 'https://plus.google.com/') === 0)
				? str_replace('https://plus.google.com/', '', $item['author_url']) : 't-'.$item['time'];

			$review =  $rw_gplaces_review->getByReviewID($review_id);
			if (!$review) {
				if (isset($item['author_url'])) {
                	$request_url = add_query_arg(array('alt'=>'json'), 'https://picasaweb.google.com/data/entry/api/user/'.$review_id);
                	$response = wp_remote_get($request_url);
	                $body = json_decode(wp_remote_retrieve_body($response), true);
					$avatar_url = preg_replace( "/^http:/i", "https:", $body['entry']['gphoto$thumbnail']['$t']);
				} else {
					$avatar_url = false;
				}

                $avatar_url = !empty($avatar_url) ? $avatar_url : 'person.png';

				$rw_gplaces_review->add(array(
					'review_id'=>$review_id,
					'place_id'=>$place_id,
					'review_rating'=>$item['rating'],
					'review_data'=>array(
						'created'=>$item['time'],
						'excerpt'=>$item['text'],
						'user_img_url'=>$avatar_url,
						'user_name'=>!empty($item['author_name']) ? $item['author_name'] : '',
						'user_url'=>!empty($item['author_url']) ? $item['author_url'] : '',
						),
					'review_date'=>date(SQL_DATE_FORMAT),
					));
			}
		}

		return $place;
	}

	private function gplaces_request($args) {
		$args['key'] = $this->options['api_key'];
		$url = add_query_arg($args, 'https://maps.googleapis.com/maps/api/place/details/json');

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$data = curl_exec($ch);
		curl_close($ch);

		return !empty($data) ? json_decode($data, true) : false;
	}
}

endif;

global $rw_gplaces;
$rw_gplaces = new RWGooglePlaces();

?>
