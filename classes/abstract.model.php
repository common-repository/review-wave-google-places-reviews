<?php if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Access denied.');

if (!defined('SQL_DATE_FORMAT')) define('SQL_DATE_FORMAT', 'Y-m-d H:i:s');


if (!class_exists('RWGplacesModel')) {

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  class RWGplacesModel {

	public $table_name;
	public $fields = array();
	public $serialized = array();


	public function add($data) {
		global $wpdb;

		$params = array();
		$values = array();

		foreach ($this->fields as $field=>$fdata) {
			$serialize = in_array($field, $this->serialized);

			if (isset($data[$field])) {
				$params[] = $field.'='.$fdata['format'];
				$values[] = $serialize ? serialize($data[$field]) : $data[$field];
			} else if (isset($fdata['default'])) {
				$params[] = $field.'='.$fdata['format'];
				$values[] = $serialize ? serialize($fdata['default']) : $fdata['default'];
			}
		}

		$sql = $wpdb->prepare("INSERT INTO `{$this->table_name}` SET ".implode(',', $params), $values);
		if ($wpdb->query($sql)) {
			return $wpdb->insert_id;
		} else {
			return false;
		}
	}

	public function deactivate() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS `{$this->table_name}`");
	}

	public function deleteAll() {
		global $wpdb;

		$wpdb->query("DELETE FROM `{$this->table_name}` WHERE 1");
	}

	public function getAll($limit=0) {
		global $wpdb;

        $sql = "SELECT * FROM `{$this->table_name}` ";
		if ($limit > 0) $sql .= 'LIMIT '.intval($limit);

        $objects = $wpdb->get_results($sql, OBJECT);

        if (!empty($objects)) {
			$list = array();
			foreach ($objects as $object) {
 				$list[] = $this->deserialize($object);
			}
			return $list;
		} else {
			return false;
		}
	}

	public function getByID($id_field, $id_value) {
		global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM `{$this->table_name}` WHERE $id_field=%s", $id_value);
        $object = $wpdb->get_results($sql, OBJECT);

        return !empty($object) ? $this->deserialize($object[0]) : false;
	}

	public function update($id_field, $id_value, $data=array()) {
		global $wpdb;

		$params = array();
		$format = array();

		foreach ($this->fields as $field=>$fdata) {
			$serialize = in_array($field, $this->serialized);

			if (isset($data[$field])) {
				$params[$field] = $serialize ? serialize($data[$field]) : $data[$field];
				$format[] = $fdata['format'];
			}
		}

		$rc = $wpdb->update($this->table_name, $params, array($id_field=>$id_value), $format, '%s');

		return !empty($rc);
	}


	protected function deserialize($objects) {
		if ($objects === false || $objects === null) return $objects;

		foreach ($this->serialized as $field) {
			if (is_object($objects)) {
				$objects->$field = unserialize($objects->$field);
			} else {
				foreach ($objects as $key=>$object) {
					$objects[$key]->$field = unserialize($objects[$key]->$field);
				}
			}
		}

		return $objects;
	}

  }

}

?>
