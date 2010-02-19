// Copyright (c) 2007 Gregory SCHURGAST (http://www.negko.com, http://prototools.negko.com)
// 
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
// VERSION 1.2.20090611

//DynTable is modified from Table_Orderer - giap nguyen 
var DynTable = Class.create();
DynTable.prototype = {
	initialize: function(element,options) {
		this.element = element;
		this.options = options;
		
		this.options = Object.extend({
			headers: [],
			observer: null	
		}, options || {});
		
		this.data = [];
		
		this.headers = this.options.headers;
		this.observer = this.options.observer;
		
		this.container = $(element);
		this.createTable();
	},
	
	clear : function()
	{
		this.data = [];
		this.updateTable();
	},
	
	update : function(headers, rows, observer){
		if (headers) this.headers = headers;
		if (rows) this.data  = rows;
		if (observer) this.observer = observer;
		this.updateTable();
	},
	
	appendData : function(row, observer){
		var dup = false;
		this.data.each(function(i){
				if (row[0] === i[0]) 
					dup=true;
			});
		if (!dup)
		{
			if (observer) this.observer = observer;
			this.data[this.data.size()] = row;
			this.updateTable();
		}
		
	},
		
	addTableObserver : function() {
		var tid = this.table.id;
		var o = this.observer;
		if (!o) return;
		if (o.td)
		{
			$$('#'+tid+' tr.even_row td').invoke('observe','click',o.td.bindAsEventListener(this));
			$$('#'+tid+' tr.odd_row td').invoke('observe','click',o.td.bindAsEventListener(this));
		}
		else if (o.tr)
		{
			$$('#'+tid+' tr.even_row').invoke('observe','click',o.tr.bindAsEventListener(this));
			$$('#'+tid+' tr.odd_row').invoke('observe','click',o.tr.bindAsEventListener(this));
		}
		
		if (o.th)
		{
			$$('#'+tid+' tr.header td').invoke('observe','click',o.th.bindAsEventListener(this));
		}
	},
	
	createTable : function(){
		this.container.update();
		this.container.insert({ top: '<table class= "tableinfo" cellspacing="1" cellpadding="3" id="dyntable-'+this.element+'"></table>' });
		this.table = $('dyntable-'+this.element);
		this.tbody = new Element('tbody');
		this.table.insert({top: this.tbody});
		this.createRows();
		this.addTableObserver();
	},
	
	updateTable : function(){
		this.tbody.update();
		this.createRows();
		this.addTableObserver();
	},
	
	createRow : function(row_data,index){
		var row;
		if (index % 2)
		{
			row = new Element('tr',{'class' : 'odd_row'});
		}
		else
		{
			row = new Element('tr',{'class' : 'even_row'});
		}
		
		var cell;
		var j = 0;
		row_data.each(function(value){
			cell = new Element('td').update(value);
			cell.table_x = j;
			cell.table_y = index;
			row.insert({bottom: cell});
			j++;
		}.bind(this));
		
		return row;
	},
	
	createHeaderRow : function(){
		var row = new Element('tr',{'class' : 'header'});
		var i = 0;
		this.headers.each(function(item){
			cell = new Element('td').update(item);
			cell.table_x = i;
			row.insert({bottom: cell});
			i++
		}.bind(this));
		
		return row;
	},
	
	createRows : function(){
		// header information
		this.tbody.insert({ top: this.createHeaderRow() });	
		
		var index = 0;
		this.data.each(function(row_data){
			this.tbody.insert({ bottom: this.createRow(row_data,index) });
			index++;
		}.bind(this));
		
		// if there are no results
		s = this.headers.size();
		if(this.data.size() === 0 && s > 0) {
			row = '<tr class="even_row">\n';
			row += '\t<td class= "center" colspan="' + s + '">...</td>\n';
			row += '\n</tr>';
			this.tbody.insert({ bottom: row });
		}
	}
};