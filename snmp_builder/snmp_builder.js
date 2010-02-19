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

// On click a node on the oid tree
// Make an ajax call to retrieve information and value of oid
// Show result in oidview table
// viewtype: 	0 : auto detect oid is table or not (assume if its name's end with string "Table", it is a table)
//		1 : it's a table.
function clickTree(oid, idx, viewtype)
{
	if (!oid) return;
	if (!viewtype || viewtype == 0){
		viewtype = 0;
		$('viewtype').checked = false; //sync with viewtype checkbox
	}

	var server_ip = $F($('server_ip'));
	var community = $F($('community'));
	var mib = $F($('mib'));
	var get_oid_url = 'snmp_builder.php?select=1&output=json';
	
	oidview._oid = oid;
	oidview._idx = idx;
	new Ajax.Request(get_oid_url, {
		method: 'post',
		parameters: {mib: mib, server_ip: server_ip, community: community, oid: oid, idx: idx, viewtype : viewtype},
		onSuccess: function(transport) {
			var json = transport.responseText.evalJSON();
			if (json.error)
			{
				alert(json.error);
				return;
			}
			
			$('oidinfo').update(json.info);
			switch (json.value.ret)
			{
				case 0: //full information
					oidview.update(['Oid/Name','Type','Value'],[json.value.row],{tr: onClickOid});
					break;
				case 1: // table
					oidview.update(json.value.headers,json.value.rows,{td: onClickCell, th: onClickHeader});
					break;
			}
		}
	});
}

//Make a simple convert from snmp type to a zabbix item
//Support INTEGER , Couter32, Timeticks, STRING.
function convertOid(oid, type)
{
	if (!type) return null; //no type?
	var row;
	switch (type)
	{
		case 'INTEGER':
			row = [oid, 'Numeric','Decimal','',60,'','No'];
			break;
		case 'Counter32':
			row = [oid, 'Numeric','Decimal','',60,'','Yes'];
			break;
		case 'Timeticks':
			row = [oid, 'Numeric','Decimal','s',60,'0.01','no'];
			break;
		case 'STRING':
			row = [oid, 'Text','','',60,'','No'];
			break;
		default:
			row = [oid, 'Text','','',60,'','No'];
			break;
	}
	return row;
}

//On click a oid on oidview
//Convert then insert it into itemlist
function onClickOid(e)
{
	
	var row = this.data[0];
	var item =convertOid(row[0],row[1]);
	if (item)
	{
		itemlist.appendData(item);
		Event.element(e).setStyle('background-color:yellow');
	}
}


//On click a cell in tableview
//Make an ajax call to retrieve full information of the oid + its index
//Convert then insert it into itemlist
function onClickCell(e)
{
	var server_ip = $F($('server_ip'));
	var community = $F($('community'));
	var mib = $F($('mib'));
	
	var x = Event.element(e).table_x;
	var y = Event.element(e).table_y;
	
	if (x > 0)
	{
		oid = this.headers[x];
		idx = this.data[y][0];
		
		var get_oid_url = 'snmp_builder.php?select=1&output=json';
		new Ajax.Request(get_oid_url, {
			method: 'post',
			parameters: {mib: mib, server_ip: server_ip, community: community, oid: oid, idx: idx},
			onSuccess: function(transport) {
				var json = transport.responseText.evalJSON();
				if (json.error)
				{
					alert(json.error);
					return;
				}
				
				$('oidinfo').update(json.info);
				switch (json.value.ret)
				{
					case 0: //full information
						var item =convertOid(json.value.row[0],json.value.row[1]);
						if (item)
						{
							itemlist.appendData(item);
						}
						break;
				}
			}
		});
		Event.element(e).setStyle('background-color:yellow');
	}
	
}

//On click a header in tableview
//Same with click a cell but we only make an ajax call to retrieve full information of first index,
//Convert then insert it into itemlist then clone the row for rest.
function onClickHeader(e)
{
	var server_ip = $F($('server_ip'));
	var community = $F($('community'));
	var mib = $F($('mib'));
	
	var x = Event.element(e).table_x;
	var get_oid_url = 'snmp_builder.php?select=1&output=json';
	if (x>0)
	{
		oid = this.headers[x];
		idx = this.data.first()[0]; 
		var s_idx = new String(idx);
				
		var server_ip = $F($('server_ip'));
		var community = $F($('community'));
		var mib = $F($('mib'));
		var get_oid_url = 'snmp_builder.php?select=1&output=json';
		new Ajax.Request(get_oid_url, {
			method: 'post',
			parameters: {mib: mib, server_ip: server_ip, community: community, oid: oid, idx: idx},
			onSuccess: function(transport) {
				var json = transport.responseText.evalJSON();
				if (json.error)
				{
					alert(json.error);
					return;
				}
				
				$('oidinfo').update(json.info);
				switch (json.value.ret)
				{
					case 0: //full information
						var item =convertOid(json.value.row[0],json.value.row[1]);
						
						if (item)
						{
							this.data.each(function (row){
								item1 = item.clone();
								s_oid1 = item1[0].substr(0,item1[0].length - s_idx.length);
								item1[0] = s_oid1 + row[0];
								
								itemlist.appendData(item1);
							});
							Event.element(e).setStyle('background-color:yellow');
						}
						break;
				}
			}.bind(this)
		});
	}
	
}

// On click save button
// Send itemlist data and update results 
function onSaveItems(e)
{
	var templateid = $F($('templateid'));
	var community = $F($('community'));
	
	if (itemlist.data.size() === 0)
		return;
	json = itemlist.data.toJSON();
	var get_oid_url = 'snmp_builder.php?save=1&output=json';
	new Ajax.Request(get_oid_url, {
		method: 'post',
		parameters: {templateid: templateid, community: community, oids: json},
		onSuccess: function(transport) {
			if (json.error)
			{
				alert(json.error);
				return;
			}
			$('message').update(transport.responseText);
		
		}	
	});
}

//On click Clear button
//Clear itemlist data
function onClearItems(e)
{
	itemlist.clear();
}

//On click a row of itemlist table
//Remove the row
function onClickItem(e)
{
	var y = Event.element(e).table_y;
	var value = this.data[y];
	this.update(null,this.data.without(value),null);
}

//On click the viewtype checkbox
function onViewType(e)
{
	var viewtype = $F($('viewtype')); // 0: autodetect, 1:table;
	clickTree(oidview._oid, oidview._idx, viewtype);
}