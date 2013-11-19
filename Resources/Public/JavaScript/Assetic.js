(function () {
	var Assetic,
		tempAssetic = window.Assetic || {},
		Ef = function () {
		},
		console = window.console || { log: Ef, info: Ef };

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

	Assetic = {
		reloadInterval: tempAssetic.reloadInterval,
		monitor: tempAssetic.monitor,

		// Cundd assetic assets
		cunddAsseticStylesheets: [],
		originalUrls: [],

		// All assets
		stylesheetAssets: [],
		stylesheetAssetsOriginalUrls: [],
		javaScriptAssets: [],
		javaScriptAssetsOriginalUrls: [],

		lastIds: {},
		startTime: (+new Date),

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
					console.log("Reload at " + dateString + " -> " + originalUrl);
					asset.href = newUrl;
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
						/* console.log(xhr.getResponseHeader("Last-Modified")); */
						/* console.log(xhr.getResponseHeader("ETag")); */
						location.reload();
					}
				})
			}
		},

		reload: function () {
			Assetic.reloadCunddAssetic();
			Assetic.reloadStylesheetAssets();
			Assetic.reloadJavaScriptAssets();
		},

		start: function () {
			Assetic.asseticIntervalCallback = window.setInterval(Assetic.reload, Assetic.reloadInterval);
		},

		stop: function () {
			window.clearInterval(Assetic.asseticIntervalCallback);
		}
	};

	document.addEventListener('DOMContentLoaded', function() {
		Assetic.init();
		Assetic.start();
		Assetic.reload();

	});

	window.Assetic = Assetic;
})();
