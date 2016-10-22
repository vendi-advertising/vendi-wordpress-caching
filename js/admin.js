(function($) {
	if (!window['wordfenceAdmin']) { //To compile for checking: java -jar /usr/local/bin/closure.jar --js=admin.js --js_output_file=test.js
		window['wordfenceAdmin'] = {
			loading16: '<div class="wfLoading16"></div>',
			loadingCount: 0,
			dbCheckTables: [],
			dbCheckCount_ok: 0,
			dbCheckCount_skipped: 0,
			dbCheckCount_errors: 0,
			issues: [],
			ignoreData: false,
			iconErrorMsgs: [],
			scanIDLoaded: 0,
			colorboxQueue: [],
			mode: '',
			visibleIssuesPanel: 'new',
			preFirstScanMsgsLoaded: false,
			newestActivityTime: 0, //must be 0 to force loading of all initially
			elementGeneratorIter: 1,
			reloadConfigPage: false,
			nonce: false,
			tickerUpdatePending: false,
			activityLogUpdatePending: false,
			lastALogCtime: 0,
			activityQueue: [],
			totalActAdded: 0,
			maxActivityLogItems: 1000,
			scanReqAnimation: false,
			debugOn: false,
			blockedCountriesPending: [],
			ownCountry: "",
			schedStartHour: false,
			currentPointer: false,
			countryMap: false,
			countryCodesToSave: "",
			performanceScale: 3,
			performanceMinWidth: 20,
			tourClosed: false,
			welcomeClosed: false,
			passwdAuditUpdateInt: false,
			_windowHasFocus: true,
			serverTimestampOffset: 0,
			serverMicrotime: 0,
			wfLiveTraffic: null,

			init: function() {
				this.nonce = WordfenceAdminVars.firstNonce;
				this.debugOn = WordfenceAdminVars.debugOn == '1' ? true : false;
				this.tourClosed = WordfenceAdminVars.tourClosed == '1' ? true : false;
				this.welcomeClosed = WordfenceAdminVars.welcomeClosed == '1' ? true : false;
				var startTicker = false;
				var self = this;

				$(window).on('blur', function() {
					self._windowHasFocus = false;
				}).on('focus', function() {
					self._windowHasFocus = true;
				}).focus();

				$(document).focus();

				if (jQuery('#wordfenceMode_caching').length > 0) {
					this.mode = 'caching';
					startTicker = false;
					this.loadCacheExclusions();
				} else {
					this.mode = false;
				}
				if (this.mode) { //We are in a Wordfence page
					if (startTicker) {
						this.updateTicker();
						this.liveInt = setInterval(function() {
							self.updateTicker();
						}, WordfenceAdminVars.actUpdateInterval);
					}
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
			updateTicker: function(forceUpdate) {
				if ((!forceUpdate) && (this.tickerUpdatePending || !this.windowHasFocus())) {
					if (!jQuery('body').hasClass('wordfenceLiveActivityPaused') && !this.tickerUpdatePending) {
						jQuery('body').addClass('wordfenceLiveActivityPaused');
					}
					return;
				}
				if (jQuery('body').hasClass('wordfenceLiveActivityPaused')) {
					jQuery('body').removeClass('wordfenceLiveActivityPaused');
				}
				this.tickerUpdatePending = true;
				var self = this;
				var alsoGet = '';
				var otherParams = '';
				var data = '';
				if (this.mode == 'liveTraffic') {
					alsoGet = 'liveTraffic';
					otherParams = this.newestActivityTime;
					data += this.wfLiveTraffic.getCurrentQueryString({
						since: this.newestActivityTime
					});

				} else if (this.mode == 'activity' && /^(?:404|hit|human|ruser|gCrawler|crawler|loginLogout)$/.test(this.activityMode)) {
					alsoGet = 'logList_' + this.activityMode;
					otherParams = this.newestActivityTime;
				} else if (this.mode == 'perfStats') {
					alsoGet = 'perfStats';
					otherParams = this.newestActivityTime;
				}
				data += '&alsoGet=' + encodeURIComponent(alsoGet) + '&otherParams=' + encodeURIComponent(otherParams);
				this.ajax('wordfence_ticker', data, function(res) {
					self.handleTickerReturn(res);
				}, function() {
					self.tickerUpdatePending = false;
				}, true);
			},
			handleTickerReturn: function(res) {
				this.tickerUpdatePending = false;
				var newMsg = "";
				var oldMsg = jQuery('#wfLiveStatus').text();
				if (res.msg) {
					newMsg = res.msg;
				} else {
					newMsg = "Idle";
				}
				if (newMsg && newMsg != oldMsg) {
					jQuery('#wfLiveStatus').hide().html(newMsg).fadeIn(200);
				}
				var haveEvents, newElem;
				this.serverTimestampOffset = (new Date().getTime() / 1000) - res.serverTime;
				this.serverMicrotime = res.serverMicrotime;

				if (this.mode == 'liveTraffic') {
					if (res.events.length > 0) {
						this.newestActivityTime = res.events[0]['ctime'];
					}
					if (typeof WFAD.wfLiveTraffic !== undefined) {
						WFAD.wfLiveTraffic.prependListings(res.events, res);
						this.reverseLookupIPs();
						this.updateTimeAgo();
					}

				} else if (this.mode == 'activity') { // This mode is deprecated as of 6.1.0
					if (res.alsoGet != 'logList_' + this.activityMode) {
						return;
					} //user switched panels since ajax request started
					if (res.events.length > 0) {
						this.newestActivityTime = res.events[0]['ctime'];
					}
					haveEvents = false;
					if (jQuery('#wfActivity_' + this.activityMode + ' .wfActEvent').length > 0) {
						haveEvents = true;
					}
					if (res.events.length > 0) {
						if (!haveEvents) {
							jQuery('#wfActivity_' + this.activityMode).empty();
						}
						for (i = res.events.length - 1; i >= 0; i--) {
							var elemID = '#wfActEvent_' + res.events[i].id;
							if (jQuery(elemID).length < 1) {
								res.events[i]['activityMode'] = this.activityMode;
								if (this.activityMode == 'loginLogout') {
									newElem = jQuery('#wfLoginLogoutEventTmpl').tmpl(res.events[i]);
								} else {
									newElem = jQuery('#wfHitsEventTmpl').tmpl(res.events[i]);
								}
								jQuery(newElem).find('.wfTimeAgo').data('wfctime', res.events[i].ctime);
								newElem.prependTo('#wfActivity_' + this.activityMode).fadeIn();
							}
						}
						this.reverseLookupIPs();
					} else {
						if (!haveEvents) {
							jQuery('#wfActivity_' + this.activityMode).html('<div>No events to report yet.</div>');
						}
					}
					var self = this;
					this.updateTimeAgo();
				} else if (this.mode == 'perfStats') {
					haveEvents = false;
					if (jQuery('#wfPerfStats .wfPerfEvent').length > 0) {
						haveEvents = true;
					}
					if (res.events.length > 0) {
						if (!haveEvents) {
							jQuery('#wfPerfStats').empty();
						}
						var curLength = parseInt(jQuery('#wfPerfStats').css('width'));
						if (res.longestLine > curLength) {
							jQuery('#wfPerfStats').css('width', (res.longestLine + 200) + 'px');
						}
						this.newestActivityTime = res.events[0]['ctime'];
						for (var i = res.events.length - 1; i >= 0; i--) {
							res.events[i]['scale'] = this.performanceScale;
							res.events[i]['min'] = this.performanceMinWidth;
							newElem = jQuery('#wfPerfStatTmpl').tmpl(res.events[i]);
							jQuery(newElem).find('.wfTimeAgo').data('wfctime', res.events[i].ctime);
							newElem.prependTo('#wfPerfStats').fadeIn();
						}
					} else {
						if (!haveEvents) {
							jQuery('#wfPerfStats').html('<p>No events to report yet.</p>');
						}
					}
					this.updateTimeAgo();
				}
			},
			reverseLookupIPs: function() {
				var self = this;
				var ips = [];
				jQuery('.wfReverseLookup').each(function(idx, elem) {
					var txt = jQuery(elem).text().trim();
					if (/^\d+\.\d+\.\d+\.\d+$/.test(txt) && (!jQuery(elem).data('wfReverseDone'))) {
						jQuery(elem).data('wfReverseDone', true);
						ips.push(txt);
					}
				});
				if (ips.length < 1) {
					return;
				}
				var uni = {};
				var uniqueIPs = [];
				for (var i = 0; i < ips.length; i++) {
					if (!uni[ips[i]]) {
						uni[ips[i]] = true;
						uniqueIPs.push(ips[i]);
					}
				}
				this.ajax('wordfence_reverseLookup', {
						ips: uniqueIPs.join(',')
					},
					function(res) {
						if (res.ok) {
							jQuery('.wfReverseLookup').each(function(idx, elem) {
								var txt = jQuery(elem).text().trim();
								for (var ip in res.ips) {
									if (txt == ip) {
										if (res.ips[ip]) {
											jQuery(elem).html('<strong>Hostname:</strong>&nbsp;' + self.htmlEscape(res.ips[ip]));
										} else {
											jQuery(elem).html('');
										}
									}
								}
							});
						}
					}, false, false);
			},
			killScan: function() {
				var self = this;
				this.ajax('wordfence_killScan', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', "Kill requested", "A termination request has been sent to any running scans.");
					} else {
						self.colorbox('400px', "Kill failed", "We failed to send a termination request.");
					}
				});
			},
			startScan: function() {
				var spinnerValues = [
					'|', '/', '-', '\\'
				];
				var count = 0;
				var scanReqAnimation = setInterval(function() {
					var ch = spinnerValues[count++ % spinnerValues.length];
					jQuery('#wfStartScanButton1,#wfStartScanButton2').html("Requesting a New Scan <span class='wf-spinner'>" + ch + "</span>");
				}, 100);
				setTimeout(function(res) {
					clearInterval(scanReqAnimation);
					jQuery('#wfStartScanButton1,#wfStartScanButton2').text("Start a Wordfence Scan");
				}, 3000);
				this.ajax('wordfence_scan', {}, function(res) {

				});
			},
			displayPWAuditJobs: function(res) {
				if (res && res.results && res.results.length > 0) {
					var wfAuditJobs = $('#wfAuditJobs');
					jQuery('#wfAuditJobs').empty();
					jQuery('#wfAuditJobsTable').tmpl().appendTo(wfAuditJobs);
					var wfAuditJobsBody = wfAuditJobs.find('.wf-pw-audit-tbody');
					for (var i = 0; i < res.results.length; i++) {
						jQuery('#wfAuditJobsInProg').tmpl(res.results[i]).appendTo(wfAuditJobsBody);
					}
				} else {
					jQuery('#wfAuditJobs').empty().html("<p>You don't have any password auditing jobs in progress or completed yet.</p>");
				}
			},
			loadIssues: function(callback) {
				if (this.mode != 'scan') {
					return;
				}
				var self = this;
				this.ajax('wordfence_loadIssues', {}, function(res) {
					self.displayIssues(res, callback);
				});
			},
			sev2num: function(str) {
				if (/wfProbSev1/.test(str)) {
					return 1;
				} else if (/wfProbSev2/.test(str)) {
					return 2;
				} else {
					return 0;
				}
			},
			displayIssues: function(res, callback) {
				var self = this;
				try {
					res.summary['lastScanCompleted'] = res['lastScanCompleted'];
				} catch (err) {
					res.summary['lastScanCompleted'] = 'Never';
				}
				jQuery('.wfIssuesContainer').hide();
				for (var issueStatus in res.issuesLists) {
					var containerID = 'wfIssues_dataTable_' + issueStatus;
					var tableID = 'wfIssuesTable_' + issueStatus;
					if (jQuery('#' + containerID).length < 1) {
						//Invalid issue status
						continue;
					}
					if (res.issuesLists[issueStatus].length < 1) {
						if (issueStatus == 'new') {
							if (res.lastScanCompleted == 'ok') {
								jQuery('#' + containerID).html('<p style="font-size: 20px; color: #0A0;">Congratulations! No security problems were detected by Wordfence.</p>');
							} else if (res['lastScanCompleted']) {
								//jQuery('#' + containerID).html('<p style="font-size: 12px; color: #A00;">The latest scan failed: ' + res.lastScanCompleted + '</p>');
							} else {
								jQuery('#' + containerID).html();
							}

						} else {
							jQuery('#' + containerID).html('<p>There are currently <strong>no issues</strong> being ignored on this site.</p>');
						}
						continue;
					}
					jQuery('#' + containerID).html('<table cellpadding="0" cellspacing="0" border="0" class="display" id="' + tableID + '"></table>');

					jQuery.fn.dataTableExt.oSort['severity-asc'] = function(y, x) {
						x = WFAD.sev2num(x);
						y = WFAD.sev2num(y);
						if (x < y) {
							return 1;
						}
						if (x > y) {
							return -1;
						}
						return 0;
					};
					jQuery.fn.dataTableExt.oSort['severity-desc'] = function(y, x) {
						x = WFAD.sev2num(x);
						y = WFAD.sev2num(y);
						if (x > y) {
							return 1;
						}
						if (x < y) {
							return -1;
						}
						return 0;
					};

					jQuery('#' + tableID).dataTable({
						"bFilter": false,
						"bInfo": false,
						"bPaginate": false,
						"bLengthChange": false,
						"bAutoWidth": false,
						"aaData": res.issuesLists[issueStatus],
						"aoColumns": [
							{
								"sTitle": '<div class="th_wrapp">Severity</div>',
								"sWidth": '128px',
								"sClass": "center",
								"sType": 'severity',
								"fnRender": function(obj) {
									var cls = 'wfProbSev' + obj.aData.severity;
									return '<span class="' + cls + '"></span>';
								}
							},
							{
								"sTitle": '<div class="th_wrapp">Issue</div>',
								"bSortable": false,
								"sWidth": '400px',
								"sType": 'html',
								fnRender: function(obj) {
									var issueType = (obj.aData.type == 'knownfile' ? 'file' : obj.aData.type);
									var tmplName = 'issueTmpl_' + issueType;
									return jQuery('#' + tmplName).tmpl(obj.aData).html();
								}
							}
						]
					});
				}
				if (callback) {
					jQuery('#wfIssues_' + this.visibleIssuesPanel).fadeIn(500, function() {
						callback();
					});
				} else {
					jQuery('#wfIssues_' + this.visibleIssuesPanel).fadeIn(500);
				}
				return true;
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
			scanRunningMsg: function() {
				this.colorbox('400px', "A scan is running", "A scan is currently in progress. Please wait until it finishes before starting another scan.");
			},
			errorMsg: function(msg) {
				this.colorbox('400px', "An error occurred:", msg);
			},
			bulkOperation: function(op) {
				var self = this;
				if (op == 'del' || op == 'repair') {
					var ids = jQuery('input.wf' + op + 'Checkbox:checked').map(function() {
						return jQuery(this).val();
					}).get();
					if (ids.length < 1) {
						this.colorbox('400px', "No files were selected", "You need to select files to perform a bulk operation. There is a checkbox in each issue that lets you select that file. You can then select a bulk operation and hit the button to perform that bulk operation.");
						return;
					}
					if (op == 'del') {
						this.colorbox('400px', "Are you sure you want to delete?", "Are you sure you want to delete a total of " + ids.length + " files? Do not delete files on your system unless you're ABSOLUTELY sure you know what you're doing. If you delete the wrong file it could cause your WordPress website to stop functioning and you will probably have to restore from backups. If you're unsure, Cancel and work with your hosting provider to clean your system of infected files.<br /><br /><input type=\"button\" value=\"Delete Files\" onclick=\"WFAD.bulkOperationConfirmed('" + op + "');\" />&nbsp;&nbsp;<input type=\"button\" value=\"Cancel\" onclick=\"jQuery.colorbox.close();\" /><br />");
					} else if (op == 'repair') {
						this.colorbox('400px', "Are you sure you want to repair?", "Are you sure you want to repair a total of " + ids.length + " files? Do not repair files on your system unless you're sure you have reviewed the differences between the original file and your version of the file in the files you are repairing. If you repair a file that has been customized for your system by a developer or your hosting provider it may leave your system unusable. If you're unsure, Cancel and work with your hosting provider to clean your system of infected files.<br /><br /><input type=\"button\" value=\"Repair Files\" onclick=\"WFAD.bulkOperationConfirmed('" + op + "');\" />&nbsp;&nbsp;<input type=\"button\" value=\"Cancel\" onclick=\"jQuery.colorbox.close();\" /><br />");
					}
				} else {
					return;
				}
			},
			bulkOperationConfirmed: function(op) {
				jQuery.colorbox.close();
				var self = this;
				this.ajax('wordfence_bulkOperation', {
					op: op,
					ids: jQuery('input.wf' + op + 'Checkbox:checked').map(function() {
						return jQuery(this).val();
					}).get()
				}, function(res) {
					self.doneBulkOperation(res);
				});
			},
			doneBulkOperation: function(res) {
				var self = this;
				if (res.ok) {
					this.loadIssues(function() {
						self.colorbox('400px', res.bulkHeading, res.bulkBody);
					});
				} else {
					this.loadIssues(function() {
					});
				}
			},
			deleteFile: function(issueID, force) {
				var self = this;
				this.ajax('wordfence_deleteFile', {
					issueID: issueID,
					forceDelete: force
				}, function(res) {
					if (res.needsCredentials) {
						document.location.href = res.redirect;
					} else {
						self.doneDeleteFile(res);
					}
				});
			},
			doneDeleteFile: function(res) {
				var cb = false;
				var self = this;
				if (res.ok) {
					this.loadIssues(function() {
						self.colorbox('400px', "Success deleting file", "The file " + res.file + " was successfully deleted.");
					});
				} else if (res.cerrorMsg) {
					this.loadIssues(function() {
						self.colorbox('400px', 'An error occurred', res.cerrorMsg);
					});
				}
			},
			deleteDatabaseOption: function(issueID) {
				var self = this;
				this.ajax('wordfence_deleteDatabaseOption', {
					issueID: issueID
				}, function(res) {
					self.doneDeleteDatabaseOption(res);
				});
			},
			doneDeleteDatabaseOption: function(res) {
				var cb = false;
				var self = this;
				if (res.ok) {
					this.loadIssues(function() {
						self.colorbox('400px', "Success removing option", "The option " + res.option_name + " was successfully removed.");
					});
				} else if (res.cerrorMsg) {
					this.loadIssues(function() {
						self.colorbox('400px', 'An error occurred', res.cerrorMsg);
					});
				}
			},
			fixFPD: function(issueID) {
				var self = this;
				var title = "Full Path Disclosure";
				issueID = parseInt(issueID);

				this.ajax('wordfence_checkFalconHtaccess', {}, function(res) {
					if (res.ok) {
						self.colorbox("400px", title, 'We are about to change your <em>.htaccess</em> file. Please make a backup of this file proceeding'
							+ '<br/>'
							+ '<a href="' + WordfenceAdminVars.ajaxURL + '?action=wordfence_downloadHtaccess&nonce=' + self.nonce + '" onclick="jQuery(\'#wfFPDNextBut\').prop(\'disabled\', false); return true;">Click here to download a backup copy of your .htaccess file now</a><br /><br /><input type="button" name="but1" id="wfFPDNextBut" value="Click to fix .htaccess" disabled="disabled" onclick="WFAD.fixFPD_WriteHtAccess(' + issueID + ');" />');
					} else if (res.nginx) {
						self.colorbox("400px", title, 'You are using an Nginx web server and using a FastCGI processor like PHP5-FPM. You will need to manually modify your php.ini to disable <em>display_error</em>');
					} else if (res.err) {
						self.colorbox('400px', "We encountered a problem", "We can't modify your .htaccess file for you because: " + res.err);
					}
				});
			},
			fixFPD_WriteHtAccess: function(issueID) {
				var self = this;
				self.colorboxClose();
				this.ajax('wordfence_fixFPD', {
					issueID: issueID
				}, function(res) {
					if (res.ok) {
						self.loadIssues(function() {
							self.colorbox("400px", "File restored OK", "The Full Path disclosure issue has been fixed");
						});
					} else {
						self.loadIssues(function() {
							self.colorbox('400px', 'An error occurred', res.cerrorMsg);
						});
					}
				});
			},

			_handleHtAccess: function(issueID, callback, title, nginx) {
				var self = this;
				return function(res) {
					if (res.ok) {
						self.colorbox("400px", title, 'We are about to change your <em>.htaccess</em> file. Please make a backup of this file proceeding'
							+ '<br/>'
							+ '<a id="dlButton" href="' + WordfenceAdminVars.ajaxURL + '?action=wordfence_downloadHtaccess&nonce=' + self.nonce + '">Click here to download a backup copy of your .htaccess file now</a>'
							+ '<br /><br /><input type="button" name="but1" id="wfFPDNextBut" value="Click to fix .htaccess" disabled="disabled" />'
						);
						jQuery('#dlButton').click('click', function() {
							jQuery('#wfFPDNextBut').prop('disabled', false);
						});
						jQuery('#wfFPDNextBut').one('click', function() {
							self[callback](issueID);
						});
					} else if (res.nginx) {
						self.colorbox("400px", title, 'You are using an Nginx web server and using a FastCGI processor like PHP5-FPM. ' + nginx);
					} else if (res.err) {
						self.colorbox('400px', "We encountered a problem", "We can't modify your .htaccess file for you because: " + res.err);
					}
				};
			},
			_hideFile: function(issueID) {
				var self = this;
				var title = 'Modifying .htaccess';
				this.ajax('wordfence_hideFileHtaccess', {
					issueID: issueID
				}, function(res) {
					jQuery.colorbox.close();
					self.loadIssues(function() {
						if (res.ok) {
							self.colorbox("400px", title, 'Your .htaccess file has been updated successfully.');
						} else {
							self.colorbox("400px", title, 'We encountered a problem while trying to update your .htaccess file.');
						}
					});
				});
			},
			hideFile: function(issueID) {
				var self = this;
				var title = "Backup your .htaccess file";
				var nginx = "You will need to manually delete those files";
				issueID = parseInt(issueID, 10);

				this.ajax('wordfence_checkFalconHtaccess', {}, this._handleHtAccess(issueID, '_hideFile', title, nginx));
			},

			restoreFile: function(issueID) {
				var self = this;
				this.ajax('wordfence_restoreFile', {
					issueID: issueID
				}, function(res) {
					if (res.needsCredentials) {
						document.location.href = res.redirect;
					} else {
						self.doneRestoreFile(res);
					}
				});
			},
			doneRestoreFile: function(res) {
				var self = this;
				if (res.ok) {
					this.loadIssues(function() {
						self.colorbox("400px", "File restored OK", "The file " + res.file + " was restored successfully.");
					});
				} else if (res.cerrorMsg) {
					this.loadIssues(function() {
						self.colorbox('400px', 'An error occurred', res.cerrorMsg);
					});
				}
			},

			disableDirectoryListing: function(issueID) {
				var self = this;
				var title = "Disable Directory Listing";
				issueID = parseInt(issueID);

				this.ajax('wordfence_checkFalconHtaccess', {}, function(res) {
					if (res.ok) {
						self.colorbox("400px", title, 'We are about to change your <em>.htaccess</em> file. Please make a backup of this file proceeding'
							+ '<br/>'
							+ '<a href="' + WordfenceAdminVars.ajaxURL + '?action=wordfence_downloadHtaccess&nonce=' + self.nonce + '" onclick="jQuery(\'#wf-htaccess-confirm\').prop(\'disabled\', false); return true;">Click here to download a backup copy of your .htaccess file now</a>' +
							'<br /><br />' +
							'<button class="button" type="button" id="wf-htaccess-confirm" disabled="disabled" onclick="WFAD.confirmDisableDirectoryListing(' + issueID + ');">Add code to .htaccess</button>');
					} else if (res.nginx) {
						self.colorbox('400px', "You are using Nginx as your web server. " +
							"You'll need to disable autoindexing in your nginx.conf. " +
							"See the <a target='_blank' href='http://nginx.org/en/docs/http/ngx_http_autoindex_module.html'>Nginx docs for more info</a> on how to do this.");
					} else if (res.err) {
						self.colorbox('400px', "We encountered a problem", "We can't modify your .htaccess file for you because: " + res.err);
					}
				});
			},
			confirmDisableDirectoryListing: function(issueID) {
				var self = this;
				this.colorboxClose();
				this.ajax('wordfence_disableDirectoryListing', {
					issueID: issueID
				}, function(res) {
					if (res.ok) {
						self.loadIssues(function() {
							self.colorbox("400px", "Directory Listing Disabled", "Directory listing has been disabled on your server.");
						});
					} else {
						//self.loadIssues(function() {
						//	self.colorbox('400px', 'An error occurred', res.errorMsg);
						//});
					}
				});
			},

			deleteIssue: function(id) {
				var self = this;
				this.ajax('wordfence_deleteIssue', {id: id}, function(res) {
					self.loadIssues();
				});
			},
			updateIssueStatus: function(id, st) {
				var self = this;
				this.ajax('wordfence_updateIssueStatus', {id: id, 'status': st}, function(res) {
					if (res.ok) {
						self.loadIssues();
					}
				});
			},
			updateAllIssues: function(op) { // deleteIgnored, deleteNew, ignoreAllNew
				var head = "Please confirm";
				var body;
				if (op == 'deleteIgnored') {
					body = "You have chosen to remove all ignored issues. Once these issues are removed they will be re-scanned by Wordfence and if they have not been fixed, they will appear in the 'new issues' list. Are you sure you want to do this?";
				} else if (op == 'deleteNew') {
					body = "You have chosen to mark all new issues as fixed. If you have not really fixed these issues, they will reappear in the new issues list on the next scan. If you have not fixed them and want them excluded from scans you should choose to 'ignore' them instead. Are you sure you want to mark all new issues as fixed?";
				} else if (op == 'ignoreAllNew') {
					body = "You have chosen to ignore all new issues. That means they will be excluded from future scans. You should only do this if you're sure all new issues are not a problem. Are you sure you want to ignore all new issues?";
				} else {
					return;
				}
				this.colorbox('450px', head, body + '<br /><br /><center><input type="button" name="but1" value="Cancel" onclick="jQuery.colorbox.close();" />&nbsp;&nbsp;&nbsp;<input type="button" name="but2" value="Yes I\'m sure" onclick="jQuery.colorbox.close(); WFAD.confirmUpdateAllIssues(\'' + op + '\');" /><br />');
			},
			confirmUpdateAllIssues: function(op) {
				var self = this;
				this.ajax('wordfence_updateAllIssues', {op: op}, function(res) {
					self.loadIssues();
				});
			},
			es: function(val) {
				if (val) {
					return val;
				} else {
					return "";
				}
			},
			noQuotes: function(str) {
				return str.replace(/"/g, '&#34;').replace(/\'/g, '&#145;');
			},
			commify: function(num) {
				return ("" + num).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
			},
			switchToLiveTab: function(elem) {
				jQuery('.wfTab1').removeClass('selected');
				jQuery(elem).addClass('selected');
				jQuery('.wfDataPanel').hide();
				var self = this;
				jQuery('#wfActivity').fadeIn(function() {
					self.completeLiveTabSwitch();
				});
			},
			completeLiveTabSwitch: function() {
				this.ajax('wordfence_loadActivityLog', {}, function(res) {
					var html = '<a href="#" class="wfALogMailLink" onclick="WFAD.emailActivityLog(); return false;"></a><a href="#" class="wfALogReloadLink" onclick="WFAD.reloadActivityData(); return false;"></a>';
					if (res.events && res.events.length > 0) {
						jQuery('#wfActivity').empty();
						for (var i = 0; i < res.events.length; i++) {
							var timeTaken = '0.0000';
							if (res.events[i + 1]) {
								timeTaken = (res.events[i].ctime - res.events[i + 1].ctime).toFixed(4);
							}
							var red = "";
							if (res.events[i].type == 'error') {
								red = ' class="wfWarn" ';
							}
							html += '<div ' + red + 'class="wfALogEntry"><span ' + red + 'class="wfALogTime">[' + res.events[i].type + '&nbsp;:&nbsp;' + timeTaken + '&nbsp;:&nbsp;' + res.events[i].timeAgo + ' ago]</span>&nbsp;' + res.events[i].msg + "</div>";
						}
						jQuery('#wfActivity').html(html);
					} else {
						jQuery('#wfActivity').html("<p>&nbsp;&nbsp;No activity to report yet. Please complete your first scan.</p>");
					}
				});
			},
			emailActivityLog: function() {
				this.colorbox('400px', 'Email Wordfence Activity Log', "Enter the email address you would like to send the Wordfence activity log to. Note that the activity log may contain thousands of lines of data. This log is usually only sent to a member of the Wordfence support team. It also contains your PHP configuration from the phpinfo() function for diagnostic data.<br /><br /><input type='text' value='wftest@wordfence.com' size='20' id='wfALogRecip' /><input type='button' value='Send' onclick=\"WFAD.completeEmailActivityLog();\" /><input type='button' value='Cancel' onclick='jQuery.colorbox.close();' /><br /><br />");
			},
			completeEmailActivityLog: function() {
				jQuery.colorbox.close();
				var email = jQuery('#wfALogRecip').val();
				if (!/^[^@]+@[^@]+$/.test(email)) {
					alert("Please enter a valid email address.");
					return;
				}
				var self = this;
				this.ajax('wordfence_sendActivityLog', {email: jQuery('#wfALogRecip').val()}, function(res) {
					if (res.ok) {
						self.colorbox('400px', 'Activity Log Sent', "Your Wordfence activity log was sent to " + email + "<br /><br /><input type='button' value='Close' onclick='jQuery.colorbox.close();' /><br /><br />");
					}
				});
			},
			reloadActivityData: function() {
				jQuery('#wfActivity').html('<div class="wfLoadingWhite32"></div>'); //&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;<br />
				this.completeLiveTabSwitch();
			},
			switchToSummaryTab: function(elem) {
				jQuery('.wfTab1').removeClass('selected');
				jQuery(elem).addClass('selected');
				jQuery('.wfDataPanel').hide();
				jQuery('#wfSummaryTables').fadeIn();
			},
			switchIssuesTab: function(elem, type) {
				jQuery('.wfTab2').removeClass('selected');
				jQuery('.wfIssuesContainer').hide();
				jQuery(elem).addClass('selected');
				this.visibleIssuesPanel = type;
				jQuery('#wfIssues_' + type).fadeIn();
			},
			switchTab: function(tabElement, tabClass, contentClass, selectedContentID, callback) {
				jQuery('.' + tabClass).removeClass('selected');
				jQuery(tabElement).addClass('selected');
				jQuery('.' + contentClass).hide().html('<div class="wfLoadingWhite32"></div>');
				var func = function() {
				};
				if (callback) {
					func = function() {
						callback();
					};
				}
				jQuery('#' + selectedContentID).fadeIn(func);
			},
			activityTabChanged: function() {
				var mode = jQuery('.wfDataPanel:visible')[0].id.replace('wfActivity_', '');
				if (!mode) {
					return;
				}
				this.activityMode = mode;
				this.reloadActivities();
			},
			reloadActivities: function() {
				jQuery('#wfActivity_' + this.activityMode).html('<div class="wfLoadingWhite32"></div>');
				this.newestActivityTime = 0;
				this.updateTicker(true);
			},
			loadPasswdAuditResults: function() {
				var self = this;
				this.ajax('wordfence_passwdLoadResults', {}, function(res) {
					self.displayPWAuditResults(res);
				});
			},
			stopPasswdAuditUpdate: function() {
				clearInterval(this.passwdAuditUpdateInt);
			},
			killPasswdAudit: function(jobID) {
				var self = this;
				this.ajax('wordfence_killPasswdAudit', {jobID: jobID}, function(res) {
					if (res.ok) {
						self.colorbox('300px', "Stop Requested", "We have sent a request to stop the password audit in progress. It may take a few minutes before results stop appearing. You can immediately start another audit if you'd like.");
					}
				});
			},
			displayPWAuditResults: function(res) {
				if (res && res.results && res.results.length > 0) {
					var wfAuditResults = $('#wfAuditResults');
					jQuery('#wfAuditResults').empty();
					jQuery('#wfAuditResultsTable').tmpl().appendTo(wfAuditResults);
					var wfAuditResultsBody = wfAuditResults.find('.wf-pw-audit-tbody');
					for (var i = 0; i < res.results.length; i++) {
						jQuery('#wfAuditResultsRow').tmpl(res.results[i]).appendTo(wfAuditResultsBody);
					}
				} else {
					jQuery('#wfAuditResults').empty().html("<p>You don't have any user accounts with a weak password at this time.</p>");
				}
			},
			doFixWeakPasswords: function() {
				var self = this;
				var mode = jQuery('#wfPasswdFixAction').val();
				var ids = jQuery('input.wfUserCheck:checked').map(function() {
					return jQuery(this).val();
				}).get();
				if (ids.length < 1) {
					self.colorbox('300px', "Please select users", "You did not select any users from the list. Select which site members you want to email or to change their passwords.");
					return;
				}
				this.ajax('wordfence_weakPasswordsFix', {
					mode: mode,
					ids: ids.join(',')
				}, function(res) {
					if (res.ok && res.title && res.msg) {
						self.colorbox('300px', res.title, res.msg);
					}
				});
			},
			ucfirst: function(str) {
				str = "" + str;
				return str.charAt(0).toUpperCase() + str.slice(1);
			},
			makeIPTrafLink: function(IP) {
				return WordfenceAdminVars.siteBaseURL + '?_wfsf=IPTraf&nonce=' + this.nonce + '&IP=' + encodeURIComponent(IP);
			},
			makeDiffLink: function(dat) {
				return WordfenceAdminVars.siteBaseURL + '?_wfsf=diff&nonce=' + this.nonce +
					'&file=' + encodeURIComponent(this.es(dat['file'])) +
					'&cType=' + encodeURIComponent(this.es(dat['cType'])) +
					'&cKey=' + encodeURIComponent(this.es(dat['cKey'])) +
					'&cName=' + encodeURIComponent(this.es(dat['cName'])) +
					'&cVersion=' + encodeURIComponent(this.es(dat['cVersion']));
			},
			makeViewFileLink: function(file) {
				return WordfenceAdminVars.siteBaseURL + '?_wfsf=view&nonce=' + this.nonce + '&file=' + encodeURIComponent(file);
			},
			makeViewOptionLink: function(option, siteID) {
				return WordfenceAdminVars.siteBaseURL + '?_wfsf=viewOption&nonce=' + this.nonce + '&option=' + encodeURIComponent(option) + '&site_id=' + encodeURIComponent(siteID);
			},
			makeTimeAgo: function(t) {
				var months = Math.floor(t / (86400 * 30));
				var days = Math.floor(t / 86400);
				var hours = Math.floor(t / 3600);
				var minutes = Math.floor(t / 60);
				if (months > 0) {
					days -= months * 30;
					return this.pluralize(months, 'month', days, 'day');
				} else if (days > 0) {
					hours -= days * 24;
					return this.pluralize(days, 'day', hours, 'hour');
				} else if (hours > 0) {
					minutes -= hours * 60;
					return this.pluralize(hours, 'hour', minutes, 'min');
				} else if (minutes > 0) {
					//t -= minutes * 60;
					return this.pluralize(minutes, 'minute');
				} else {
					return Math.round(t) + " seconds";
				}
			},
			pluralize: function(m1, t1, m2, t2) {
				if (m1 != 1) {
					t1 = t1 + 's';
				}
				if (m2 != 1) {
					t2 = t2 + 's';
				}
				if (m1 && m2) {
					return m1 + ' ' + t1 + ' ' + m2 + ' ' + t2;
				} else {
					return m1 + ' ' + t1;
				}
			},
			makeElemID: function() {
				return 'wfElemGen' + this.elementGeneratorIter++;
			},
			pulse: function(sel) {
				jQuery(sel).fadeIn(function() {
					setTimeout(function() {
						jQuery(sel).fadeOut();
					}, 2000);
				});
			},
			getCacheStats: function() {
				var self = this;
				this.ajax('wordfence_getCacheStats', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', res.heading, res.body);
					}
				});
			},
			clearPageCache: function() {
				var self = this;
				this.ajax('wordfence_clearPageCache', {}, function(res) {
					if (res.ok) {
						self.colorbox('400px', res.heading, res.body);
					}
				});
			},
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
			saveConfig: function() {
				var qstr = jQuery('#wfConfigForm').serialize();
				var self = this;
				jQuery('.wfSavedMsg').hide();
				jQuery('.wfAjax24').show();
				this.ajax('wordfence_saveConfig', qstr, function(res) {
					jQuery('.wfAjax24').hide();
					if (res.ok) {
						if (res['paidKeyMsg']) {
							self.colorbox('400px', "Congratulations! You have been upgraded to Premium Scanning.", "You have upgraded to a Premium API key. Once this page reloads, you can choose which premium scanning options you would like to enable and then click save. Click the button below to reload this page now.<br /><br /><center><input type='button' name='wfReload' value='Reload page and enable Premium options' onclick='window.location.reload(true);' /></center>");
							return;
						} else if (res['reload'] == 'reload' || WFAD.reloadConfigPage) {
							self.colorbox('400px', "Please reload this page", "You selected a config option that requires a page reload. Click the button below to reload this page to update the menu.<br /><br /><center><input type='button' name='wfReload' value='Reload page' onclick='window.location.reload(true);' /></center>");
							return;
						} else {
							self.pulse('.wfSavedMsg');
						}
					} else if (res.errorMsg) {
						return;
					} else {
						self.colorbox('400px', 'An error occurred', 'We encountered an error trying to save your changes.');
					}
				});
			},
			saveDebuggingConfig: function() {
				var qstr = jQuery('#wfDebuggingConfigForm').serialize();
				var self = this;
				jQuery('.wfSavedMsg').hide();
				jQuery('.wfAjax24').show();
				this.ajax('wordfence_saveDebuggingConfig', qstr, function(res) {
					jQuery('.wfAjax24').hide();
					if (res.ok) {
						self.pulse('.wfSavedMsg');
					} else if (res.errorMsg) {
						return;
					} else {
						self.colorbox('400px', 'An error occurred', 'We encountered an error trying to save your changes.');
					}
				});
			},
			removeCacheExclusion: function(id) {
				this.ajax('wordfence_removeCacheExclusion', {id: id}, function(res) {
					window.location.reload(true);
				});
			},
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

			windowHasFocus: function() {
				if (typeof document.hasFocus === 'function') {
					return document.hasFocus();
				}
				// Older versions of Opera
				return this._windowHasFocus;
			},

			htmlEscape: function(html) {
				return String(html)
					.replace(/&/g, '&amp;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#39;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			},

			permanentlyBlockAllIPs: function(type) {
				var self = this;
				this.ajax('wordfence_permanentlyBlockAllIPs', {
					type: type
				}, function(res) {
					$('#wfTabs').find('.wfTab1').eq(0).trigger('click');
				});
			},

			showTimestamp: function(timestamp, serverTime, format) {
				serverTime = serverTime === undefined ? new Date().getTime() / 1000 : serverTime;
				format = format === undefined ? '${dateTime} (${timeAgo} ago)' : format;
				var date = new Date(timestamp * 1000);

				return jQuery.tmpl(format, {
					dateTime: date.toLocaleDateString() + ' ' + date.toLocaleTimeString(),
					timeAgo: this.makeTimeAgo(serverTime - timestamp)
				});
			},

			updateTimeAgo: function() {
				var self = this;
				jQuery('.wfTimeAgo-timestamp').each(function(idx, elem) {
					var el = jQuery(elem);
					var testEl = el;
					if (typeof jQuery === "function" && testEl instanceof jQuery) {
						testEl = testEl[0];
					}

					var rect = testEl.getBoundingClientRect();
					if (!(rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth))) {
						return;
					}
					
					var timestamp = el.data('wfctime');
					if (!timestamp) {
						timestamp = el.attr('data-timestamp');
					}
					var serverTime = self.serverMicrotime;
					var format = el.data('wfformat');
					if (!format) {
						format = el.attr('data-format');
					}
					el.html(self.showTimestamp(timestamp, serverTime, format));
				});
			},

			wafData: {
				whitelistedURLParams: []
			},

			wafConfigSave: function(action, data, onSuccess, showColorBox) {
				showColorBox = showColorBox === undefined ? true : !!showColorBox;
				var self = this;
				if (typeof(data) == 'string') {
					if (data.length > 0) {
						data += '&';
					}
					data += 'wafConfigAction=' + action;
				} else if (typeof(data) == 'object' && data instanceof Array) {
					// jQuery serialized form data
					data.push({
						name: 'wafConfigAction',
						value: action
					});
				} else if (typeof(data) == 'object') {
					data['wafConfigAction'] = action;
				}

				this.ajax('wordfence_saveWAFConfig', data, function(res) {
					if (typeof res === 'object' && res.success) {
						if (showColorBox) {
							self.colorbox('400px', 'Firewall Configuration', 'The Wordfence Web Application Firewall ' +
								'configuration was saved successfully.');
						}
						self.wafData = res.data;
						self.wafConfigPageRender();
						if (typeof onSuccess === 'function') {
							return onSuccess.apply(this, arguments);
						}
					} else {
						self.colorbox('400px', 'Error saving Firewall configuration', 'There was an error saving the ' +
							'Web Application Firewall configuration settings.');
					}
				});
			},

			wafConfigPageRender: function() {
				var whitelistedIPsEl = $('#waf-whitelisted-urls-tmpl').tmpl(this.wafData);
				$('#waf-whitelisted-urls-wrapper').html(whitelistedIPsEl);

				var rulesEl = $('#waf-rules-tmpl').tmpl(this.wafData);
				$('#waf-rules-wrapper').html(rulesEl);

				if (this.wafData['rulesLastUpdated']) {
					var date = new Date(this.wafData['rulesLastUpdated'] * 1000);
					this.renderWAFRulesLastUpdated(date);
				}
				$(window).trigger('wordfenceWAFConfigPageRender');
			},

			renderWAFRulesLastUpdated: function(date) {
				var dateString = date.toString();
				if (date.toLocaleString) {
					dateString = date.toLocaleString();
				}
				$('#waf-rules-last-updated').text('Last Updated: ' + dateString)
					.css({
						'opacity': 0
					})
					.animate({
						'opacity': 1
					}, 500);
			},
		};

		window['WFAD'] = window['wordfenceAdmin'];
		setInterval(function() {
			WFAD.updateTimeAgo();
		}, 1000);
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

/*! @source http://purl.eligrey.com/github/FileSaver.js/blob/master/FileSaver.js */
var saveAs=saveAs||function(e){"use strict";if(typeof e==="undefined"||typeof navigator!=="undefined"&&/MSIE [1-9]\./.test(navigator.userAgent)){return}var t=e.document,n=function(){return e.URL||e.webkitURL||e},r=t.createElementNS("http://www.w3.org/1999/xhtml","a"),o="download"in r,i=function(e){var t=new MouseEvent("click");e.dispatchEvent(t)},a=/constructor/i.test(e.HTMLElement),f=/CriOS\/[\d]+/.test(navigator.userAgent),u=function(t){(e.setImmediate||e.setTimeout)(function(){throw t},0)},d="application/octet-stream",s=1e3*40,c=function(e){var t=function(){if(typeof e==="string"){n().revokeObjectURL(e)}else{e.remove()}};setTimeout(t,s)},l=function(e,t,n){t=[].concat(t);var r=t.length;while(r--){var o=e["on"+t[r]];if(typeof o==="function"){try{o.call(e,n||e)}catch(i){u(i)}}}},p=function(e){if(/^\s*(?:text\/\S*|application\/xml|\S*\/\S*\+xml)\s*;.*charset\s*=\s*utf-8/i.test(e.type)){return new Blob([String.fromCharCode(65279),e],{type:e.type})}return e},v=function(t,u,s){if(!s){t=p(t)}var v=this,w=t.type,m=w===d,y,h=function(){l(v,"writestart progress write writeend".split(" "))},S=function(){if((f||m&&a)&&e.FileReader){var r=new FileReader;r.onloadend=function(){var t=f?r.result:r.result.replace(/^data:[^;]*;/,"data:attachment/file;");var n=e.open(t,"_blank");if(!n)e.location.href=t;t=undefined;v.readyState=v.DONE;h()};r.readAsDataURL(t);v.readyState=v.INIT;return}if(!y){y=n().createObjectURL(t)}if(m){e.location.href=y}else{var o=e.open(y,"_blank");if(!o){e.location.href=y}}v.readyState=v.DONE;h();c(y)};v.readyState=v.INIT;if(o){y=n().createObjectURL(t);setTimeout(function(){r.href=y;r.download=u;i(r);h();c(y);v.readyState=v.DONE});return}S()},w=v.prototype,m=function(e,t,n){return new v(e,t||e.name||"download",n)};if(typeof navigator!=="undefined"&&navigator.msSaveOrOpenBlob){return function(e,t,n){t=t||e.name||"download";if(!n){e=p(e)}return navigator.msSaveOrOpenBlob(e,t)}}w.abort=function(){};w.readyState=w.INIT=0;w.WRITING=1;w.DONE=2;w.error=w.onwritestart=w.onprogress=w.onwrite=w.onabort=w.onerror=w.onwriteend=null;return m}(typeof self!=="undefined"&&self||typeof window!=="undefined"&&window||this.content);if(typeof module!=="undefined"&&module.exports){module.exports.saveAs=saveAs}else if(typeof define!=="undefined"&&define!==null&&define.amd!==null){define([],function(){return saveAs})}

!function(t){"use strict";if(t.URL=t.URL||t.webkitURL,t.Blob&&t.URL)try{return void new Blob}catch(e){}var n=t.BlobBuilder||t.WebKitBlobBuilder||t.MozBlobBuilder||function(t){var e=function(t){return Object.prototype.toString.call(t).match(/^\[object\s(.*)\]$/)[1]},n=function(){this.data=[]},o=function(t,e,n){this.data=t,this.size=t.length,this.type=e,this.encoding=n},i=n.prototype,a=o.prototype,r=t.FileReaderSync,c=function(t){this.code=this[this.name=t]},l="NOT_FOUND_ERR SECURITY_ERR ABORT_ERR NOT_READABLE_ERR ENCODING_ERR NO_MODIFICATION_ALLOWED_ERR INVALID_STATE_ERR SYNTAX_ERR".split(" "),s=l.length,u=t.URL||t.webkitURL||t,d=u.createObjectURL,f=u.revokeObjectURL,R=u,p=t.btoa,h=t.atob,b=t.ArrayBuffer,g=t.Uint8Array,w=/^[\w-]+:\/*\[?[\w\.:-]+\]?(?::[0-9]+)?/;for(o.fake=a.fake=!0;s--;)c.prototype[l[s]]=s+1;return u.createObjectURL||(R=t.URL=function(t){var e,n=document.createElementNS("http://www.w3.org/1999/xhtml","a");return n.href=t,"origin"in n||("data:"===n.protocol.toLowerCase()?n.origin=null:(e=t.match(w),n.origin=e&&e[1])),n}),R.createObjectURL=function(t){var e,n=t.type;return null===n&&(n="application/octet-stream"),t instanceof o?(e="data:"+n,"base64"===t.encoding?e+";base64,"+t.data:"URI"===t.encoding?e+","+decodeURIComponent(t.data):p?e+";base64,"+p(t.data):e+","+encodeURIComponent(t.data)):d?d.call(u,t):void 0},R.revokeObjectURL=function(t){"data:"!==t.substring(0,5)&&f&&f.call(u,t)},i.append=function(t){var n=this.data;if(g&&(t instanceof b||t instanceof g)){for(var i="",a=new g(t),l=0,s=a.length;s>l;l++)i+=String.fromCharCode(a[l]);n.push(i)}else if("Blob"===e(t)||"File"===e(t)){if(!r)throw new c("NOT_READABLE_ERR");var u=new r;n.push(u.readAsBinaryString(t))}else t instanceof o?"base64"===t.encoding&&h?n.push(h(t.data)):"URI"===t.encoding?n.push(decodeURIComponent(t.data)):"raw"===t.encoding&&n.push(t.data):("string"!=typeof t&&(t+=""),n.push(unescape(encodeURIComponent(t))))},i.getBlob=function(t){return arguments.length||(t=null),new o(this.data.join(""),t,"raw")},i.toString=function(){return"[object BlobBuilder]"},a.slice=function(t,e,n){var i=arguments.length;return 3>i&&(n=null),new o(this.data.slice(t,i>1?e:this.data.length),n,this.encoding)},a.toString=function(){return"[object Blob]"},a.close=function(){this.size=0,delete this.data},n}(t);t.Blob=function(t,e){var o=e?e.type||"":"",i=new n;if(t)for(var a=0,r=t.length;r>a;a++)Uint8Array&&t[a]instanceof Uint8Array?i.append(t[a].buffer):i.append(t[a]);var c=i.getBlob(o);return!c.slice&&c.webkitSlice&&(c.slice=c.webkitSlice),c};var o=Object.getPrototypeOf||function(t){return t.__proto__};t.Blob.prototype=o(new t.Blob)}("undefined"!=typeof self&&self||"undefined"!=typeof window&&window||this.content||this);
