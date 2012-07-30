function completeInsertShop(ret_obj, response_tags) {
	alert(ret_obj['message']);
	location.href=current_url.setQuery('act','dispShopAdminList');
}

function completeInsertGrant(ret_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	var page = ret_obj['page'];
	var module_srl = ret_obj['module_srl'];
	alert(message);
}

function completeInsertConfig(ret_obj, response_tags) {
	alert(ret_obj['message']);
	location.reload();
}

function completeDeleteShop(ret_obj) {
	alert(ret_obj['message']);
	location.href=current_url.setQuery('act','dispShopAdminList').setQuery('module_srl','');
}


function toggleAccessType(target) {
	switch(target) {
		case 'domain' :
				xGetElementById('shopFo').domain.value = '';
				xGetElementById('accessDomain').style.display = 'block';
				xGetElementById('accessVid').style.display = 'none';
			break;
		case 'vid' :
				xGetElementById('shopFo').vid.value = '';
				xGetElementById('accessDomain').style.display = 'none';
				xGetElementById('accessVid').style.display = 'block';
			break;
	}
}

function completeReload() {
    location.reload();
}

function doApplySubChecked(obj, id) {
    jQuery('div.menu_box_'+id).find('input[type=checkbox]').each(function() { this.checked = obj.checked; });

}
