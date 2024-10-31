<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');

global $rw_gplaces_place;

if (empty($rw_gplaces_place)) {

  class RWGplacesPlaceModel extends RWGplacesModel {

	public function __construct() {
		global $wpdb;

        $this->table_name = $wpdb->prefix . 'rw_gplaces_place';
		$this->fields = array(
			'rwp_id'=>array('format'=>'%d'),
			'place_id'=>array('default'=>'', 'format'=>'%s'),
			'place_data'=>array('default'=>'', 'format'=>'%s'),
			'place_date'=>array('default'=>'0000-00-00 00:00:00', 'format'=>'%s'),
			);
		$this->serialized = array('place_data');
	}

	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$this->table_name}` (
		        `rwp_id`     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
		        `place_id`   VARCHAR(255) NOT NULL DEFAULT '',
		        `place_data` TEXT NOT NULL DEFAULT '',
		        `place_date` DATETIME NOT NULL DEFAULT '0000-00-00',
		        PRIMARY KEY  (rwp_id)
		        ) $charset_collate;";

		dbDelta($sql);
	}

	public function getByPlaceID($place_id) {
		return parent::getByID('place_id', $place_id);
	}

	public function updateByPlaceID($place_id, $data=array()) {
		return parent::update('place_id', $place_id, $data);
	}

  }

  $rw_gplaces_place = new RWGplacesPlaceModel();

}

?>
