(function () {
	var Assetic,
		addEventListenerForPageVisibilityChange,
		tempAssetic = window.Assetic || {};

	if (typeof document.querySelectorAll !== "function") {
		return;
	}

	tempAssetic.reloadInterval = tempAssetic.reloadInterval || 1750;
	tempAssetic.monitor = tempAssetic.monitor || [ 'js', 'css' ];

	function load(url, callback) {
		var xhr = new XMLHttpRequest();
		xhr.onreadystatechange = ensureReadiness;

		function ensureReadiness() {
			if (xhr.readyState < 4) {
				return;
			}
			if (xhr.status !== 200) {
				return;
			}
			if (xhr.readyState === 4) {
				callback(xhr);
			}
		}

		xhr.open("HEAD", url, true);
		xhr.send("");
	}


	addEventListenerForPageVisibilityChange = function(callback) {
		var hidden = "hidden";
		// Standards:
		if (hidden in document)
			document.addEventListener("visibilitychange", callback);
		else if ((hidden = "mozHidden") in document)
			document.addEventListener("mozvisibilitychange", callback);
		else if ((hidden = "webkitHidden") in document)
			document.addEventListener("webkitvisibilitychange", callback);
		else if ((hidden = "msHidden") in document)
			document.addEventListener("msvisibilitychange", callback);
		// IE 9 and lower:
		else if ('onfocusin' in document)
			document.onfocusin = document.onfocusout = callback;
		// All others:
		else
			window.onpageshow = window.onpagehide
				= window.onfocus = window.onblur = callback;
	};


	Assetic = {
		reloadInterval: tempAssetic.reloadInterval,
		monitor: tempAssetic.monitor,
		isWatching: false,
		isVisible: true,

		// Cundd assetic assets
		cunddAsseticStylesheets: [],
		originalUrls: [],

		// All assets
		stylesheetAssets: tempAssetic.stylesheetAssets || [],
		stylesheetAssetsOriginalUrls: tempAssetic.stylesheetAssetsOriginalUrls || [],
		javaScriptAssets: tempAssetic.javaScriptAssets || [],
		javaScriptAssetsOriginalUrls: tempAssetic.javaScriptAssetsOriginalUrls || [],

		recentlyChangedStylesheetAsset: null,

		runCounter: 0,
		reloadJavaScriptEach: 5,
		reloadStylesheetsEach: 5,

		lastIds: {},
		startTime: (+new Date),
		lostFocusTime: null,

		init: function () {
			var length;
			Assetic.cunddAsseticStylesheets = document.querySelectorAll("link[href*=cundd_assetic]");

			length = Assetic.cunddAsseticStylesheets.length;
			for (var i = 0; i < length; i++) {
				var originalUrl = "", lastSlashPosition,
					stylesheet = Assetic.cunddAsseticStylesheets[i];
				originalUrl = stylesheet.href.replace(/__\w*\.css/, "_.css");


				lastSlashPosition = originalUrl.lastIndexOf("/");
				Assetic.originalUrls[i] = originalUrl.substr(0, lastSlashPosition) + "/_debug_" + originalUrl.substr(lastSlashPosition + 1);
			}

			/*
			 * Find JavaScript assets
			 */
			if (Assetic.monitor.indexOf('js') !== -1) {
				Assetic.javaScriptAssets = Array.prototype.filter.call(
					Array.prototype.slice.call(document.querySelectorAll("script[src]")), 
					this.isLocalAsset
					);
				Assetic.javaScriptAssetsOriginalUrls = Assetic.javaScriptAssets.map(function (element) {
					return element.src;
				});
			}

			/*
			 * Find style assets
			 */
			if (Assetic.monitor.indexOf('css') !== -1) {
				Assetic.stylesheetAssets = Array.prototype.filter.call(
					Array.prototype.slice.call(document.querySelectorAll("link[rel='stylesheet']:not([href*=cundd_assetic])")), 
					this.isLocalAsset
					);
				Assetic.stylesheetAssetsOriginalUrls = Assetic.stylesheetAssets.map(function (element) {
					return element.href;
				});
			}

			addEventListenerForPageVisibilityChange(Assetic.pageVisibilityChanged);
			window.addEventListener('blur', Assetic.pageFocusChanged);
			window.addEventListener('focus', Assetic.pageFocusChanged);

		},
		
		log: function(message) {
			if (window.console) {
				window.console.log('Assetic:', message);
			}
		},

		isLocalAsset: function (asset) {
			var loc = document.location,
				reg = new RegExp("^\\.|^\/(?!\/)|^[\\w]((?!://).)*$|" + loc.protocol + "//" + loc.host),
				url = asset.src || asset.href;
			return url.match(reg);
		},

		reloadStylesheets: function (newUrl, originalUrl, asset) {
			load(newUrl, function (xhr) {
				var responseHeaderKey = "Last-Modified"; // Or "Etag"
				if (xhr.getResponseHeader(responseHeaderKey) !== Assetic.lastIds[originalUrl]) {
					var date = (new Date),
						dateString = date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds();
					Assetic.lastIds[originalUrl] = xhr.getResponseHeader(responseHeaderKey);
					Assetic.log("Reload at " + dateString + " -> " + originalUrl);
					asset.href = newUrl;

					if (Assetic.runCounter > 1) {
						Assetic.recentlyChangedStylesheetAsset = asset;
					}
				}
			});
		},

		reloadCunddAssetic: function () {
			var stylesheets = Assetic.cunddAsseticStylesheets,
				length = stylesheets.length,
				originalUrls = Assetic.originalUrls,
				timestamp = (+new Date),
				stylesheet = null,
				originalUrl,
				newUrl;

			for (var i = 0; i < length; i++) {
				stylesheet = stylesheets[i];
				originalUrl = originalUrls[i];
				newUrl = originalUrl + "?reload=" + timestamp;
				this.reloadStylesheets(newUrl, originalUrl, stylesheet);
			}
		},

		reloadRecentlyChangedStylesheetAsset: function () {
			var _recentlyChangedStylesheetAsset = Assetic.recentlyChangedStylesheetAsset,
				assets = Assetic.stylesheetAssets,
				originalUrls = Assetic.stylesheetAssetsOriginalUrls,
				length = assets.length,
				timestamp = (+new Date),
				originalUrl = null,
				asset, newUrl;

			for (var i = 0; i < length; i++) {
				asset = assets[i];
				if (asset == _recentlyChangedStylesheetAsset) {
					originalUrl = originalUrls[i];
					newUrl = originalUrl + "?reload=" + timestamp;
					this.reloadStylesheets(newUrl, originalUrl, asset);
				}
			}
		},

		reloadStylesheetAssets: function () {
			var assets = Assetic.stylesheetAssets,
				originalUrls = Assetic.stylesheetAssetsOriginalUrls,
				length = assets.length,
				timestamp = (+new Date),
				originalUrl = null,
				asset, newUrl;

			for (var i = 0; i < length; i++) {
				asset = assets[i];
				originalUrl = originalUrls[i];
				newUrl = originalUrl + "?reload=" + timestamp;
				this.reloadStylesheets(newUrl, originalUrl, asset);
			}
		},

		reloadJavaScriptAssets: function () {
			var assets = Assetic.javaScriptAssets,
				originalUrls = Assetic.javaScriptAssetsOriginalUrls,
				length = assets.length,
				timestamp = (+new Date),
				originalUrl = null,
				asset, newUrl;

			for (var i = 0; i < length; i++) {
				asset = assets[i];
				originalUrl = originalUrls[i];
				newUrl = originalUrl + "?reload=" + timestamp;
				load(newUrl, function (xhr) {
					var responseHeaderKey = "Last-Modified";
					if (+new Date(xhr.getResponseHeader(responseHeaderKey)) > Assetic.startTime) {
						/* Assetic.log(xhr.getResponseHeader("Last-Modified")); */
						/* Assetic.log(xhr.getResponseHeader("ETag")); */
						location.reload();
					}
				})
			}
		},

		pageFocusChanged: function() {
			var date, dateString;
			if (Assetic.isWatching && Assetic.lostFocusTime) {
				date = (new Date);
				dateString = date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds();
				if ((+new Date) - Assetic.lostFocusTime > 60 * 15) {
					if (Assetic.isVisible) {
						
						Assetic.log("Going to sleep at " + dateString);
						
						
						Assetic._stopTimer();
					} else {
						Assetic.start();
					}
					Assetic.isVisible = !Assetic.isVisible;
				}
			} else {
				Assetic.lostFocusTime = (+new Date);
			}
		},

		pageVisibilityChanged: function() {
			if (Assetic.isWatching) {
				if (Assetic.isVisible) {
					Assetic._stopTimer();
				} else {
					Assetic.start();
				}
				Assetic.isVisible = !Assetic.isVisible;
			}
		},

		_stopTimer: function() {
			window.clearInterval(Assetic.asseticIntervalCallback);
		},

		run: function() {
			Assetic.reload();
		},

		reload: function () {
			var _runCounter = Assetic.runCounter++;
			Assetic.reloadCunddAssetic();


			// Reload the JavaScript assets each X. time
			if ((_runCounter % Assetic.reloadJavaScriptEach) == 0) {
				Assetic.reloadJavaScriptAssets();
			}

			// Reload the Stylesheet assets each X. time or if recentlyChangedStylesheetAsset is not defined
			if ((_runCounter % Assetic.reloadStylesheetsEach) == 0 || !Assetic.recentlyChangedStylesheetAsset) {
				Assetic.reloadStylesheetAssets();
			} else {
				Assetic.log('reload recent css', Assetic.recentlyChangedStylesheetAsset)
				Assetic.reloadRecentlyChangedStylesheetAsset();
			}
		},

		start: function () {
			Assetic.asseticIntervalCallback = window.setInterval(Assetic.run, Assetic.reloadInterval);
			Assetic.isWatching = true;
		},

		stop: function () {
			Assetic._stopTimer();
			Assetic.isWatching = false;
		}
	};

	document.addEventListener('DOMContentLoaded', function() {
		Assetic.init();
		Assetic.start();
		Assetic.reload();
	});



	window.Assetic = Assetic;
})();
