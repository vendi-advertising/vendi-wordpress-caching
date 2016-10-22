/* globals WordfenceAdminVars, jQuery */
(function($) {
	if (!window.hasOwnProperty( 'wordfenceAdmin')) {
		window.wordfenceAdmin = {
			loadingCount: 0,
			colorboxQueue: [],
			nonce: false,

			msgs: {
			},

			init: function() {
				this.setupAdminVars();
				var self = this;

				$(document).focus();

				if (jQuery('#wordfenceMode_caching').length > 0) {
					this.loadCacheExclusions();
					jQuery(document).bind('cbox_closed', function() {
						self.colorboxIsOpen = false;
						self.colorboxServiceQueue();
					});
				}
			},

			setupAdminVars: function() {
				this.nonce = WordfenceAdminVars.firstNonce;

				this.msgs.msg_loading = WordfenceAdminVars.msg_loading;
				this.msgs.msg_general_error = WordfenceAdminVars.msg_general_error;

				this.msgs.msg_heading_enable_enhanced = WordfenceAdminVars.msg_heading_enable_enhanced;
				this.msgs.msg_heading_error = WordfenceAdminVars.msg_heading_error;
				this.msgs.msg_heading_invalid_pattern = WordfenceAdminVars.msg_heading_invalid_pattern;
				this.msgs.msg_heading_cache_exclusions = WordfenceAdminVars.msg_heading_cache_exclusions;

				this.msgs.msg_switch_apache = WordfenceAdminVars.msg_switch_apache;
				this.msgs.msg_switch_nginx = WordfenceAdminVars.msg_switch_nginx;
				this.msgs.msg_switch_error = WordfenceAdminVars.msg_switch_error;
				this.msgs.msg_manual_update = WordfenceAdminVars.msg_manual_update;
				this.msgs.msg_invalid_pattern = WordfenceAdminVars.msg_invalid_pattern;
				this.msgs.msg_no_exclusions = WordfenceAdminVars.msg_no_exclusions;
			},
			showLoading: function() {
				this.loadingCount++;
				if (this.loadingCount == 1) {
					jQuery('<div id="wordfenceWorking">' + this.msgs.msg_loading + '</div>').appendTo('body');
				}
			},
			removeLoading: function() {
				this.loadingCount--;
				if ( 0 === this.loadingCount ) {
					jQuery('#wordfenceWorking').remove();
				}
			},
			ajax: function(action, data, cb, cbErr, noLoading) {
				if (typeof(data) == 'string') {
					if (data.length > 0) {
						data += '&';
					}
					data += 'action=' + action + '&nonce=' + this.nonce;
				} else if (typeof(data) == 'object' && data instanceof Array) {
					// jQuery serialized form data
					data.push({
						name: 'action',
						value: action
					});
					data.push({
						name: 'nonce',
						value: this.nonce
					});
				} else if (typeof(data) == 'object') {
					data.action = action;
					data.nonce = this.nonce;
				}
				if (!cbErr) {
					cbErr = function() {
					};
				}
				var self = this;
				if (!noLoading) {
					this.showLoading();
				}
				jQuery.ajax({
					type: 'POST',
					url: WordfenceAdminVars.ajaxURL,
					dataType: "json",
					data: data,
					success: function(json) {
						if (!noLoading) {
							self.removeLoading();
						}
						if (json && json.nonce) {
							self.nonce = json.nonce;
						}
						if (json && json.errorMsg) {
							self.colorbox('400px', this.msgs.msg_general_error, json.errorMsg);
						}
						cb(json);
					},
					error: function() {
						if (!noLoading) {
							self.removeLoading();
						}
						cbErr();
					}
				});
			},
			colorbox: function(width, heading, body, settings) {
				if (typeof settings === 'undefined') {
					settings = {};
				}
				this.colorboxQueue.push([width, heading, body, settings]);
				this.colorboxServiceQueue();
			},
			colorboxServiceQueue: function() {
				if (this.colorboxIsOpen) {
					return;
				}
				if (this.colorboxQueue.length < 1) {
					return;
				}
				var elem = this.colorboxQueue.shift();
				this.colorboxOpen(elem[0], elem[1], elem[2], elem[3]);
			},
			colorboxOpen: function(width, heading, body, settings) {
				var self = this;
				this.colorboxIsOpen = true;
				jQuery.extend(settings, {
					width: width,
					html: "<h3>" + heading + "</h3><p>" + body + "</p>",
					onClosed: function() {
						self.colorboxClose();
					}
				});
				jQuery.colorbox(settings);
			},
			colorboxClose: function() {
				this.colorboxIsOpen = false;
				jQuery.colorbox.close();
			},
			errorMsg: function(msg) {
				this.colorbox('400px', this.msgs.msg_general_error, msg);
			},
			//KEEP
			getCacheStats: function() {
				var self = this;
				this.ajax('wordfence_getCacheStats', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', res.heading, res.body);
					}
				});
			},
			//KEEP
			clearPageCache: function() {
				var self = this;
				this.ajax('wordfence_clearPageCache', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', res.heading, res.body);
					}
				});
			},
			//KEEP
			switchToFalcon: function() {
				var self = this;
				this.ajax('wordfence_checkFalconHtaccess', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', self.msgs.msg_heading_enable_enhanced, self.msgs.msg_switch_apache );
					} else if (res.nginx) {
						self.colorbox('400px', self.msgs.msg_heading_enable_enhanced, self.msgs.msg_switch_nginx );
					} else if (res.err) {
						var p1 = res.err;
						var p2 = jQuery('<div/>').text(res.code).html();
						var msg = self.msgs.msg_switch_error.replace( '@@1@@', p1 ).replace( '@@2@@', p2 );
						self.colorbox('400px', self.msgs.msg_heading_error, msg );
					}
				});
			},
			//KEEP
			confirmSwitchToFalcon: function(noEditHtaccess) {
				jQuery.colorbox.close();
				var cacheType = 'enhanced';
				var self = this;
				this.ajax('wordfence_saveCacheConfig', {
						cacheType: cacheType,
						noEditHtaccess: noEditHtaccess
					}, function(res) {
						if (res.ok) {
							self.colorbox('400px', res.heading, res.body);
						}
					}
				);
			},
			//KEEP
			saveCacheConfig: function() {
				var cacheType = jQuery('input:radio[name=cacheType]:checked').val();
				if (cacheType == 'enhanced') {
					return this.switchToFalcon();
				}
				var self = this;
				this.ajax('wordfence_saveCacheConfig', {
						cacheType: cacheType
					}, function(res) {
						if (res.ok) {
							self.colorbox('400px', res.heading, res.body);
						}
					}
				);
			},
			//KEEP
			saveCacheOptions: function() {
				var self = this;
				this.ajax('wordfence_saveCacheOptions', {
						allowHTTPSCaching: (jQuery('#wfallowHTTPSCaching').is(':checked') ? 1 : 0),
						addCacheComment: (jQuery('#wfaddCacheComment').is(':checked') ? 1 : 0),
						clearCacheSched: (jQuery('#wfclearCacheSched').is(':checked') ? 1 : 0)
					}, function(res) {
						if (res.updateErr) {
							var p1 = res.updateErr;
							var p2 = jQuery('<div/>').text(res.code).html();
							var msg = self.msgs.msg_manual_update.replace( '@@1@@', p1 ).replace( '@@2@@', p2 );
							self.colorbox('400px', msg );
						}
					}
				);
			},
			//KEEP
			removeCacheExclusion: function(id) {
				this.ajax('wordfence_removeCacheExclusion', {id: id}, function(res) {
					window.location.reload(true);
				});
			},
			//KEEP
			addCacheExclusion: function(patternType, pattern) {
				if (/^https?:\/\//.test(pattern)) {
					this.colorbox('400px', this.msgs.msg_heading_invalid_pattern, this.msgs.msg_invalid_pattern );
					return;
				}

				this.ajax('wordfence_addCacheExclusion', {
					patternType: patternType,
					pattern: pattern
				}, function(res) {
					if (res.ok) { //Otherwise errorMsg will get caught
						window.location.reload(true);
					}
				});
			},
			//KEEP
			loadCacheExclusions: function() {
				var self = this;
				this.ajax('wordfence_loadCacheExclusions', {}, function(res) {
					if (res.ex instanceof Array && res.ex.length > 0) {
						for (var i = 0; i < res.ex.length; i++) {
							var newElem = jQuery('#wfCacheExclusionTmpl').tmpl(res.ex[i]);
							newElem.prependTo('#wfCacheExclusions').fadeIn();
						}
						jQuery('<h2>' + self.msgs.msg_heading_cache_exclusions + '</h2>').prependTo('#wfCacheExclusions');
					} else {
						jQuery('<h2>' + self.msgs.msg_heading_cache_exclusions + '</h2><p style="width: 500px;">' + self.msgs.msg_no_exclusions + '</p>').prependTo('#wfCacheExclusions');
					}

				});
			}
		};

		window.WFAD = window.wordfenceAdmin;
	}
	jQuery(function() {
		window.wordfenceAdmin.init();
		jQuery(window).on('focus', function() {
			if (jQuery('body').hasClass('wordfenceLiveActivityPaused')) {
				jQuery('body').removeClass('wordfenceLiveActivityPaused');
			}
		});
	});
})(jQuery);
