<?php

class sitemap implements module {

	public function getHtml($userId, $moduleComponentId, $action) {
		$this->userId = $userId;
		$this->moduleComponentId = $moduleComponentId;
		$this->action = $action;

		if($action == "view")
			return $this->actionView();
	}

	public function actionView() {
		return $this->generateTree(0, $this->userId, 0, 'view');
	}
	public function actionCreate() {}

	function generateTree($pageId, $userId, $permId, $action = '') {
		global $sourceFolder, $urlRequestRoot, $templateFolder;
		$imagesFolder = "$urlRequestRoot/$sourceFolder/$templateFolder/common/images";
		$scriptsFolder = "$urlRequestRoot/$sourceFolder/$templateFolder/common/scripts";

		$treeData= '(Click on the folder icon to expand the sitemap)<br \><br \><div id="directorybrowser"><ul class="treeview" id="sitemap">' .
				'<script type="text/javascript" language="javascript" src="'.$scriptsFolder.'/treemenu.js"></script>';

		if($action != '') {
			$treeData .= $this->getNodeHtml($pageId, $userId, '', $action, $urlRequestRoot . '/');
		}
		else {
			$permQuery = 'SELECT `page_module`, `perm_action` FROM `' . MYSQL_DATABASE_PREFIX . 'permissionlist` WHERE `perm_id` = ' . $permId;
			$permResult = mysql_query($permQuery);
			$permRow = mysql_fetch_row($permResult);
			$module = $permRow[0];
			$action = $permRow[1];
			$treeData .= $this->getNodeHtml($pageId, $userId, $module, $action, $urlRequestRoot . '/');
		}
		$treeData .= '</ul></div>';

		$treeData .= <<<TREEDATA
			<script type="text/javascript" language="javascript">
			<!--
//				siteMapLinks = document.getElementById('sitemap').getElementsByTagName('a');
//				for(i = 0; i < siteMapLinks.length; i++) {
//					siteMapLinks[i].onclick = treeLinkClicked;
//				}

//				setupMenuDependencies("$imagesFolder", '');
//				ddtreemenu.createTree("sitemap", true, 5);

				ddtreemenu = new JSTreeMenu('sitemap', '', '$imagesFolder', false);
			-->
			</script>

TREEDATA;

		return $treeData;
	}

	function getNodeHtml($pageId, $userId, $module, $action, $parentPath) {
		$htmlOut = '';
		if(getPermissions($userId, $pageId, $action, $module)) {
			$pageInfo = getPageInfo($pageId);
			$pagePath = $parentPath;
			if($pageInfo['page_name'] != '')
				$pagePath .= $pageInfo['page_name'] . '/';

			$htmlOut .= "<li><a href=\"$pagePath\">" . getPageTitle($pageId) . "</a>\n";

			$childrenQuery = 'SELECT `page_id`, `page_displayinmenu` FROM `' . MYSQL_DATABASE_PREFIX  . 'pages` WHERE `page_parentid` <> `page_id` AND `page_parentid` = ' . $pageId;
			$childrenResult = mysql_query($childrenQuery);

			$childrenHtml = '';
			while($childrenRow = mysql_fetch_row($childrenResult)) {
				if($childrenRow[1] == 1) {
					$childrenHtml .= $this->getNodeHtml($childrenRow[0], $userId, $module, $action, $pagePath);
				}
			}
			if($childrenHtml != '') {
				$htmlOut .= "<ul>$childrenHtml</ul>\n";
			}

			$htmlOut .= "</li>\n";
		}
		return $htmlOut;
	}


	public function createModule(&$moduleComponentId) {
		$idQuery = 'SELECT MAX(`page_modulecomponentid`) FROM `' . MYSQL_DATABASE_PREFIX . 'pages` WHERE `page_module` = \'sitemap\'';
		$idResult = mysql_query($idQuery);
		$idRow = mysql_fetch_row($idResult);
		$moduleComponentId = 1;
		if($idRow && !is_null($idRow[0])) {
			$moduleComponentId = $idRow[0] + 1;
		}
		return true;
	}
	public function deleteModule($moduleComponentId) {}
	public function copyModule($moduleComponentId) {}
}

?>