<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');

global $rw_gplaces_review;

if (empty($rw_gplaces_review)) {

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  class RWGplacesReviewModel extends RWGplacesModel {


	public function __construct() {
		global $wpdb;

        $this->table_name = $wpdb->prefix . 'rw_gplaces_review';
		$this->fields = array(
			'rwr_id'=>array('format'=>'%d'),
			'review_id'=>array('default'=>'', 'format'=>'%s'),
			'place_id'=>array('default'=>'', 'format'=>'%s'),
			'review_rating'=>array('default'=>5, 'format'=>'%d'),
			'review_data'=>array('default'=>'', 'format'=>'%s'),
			'review_weight'=>array('default'=>'', 'format'=>'%d'),
			'review_date'=>array('default'=>'0000-00-00 00:00:00', 'format'=>'%s'),
			);
		$this->serialized = array('review_data');
	}

	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$this->table_name}` (
		        `rwr_id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
		        `review_id`        VARCHAR(255) NOT NULL DEFAULT '',
		        `place_id`         VARCHAR(255) NOT NULL DEFAULT '',
		        `review_rating`    INT(11) NOT NULL DEFAULT '5',
		        `review_data`      TEXT NOT NULL DEFAULT '',
		        `review_weight`    INT(11) NOT NULL DEFAULT '0',
		        `review_date`      DATETIME NOT NULL DEFAULT '0000-00-00',
		        PRIMARY KEY  (rwr_id)
		        ) $charset_collate;";

		dbDelta($sql);
	}

	public function getByPlaceID($place_id, $options=array()) {
		global $wpdb;

		$sql = "SELECT * FROM `{$this->table_name}` WHERE place_id=%s ";
		$params = array($place_id);

		foreach ($options as $option=>$value) {
			switch ($option) {
			case 'active';
				if ($value) $sql .= "AND review_weight >= 0 ";
				break;

			case 'min_rating';
				$sql .= "AND review_rating >= %d ";
				$params[] = $value;
				break;
			}
		}

		if (!empty($options['promoted'])) {
			$sql .= 'ORDER BY review_weight DESC, review_date DESC ';
		} else {
			$sql .= 'ORDER BY review_date DESC ';
		}

		if (isset($options['num_reviews']) && $options['num_reviews'] > 0) {
			$sql .= 'LIMIT '.intval($options['num_reviews']).' ';
		}

        $sql = $wpdb->prepare($sql, $params);

        return $this->deserialize($wpdb->get_results($sql, OBJECT));
	}

	public function getByReviewID($review_id) {
		return parent::getByID('review_id', $review_id);
	}

	public function updateByReviewWaveID($rwr_id, $data=array()) {
		return parent::update('rwr_id', $rwr_id, $data);
	}

	public function updateByReviewID($review_id, $data=array()) {
		return parent::update('review_id', $review_id, $data);
	}

  }

  $rw_gplaces_review = new RWGplacesReviewModel();

}

?>
