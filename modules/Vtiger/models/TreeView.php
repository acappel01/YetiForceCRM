<?php

/**
 * Basic TreeView Model Class
 * @package YetiForce.TreeView
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Vtiger_TreeView_Model extends Vtiger_Base_Model
{

	static $_cached_instance;

	/**
	 * Function to get the Module Name
	 * @return string Module name
	 */
	public function getModuleName()
	{
		return $this->get('module')->get('name');
	}

	/**
	 * Active tree tab
	 * @return boolean
	 */
	public function isActive()
	{
		return false;
	}

	/**
	 * Load tree tab label
	 * @return string
	 */
	public function getName()
	{
		return 'LBL_TREE_VIEW';
	}

	/**
	 * Load tree ID
	 * @return type
	 */
	public function getTemplate()
	{
		$field = $this->getTreeField();
		return $field['fieldparams'];
	}

	/**
	 * Load tree field info
	 * @return array
	 */
	public function getTreeField()
	{
		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT tablename,columnname,fieldname,fieldparams FROM vtiger_field WHERE uitype = ? AND tabid = ?', [302, Vtiger_Functions::getModuleId($this->getModuleName())]);
		return $db->getRow($result);
	}

	/**
	 * Load filter parameters
	 * @param array $branches selected tree branche
	 * @return array
	 */
	public function getSearchParams($branches)
	{
		$field = $this->getTreeField();
		$searchParams = [
			['columns' => [[
					'columnname' => $field['tablename'] . ':' . $field['columnname'] . ':' . $field['fieldname'],
					'value' => implode(',', $branches),
					'column_condition' => '',
					'comparator' => 'c',
					]]],
		];
		return $searchParams;
	}

	/**
	 * Load records tree address
	 * @return <String> - url
	 */
	public function getTreeViewUrl()
	{
		return 'index.php?module=' . $this->getModuleName() . '&view=TreeRecords';
	}

	/**
	 * Static Function to get the instance of Vtiger TreeView Model for the given Vtiger Module Model
	 * @param string name of the module
	 * @return Vtiger_TreeView_Model instance
	 */
	public static function getInstance($moduleModel)
	{
		$moduleName = $moduleModel->get('name');
		if (isset(self::$_cached_instance[$moduleName])) {
			return self::$_cached_instance[$moduleName];
		}
		$modelClassName = Vtiger_Loader::getComponentClassName('Model', 'TreeView', $moduleName);
		$instance = new $modelClassName();
		self::$_cached_instance[$moduleName] = $instance->set('module', $moduleModel);
		return self::$_cached_instance[$moduleName];
	}

	/**
	 * Load tree
	 * @return String
	 */
	public function getTreeList()
	{
		$tree = [];
		$db = PearDatabase::getInstance();
		$lastId = 0;
		$result = $db->pquery('SELECT * FROM vtiger_trees_templates_data WHERE templateid = ?', [$this->getTemplate()]);
		while ($row = $db->getRow($result)) {
			$treeID = (int) ltrim($row['tree'], 'T');
			$pieces = explode('::', $row['parenttrre']);
			end($pieces);
			$parent = (int) ltrim(prev($pieces), 'T');
			$tree[] = [
				'id' => $treeID,
				'record_id' => $row['tree'],
				'parent' => $parent == 0 ? '#' : $parent,
				'text' => vtranslate($row['name'], $this->getModuleName()),
				'state' => ($row['state']) ? $row['state'] : '',
				'icon' => $row['icon']
			];
			if ($treeID > $lastId) {
				$lastId = $treeID;
			}
		}
		$this->lastTreeId = $lastId;
		return $tree;
	}
}