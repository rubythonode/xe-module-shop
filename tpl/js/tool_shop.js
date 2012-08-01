function unique(t) {
	var a = [];
	var l = t.length;
	for(var i=0; i<l; i++) {
		for(var j=i+1; j<l; j++) {
			if (t[i] === t[j])
				j = ++i;
		}
		a.push(t[i]);
	}
	return a;
}

function completeInsertCategory(){
	jQuery('#category_info').html("");
	Tree(xml_url);
}

function completeModifyPassword(ret_obj, response_tags, args, fo_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	alert(message);
	location.reload();
}

function completeInsertConfig(ret_obj, response_tags, args, fo_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	var mid = ret_obj['mid'];

	location.reload();

}


function addCategory(){
	var category_title= jQuery('[name=add_category]').val();
	var parent_srl = jQuery('#category').val();
	if(!category_title) return;
	var response_tags = new Array('error','message','xml_file','category_srl');
	exec_xml('document','procDocumentInsertCategory',{'mid':current_mid,'title':category_title,'parent_srl':parent_srl},completeAddCategory,response_tags);
}

function completeAddCategory(ret_obj, response_tags, args, fo_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	var xml_file = ret_obj['xml_file'];
	var category_srl = ret_obj['category_srl'];

	var sel = jQuery('#category').get(0);	
	var n = sel.options[0].text[sel.options[0].text.length-1];
	n+=n;
	for(i=0,c=sel.length;i<c;i++) sel.options[1] = null;

	jQuery.get(xml_file,function(data){
		var c = '';
			jQuery(data).find("node").each(function(j){
				var node_srl = jQuery(this).attr("node_srl");
				var document_count = jQuery(this).attr("document_count");
				var text = jQuery(this).attr("text") +'('+document_count+')';

				for(i=0,c=jQuery(this).parents('node').size();i<c;i++) text = n +text;
				sel.options[sel.options.length] = new Option(text,node_srl, false,false);
				if(node_srl == category_srl) sel.selectedIndex = j;
			});
	});
	jQuery('[name=add_category]').val('');
	jQuery('#add_category').removeClass('open');
}

function completeReload(ret_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	location.href = location.href;
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

function completeInsertProfile(ret_obj) {
	var fo = jQuery('#foProfile');
	var photo = jQuery('#photo');
	var src = photo.get(0).value;
	if(!photo.get(0).value || !/\.(jpg|jpeg|gif|png)$/i.test(src)) {
		location.reload();
		return;
	}
	fo.append('<input type="hidden" name="act" value="procShopProfileImageUpload" />');
	fo.get(0).submit();
}

function getEditorSkinColorList(skin_name,selected_colorset,type){
	if(skin_name.length>0){
		type = type || 'comment';
		var response_tags = new Array('error','message','colorset');
		exec_xml('editor','dispEditorSkinColorset',{skin:skin_name},resultGetEditorSkinColorList,response_tags,{'selected_colorset':selected_colorset,'type':type});
	}
}

function resultGetEditorSkinColorList(ret_obj,response_tags, params) {

	var selectbox = null;
	if(params.type == 'comment'){
		selectbox = xGetElementById("sel_editor_comment_colorset");
	}else{
		selectbox = xGetElementById("sel_editor_guestbook_colorset");
	}

	if(ret_obj['error'] == 0 && ret_obj.colorset){
		var it = new Array();
		var items = ret_obj['colorset']['item'];
		if(typeof(items[0]) == 'undefined'){
			it[0] = items;
		}else{
			it = items;
		}
		var sel = 0;
		for(var i=0,c=it.length;i<c;i++){
			selectbox.options[i]=new Option(it[i].title,it[i].name);
			if(params.selected_colorset && params.selected_colorset == it[i].name) sel = i;
		}
		selectbox.options[sel].selected = true;
		selectbox.style.display="";
	}else{
		selectbox.style.display="none";
		selectbox.innerHTML="";
	}
}

function moveDate() {
	location.href = current_url.setQuery('selected_date',jQuery('#str_selected_date').text().replace(/\./g,''));
}

function doSelectSkin(skin) {
	var params = new Array();
	var response_tags = new Array('error','message');
	params['skin'] = skin;
	params['mid'] = current_mid;
	exec_xml('shop', 'procShopToolLayoutConfigSkin', params, completeReload, response_tags);
}

function doResetLayoutConfig() {
	var params = new Array();
	var response_tags = new Array('error','message');
	params['mid'] = current_mid;
	params['vid'] = xeVid;
	exec_xml('shop', 'procShopToolLayoutResetConfigSkin', params, completeReload, response_tags);
}

function completeUpdateAllow(ret_obj) {
	jQuery('.layerCommunicationConfig').removeClass('open');
	location.href=location.href;
}

function openLayerCommuicationConfig(){
	jQuery('input[name=document_srl]','.layerCommunicationConfig').val('');
	var v,srls = [];
	jQuery("input[name=document_srl]:checked").each(function(){
		v = jQuery(this).val();
		if(v) srls.push(v);
	});
	if(srls.length<1) return;
	jQuery('input[name=document_srl]','.layerCommunicationConfig').val(srls.join(','));
	jQuery('.layerCommunicationConfig').addClass('open');
}


function hideLayerCommuicationConfig(){
	jQuery('.layerCommunicationConfig').removeClass('open');
	jQuery('input[name=document_srl]','.layerCommunicationConfig').val('');
}



function toggleLnb() {
	if(xGetCookie('tclnb')) {
		xDeleteCookie('tclnb','/');
		jQuery(document.body).addClass('lnbToggleOpen');
		jQuery(document.body).removeClass('lnbClose');
	} else {
		var d = new Date();
		d.setDate(31);
		d.setMonth(12);
		d.setFullYear(2999);
		xSetCookie('tclnb',1,d,'/');
		jQuery(document.body).removeClass('lnbToggleOpen');
		jQuery(document.body).addClass('lnbClose');
	}
}

jQuery(function(){
	
	var saved_st_menu = xGetCookie('tclnb_menu');
	if(saved_st_menu) saved_st_menu = saved_st_menu.split(',');
	else saved_st_menu = [];

	jQuery("div#tool_navigation > ul > li:has(ul) > a").click(function(evt){
		jQuery(this).parent('li').toggleClass('open');
		jQuery(document.body).addClass('lnbToggleOpen');
		jQuery(document.body).removeClass('lnbClose');

		st_menu = [];
		jQuery("div#tool_navigation > ul > li:has(ul) > a").each(function(i){
			if(jQuery(this).parent('li').hasClass('open')) st_menu.push(i);
		});

		var d = new Date();
		d.setDate(31);
		d.setMonth(12);
		d.setFullYear(2999);
		st_menu = jQuery.unique(st_menu);
		xSetCookie('tclnb_menu',st_menu.join(','),d,'/');

		return false;
	}).each(function(i){
		if(jQuery.inArray(i+'',saved_st_menu)>-1) jQuery(this).parent('li').addClass('open');
	});

jQuery("div#tool_navigation > ul > li").hover(
	function(e){
		jQuery(this).addClass('hover');
	},function(e){
		jQuery(this).removeClass('hover');
	});

	jQuery("div.dashboardNotice>button").click(function(){
		jQuery("div.dashboardNotice").toggleClass('open','');
	});
});

addNode = function(node,e) {
    var params ={ "category_srl":0,"parent_srl":node,"module_srl":jQuery("#fo_category [name=module_srl]").val() };
    jQuery.exec_json('document.getDocumentCategoryTplInfo', params, function(data){
        jQuery('#category_info').html(data.tpl).css('left',e.pageX).css('top',e.pageY);
        if(node) jQuery('#category_info').find('tr').get(4).style.display = 'none';
        else jQuery('#category_info').find('tr').get(3).style.display = 'none';
    });


}
modifyNode = function(node,e) {
    var params ={ "category_srl":node ,"parent_srl":0 ,"module_srl":jQuery("#fo_category [name=module_srl]").val() };
    jQuery.exec_json('document.getDocumentCategoryTplInfo', params, function(data){
        jQuery('#category_info').html(data.tpl).css('left',e.pageX).css('top',e.pageY);
        jQuery('#category_info').find('tr').get(3).style.display = 'none';
    });
}



function doSetupComponent(component_name) {
    popopen(request_uri.setQuery('module','editor').setQuery('act','dispEditorAdminSetupComponent').setQuery('component_name',component_name), 'SetupComponent');
}

function doEnableComponent(component_name) {
    var params = new Array();
    params['component_name'] = component_name;

    exec_xml('editor', 'procEditorAdminEnableComponent', params, completeUpdate);
}
function doDisableComponent(component_name) {
    var params = new Array();
    params['component_name'] = component_name;

    exec_xml('editor', 'procEditorAdminDisableComponent', params, completeUpdate);
}

function doMoveListOrder(component_name, mode) {
    var params = new Array();
    params['component_name'] = component_name;
    params['mode'] = mode;

    exec_xml('editor', 'procEditorAdminMoveListOrder', params, completeUpdate);
}


function completeUpdate(ret_obj) {
    location.reload();
}

function initShop() {
	var params = new Array();
	params['mid'] = current_mid;
	params['vid'] = xeVid;

    exec_xml('shop','procShopToolInit', params,
		function(ret_obj){
			alert(ret_obj['message']);
			location.href = current_url.setQuery('act','dispShopToolDashboard');
		}, new Array('error','message'));
}
function checkUserImage(f,msg){
    var filename = jQuery('[name=user_image]',f).val();
    if(/\.(gif|jpg|jpeg|gif|png|swf|flv)$/i.test(filename)){
        return true;
    }else{
        alert(msg);
        return false;
    }
}
function deleteUserImage(filename){
    var params ={
            "mid":current_mid
			,"vid":xeVid
            ,"filename":filename
            };
    jQuery.exec_json('shop.procShopToolUserImageDelete', params, function(data){
        document.location.reload();
    });
}


(function($){

var inputPublish, submitButtons;
var validator = xe.getApp('Validator')[0];

validator.cast('ADD_CALLBACK', ['save_post', function callback(form) {
	var params={}, responses=[], elms=form.elements, data=jQuery(form).serializeArray();
	$.each(data, function(i, field) {
		var val = $.trim(field.value);
		if(!val) return true;
		if(/\[\]$/.test(field.name)) field.name = field.name.replace(/\[\]$/, '');
		if(params[field.name]) params[field.name] += '|@|'+val;
		else params[field.name] = field.value;
	});
	responses = ['error','message','mid','document_srl','category_srl', 'redirect_url'];
	exec_xml('shop','procShopPostsave', params, completePostsave, responses, params, form);

	inputPublish.val('N');
}]);

$(function(){
	inputPublish  = $('input[name=publish]');
	inputPreview  = $('input[name=preview]');
	submitButtons = $('#wPublishButtonContainer button');

	submitButtons.click(function(){
		inputPublish.val( $(this).parent().hasClass('_publish')?'Y':'N' );
		inputPreview.val( $(this).parent().hasClass('_preview')?'Y':'N' );
		$('input:text,textarea', this.form).each(function(){
			var t = $(this);
			var v = $.trim(t.val());
			if (v && v == t.attr('title')) t.val('');
		});

		if(editorRelKeys[1]) editorRelKeys[1].content.value = editorRelKeys[1].func();
	});
});

})(jQuery);



function changeMenuType(obj) {
    var sel = obj.options[obj.selectedIndex].value;
    if(sel == 'url') {
        jQuery('#urlForm').css("display","block");
    } else {
        jQuery('#urlForm').css("display","none");
    }
}


function isLive(){
	exec_xml('shop', 'procShopToolLive', []);
}

jQuery(function($){
	// Label Text Clear
	var iText = $('.fItem>.iLabel').next('.iText');
	$('.fItem>.iLabel').css('position','absolute');
	iText
		.focus(function(){
			$(this).prev('.iLabel').css('visibility','hidden');
		})
		.blur(function(){
			if($(this).val() == ''){
				$(this).prev('.iLabel').css('visibility','visible');
			} else {
				$(this).prev('.iLabel').css('visibility','hidden');
			}
		})
		.change(function(){
			if($(this).val() == ''){
				$(this).prev('.iLabel').css('visibility','visible');
			} else {
				$(this).prev('.iLabel').css('visibility','hidden');
			}
		})
		.blur();
});

jQuery(function($){
    // Label Text Clear
    var iTextarea = $('.fItem>.iLabel').next('.iTextarea');
    $('.fItem>.iLabel').css('position','absolute');
    iTextarea
        .focus(function(){
        $(this).prev('.iLabel').css('visibility','hidden');
    })
        .blur(function(){
            if($(this).val() == ''){
                $(this).prev('.iLabel').css('visibility','visible');
            } else {
                $(this).prev('.iLabel').css('visibility','hidden');
            }
        })
        .change(function(){
            if($(this).val() == ''){
                $(this).prev('.iLabel').css('visibility','visible');
            } else {
                $(this).prev('.iLabel').css('visibility','hidden');
            }
        })
        .blur();
});