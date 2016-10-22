(function($) {
	if (!window['wordfenceAdmin']) { //To compile for checking: java -jar /usr/local/bin/closure.jar --js=admin.js --js_output_file=test.js
		window['wordfenceAdmin'] = {
			loadingCount: 0,
			colorboxQueue: [],
			nonce: false,

			init: function() {
				this.nonce = WordfenceAdminVars.firstNonce;
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
			showLoading: function() {
				this.loadingCount++;
				if (this.loadingCount == 1) {
					jQuery('<div id="wordfenceWorking">Wordfence is working...</div>').appendTo('body');
				}
			},
			removeLoading: function() {
				this.loadingCount--;
				if (this.loadingCount == 0) {
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
					data['action'] = action;
					data['nonce'] = this.nonce;
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
							self.colorbox('400px', 'An error occurred', json.errorMsg);
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
				this.colorbox('400px', "An error occurred:", msg);
			},
			es: function(val) {
				if (val) {
					return val;
				} else {
					return "";
				}
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
						self.colorbox('400px', "Enabling Falcon Engine", 'First read this <a href="http://www.wordfence.com/introduction-to-wordfence-falcon-engine/" target="_blank">Introduction to Falcon Engine</a>. Falcon modifies your website configuration file which is called your .htaccess file. To enable Falcon we ask that you make a backup of this file. This is a safety precaution in case for some reason Falcon is not compatible with your site.<br /><br /><a href="' + WordfenceAdminVars.ajaxURL + '?action=wordfence_downloadHtaccess&nonce=' + self.nonce + '" onclick="jQuery(\'#wfNextBut\').prop(\'disabled\', false); return true;">Click here to download a backup copy of your .htaccess file now</a><br /><br /><input type="button" name="but1" id="wfNextBut" value="Click to Enable Falcon Engine" disabled="disabled" onclick="WFAD.confirmSwitchToFalcon(0);" />');
					} else if (res.nginx) {
						self.colorbox('400px', "Enabling Falcon Engine", 'You are using an Nginx web server and using a FastCGI processor like PHP5-FPM. To use Falcon you will need to manually modify your nginx.conf configuration file and reload your Nginx server for the changes to take effect. You can find the <a href="http://www.wordfence.com/blog/2014/05/nginx-wordfence-falcon-engine-php-fpm-fastcgi-fast-cgi/" target="_blank">rules you need to make these changes to nginx.conf on this page on wordfence.com</a>. Once you have made these changes, compressed cached files will be served to your visitors directly from Nginx making your site extremely fast. When you have made the changes and reloaded your Nginx server, you can click the button below to enable Falcon.<br /><br /><input type="button" name="but1" id="wfNextBut" value="Click to Enable Falcon Engine" onclick="WFAD.confirmSwitchToFalcon(1);" />');
					} else if (res.err) {
						self.colorbox('400px', "We encountered a problem", "We can't modify your .htaccess file for you because: " + res.err + "<br /><br />Advanced users: If you would like to manually enable Falcon yourself by editing your .htaccess, you can add the rules below to the beginning of your .htaccess file. Then click the button below to enable Falcon. Don't do this unless you understand website configuration.<br /><textarea style='width: 300px; height:100px;' readonly>" + jQuery('<div/>').text(res.code).html() + "</textarea><br /><input type='button' value='Enable Falcon after manually editing .htaccess' onclick='WFAD.confirmSwitchToFalcon(1);' />");
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
							self.colorbox('400px', "You need to manually update your .htaccess", res.updateErr + "<br />Your option was updated but you need to change the Wordfence code in your .htaccess to the following:<br /><textarea style='width: 300px; height: 120px;'>" + jQuery('<div/>').text(res.code).html() + '</textarea>');
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
					this.colorbox('400px', "Incorrect pattern for exclusion", "You can not enter full URL's for exclusion from caching. You entered a full URL that started with http:// or https://. You must enter relative URL's e.g. /exclude/this/page/. You can also enter text that might be contained in the path part of a URL or at the end of the path part of a URL.");
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
				this.ajax('wordfence_loadCacheExclusions', {}, function(res) {
					if (res.ex instanceof Array && res.ex.length > 0) {
						for (var i = 0; i < res.ex.length; i++) {
							var newElem = jQuery('#wfCacheExclusionTmpl').tmpl(res.ex[i]);
							newElem.prependTo('#wfCacheExclusions').fadeIn();
						}
						jQuery('<h2>Cache Exclusions</h2>').prependTo('#wfCacheExclusions');
					} else {
						jQuery('<h2>Cache Exclusions</h2><p style="width: 500px;">There are not currently any exclusions. If you have a site that does not change often, it is perfectly normal to not have any pages you want to exclude from the cache.</p>').prependTo('#wfCacheExclusions');
					}

				});
			},
		};

		window['WFAD'] = window['wordfenceAdmin'];
	}
	jQuery(function() {
		wordfenceAdmin.init();
		jQuery(window).on('focus', function() {
			if (jQuery('body').hasClass('wordfenceLiveActivityPaused')) {
				jQuery('body').removeClass('wordfenceLiveActivityPaused');
			}
		});
	});
})(jQuery);
