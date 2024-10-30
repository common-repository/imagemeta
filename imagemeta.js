/**********************************************************************************************
* 	ImageMeta Handlers
**********************************************************************************************/
jQuery(document).ready(function() {

    jQuery('#imagemetas input[type="text"]').addClass("idleField");  
    jQuery('#imagemetas input[type="text"]').focus(function() {  
        jQuery(this).removeClass("idleField").addClass("focusField");  
        if (this.value == this.defaultValue){  
            this.select(); 
        }  
    });  
    jQuery('#imagemetas input[type="text"]').blur(function() {  
        jQuery(this).removeClass("focusField").addClass("idleField");
        var fd = jQuery(this);
        if (fd.val() == unescape(this.defaultValue)){  
            this.value = (this.defaultValue ? unescape(this.defaultValue) : '');  
        } else {	var fname = fd.attr('id').split(":");
        	var fval = fd.val();
        	updateField(fname,fval);
        }
	});
	//purging unused images
	jQuery("a.purge").on("click",function(){
		if(parseInt(jQuery(this).attr("rel")) > 0){
			var postid = parseInt(jQuery(this).attr("rel"));
			purgeImage(postid,false); //confirm the purge first
		}
	});
	//pager, sort, search & delete
	jQuery("span.sort").on("click", function(){
		var sort = jQuery(this).attr("rel"), cursort = jQuery("#s");
		if(sort == cursort.val()) return(false);
		cursort.val(sort);
		jQuery("#form_imagemeta").submit();
	});
	jQuery("li.pg").on("click", function(){
		var page = jQuery(this).attr("rel"), curpage = jQuery("#p");
		if(page == curpage.val()) return(false);
		curpage.val(page);
		jQuery("#form_imagemeta").submit();
	});
	jQuery("#imgsearch").on("keyup", function( event ){
		if( event.which !== 13 ) return(false);
		jQuery("#form_imagemeta").submit();
	});
	jQuery("span.delete").on("click", function(){
		var remove = parseInt(jQuery(this).attr("rel"));
		if(!Number.isInteger(remove) || remove < 0) return(false);
		if(!confirm("Are you sure you want to delete this item?")) return(false);
		jQuery("#remove").val(remove);
		jQuery("#form_imagemeta").submit();
	});
	
	
	jQuery("a.cleanup").on("click",function(){
		if (confirm("Delete "+missing.length+" images from your WordPress database?\nThis action cannot be undone.") == true) {
			var req = { action: 'cleanup', ids: missing }
			var jqxhr = jQuery.post( ajax_object.ajaxurl, req, function( data ){
				if(typeof(data.cleanup)!="undefined") jQuery("#cleanup").val(data.cleanup);
				jQuery("#form_imagemeta").submit();
			});
		} else { /* do nothing */ } 

	});
	
	//show the donate panel
	var $donateform = jQuery("div.donate").html();
	jQuery("div.donate").html("<form action='https://www.paypal.com/cgi-bin/webscr' method='post' id='donate' target='_blank'>"+$donateform+"</form>").css("display","block");

});

function updateField(fname,fval){
    showLoading(fname[0]+":"+fname[1]);  		// shows updating gif
	jQuery.post(ajax_object.ajaxurl, {
		action: 'ajax_action',
		fval: fval,
		fname: fname							// query is built in ajax function; returns true/false
	}, function(data) {
		//alert(data); 							// changes default value
		document.getElementById(fname[0]+":"+fname[1]).defaultValue = escape(fval);
		hideLoading(fname[0]+":"+fname[1]);		// hides updating gif
	});
	return;
}
// update indicators
function showLoading(div){
	if(document.getElementById(div)) {
		document.getElementById(div).className = 'updateField';
	}
}
function hideLoading(div){
	if(document.getElementById(div)) {
		document.getElementById(div).className = 'idleField';
	}
}
// copy titles across fields
function copyAcross(fID,mID){
	fval = document.getElementById("post_title:"+fID).value;
	var caption = document.getElementById('post_excerpt:'+fID);
	if(caption) { caption.value = unescape(fval);
				  updateField(["post_excerpt",fID],fval); }
				  
	var description = document.getElementById('post_content:'+fID);
	if(description) { description.value = unescape(fval);
					  updateField(["post_content",fID],fval); }
					  
	var alt = document.getElementById('meta_value:'+mID);
	if(alt) { alt.value = unescape(fval);
			  updateField(["meta_value",mID],fval); }
	return;	
}
//purging unused images
function purgeImage(postid, confirmed){
	var req = { action: 'purge', postid: postid, confirmed: confirmed }
	var jqxhr = jQuery.post( ajax_object.ajaxurl, req, function( data ){
		
		//commit the purge
		if(req.confirmed){
			jQuery('<div></div>').dialog({
				modal: true,
				appendTo: "#imagemeta",
				title: "Results",
				open: function() {
					jQuery(this).html(data.results);
					var modal = this;
					jQuery("#imagemeta .ui-widget-overlay.ui-front").one("click", function(){
						jQuery(modal).dialog("close");
					});
				},
				buttons: {
					Ok: function() {
						jQuery( this ).dialog( "close" );
						if(data.errors < 1) jQuery(data.row).addClass("purged");
					}
				}
			});
			return;
		}

		//open the confirmation dialog
		jQuery('<div></div>').dialog({
			modal: true,
			appendTo: "#imagemeta",
			title: "Delete Unused Image?",
			open: function() {
				jQuery(this).html(data.confirm);
				var modal = this;
				jQuery("#imagemeta .ui-widget-overlay.ui-front").one("click", function(){
					jQuery(modal).dialog("close");
				});
			},
			buttons: {
				Delete: function() {
					jQuery( this ).dialog( "close" );
					purgeImage(req.postid, true)
				}
			}
		});	
	});
}