(function () {
    const tempAssetic = window.Assetic || {};
    const asseticConfiguration = {};

    asseticConfiguration.reloadInterval = tempAssetic.reloadInterval || 1750;
    asseticConfiguration.monitor = tempAssetic.monitor || ['cundd_assetic', 'js', 'css'];
    asseticConfiguration.sleepInterval = tempAssetic.sleepInterval || 10000;
    asseticConfiguration.autostart = tempAssetic.autostart || true;

    const load = function (url, callback) {
        const xhr = new XMLHttpRequest();
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

        xhr.open('HEAD', url, true);
        xhr.send('');
    };

    const filter = function (collection, callback) {
        return Array.prototype.filter.call(collection, callback);
    };

    const slice = function (collection, start, end) {
        return Array.prototype.slice.call(collection, start, end);
    };

    const addEventListenerForPageVisibilityChange = function (callback) {
        let hidden;
        let visibilityChange;
        if (typeof document.hidden !== 'undefined') { /* Opera 12.10 and Firefox 18 and later support */
            hidden = 'hidden';
            visibilityChange = 'visibilitychange';
        } else if (typeof document.msHidden !== 'undefined') {
            hidden = 'msHidden';
            visibilityChange = 'msvisibilitychange';
        } else if (typeof document.webkitHidden !== 'undefined') {
            hidden = 'webkitHidden';
            visibilityChange = 'webkitvisibilitychange';
        }

        const handleVisibilityChange = function () {
            if (document[hidden]) {
                callback(true);
            } else {
                callback(false);
            }
        };

        document.addEventListener(visibilityChange, handleVisibilityChange, false);
    };

    const Assetic = {
        monitor: asseticConfiguration.monitor,
        reloadInterval: asseticConfiguration.reloadInterval,
        sleepInterval: asseticConfiguration.sleepInterval,
        isWatching: false,
        isVisible: true,

        // Cundd assetic assets
        cunddAsseticStylesheets: [],
        originalUrls: [],

        // All assets
        stylesheetAssets: asseticConfiguration.stylesheetAssets || [],
        stylesheetAssetsOriginalUrls: asseticConfiguration.stylesheetAssetsOriginalUrls || [],
        javaScriptAssets: asseticConfiguration.javaScriptAssets || [],
        javaScriptAssetsOriginalUrls: asseticConfiguration.javaScriptAssetsOriginalUrls || [],

        recentlyChangedStylesheetAsset: null,

        runCounter: 0,
        reloadJavaScriptEach: 5,
        reloadStylesheetsEach: 5,

        lastIds: {},
        startTime: (+new Date),
        lostFocusTime: null,

        debugMode: !!asseticConfiguration.debugMode,

        init: function () {
            /*
             * Find style assets with cundd_assetic in their URL
             */
            if (Assetic.monitor.indexOf('cundd_assetic') !== -1 || Assetic.monitor.indexOf('css') !== -1) {
                Assetic.cunddAsseticStylesheets = document.querySelectorAll('link[href*=cundd_assetic]');

                const length = Assetic.cunddAsseticStylesheets.length;
                for (let i = 0; i < length; i++) {
                    const stylesheet = Assetic.cunddAsseticStylesheets[i];
                    const originalUrl = stylesheet.href;
                    const lastSlashPosition = originalUrl.lastIndexOf('/');
                    const fileName = originalUrl.substring(lastSlashPosition + 1);
                    if (fileName.startsWith('_debug_')) {
                        Assetic.originalUrls[i] = originalUrl;
                    } else {
                        const urlWithoutHash = originalUrl.replace(/_\w*\.css/, '.css');
                        const path = urlWithoutHash.substr(0, lastSlashPosition);
                        Assetic.originalUrls[i] = path + '/_debug_' + fileName;
                    }
                }
            }

            /*
             * Find JavaScript assets
             */
            if (Assetic.monitor.indexOf('js') !== -1) {
                Assetic.javaScriptAssets = filter(
                    slice(document.querySelectorAll('script[src]')),
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
                Assetic.stylesheetAssets = filter(
                    slice(document.querySelectorAll('link[rel=\'stylesheet\']:not([href*=cundd_assetic])')),
                    this.isLocalAsset
                );
                Assetic.stylesheetAssetsOriginalUrls = Assetic.stylesheetAssets.map(function (element) {
                    return element.href;
                });
            }

            addEventListenerForPageVisibilityChange(Assetic.pageVisibilityChanged);
        },

        log: function (message) {
            if (window.console) {
                window.console.log('%c Assetic ', 'background: blue; color: #fff', message);
            }
        },

        debug: function (message) {
            if (Assetic.debugMode && window.console) {
                window.console.debug('%c Assetic ', 'background: #8500ff; color: #fff', message);
            }
        },

        isLocalAsset: function (asset) {
            const loc = document.location;
            const reg = new RegExp('^\\.|^\/(?!\/)|^[\\w]((?!://).)*$|' + loc.protocol + '//' + loc.host);
            const url = asset.src || asset.href;

            return url.match(reg);
        },

        reloadStylesheets: function (newUrl, originalUrl, asset) {
            load(newUrl, function (xhr) {
                const responseHeaderKey = 'Last-Modified'; // Or "Etag"
                if (xhr.getResponseHeader(responseHeaderKey) !== Assetic.lastIds[originalUrl]) {
                    const date = (new Date);
                    const dateString = date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds();
                    Assetic.lastIds[originalUrl] = xhr.getResponseHeader(responseHeaderKey);
                    Assetic.log('Reload at ' + dateString + ' -> ' + originalUrl);
                    asset.href = newUrl;

                    if (Assetic.runCounter > 1) {
                        Assetic.recentlyChangedStylesheetAsset = asset;
                    }
                }
            });
        },

        reloadCunddAssetic: function () {
            const stylesheets = Assetic.cunddAsseticStylesheets;
            const length = stylesheets.length;
            const originalUrls = Assetic.originalUrls;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const stylesheet = stylesheets[i];
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                this.reloadStylesheets(newUrl, originalUrl, stylesheet);
            }
        },

        reloadRecentlyChangedStylesheetAsset: function () {
            const _recentlyChangedStylesheetAsset = Assetic.recentlyChangedStylesheetAsset;
            const assets = Assetic.stylesheetAssets;
            const originalUrls = Assetic.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                if (asset === _recentlyChangedStylesheetAsset) {
                    const originalUrl = originalUrls[i];
                    const newUrl = originalUrl + '?reload=' + timestamp;
                    this.reloadStylesheets(newUrl, originalUrl, asset);
                }
            }
        },

        reloadStylesheetAssets: function () {
            const assets = Assetic.stylesheetAssets;
            const originalUrls = Assetic.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                this.reloadStylesheets(newUrl, originalUrl, asset);
            }
        },

        reloadJavaScriptAssets: function () {
            const assets = Assetic.javaScriptAssets;
            const originalUrls = Assetic.javaScriptAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                load(newUrl, function (xhr) {
                    const responseHeaderKey = 'Last-Modified';
                    if (+new Date(xhr.getResponseHeader(responseHeaderKey)) > Assetic.startTime) {
                        /* Assetic.log(xhr.getResponseHeader("Last-Modified")); */
                        /* Assetic.log(xhr.getResponseHeader("ETag")); */
                        location.reload();
                    }
                });
            }
        },

        pageVisibilityChanged: function (pageHidden) {
            if (Assetic.isWatching) {
                if (pageHidden) {
                    Assetic._stopTimer();
                } else {
                    Assetic.start();
                }
                Assetic.isVisible = !pageHidden;
            }
        },

        _stopTimer: function () {
            window.clearInterval(Assetic.asseticIntervalCallback);
        },

        run: function () {
            Assetic.reload();
        },

        reload: function () {
            const _runCounter = Assetic.runCounter++;
            Assetic.reloadCunddAssetic();

            // Reload the JavaScript assets each X. time
            if ((_runCounter % Assetic.reloadJavaScriptEach) === 0) {
                Assetic.reloadJavaScriptAssets();
            }

            // Reload the Stylesheet assets each X. time or if recentlyChangedStylesheetAsset is not defined
            if ((_runCounter % Assetic.reloadStylesheetsEach) === 0 || !Assetic.recentlyChangedStylesheetAsset) {
                Assetic.reloadStylesheetAssets();
            } else {
                Assetic.debug('reload recent css', Assetic.recentlyChangedStylesheetAsset);
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

    if (asseticConfiguration.autostart) {
        Assetic.init();
        Assetic.start();
        Assetic.reload();
    }

    window.Assetic = Assetic;
})();
