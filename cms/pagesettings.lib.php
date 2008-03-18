<?php

/**
 * pagesettings.lib.php: Everything to do with page settings, creation, and moving or copying of pages
 *
 * Created on Oct 13, 2007
 * abhilash #:-S
 */



/**
 * Retrieves the module types a person can create at a given pageid
 * @param $userid Integer indicating the user id of the person
 * @param $pageid Integer indicating the page id of the page at which the user is trying to create the page
 * @return List of modules the person has permission to create at (as a child of) the given page
 */
function getCreatablePageTypes($userid, $pageid) {
	$moduleQuery = "SELECT `page_module` FROM `" . MYSQL_DATABASE_PREFIX . "permissionlist` WHERE `perm_action` = 'create'";
	$moduleResult = mysql_query($moduleQuery);
	$creatableModules = array ();

	while ($moduleResultRow = mysql_fetch_row($moduleResult))
		if (getPermissions($userid, $pageid, "create", $moduleResultRow[0]))
			$creatableModules[] = $moduleResultRow[0];
	return $creatableModules;
}

/**
 * Generate HTML for a form to help edit settings for a given page
 * @param $pageId Page id of the requested page
 * @param $userId User id of the current user
 * @return String containing HTML of the generated form, or a null string if required data could not be found
 */
function getSettingsForm($pageId, $userId) {
	$page_query = "SELECT `page_name`, `page_title`, `page_displaymenu`, `page_displayinmenu`, `page_displaysiblingmenu` , `page_module` " .
	"FROM `" . MYSQL_DATABASE_PREFIX . "pages` WHERE `page_id`=" . $pageId;
	$page_result = mysql_query($page_query);
	$page_values = mysql_fetch_assoc($page_result);

	if (!$page_values) {
		return '';
	}

	$modifiers = '';
	$showInMenuBox = '';
	if ($pageId == 0) {
		$modifiers = 'disabled="disabled" ';
		$showInMenuBox = '<tr><td ><label for="showinmenu">Show page in menu bar</td></label><td><input type="checkbox" name="showinmenu" id="showinmenu" ' . ($page_values['page_displayinmenu'] == 1 ? 'checked="checked" ' : '') . '/></td></tr>';
	}

	$showmenubar = ($page_values['page_displaymenu'] == 1 ? 'checked="checked" ' : '');
	$showsiblingmenu = $page_values['page_displaysiblingmenu'] == 1 ? 'checked="checked" ' : '';

	$page_query = "SELECT * FROM `" . MYSQL_DATABASE_PREFIX . "pages` WHERE `page_parentid` = $pageId AND `page_parentid` != `page_id` ORDER BY `page_menurank` ASC  ";
	$page_result = mysql_query($page_query) or die(mysql_error());
	$childList ="";
	if(mysql_num_rows($page_result)==0)
		$childList = "There are no child pages associated with this page.";
	else
		$childList = "<table border=\"1\" width=\"100%\"><tr><th>Page Name</th><th>Display in menu bar</th><th>Move page up</th><th>Move page down</th><th>Delete</th></tr>";
	while ($page_result_row = mysql_fetch_assoc($page_result)) {
		$childList .= '<tr><td><a href="./'.$page_result_row['page_name'].'+settings">' . $page_result_row['page_title'] . '</a></td>' .
				'<td><input type="checkbox" name="menubarshowchildren[]" id="'.$page_result_row['page_name'].'" value="' . $page_result_row['page_name'] . '" ' . ($page_result_row['page_displayinmenu'] == 1 ? 'checked="yes" ' : '') . '/></td>'.
				'<td align="center"><input type="submit" name="moveUp" onclick="this.form.action+=\''.$page_result_row['page_name'].'\'" value="Move Up" /></td>' .
				'<td align="center"><input type="submit" name="moveDn" onclick="this.form.action+=\''.$page_result_row['page_name'].'\'" value="Move Down" /></td>' .
				'<td align="center"><input type="submit" name="deletePage" onclick="return checkDelete(this,\''.$page_result_row['page_name'].'\')"  value="Delete" /></td></tr>';
	}
//confirm(Delete);this.form.action+=\''.$page_result_row['page_id'].'\'
	if(!mysql_num_rows($page_result)==0)
		$childList .= "</table>";

/* PAGE CREATE TEXT*/
	$createdPageSettingsText = "";
	if(getPageModule($pageId)=="link") {
		$dereferencePagePathIds = array();
		parseUrlDereferenced($pageId, $dereferencePagePathIds);
		$dereferencePagePath = "";
		foreach ($dereferencePagePathIds as $page) {
			$info = getPageInfo($page);
			$dereferencePagePath .= $info['name']."/";
		}
		global $urlRequestRoot;
		$createdPageSettingsText = "Please use the <a href='".$urlRequestRoot."/home".$dereferencePagePath."+settings'>linked page settings</a> to create a child page.";
	}
	else if(getPageModule($pageId)=="external") {
		$createdPageSettingsText = "You cannot create a child page of a page of type \"external link\".";
	}
	else {
		$generatedTree = generateDirectoryTree($userId, "page", "settings", 0)->toHtml('childPageTreeContainer', 'childPageTree', 'childpagelink');
		$creatableTypesText = '<option value=""> </option><option value="menu">Menu</option><option value="link">Link</option><option value="external">External Link</option>';
		foreach (getCreatablePageTypes($userId, $pageId) as $creatableType) {
			$creatableTypesText .= "<option value=\"$creatableType\">".ucfirst($creatableType)."</option>";
		}
		$createdPageSettingsText =<<<CREATE
		    <form name="pagesettings" action="./+settings&subaction=create" onsubmit="return childOnSubmit();" method="POST">
		    <script type="text/javascript" language="javascript">
			<!--
				function childOnSubmit(){
					if(document.getElementById("childpagetype").selectedIndex==0) { alert("Please select a page type."); return false;}
					if(document.getElementById("childpagename").value=="") {alert("Please fill the page name"); return false;}
					if(document.getElementById("childpagelink").value=="" && document.getElementById("childpagetype").selectedIndex==2) {alert("Please select the linked page path"); return false;}
					if(document.getElementById("externallink").value=="" && document.getElementById("childpagetype").selectedIndex==3) {alert("Please enter the external page path"); return false;}
				}
				function childShowTree(obj) {

					if(obj.selectedIndex==2) {
						document.getElementById("childlinktree").style.display="";
						document.getElementById("childlinkentry").style.display="";
					}
					else {
						document.getElementById("childlinktree").style.display="none";
						document.getElementById("childlinkentry").style.display="none";
					}
					if(obj.selectedIndex==3) {
						document.getElementById("externallinktr").style.display="";
					}
					else {
						document.getElementById("externallinktr").style.display="none";
					}
				}
			-->
		</script>
	 <fieldset>
        <legend>Create Child Page</legend>
        <table>
        	<tr>
        		<td valign="top">
					<table border="1">
				        <tr><td>Page type:</td><td><select name="childpagetype" id="childpagetype" onchange="childShowTree(this);">$creatableTypesText</select></td></tr>
				        <tr><td>Page name:</td><td><input type="text" name="childpagename" id="childpagename" /></td></tr>
				        <tr id="childlinkentry" style="display:none"><td>Page link:</td><td><input type="text" name="childpagelink" id="childpagelink" /></td></tr>
				        <tr id="externallinktr" style="display:none"><td>External link:</td><td><input type="text" name="externallink" id="externallink" /></td></tr>
					</table>
				</td>
				<td id="childlinktree" style="display:none">Click to select link path :
					$generatedTree
				</td>
			</tr>
		</table>

	   	<input type="submit" name="btnSubmit2" value="Submit" />&nbsp;&nbsp;<input type="reset" name="btnReset" value="Reset" />
      </fieldset>
      </form>
CREATE;
	}
/* PAGE CREATE TEXT ENDS*/

	$generatedTree = generateDirectoryTree($userId, "page", "settings", 0)->toHtml('fileCopyTreeContainer', 'fileCopyTree', "parentpagepath");
	$movecopyPageSettingsText =<<<MOVECOPY
		<script type="text/javascript" language="javascript">
			function moveOnSubmit(){
				if(document.getElementById("parentpagepath").value=="") {alert("Please fill the page path"); return false;}
				if(document.getElementById("destinationpagetitle").value=="") { alert("Please select a page title."); return false;}
				if(document.getElementById("destinationpagename").value=="") {alert("Please fill the page name"); return false;}
			}
			function movecopyChange(obj){
				if(obj.checked==true)
					document.getElementById("recursivelycopypage").disabled=true;
				else
					document.getElementById("recursivelycopypage").disabled=false;
			}
		-->
	</script>
	<form name="pagesettings" action="./+settings&subaction=move" onsubmit="return moveOnSubmit()" method="POST">
	 <fieldset>
        <legend>Copy or Move Page</legend>
		<table border="1">
			<tr>
				<td valign="top">
			        <table border="1" cellpadding="2px" cellspacing="2px">
			        	<tr><td colspan="2">Click on the generated page tree to select the parent page path : </td></tr>
			          <tr><td>Path of the distination parent page :</td><td><input type="text" id="parentpagepath" name="parentpagepath"/></td></tr>
			          <tr><td>Destination page title:</td><td><input type="text" name="destinationpagetitle" id="destinationpagetitle" value="{$page_values['page_title']}"/></td></tr>
			          <tr><td>Destination page name:</td><td><input type="text" name="destinationpagename" id="destinationpagename" value="{$page_values['page_name']}"/></td></tr>
 			          <tr><td><label for="deleteoriginalpage">Delete original entry (Move instead of Copy)</label></td><td><input type="checkbox" name="deleteoriginalpage" id="deleteoriginalpage" checked="true" onclick="movecopyChange(this);"/></td></tr>
 			          <tr><td><label for="recusivelycopypage">Copy recursively? (in case of Copy)</label></td><td><input type="checkbox" name="recursivelycopypage" id="recursivelycopypage" disabled="true" /></td></tr>
 			        </table>
 			        Legend:
 			        <table cellpadding="2px" cellspacing="2px">
 			        	<tr><td style="border: 1px solid black; width: 18px; background-color: #E8FFE8"></td><td>Accessible Items</td></tr>
 			        	<tr><td style="border: 1px solid black; width: 18px; background-color: #FFE8E8"></td><td>Inaccessible Items</td></tr>
 			        </table>
			    </td>
			    <td valign="top">
					<div id="pathtree">Click to select destination path : $generatedTree</div>
			    </td>
			</tr>
		</table>

	    	<input type="submit" name="btnSubmit2" value="Submit" />&nbsp;&nbsp;<input type="reset" name="btnReset" value="Reset" />
      </fieldset>
      </form>

MOVECOPY;

	global $pageFullPath;
	$parentPath = ($pageId==0?'':'<a href="../+settings">Parent page link.</a>');
	$pageType=$page_values['page_module'];
	$formDisplay =<<<FORMDISPLAY

	<div id="page_settings">
    <form name="pagesettings" action="./+settings&subaction=pagesettings&pageName=" method="POST" onsubmit="return settingsOnSubmit();">
		<script type="text/javascript" language="javascript">
			function settingsOnSubmit(){
				if(!document.getElementById("pagename").disabled) {
				 	if(document.getElementById("pagename").value=="") {alert("Please fill the page name."); return false;}
				}
				if(document.getElementById("pagetitle").value=="") { alert("Please fill the page title."); return false;}
			}
			function checkDelete(butt,fileName)
			{
				if(confirm('Are you sure you want to delete '+fileName+'?'))
				{
					butt.form.action+=fileName;
					butt.form.submit();
				}
				else return false;
			}
		</script>


        	<br />
      	<fieldset>
        	<legend>Page Information</legend>
	        <table border="1" cellpadding="2px" cellspacing="2px">
				<tr><td>Page path:</td><td>$pageFullPath</td></tr>
	        	<tr><td>Page name:</td><td><input type="text" id="pagename" name="pagename" value="{$page_values['page_name']}" $modifiers/></td></tr>
	  			<tr><td>Page title:</td><td><input type="text" id="pagetitle" name="pagetitle" value="{$page_values['page_title']}" $modifiers/></td></tr>
	  				$showInMenuBox
	        	<tr><td ><label for="showmenubar">Show menu bar in page</label></td><td><input type="checkbox" id="showmenubar" name="showmenubar" $showmenubar/></td></tr>
	        	<tr><td ><label for="showsiblingmenu"> Show sibling menu in page</label></td><td><input type="checkbox" name="showsiblingmenu" id="showsiblingmenu" $showsiblingmenu/></td></tr>
	        	<tr><td >Page type: </td><td>$pageType</td></tr>
	        	<tr><td colspan="2">Child pages: (Click on links for children&#39;s settings.) $parentPath <br />
	          	$childList
	          		</td>
	          	</tr>
	        </table>
    		<input type="submit" name="btnSubmit" value="Submit" />&nbsp;&nbsp;<input type="reset" name="btnReset" value="Reset" />
      	</fieldset>
    </form><br />
		$createdPageSettingsText
		<br /><br />
		$movecopyPageSettingsText
	</div>
FORMDISPLAY;
	return $formDisplay;
}

/**
 * Updates the settings for a given page
 * @param $pageId Page id of the requested page
 * @param $userId User id of the current user
 * @param $pageName New name (page link) for the page
 * @param $pageTitle New title (page heading) for the page
 * @param $showInMenu Boolean indicating whether the page is to be shown in the menu
 * @param $showMenuBar Boolean indicating whether the page shows its menubar
 * @return String containing a description of any errors encountered, a null string indicating success
 */
function updateSettings($pageId, $userId, $pageName, $pageTitle, $showInMenu, $showMenuBar, $showSiblingMenu, $visibleChildList) {
	// $oldvalueQuery = "SELECT `page_name`, `page_title`, `displaymenu`, `displayinmenu`, `show"

	$updateQuery = '';
	$updates = array ();
	$errors = '';

	if ($pageId == 0) {
		if (is_bool($showInMenu)) {
			$updates[] = '`page_displayinmenu` = ' . ($showInMenu == true ? 1 : 0);
		}
	}

	if (is_bool($showMenuBar)) {
		$updates[] = '`page_displaymenu` = ' . ($showMenuBar == true ? 1 : 0);
	}

	if (is_bool($showSiblingMenu)) {
		$updates[] = '`page_displaysiblingmenu` = ' . ($showSiblingMenu == true ? 1 : 0);
	}

	if ($pageId != 0 && isset ($pageName) && isset ($pageTitle)) {
		if(preg_match('/^[a-zA-Z][\_a-zA-Z0-9]*$/', $pageName)) {
			$query = "SELECT `page_id` FROM `" . MYSQL_DATABASE_PREFIX . "pages` WHERE `page_name` = '$pageName' AND `page_id` != $pageId AND `page_parentid` = " .
								"(SELECT `page_parentid` FROM `" . MYSQL_DATABASE_PREFIX . "pages` WHERE `page_id` = $pageId)";
			$result = mysql_query($query);
			if (mysql_num_rows($result) > 0) {
				$errors = 'A page with the same name already exists in the folder.<br />';
			} else {
				$updates[] = "`page_name` = '$pageName'";
			}
		}
		else {
			$errors = 'Page name can contain only alphabets (a-z, and A-Z), digits (0-9) and underscores.';
		}
		$updates[] = "`page_title` = '$pageTitle'";
	}

	if (count($updates) > 0) {
		$updateQuery = 'UPDATE `' . MYSQL_DATABASE_PREFIX . 'pages` SET ' . join($updates, ', ') . " WHERE `page_id` = $pageId;";
		mysql_query($updateQuery);
	}

	if (is_array($visibleChildList) && count($visibleChildList) > 0) {
		$visibleChildList = "'" . join($visibleChildList, "', '") . "'";
		$updateQuery = 'UPDATE `' . MYSQL_DATABASE_PREFIX . 'pages` SET `page_displayinmenu` = 1 WHERE ' .
		"`page_name` IN ($visibleChildList) AND `page_parentid` = $pageId AND `page_parentid` != `page_id`";
		mysql_query($updateQuery);
		$updateQuery = 'UPDATE `' . MYSQL_DATABASE_PREFIX . 'pages` SET `page_displayinmenu` = 0 WHERE ' .
		"`page_name` NOT IN ($visibleChildList) AND `page_parentid` = $pageId AND `page_parentid` != `page_id`";
	} else {
		$updateQuery = 'UPDATE `' . MYSQL_DATABASE_PREFIX . 'pages` SET `page_displayinmenu` = 0 WHERE ' .
		"`page_parentid` = $pageId AND `page_parentid` != `page_id`";
	}

	mysql_query($updateQuery);

	return $errors;
}



/**
 * Displays a page settings form, as well as handles its submission, given a page id and a user id
 * @param $pageId Page id of the page where the user is trying to modify settings
 * @param $userId User id of the current user
 * @return HTML content for the page (the form, or the notifications after the form handling)
 */
function pagesettings($pageId, $userId) {
	//($pageId, $userId, $pageName, $pageTitle, $showInMenu, $showMenuBar, $showSiblingMenu, $visibleChildList)
	global $sourceFolder;
	require_once($sourceFolder."/tree.lib.php");

	if(isset($_GET['displayinfo'])) {
		displayinfo($_GET['displayinfo']);
	}
	if(isset($_GET['displayerror'])) {
		displayerror($_GET['displayerror']);
	}
	if (isset ($_GET['subaction']))	 {
		if($_GET['subaction']=="pagesettings") {

			$childPageName=$_GET['pageName'];
			if(isset($_POST['btnSubmit'])) {
				$visibleChildList = array();

				if(isset($_POST['menubarshowchildren']) && is_array($_POST['menubarshowchildren'])) {
					for($i = 0; $i < count($_POST['menubarshowchildren']); $i++) {
						$visibleChildList[] = $_POST['menubarshowchildren'][$i];
					}
				}

				$pageInfoRow = getPageInfo($pageId);
				$updateErrors = updateSettings($pageId, $userId, $_POST['pagename'], $_POST['pagetitle'], isset($_POST['showinmenu']), isset($_POST['showmenubar']), isset($_POST['showsiblingmenu']), $visibleChildList);
				$pageInfoRow = getPageInfo($pageId);
				if($updateErrors == '') {
					disconnect();
					header("Location: ../".$pageInfoRow['page_name']."+settings&displayinfo=".rawurlencode('Page settings updated successfully!'));
				}
				else {
					disconnect();
					header("Location: ../".$pageInfoRow['page_name']."+settings&displayerror=".rawurlencode($updateErrors));
				}
			}
			if(isset($_POST['moveUp'])||isset($_POST['moveDn'])) {
				if(isset($_POST['moveUp']))
				{
					$comparison="<=";
					$sortOrder="DESC";
				}
				else
				{
					$comparison=">=";
					$sortOrder="ASC";
				}
				$childPageName=$_GET['pageName'];
				$query="SELECT `page_menurank`,`page_id` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId AND `page_name`='$childPageName' AND `page_id` != $pageId ORDER BY `page_menurank` $sortOrder LIMIT 0,1 ";
				$result=mysql_query($query);
				$temp=mysql_fetch_assoc($result);
				$childPageId=$temp['page_id'];
				$query="SELECT `page_menurank`,`page_id` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId AND `page_menurank` $comparison(SELECT `page_menurank` FROM  `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId AND `page_name`='$childPageName') AND `page_id` != $childPageId  AND `page_parentid` != `page_id` ORDER BY `page_menurank` $sortOrder LIMIT 0,1 ";
				$result=mysql_query($query) or displayinfo(mysql_error());
				if(mysql_num_rows($result)==0){
					displayerror("You cannot move up/down the first/last page in menu");

				}
				$tempTarg=mysql_fetch_assoc($result);
				$query="SELECT `page_menurank`,`page_parentid` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_id`=$childPageId";
				$result=mysql_query($query);
				$tempSrc=mysql_fetch_assoc($result);
				if(($tempTarg['page_menurank'])==($tempSrc['page_menurank']))
				{
					$query="UPDATE `".MYSQL_DATABASE_PREFIX."pages` SET `page_menurank` = `page_id` WHERE `page_parentid`=$tempSrc[page_parentid]";
		 			mysql_query($query);
		 			displayinfo("Error in menu rank corrected. Please reorder the pages");
				}
				else{
				$query="UPDATE `".MYSQL_DATABASE_PREFIX."pages`  SET `page_menurank` =$tempSrc[page_menurank] WHERE `page_id` = $tempTarg[page_id] ";
				mysql_query($query);
				$query="UPDATE `".MYSQL_DATABASE_PREFIX."pages`  SET `page_menurank` =$tempTarg[page_menurank] WHERE `page_id` = $childPageId ";
				mysql_query($query);
				}
			}
			if(isset($_POST['deletePage']))
			{
				if(isset($_GET['pageName']) || $_GET['pageName']=="") {
					$childPageName=$_GET['pageName'];
					$query="SELECT `page_id` FROM  `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId AND `page_name`='$childPageName'";
					$result=mysql_query($query);
					$temp=mysql_fetch_assoc($result);
					$childPageId=$temp['page_id'];
					if(deletePage($childPageId,$userId))
						displayinfo("Page deleted successfully.");
				}
				else
					displayerror("Not enough information available");
			}
		}
		elseif($_GET['subaction']=="move") {

			if ($_POST['parentpagepath'] == '')
				$updateErrors = "Null page id";
			else {
				global $sourceFolder;
				require_once($sourceFolder."/parseurl.lib.php");
				$pageIdArray = array();
				$parentId = parseUrlReal($_POST['parentpagepath'], $pageIdArray);
				$updateErrors = move_page($userId,$pageId, $parentId, $_POST['destinationpagetitle'], $_POST['destinationpagename'],isset( $_POST['deleteoriginalpage']));
			}

			if ($updateErrors != '') {
				displayerror($updateErrors);
			}
		}
		elseif($_GET['subaction']=="create") {
			/**
			 * Have page rank equal to page id to ensure unique ranks
			 * for links, rank equals to page id of the target sure, coz we can sure the pageid of parent page is unique at destination
			 * check if there is any child page with same name
			 * page name should not contain any special characters. (esp space)
			 * ask for page name only and page title = ucfirst(pagename)
			 * check if the guy has permission to create the page of that type
			 * call modules createModule function
			 */
			if(isset($_POST['childpagetype'])&&isset($_POST['childpagename'])) {
				$maxquery="SELECT MAX( page_id ) AS MAX FROM ".MYSQL_DATABASE_PREFIX."pages";
				$maxqueryresult = mysql_query($maxquery);
				$maxqueryrow = mysql_fetch_array($maxqueryresult);
				$maxpageid = $maxqueryrow[0]+1;

				$alreadyexistquery="SELECT page_name FROM ".MYSQL_DATABASE_PREFIX."pages WHERE page_parentid='$pageId' AND page_name='".$_POST['childpagename']."'";
				$alreadyexistqueryresult = mysql_query($alreadyexistquery);
				$alreadyexistquerynumrows = mysql_num_rows($alreadyexistqueryresult);
				$childPageName = str_replace(' ', '_', strtolower($_POST['childpagename']));
				$childPageTitle = $_POST['childpagename'];
				if(!preg_match('/^[a-z][\_a-z0-9]*$/',  $childPageName))
					displayerror("Invalid page name.");
				elseif($alreadyexistquerynumrows>=1)
					displayerror("A page with the given name already exists at this location.");
				elseif($_POST['childpagetype']=="menu") {
					$menuquery = "INSERT INTO `".MYSQL_DATABASE_PREFIX."pages` (`page_id` ,`page_name` ,`page_parentid` ,`page_title` ,`page_module` ,`page_modulecomponentid` ,`page_menurank`) " .
							"VALUES ('$maxpageid', '".$childPageName."', '$pageId', '".$childPageTitle."', '".$_POST['childpagetype']."', '0', '$maxpageid')";
					mysql_query($menuquery);
						if (mysql_affected_rows() != 1)
							displayerror( 'Unable to create a new page');
				}
				elseif($_POST['childpagetype']=="link") {
					global $sourceFolder;
					require_once($sourceFolder."/parseurl.lib.php");
					$pageIdArray = array();
					$parentId = parseUrlReal($_POST['childpagelink'], $pageIdArray);
					if(getPermissions($userId, $parentId, "settings")) {
					$linkquery = "INSERT INTO `".MYSQL_DATABASE_PREFIX."pages` (`page_id` ,`page_name` ,`page_parentid` ,`page_title` ,`page_module` ,`page_modulecomponentid` ,`page_menurank`) " .
							"VALUES ('$maxpageid', '$childPageName', '$pageId', '$childPageTitle', '{$_POST['childpagetype']}', '$parentId', '$maxpageid')";
					mysql_query($linkquery);
						if (mysql_affected_rows() != 1)
							displayerror( 'Unable to create a new page');
					}
					else
						displayerror("Not enough permission to create a link for that location.");
				}
				elseif($_POST['childpagetype']=="external") {
					$extquery="SELECT MAX( page_modulecomponentid ) AS MAX FROM ".MYSQL_DATABASE_PREFIX."external";
					$extqueryresult = mysql_query($extquery);
					$extqueryrow = mysql_fetch_array($extqueryresult);
					$extpageid = $extqueryrow[0]+1;

					$query="INSERT INTO `".MYSQL_DATABASE_PREFIX."external` (`page_modulecomponentid`,`page_extlink`) " .
							"VALUES('$extpageid','".$_POST['externallink']."')";
					if(!($result = mysql_query($query))) {
						displayerror("Unable to create a new page.");
						return false;
					}
					$linkquery = "INSERT INTO `".MYSQL_DATABASE_PREFIX."pages` (`page_id` ,`page_name` ,`page_parentid` ,`page_title` ,`page_module` ,`page_modulecomponentid` ,`page_menurank`) " .
						"VALUES ('$maxpageid', '".$_POST['childpagename']."', '$pageId', '".ucfirst($_POST['childpagename'])."', '".$_POST['childpagetype']."', '$extpageid', '$maxpageid')";
					mysql_query($linkquery);
					if (mysql_affected_rows() != 1)
						displayerror( 'Unable to create a new page');
				}
				else {
					$moduleType = $_POST['childpagetype'];
					global $sourceFolder;
					global $moduleFolder;
					require_once($sourceFolder."/".$moduleFolder."/".$moduleType.".lib.php");
					$page = new $moduleType();
					$newModuleComponentId=-1;
					$page->createModule($newModuleComponentId);
					if($newModuleComponentId==-1)
						displayerror("Unable to create a new page of type $moduleType");
					else {
						$createquery = "INSERT INTO `".MYSQL_DATABASE_PREFIX."pages` (`page_id` ,`page_name` ,`page_parentid` ,`page_title` ,`page_module` ,`page_modulecomponentid` ,`page_menurank`) " .
							"VALUES ('$maxpageid', '$childPageName', '$pageId', '$childPageTitle', '".$_POST['childpagetype']."', '$newModuleComponentId', '$maxpageid')";
						mysql_query($createquery);
							if (mysql_affected_rows() != 1)
								displayerror( 'Unable to create a new page.');
					}
				}
			}
			else
				displayerror("One or more parameters not set.");
		}
	}
	if ($settingsForm = getSettingsForm($pageId, $userId))
		return $settingsForm;
	else {
		displayerror('Could not find page settings for the requested page.');
		return '';
	}
}



/**
 * Generates HTML (and javascript, and some CSS) code for a foldable tree representation of the given node
 * @param $userId User id of the current user
 * @param $module Name of the module, depending on which the tree must check permissions
 * @param $action Name of the action, depending on which the tree must check permissions
 * @param $pageId Page id of the root of the tree, if omitted, it is taken as 0 (the home directory)
 * @return HTML code for the generated tree
 */


function deletePage($pageId,$userId){
 	$query="SELECT `page_id` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId AND `page_id`!=`page_parentid` ";
 	$result=mysql_query($query);
 	$deleteAll = true;
 	while($temp=mysql_fetch_assoc($result))
 	{

  		if(getPermissions($userId,$pageId,"settings"))
	 		$deleteAll = deletePage($temp['page_id'],$userId);
	 	else
	 		$deleteAll = false;
 	}

 	if($deleteAll == true) {
	 	$pageInfo = getPageInfo($pageId);
		$moduleType = $pageInfo['page_module'];
		$deleted = true;
		if($moduleType!="menu" && $moduleType != "link") {
			if($moduleType!="external") {
				global $sourceFolder;
				global $moduleFolder;
				require_once($sourceFolder."/".$moduleFolder."/".$moduleType.".lib.php");
				$page = new $moduleType();
				$deleted = $page->deleteModule($pageInfo['page_modulecomponentid']);
				if(!$deleted)
					displayerror("There was an error in deleting the page at module level.");
			}
			else {
				$query="DELETE FROM `".MYSQL_DATABASE_PREFIX."external` WHERE `page_modulecomponentid`=".$pageInfo['page_modulecomponentid'];
				mysql_query($query);
				if (mysql_affected_rows()>0) $deleted=true;
				else {
					$deleted=false;
					displayerror("There was an error in deleting the external link");
				}

			}
		}
		//query to delete page row itself
		if($deleted) {
			$query="DELETE FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_id`=$pageId";
			mysql_query($query);
			if (mysql_affected_rows()>0) return true;
			else return false;
		}
		else return false;
 	}
 	else {
 		displayerror("You don't have permission to delete one or more of the children pages.");
 		return false;
 	}



 }

function generateDirectoryTree($userId, $module, $action, $pageId = 0) {
	global $sourceFolder;
	require_once("$sourceFolder/tree.lib.php");
	$dirTree = new DirectoryTree($pageId, $userId, $action, $module);

	return $dirTree;
}



function move_page($userId,$pageId, $parentId, $pagetitle,$pagename,$deleteoriginalentry) {
/**
 * return true or false.
 * First check if page with same name exists in destination parent. If it does, and the parent is different from
 * current parent, dont copy or move and return false
 *
 */
	//var_dump($str);
	$query = "SELECT `page_id` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid` = $parentId AND `page_name` = '$pagename'";
	$result = mysql_query($query);
	if(mysql_num_rows($result) > 0)
		return "Error: There exists a page with the same name in the destination path.";
	$parentInfo = getPageInfo($parentId);
	if(!getPermissions($userId, $parentId, "settings"))
		return "Error: You do not have permission to copy or move to the destination page.";
	if($parentInfo['page_module']=="link")
		return "Error: Cannot move or copy a page to a page of the type link.";
	$str = array();
	parseUrlDereferenced($parentId,$str);
	$arrlen = count($str);
	for($i = 0; $i<count($str);$i++){
		if($pageId==$str[$i])
			return 'Error : You are trying to copy a parent to a child page. This will create a loop';
	}
	//if the deleteoriginal entry is set then the page is MOVED from the original location to the new location.
	if ($deleteoriginalentry == true) {
		if ($pageId != 0) {
			$query = "UPDATE `" . MYSQL_DATABASE_PREFIX . "pages` SET `page_parentid` = '" . $parentId . "' , `page_title` = '" . $pagetitle . "' , `page_name` = '" . $pagename . "' WHERE `page_id` =$pageId ;";
			$result = mysql_query($query);
				if (mysql_affected_rows() != 1)
					return 'Unable to perform the required action';
			global $urlRequestRoot;
			header("location:".$urlRequestRoot.getPagePath($pageId)."+settings&displayinfo=".rawurlencode("The page has been successfully moved."));
		} else
			return 'Error : You do not have permission to move the root page.';
	}
//if the deleteoriginal entry is not set then the page is COPIED from the original location to the new location.
	else {
		$recursive= false;
		if(isset($_POST['recursivelycopypage'])) $recursive = true;
		if(copyPage($userId,$pageId,$parentId,$pagetitle,$pagename,$recursive))
			displayinfo("Page copied successfully!");
	}
}

function copyPage($userId,$pageId,$parentId, $pagetitle,$pagename,$recursive) {
	if(!getPermissions($userId, $parentId, "settings"))
		return false;
	$parentInfo = getPageInfo($parentId);
	$parentmoduleType =$parentInfo['page_module'];
	if($parentmoduleType=="link")
		return false;
	$pageInfo = getPageInfo($pageId);
	$moduleType =$pageInfo['page_module'];
	if($moduleType=="link")
		return false;
	$newmodulecomponentid = 0;
	if($moduleType!="menu" && $moduleType!="external") {
		global $sourceFolder;
		global $moduleFolder;
		require_once($sourceFolder."/".$moduleFolder."/".$moduleType.".lib.php");
		$page = new $moduleType();
		$newmodulecomponentid=$page->copyModule($pageInfo['page_modulecomponentid']);
		if($newmodulecomponentid===false) {
			displayerror("Unable to copy the page ".$pageId);
			return false;
		}
	}
	if($moduleType=="external"){
		$extquery="SELECT MAX( page_modulecomponentid ) AS MAX FROM ".MYSQL_DATABASE_PREFIX."external";
		$extqueryresult = mysql_query($extquery);
		$extqueryrow = mysql_fetch_array($extqueryresult);
		$extpageid = $extqueryrow[0]+1;
		$linkquery="SELECT page_extlink FROM ".MYSQL_DATABASE_PREFIX."external WHERE page_modulecomponentid=".$pageInfo['page_modulecomponentid'];
		$linkqueryresult = mysql_query($linkquery);
		$linkqueryrow = mysql_fetch_array($linkqueryresult);
		$link = $linkqueryrow[0];

		$query="INSERT INTO `".MYSQL_DATABASE_PREFIX."external` (`page_modulecomponentid`,`page_extlink`) " .
				"VALUES('$extpageid','$link')";
		if(!($result = mysql_query($query))) {
			displayerror("Unable to copy the page.");
			return false;
		}
	}

	$maxquery="SELECT MAX( page_id ) AS MAX FROM ".MYSQL_DATABASE_PREFIX."pages";
	$maxqueryresult = mysql_query($maxquery);
	$maxqueryrow = mysql_fetch_array($maxqueryresult);
	$maxpageid = $maxqueryrow[0]+1;

	$query = "INSERT INTO `".MYSQL_DATABASE_PREFIX."pages` (`page_id`,`page_name`,`page_title`,`page_parentid`,`page_module`,`page_modulecomponentid`,`page_displayinmenu`, `page_displaymenu`, `page_displaysiblingmenu`,`page_menurank`) " .
			"VALUES('$maxpageid','$pagename','$pagetitle','$parentId','{$pageInfo['page_module']}','$newmodulecomponentid','{$pageInfo['page_displayinmenu']}','{$pageInfo['page_displaymenu']}','{$pageInfo['page_displaysiblingmenu']}','$maxpageid')";
	if(!($result = mysql_query($query))) {
		displayerror("Unable to copy the page.");
		return false;
	}
	if($recursive) {
		$childrenquery="SELECT `page_id`,`page_name`,`page_title` FROM `".MYSQL_DATABASE_PREFIX."pages` WHERE `page_parentid`=$pageId ";
		$childrenresult=mysql_query($childrenquery);
		while($temp=mysql_fetch_assoc($childrenresult))
		{
	 		copyPage($userId,$temp['page_id'],$maxpageid, $temp['page_title'],$temp['page_name'],$recursive);
		}
	}
	return true;
}

?>