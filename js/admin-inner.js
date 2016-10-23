/* globals VendiCacheAdminVars, jQuery */
if(! window.hasOwnProperty( 'vendiCacheExt' )) {
window.vendiCacheExt = {
	nonce: false,
	loadingCount: 0,
	init: function(){
		this.nonce = VendiCacheAdminVars.firstNonce; 
	},
	showLoading: function(){
		this.loadingCount++;
		if(this.loadingCount == 1){
			jQuery('<div style="padding: 2px 8px 2px 24px; z-index: 100000; position: fixed; right: 2px; bottom: 2px; border: 1px solid #000; background-color: #F00; color: #FFF; font-size: 12px; font-weight: bold; font-family: Arial; text-align: center;" id="wordfenceWorking">Wordfence is working...</div>').appendTo('body');
		}
	},
	removeLoading: function(){
		this.loadingCount--;
		if( 0 === this.loadingCount){
			jQuery('#wordfenceWorking').remove();
		}
	},
	removeFromCache: function(postID){
		this.ajax('wordfence_removeFromCache', {
			id: postID
			}, 
			function(res){ if(res.ok){ alert("Item removed from the Wordfence cache."); } },
			function(){}
			);
	},
	ajax: function(action, data, cb, cbErr, noLoading){
		if(typeof(data) == 'string'){
			if(data.length > 0){
				data += '&';
			}
			data += 'action=' + action + '&nonce=' + this.nonce;
		} else if(typeof(data) == 'object'){
			data.action = action;
			data.nonce = this.nonce;
		}
		if(! cbErr){
			cbErr = function(){};
		}
		var self = this;
		if(! noLoading){
			this.showLoading();
		}
		jQuery.ajax({
			type: 'POST',
			url: VendiCacheAdminVars.ajaxURL,
			dataType: "json",
			data: data,
			success: function(json){ 
				if(! noLoading){
					self.removeLoading();
				}
				if(json && json.nonce){
					self.nonce = json.nonce;
				}
				cb(json); 
			},
			error: function(){ 
				if(! noLoading){
					self.removeLoading();  
				}
				cbErr();
			}
			});
	}
};
}
jQuery(function(){
	window.vendiCacheExt.init();
});