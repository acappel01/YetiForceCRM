<?php
/* * *******************************************************************************
 * * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * ****************************************************************************** */

require_once('include/logging.php');
require_once('modules/CustomView/CustomView.php');

class ListViewSession
{

	var $module = null;
	var $viewname = null;
	var $start = null;
	var $sorder = null;
	var $sortby = null;
	var $page_view = null;

	/*	 * initializes ListViewSession
	 * Portions created by vtigerCRM are Copyright (C) vtigerCRM.
	 * All Rights Reserved.
	 */

	function ListViewSession()
	{
		$log = vglobal('log');
		$currentModule = vglobal('currentModule');
		$log->debug("Entering ListViewSession() method ...");

		$this->module = $currentModule;
		$this->sortby = 'ASC';
		$this->start = 1;
	}

	function getCurrentPage($currentModule, $viewId)
	{
		if (!empty($_SESSION['lvs'][$currentModule][$viewId]['start'])) {
			return $_SESSION['lvs'][$currentModule][$viewId]['start'];
		}
		return 1;
	}

	function getRequestStartPage()
	{
		$start = AppRequest::get('start');
		if (!is_numeric($start)) {
			$start = 1;
		}
		if ($start < 1) {
			$start = 1;
		}
		$start = ceil($start);
		return $start;
	}

	public static function getListViewNavigation($currentRecordId)
	{
		$adb = PearDatabase::getInstance();
		$log = vglobal('log');
		$currentModule = vglobal('currentModule');
		$current_user = vglobal('current_user');
		$list_max_entries_per_page = vglobal('list_max_entries_per_page');

		Zend_Json::$useBuiltinEncoderDecoder = true;
		$reUseData = false;
		$displayBufferRecordCount = 10;
		$bufferRecordCount = 15;
		if ($currentModule == 'Documents') {
			$sql = "select folderid from vtiger_notes where notesid=?";
			$params = array($currentRecordId);
			$result = $adb->pquery($sql, $params);
			$folderId = $adb->query_result($result, 0, 'folderid');
		}
		$cv = new CustomView();
		$viewId = $cv->getViewId($currentModule);
		if (!empty($_SESSION[$currentModule . '_DetailView_Navigation' . $viewId])) {
			$recordNavigationInfo = Zend_Json::decode($_SESSION[$currentModule . '_DetailView_Navigation' . $viewId]);
			$pageNumber = 0;
			if (count($recordNavigationInfo) == 1) {
				foreach ($recordNavigationInfo as $recordIdList) {
					if (in_array($currentRecordId, $recordIdList)) {
						$reUseData = true;
					}
				}
			} else {
				$recordList = [];
				$recordPageMapping = [];
				foreach ($recordNavigationInfo as $start => $recordIdList) {
					foreach ($recordIdList as $index => $recordId) {
						$recordList[] = $recordId;
						$recordPageMapping[$recordId] = $start;
						if ($recordId == $currentRecordId) {
							$searchKey = count($recordList) - 1;
							AppRequest::set('start', $start);
						}
					}
				}
				if ($searchKey > $displayBufferRecordCount - 1 && $searchKey < count($recordList) - $displayBufferRecordCount) {
					$reUseData = true;
				}
			}
		}

		$list_query = $_SESSION[$currentModule . '_listquery'];

		if ($reUseData === false && !empty($list_query)) {
			$recordNavigationInfo = [];
			if (!AppRequest::isEmpty('start')) {
				$start = ListViewSession::getRequestStartPage();
			} else {
				$start = ListViewSession::getCurrentPage($currentModule, $viewId);
			}
			$startRecord = (($start - 1) * $list_max_entries_per_page) - $bufferRecordCount;
			if ($startRecord < 0) {
				$startRecord = 0;
			}

			$instance = CRMEntity::getInstance($currentModule);
			$instance->getNonAdminAccessControlQuery($currentModule, $current_user);
			vtlib_setup_modulevars($currentModule, $instance);
			if ($currentModule == 'Documents' && !empty($folderId)) {
				$list_query = preg_replace("/[\n\r\s]+/", " ", $list_query);
				$list_query = explode('ORDER BY', $list_query);
				$default_orderby = $list_query[1];
				$list_query = $list_query[0];
				$list_query .= " AND vtiger_notes.folderid='$folderId'";
				$order_by = $instance->getOrderByForFolder($folderId);
				$sorder = $instance->getSortOrderForFolder($folderId);
				$tablename = getTableNameForField($currentModule, $order_by);
				$tablename = (($tablename != '') ? ($tablename . ".") : '');

				if (!empty($order_by)) {
					$list_query .= ' ORDER BY ' . $tablename . $order_by . ' ' . $sorder;
				} elseif (!empty($default_orderby)) {
					$list_query .= ' ORDER BY ' . $default_orderby . '';
				}
			}
			if ($start != 1) {
				$recordCount = ($list_max_entries_per_page * $start + $bufferRecordCount);
			} else {
				$recordCount = ($list_max_entries_per_page + $bufferRecordCount);
			}
			if ($adb->isPostgres()) {
				$list_query .= " OFFSET $startRecord LIMIT $recordCount";
			} else {
				$list_query .= " LIMIT $startRecord, $recordCount";
			}

			$resultAllCRMIDlist_query = $adb->pquery($list_query, []);
			$navigationRecordList = [];
			while ($forAllCRMID = $adb->fetch_array($resultAllCRMIDlist_query)) {
				$navigationRecordList[] = $forAllCRMID[$instance->table_index];
			}

			$pageCount = 0;
			$current = $start;
			if ($start == 1) {
				$firstPageRecordCount = $list_max_entries_per_page;
			} else {
				$firstPageRecordCount = $bufferRecordCount;
				$current -=1;
			}

			$searchKey = array_search($currentRecordId, $navigationRecordList);
			$recordNavigationInfo = [];
			if ($searchKey !== false) {
				foreach ($navigationRecordList as $index => $recordId) {
					if (!is_array($recordNavigationInfo[$current])) {
						$recordNavigationInfo[$current] = [];
					}
					if ($index == $firstPageRecordCount || $index == ($firstPageRecordCount + $pageCount * $list_max_entries_per_page)) {
						$current++;
						$pageCount++;
					}
					$recordNavigationInfo[$current][] = $recordId;
				}
			}
			$_SESSION[$currentModule . '_DetailView_Navigation' . $viewId] = Zend_Json::encode($recordNavigationInfo);
		}
		return $recordNavigationInfo;
	}

	function getRequestCurrentPage($currentModule, $query, $viewid, $queryMode = false)
	{
		global $list_max_entries_per_page, $adb;
		$start = 1;
		if (AppRequest::has('query') && AppRequest::get('query') == 'true' && AppRequest::get('start') != 'last') {
			return ListViewSession::getRequestStartPage();
		}
		if (!AppRequest::isEmpty('start')) {
			$start = AppRequest::get('start');
			if ($start == 'last') {
				$count_result = $adb->query(Vtiger_Functions::mkCountQuery($query));
				$noofrows = $adb->query_result($count_result, 0, "count");
				if ($noofrows > 0) {
					$start = ceil($noofrows / $list_max_entries_per_page);
				}
			}
			if (!is_numeric($start)) {
				$start = 1;
			} elseif ($start < 1) {
				$start = 1;
			}
			$start = ceil($start);
		} else if (!empty($_SESSION['lvs'][$currentModule][$viewid]['start'])) {
			$start = $_SESSION['lvs'][$currentModule][$viewid]['start'];
		}
		if (!$queryMode) {
			$_SESSION['lvs'][$currentModule][$viewid]['start'] = intval($start);
		}
		return $start;
	}

	public static function setSessionQuery($currentModule, $query, $viewid)
	{
		if (Vtiger_Session::has($currentModule . '_listquery')) {
			if (Vtiger_Session::get($currentModule . '_listquery') != $query) {
				Vtiger_Session::remove($currentModule . '_DetailView_Navigation' . $viewid);
			}
		}
		Vtiger_Session::set($currentModule . '_listquery', $query);
	}

	public static function hasViewChanged($currentModule, $viewId = false)
	{
		if (empty($_SESSION['lvs'][$currentModule]['viewname']))
			return true;
		if (!AppRequest::isEmpty('viewname') && (AppRequest::get('viewname') != $_SESSION['lvs'][$currentModule]['viewname']))
			return true;
		if (!empty($viewId) && ($viewId != $_SESSION['lvs'][$currentModule]['viewname']))
			return true;
		return false;
	}

	/**
	 * Function that sets the module filter in session
	 * @param <String> $module - module name
	 * @param <Integer> $viewId - filter id
	 */
	public static function setCurrentView($module, $viewId, $pjax = true)
	{
		if($pjax && AppRequest::has('_pjax')){
			$_SESSION['lvs'][$module]['viewname'] = $viewId;
		}elseif(empty($pjax)){
			$_SESSION['lvs'][$module]['viewname'] = $viewId;
		}
	}

	/**
	 * Function that reads current module filter
	 * @param <String> $module - module name
	 * @return <Integer>
	 */
	public static function getCurrentView($module)
	{
		if (!empty($_SESSION['lvs'][$module]['viewname'])) {
			return $_SESSION['lvs'][$module]['viewname'];
		}
	}

	public static function getSorder($module)
	{
		if (!empty($_SESSION['lvs'][$module]['sorder'])) {
			return $_SESSION['lvs'][$module]['sorder'];
		}
	}

	public static function getSortby($module)
	{
		if (!empty($_SESSION['lvs'][$module]['sortby'])) {
			return $_SESSION['lvs'][$module]['sortby'];
		}
	}

	public static function setDefaultSortOrderBy($module, $defaultSortOrderBy = [])
	{
		if (AppRequest::has('orderby')) {
			$_SESSION['lvs'][$module]['sortby'] = AppRequest::get('orderby');
		}
		if (AppRequest::has('sortorder')) {
			$_SESSION['lvs'][$module]['sorder'] = AppRequest::get('sortorder');
		}
		if (isset($defaultSortOrderBy['orderBy'])) {
			$_SESSION['lvs'][$module]['sortby'] = $defaultSortOrderBy['orderBy'];
		}
		if (isset($defaultSortOrderBy['sortOrder'])) {
			$_SESSION['lvs'][$module]['sorder'] = $defaultSortOrderBy['sortOrder'];
		}
	}
}
