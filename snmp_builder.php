<?php
// Copyright (c) 2009 GiapNguyen
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to
// permit persons to whom the Software is furnished to do so, subject to
// the following conditions:
// 
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
// LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
// OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
// WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
//
// This is distributed under Free-BSD licence.
?>
<?php

define('MIBS_ALL_PATH', '/var/www/html/zabbix/snmp_builder/mibs:/usr/share/snmp/mibs');

require_once('include/config.inc.php');

require_once('include/js.inc.php');
require_once('include/html.inc.php');
require_once('include/items.inc.php');

$page["title"] = "SNMP Builder";
$page['file'] = 'snmp_builder.php';
$page['scripts'] = array('../snmp_builder/Tree.js','../snmp_builder/snmp_builder.js','../snmp_builder/DynTable.js','scriptaculous.js?load=effects,dragdrop');
$page['hist_arg'] = array();
$page['type'] = detect_page_type();
include_once('include/page_header.php');

?>
<?php

//---------------------------------- CHECKS ------------------------------------

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"msg"=>		array(T_ZBX_STR, O_OPT,	 null,	null ,NULL),

// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'hat'"),		NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
//action
		'select'=> 		array(T_ZBX_INT, O_OPT, NULL,	NULL,		NULL),
		'save'=> 		array(T_ZBX_INT, O_OPT, NULL,	NULL,		NULL),
		'viewtype'=> 		array(T_ZBX_INT, O_OPT, NULL,	NULL,		NULL),
		'oid' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'oids' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'idx' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'mib' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'templateid' => array(T_ZBX_INT, O_OPT, NULL,	NULL,		NULL),
		'server_ip' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'community' => 		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
	);

	check_fields($fields);

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.services.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'],PROFILE_TYPE_INT);
		}
	}

//filter os command injection
	if (isset($_REQUEST['mib']) && !empty($_REQUEST['mib']))
	{
		if (!preg_match('/^[a-z,0-9,\.,\-]+$/i',$_REQUEST['mib']))
		{
			json_error('Invalid mib name '.$_REQUEST['mib']);
		}
		$mib = escapeshellcmd($_REQUEST['mib']);
	}
	else
		$mib ='';
	
	if (isset($_REQUEST['oid']) && !empty($_REQUEST['oid']))
	{
		if (!preg_match('/^[a-z,0-9,\.,\-,\:]+$/i', $_REQUEST['oid']))
		{
			json_error('Invalid oid '.$_REQUEST['oid']);
		}
		$oid = escapeshellcmd($_REQUEST['oid']);
	}
	else
		$oid ='';
	
	if (isset($_REQUEST['idx']) && !empty($_REQUEST['idx']))
	{
		$idx = escapeshellcmd($_REQUEST['idx']);
	}
	else
		$idx =0;
		
	if (isset($_REQUEST['server_ip']) && !empty($_REQUEST['server_ip']))
	{
		if (!preg_match('/^[0-9,\.]+$/i', $_REQUEST['server_ip']))
		{
			json_error('Invalid server ip '.$_REQUEST['server_ip']);
		}
		$server_ip = escapeshellcmd($_REQUEST['server_ip']);
	}
	else
		$server_ip ='';
		
	if (isset($_REQUEST['community']) && !empty($_REQUEST['community']))
	{
		$community = escapeshellcmd($_REQUEST['community']);
	}
	else
		$community ='public';
	
	$templateid = 0;
	if (isset($_REQUEST['templateid']))
		$templateid = $_REQUEST['templateid'];
	if (isset($_REQUEST['oids']))
		$oids = $_REQUEST['oids'];
	if (isset($_REQUEST['viewtype']))
		$viewtype =  $_REQUEST['viewtype'];
		
////////////////////////////////////////////////

// actions
		
	if (isset($_REQUEST['select']))
	{
		if (!$oid || !$mib)
		{
			json_error('Missing oid or mib');
			exit;
		}	
		$content = get_oid_content($oid);
		
		if ($viewtype == 1 || preg_match('/Table$/',$oid)) 
		{
			$value = get_table_value($community, $server_ip, $oid);
		}
		else {
			//value
			$value = get_oid_value($community, $server_ip, $oid, $idx);
			if ($content == '') //Fix for table cells
			{
				$content = get_oid_content(escapeshellcmd($value['row'][0]));
			}
		
		}
		
		$json = json_encode(array('info' => $content, 'value' => $value));
		echo $json;
		exit;
	}
	else if (isset($_REQUEST['save'])) {
	
		if (!$oids || !$templateid )
		{
			json_error('Missing oid list or templateid');
		}
		
		$oidlist = json_decode($oids);
		
		if (count($oidlist) === 0)
			json_error('Oid list is null');
		
		$items = array();
		foreach($oidlist as $oid)
		{
			//item = [oid, 'Numeric','Decimal','s',60,'0.01','no']
			$oid_num = get_oid_from_name(escapeshellcmd($oid[0]));
			if (!$oid_num)
				json_error('Oid is null '.$oid[0]);
			
			//value_type	
			switch($oid[1])
			{
				case 'Numeric' :
					$value_type = ITEM_VALUE_TYPE_UINT64;
					break;
				case 'Text' :
					$value_type = ITEM_VALUE_TYPE_TEXT;
					break;
				default:
					json_error("invalid type ".$oid[1]);
			}
			
			//data_type
			switch($oid[2])
			{
				case 'Decimal' :
					$data_type = ITEM_DATA_TYPE_DECIMAL;
					break;
				default:
					$data_type = ITEM_DATA_TYPE_DECIMAL;
			}
			//unit
			if (!$oid[3])
				$oid[3] = '';
			//interval
			$oid[4] = (int)$oid[4];
			
			//multiplier
			if (!$oid[5])
			{
				$multiplier = 0;
				$oid[5] = null;
			}
			else
			{
				$multiplier = 1;
				$oid[5] = (int)$oid[5];
			}	
			switch($oid[6])
			{
				case 'yes':
					$delta = 1;
					break;
				default:
					$delta = 0;
			}
			
			// From 1.8.1 zabbix not accept special char in key, :( so we must replace them with underscore
			$newkey = preg_replace('/[^0-9a-zA-Z_\.]/','_',$oid[0]);
			
			$item = [
				'description'	=> $oid[0],
                                'name'                  => $oid[0],
				'key_'			=> $newkey,
				'hostid'		=> $templateid,
				'delay'		=> $oid[4],
				'history'		=> 90*3600,
				'status'		=> ITEM_STATUS_ACTIVE,
				'type'			=> ITEM_TYPE_SNMPV2C,
				'snmp_community'=> $community,
				'snmp_oid'		=> $oid_num,
				'value_type'	=> $value_type,
//				'trapper_hosts'	=> null,
//// //				'snmp_port'		=> null,
				'units'			=> $oid[3],
				'multiplier'	=> $multiplier,
				'delta'			=> $delta,
/*				'snmpv3_securityname'	=> null,
				'snmpv3_securitylevel'	=> null,
				'snmpv3_authpassphrase'	=> null,
				'snmpv3_privpassphrase'	=> null, */
				'formula'			=> $oid[5],
/*				'trends'			=> null,
				'logtimefmt'		=> null,
				'valuemapid'		=> null,*/
				'delay_flex'		=> null,
/*				'authtype'		=> null,
				'username'		=> null,
				'password'		=> null,
				'publickey'		=> null,
				'privatekey'		=> null,
				'params'			=> null,
				'ipmi_sensor'		=> null, */
				'data_type'		=> $data_type];
				
			array_push($items, $item);

		}
		
		
#		foreach ($items as $item)
#		{
			DBstart();
			$itemid = false;
			$itemid = API::Item()->create($items);
                        
			$result = DBend($itemid);
#		}
		
		
		
	}
	else{
// Build widget

	
	$snmp_wdgt = new CWidget();
	$message_div = new CDiv();
	$message_div->setAttribute("id","message");
	$snmp_wdgt->addItem($message_div);
	
	//Header
	$form = new CForm();
	$form->setMethod('post');
	
	// Template selector
	$cmbTemplates = new CComboBox('templateid',$templateid);
	foreach(get_templates() as $temp)
	{
		$cmbTemplates->addItem($temp['key'],$temp['host']);
	}
	$form->addItem(array('Template:'.SPACE,$cmbTemplates,SPACE));
	
	//Mib selector
	$cmbMibs = new CComboBox('mib',$mib,'javascript: submit();');
	$paths = explode(':', MIBS_ALL_PATH);
	foreach($paths as $path)
	{
		$cmbMibs->addItem('','---'.$path);
		foreach(glob($path."/*.txt")  as $filename){
			$modulename = get_module_name($filename);
			if ($modulename)	
				$cmbMibs->addItem($modulename,$modulename);
		}
		foreach(glob($path."/*.mib")  as $filename){
			$modulename = get_module_name($filename);
			if ($modulename)	
				$cmbMibs->addItem($modulename,$modulename);
		}
		
	}
	
	$form->addItem(array('MIB:'.SPACE,$cmbMibs,SPACE));
	$form->addItem((new CTag('br')));
	// server textbox
	$ipbServer = new CTextBox('server_ip',$server_ip);
	$form->addItem(array('Server:'.SPACE,$ipbServer,SPACE));
	
	// community textbox
	$tbCommunity = new CTextBox('community',$community);
	$form->addItem(array('Community:'.SPACE,$tbCommunity ,SPACE));
	
	#$snmp_wdgt->addHeader('SNMP Builder', $form);
	$snmp_wdgt->setTitle('SNMP Builder');
        $snmp_wdgt->setControls($form);
	
	//Body
	$outer_table = new CTable();
	
	$outer_table->setAttribute('border',0);
	$outer_table->setAttribute('width','100%');
	$outer_table->setCellPadding(2);
	$outer_table->setCellSpacing(2);
	
	//Left panel
	$left_tab = new CTable();
	//Oid tree
	$oid_tree_w = new CColHeader("Oid Tree");
	
	$oid_tree_div = new CDiv();
	$oid_tree_div->setAttribute("id","oidtree");
	
	$oid_tree_container = new CDiv($oid_tree_div);
        $oid_tree_container->addClass(ZBX_STYLE_TREEVIEW);
	$oid_tree_container->addStyle("overflow: auto; height: 300px; width: 300px;");
	
	$oid_tree_w->addItem($oid_tree_container);
	$left_tab->addRow($oid_tree_w);
	
	//Oid description
	$oid_info_w = new CColHeader("Information");
	
	$oid_info_div = new CDiv();
	$oid_info_div->setAttribute("id","oidinfo");
	$oid_info_div->addStyle("overflow: auto; max-height: 100px;  width: 300px;");
	$oid_info_w->addItem($oid_info_div);
	$left_tab->addRow($oid_info_w);
	
	//Right panel
	$right_tab = new CTable();
	//Oidview
	$oid_view_w = (new CColHeader(
                        ( (new CCheckBox('viewtype'))->onClick('onViewType()') ) ));
//	$oid_view_w->addHeader(array("Oid View - click to view as table:",));
	
	
	$oid_view_div =  new CDiv();
	$oid_view_div->setAttribute("id","oidview");
	$oid_view_div ->addStyle("overflow: auto; max-height: 250px; width: 800px");
	$oid_view_w->addItem($oid_view_div);
	$right_tab->addRow($oid_view_w);
	//Itemlist
	$item_list_w = new CColHeader('Item List');
	
	$item_list_div = new CDiv();
	$item_list_div->setAttribute("id","itemlist");
	$item_list_div ->addStyle("overflow: auto; max-height: 150px; width: 800px");
	$item_list_w->addItem($item_list_div);
	$right_tab->addRow($item_list_w);
	
	//Action srow
        
	$action_w= (new CButton('save',_('Save')))->addStyle("margin: 10px;")->onClick('onSaveItems()');
	$action_c= (new CButton('clear',_('Clear')))->addStyle("margin: 10px;")->onClick('onClearItems()');

	$right_tab->addRow([[],[$action_w,$action_c ]]);

	// Left panel
	$td_l = new CCol($left_tab);
	$td_l->setAttribute('valign','top');
	$td_l->setAttribute('width','300px');
	
	//Right panel
	$td_r = new CCol($right_tab);
	$td_r->setAttribute('valign','top');
	$td_r->setAttribute('width','800px');
	
	
	$outer_table->addRow(array($td_l,$td_r));
	$snmp_wdgt->addItem($outer_table);
	$snmp_wdgt->show();

// Javascript GUI init
	if ($mib)
	{
		$oid_tree = get_oid_tree($mib);
		
		insert_js("	
				var oidview = new DynTable('oidview',{'headers' : ['Oid/Name','Type','Value']});
				var itemlist = new DynTable('itemlist',{'headers' : ['Key','Type','Data','Unit','Interval','Multiple','Delta'], 'observer' : {'tr': onClickItem}});
				var oidtree = new TafelTree('oidtree', [".json_encode($oid_tree)."], {
					'imgBase' : 'snmp_builder/imgs/', 
					'defaultImg' : 'page.gif',
					'defaultImgOpen' : 'folderopen.gif',
					'defaultImgClose' : 'folder.gif',
					'onClick' : function (branch) { 
							clickTree(branch.getId(),0) ;
						}
					});
					oidtree.generate();
			");
	}
	}
?>
<?php

include_once('include/page_footer.php');

?>

<?php
function get_module_name($filename)
{
	$modulename = '';
	$handle = @fopen($filename, "r");
	if ($handle) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 4096);
			if (preg_match('/^\s*(\S+)\s*DEFINITIONS\s*::=\s*BEGIN/i',$buffer,$matches))
			{
				$modulename = $matches[1];
				break;
			}
			
		}
		fclose($handle);
	}
	return ($modulename);
}

function get_oid_from_name($name)
{
	$oid = exec("snmptranslate -M ".MIBS_ALL_PATH." -m ALL -On $name");
	
	if (preg_match('/[0123456789\.]+/', $oid))
		return $oid;
	else
		return null;
}

function get_table_value($community, $server_ip, $oid)
{
	// table view
	$rows = array();
	if ($server_ip =="")
	{
		$rows[0] = array("No server ip.");
	}
	else
	{
		exec("snmptable -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid -Ci -Ch -Cf \",\"", $results);
		$headers = explode(",",$results[0]);
		unset($results);
		exec("snmptable -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid -Ci -CH -Cf \",\"", $results);
		foreach ($results as $line)
		{
			$row = explode(",",$line);
			array_push($rows, $row);
		}
		unset($results);
	}
			
	$value = array('ret' => 1,'headers' => $headers, 'rows' => $rows);
	return ($value);
}

function get_oid_value($community, $server_ip, $oid, $idx)
{	
	if (!$server_ip){
		$row = array('Missing server ip.','','');
		$value = array('ret' => 0,'row' => $row);
		return ($value);
	}
	
	// idx is number or string thank danrog
	if (preg_match('/^[0-9]+$/', $idx)) {
		$cmd = "snmpget -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid.$idx";  
    } else {        
		$cmd = "snmpget -v 2c -c $community -M ".MIBS_ALL_PATH." -m ALL $server_ip $oid.\"".$idx."\"";
    }	
	$results = exec($cmd);
	
	//exampe: IP-MIB::ipOutRequests.0 = Counter32: 12303729
	if (preg_match('/^(\S+) = (\S+): (.+)$/i', $results, $matches)) // full information
	{
		$row = array($matches[1], $matches[2], $matches[3]);
	}
	else if (preg_match('/^(\S+) = (\S+):$/i', $results, $matches)) //no value
	{
		$row = array($matches[1], $matches[2],'');
	}
	else if (preg_match('/^(\S+) = (.+)$/i', $results, $matches)) //no type
	{
		$row = array($matches[1], '',$matches[2]);
	}
	else // error
		$row = array($results,'','');
	$value = array('ret' => 0,'row' => $row);
	return ($value);
}

function get_oid_content($oid)
{
	exec("snmptranslate -Td -OS -M ".MIBS_ALL_PATH." -m ALL $oid", $results);
		
	$content = implode("<br>",$results);
	return ($content);
}

//Get oid tree per mib 
function get_oid_tree($mib)
{
	exec("snmptranslate -Ts -M ".MIBS_ALL_PATH." -m $mib 2>&1", $results);
	$oid_tree = explodeTree($mib, $results);
	return $oid_tree;
}

function get_templates()
{
	$options = array(
			'extendoutput' => 1,
			'select_templates' => 1,
			'nopermissions' => 1
		);
	$template = array();
        $templateget = API::Template()->get([
               'output' => ['templateid', 'name'],
               'preservekeys' => true
        ]);

#	foreach (CTemplate::get($options) as $key => $value)
#	{
#		array_push($template, array('key' => $key, 'host' => $value['host']));
#	}
#	
	foreach ($templateget as $key => $value)
	{
		array_push($template, array('key' => $key, 'host' => $value['name']));
	}
	return $template;
	
}

function json_error($msg)
{
	echo json_encode(array('error' => $msg));
	exit();
}

function explodeTree($mib, $array, $delimiter = '.')
{
	if(!is_array($array)) return false;
	$splitRE   = '/' . preg_quote($delimiter, '/') . '/';
	$returnArr['id']='';
	$returnArr['txt']=$mib;
	$returnArr['imgopen'] = 'globe.gif';
	$returnArr['imgclose'] = 'globe.gif';
	$returnArr['items']=array(array('id'=>'.iso','txt'=>'iso'),array('id'=>'.ccitt','txt'=>'ccitt'));

    foreach ($array as $key) {
		
        // Get parent parts and the current leaf
        $parts    = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
		$leaf = array_pop($parts);
		$parentArr = &$returnArr;
	
		foreach ($parts as $part) {
			$child_id = $parentArr['id'].'.'.$part;
			if (!isset($parentArr['items']))
				$parentArr['items'] = array();
		
			for ($i = 0; $i <count($parentArr['items']); $i++)
			{
				if ($parentArr['items'][$i]['id'] == $child_id)
					break;
			}
		
			if (!isset($parentArr['items'][$i]))
			{
				echo $child_id." ".$leaf." ".$key;
				exit();
			}
		
			$parentArr = &$parentArr['items'][$i];
		}
		if (!isset($parentArr['items']))
			$parentArr['items'] = array();
		$i = count($parentArr['items']);
		$parentArr['items'][$i]['id'] = $key;
		$parentArr['items'][$i]['txt'] = $leaf;
		if  (preg_match('/^\w+Table$/',$leaf))
		{
			$parentArr['items'][$i]['imgopen'] = 'table.gif';
			$parentArr['items'][$i]['imgclose'] = 'table.gif';
		}
	
    }
    
    return $returnArr;
}
?>


