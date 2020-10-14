/**
 * Loading:
 *
 * ```js
 (function() {
        const loaderTag = document.createElement('script');
        loaderTag.src = '/Autoreload.js';
        loaderTag.crossOrigin="anonymous";
        loaderTag.async = true;
        loaderTag.onload = function() {
            // do something
        };
        document.getElementsByTagName('head')[0].appendChild(loaderTag);
    })()
 * ```
 */
(function (exports) {
    const tempAssetic = window.Assetic || {};
    const asseticConfiguration = {};

    asseticConfiguration.reloadInterval = tempAssetic.reloadInterval || 1750;
    asseticConfiguration.monitor = tempAssetic.monitor || ['cundd_assetic', 'js', 'css'];
    asseticConfiguration.sleepInterval = tempAssetic.sleepInterval || 10000;
    asseticConfiguration.autostart = tempAssetic.autostart || false;
    asseticConfiguration.replace = tempAssetic.replace || {};

    class Autoreload {
        constructor(configuration) {
            /* BIND METHODS */
            this.reload = this.reload.bind(this);
            this.run = this.run.bind(this);
            this.start = this.start.bind(this);
            this.stop = this.stop.bind(this);
            this.pageVisibilityChanged = this.pageVisibilityChanged.bind(this);
            this.reloadCunddAssetic = this.reloadCunddAssetic.bind(this);
            this.reloadRecentlyChangedStylesheetAsset = this.reloadRecentlyChangedStylesheetAsset.bind(this);
            this.reloadStylesheetAssets = this.reloadStylesheetAssets.bind(this);
            this.reloadJavaScriptAssets = this.reloadJavaScriptAssets.bind(this);

            /* ASSIGN CONFIGURATION */
            this.monitor = configuration.monitor;
            this.reloadInterval = configuration.reloadInterval;
            this.sleepInterval = configuration.sleepInterval;
            this.replace = configuration.replace;
            this.isWatching = false;
            this.isVisible = true;

            /* Cundd assetic assets */
            this.cunddAsseticStylesheets = [];
            this.originalUrls = [];

            /* All assets */
            this.stylesheetAssets = configuration.stylesheetAssets || [];
            this.stylesheetAssetsOriginalUrls = configuration.stylesheetAssetsOriginalUrls || [];
            this.javaScriptAssets = configuration.javaScriptAssets || [];
            this.javaScriptAssetsOriginalUrls = configuration.javaScriptAssetsOriginalUrls || [];

            this.recentlyChangedStylesheetAsset = null;

            this.runCounter = 0;
            this.reloadJavaScriptEach = 5;
            this.reloadStylesheetsEach = 5;

            this.lastIds = {};
            this.startTime = (+new Date);
            this.lostFocusTime = null;

            this.debugMode = !!configuration.debugMode;
        }

        init() {
            /*
             * Find style assets with cundd_assetic in their URL
             */
            if (this.monitor.indexOf('cundd_assetic') !== -1 || this.monitor.indexOf('css') !== -1) {
                this.cunddAsseticStylesheets = document.querySelectorAll('link[href*=cundd_assetic]');

                const length = this.cunddAsseticStylesheets.length;
                for (let i = 0; i < length; i++) {
                    const stylesheet = this.cunddAsseticStylesheets[i];
                    const originalUrl = stylesheet.href;
                    const lastSlashPosition = originalUrl.lastIndexOf('/');
                    const fileName = originalUrl.substring(lastSlashPosition + 1);
                    if (fileName.startsWith('_debug_')) {
                        this.originalUrls[i] = originalUrl;
                    } else {
                        const urlWithoutHash = originalUrl.replace(/_\w*\.css/, '.css');
                        const path = urlWithoutHash.substr(0, lastSlashPosition);
                        this.originalUrls[i] = path + '/_debug_' + fileName;
                    }
                }
            }

            /*
             * Find JavaScript assets
             */
            if (this.monitor.indexOf('js') !== -1) {
                this.javaScriptAssets = filter(
                    slice(document.querySelectorAll('script[src]')),
                    this._isLocalAsset
                );
                this.javaScriptAssetsOriginalUrls = this.javaScriptAssets.map(function (element) {
                    return element.src;
                });
            }

            /*
             * Find style assets
             */
            if (this.monitor.indexOf('css') !== -1) {
                this.stylesheetAssets = filter(
                    slice(document.querySelectorAll('link[rel=\'stylesheet\']:not([href*=cundd_assetic])')),
                    this._isLocalAsset
                );
                this.stylesheetAssetsOriginalUrls = this.stylesheetAssets.map(function (element) {
                    return element.href;
                });
            }

            addEventListenerForPageVisibilityChange(this.pageVisibilityChanged);
        }

        run() {
            this.reload();
        }

        reload() {
            const _runCounter = this.runCounter++;
            this.reloadCunddAssetic();

            // Reload the JavaScript assets each X. time
            if ((_runCounter % this.reloadJavaScriptEach) === 0) {
                this.reloadJavaScriptAssets();
            }

            // Reload the Stylesheet assets each X. time or if recentlyChangedStylesheetAsset is not defined
            if ((_runCounter % this.reloadStylesheetsEach) === 0 || !this.recentlyChangedStylesheetAsset) {
                this.reloadStylesheetAssets();
            } else {
                this.debug('reload recent css', this.recentlyChangedStylesheetAsset);
                this.reloadRecentlyChangedStylesheetAsset();
            }
        }

        start() {
            this.asseticIntervalCallback = window.setInterval(this.run, this.reloadInterval);
            this.isWatching = true;
        }

        stop() {
            this._stopTimer();
            this.isWatching = false;
        }

        reloadCunddAssetic() {
            const stylesheets = this.cunddAsseticStylesheets;
            const length = stylesheets.length;
            const originalUrls = this.originalUrls;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const stylesheet = stylesheets[i];
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                this._reloadStylesheets(newUrl, originalUrl, stylesheet);
            }
        }

        reloadRecentlyChangedStylesheetAsset() {
            const _recentlyChangedStylesheetAsset = this.recentlyChangedStylesheetAsset;
            const assets = this.stylesheetAssets;
            const originalUrls = this.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                if (asset === _recentlyChangedStylesheetAsset) {
                    const originalUrl = originalUrls[i];
                    const newUrl = originalUrl + '?reload=' + timestamp;
                    this._reloadStylesheets(newUrl, originalUrl, asset);
                }
            }
        }

        reloadStylesheetAssets() {
            const assets = this.stylesheetAssets;
            const originalUrls = this.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                this._reloadStylesheets(newUrl, originalUrl, asset);
            }
        }

        reloadJavaScriptAssets() {
            const assets = this.javaScriptAssets;
            const originalUrls = this.javaScriptAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = (+new Date);

            for (let i = 0; i < length; i++) {
                const originalUrl = originalUrls[i];
                const newUrl = originalUrl + '?reload=' + timestamp;
                load(newUrl).then(response => {
                    const responseHeaderKey = 'Last-Modified'; /* Or "Etag" */
                    const responseHeaderValue = response.headers.get(responseHeaderKey);
                    if (+new Date(responseHeaderValue) > this.startTime) {
                        /* this.log(xhr.getResponseHeader("Last-Modified")); */
                        /* this.log(xhr.getResponseHeader("ETag")); */
                        location.reload();
                    }
                });
            }
        }

        pageVisibilityChanged(pageHidden) {
            if (this.isWatching) {
                if (pageHidden) {
                    this._stopTimer();
                } else {
                    this.start();
                }
                this.isVisible = !pageHidden;
            }
        }

        /**
         * @param newUrl
         * @param originalUrl
         * @param asset
         * @private
         */
        _reloadStylesheets(newUrl, originalUrl, asset) {
            load(newUrl).then(response => {
                const responseHeaderKey = 'Last-Modified'; /* Or "Etag" */
                const responseHeaderValue = response.headers.get(responseHeaderKey);
                if (responseHeaderValue !== this.lastIds[originalUrl]) {
                    const date = (new Date);
                    const dateString = date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds();
                    this.lastIds[originalUrl] = responseHeaderValue;
                    this.log('Reload at ' + dateString + ' -> ' + originalUrl);
                    asset.href = newUrl;

                    if (this.runCounter > 1) {
                        this.recentlyChangedStylesheetAsset = asset;
                    }
                }
            });
        }

        /**
         * @private
         */
        _stopTimer() {
            window.clearInterval(this.asseticIntervalCallback);
        }

        /**
         * @param asset
         * @return {boolean}
         * @private
         */
        _isLocalAsset(asset) {
            const loc = document.location;
            const reg = new RegExp('^\\.|^\/(?!\/)|^[\\w]((?!://).)*$|' + loc.protocol + '//' + loc.host);
            const url = asset.src || asset.href;

            return url.match(reg);
        }

        /**
         * @param message
         * @private
         */
        log(message) {
            console.log('%c Assetic ', 'background: blue; color: #fff', message);
        }

        /**
         * @param message
         * @private
         */
        debug(message) {
            if (this.debugMode) {
                console.debug('%c Assetic ', 'background: #8500ff; color: #fff', message);
            }
        }
    }


    const load = function (url) {
        return fetch(url, {
            method: 'HEAD',
            mode: 'no-cors',
            cache: 'no-cache',
            credentials: 'include',
            redirect: 'follow',
            referrerPolicy: 'no-referrer',
        });
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

    if (asseticConfiguration.autostart) {
        const autoreload = new Autoreload(asseticConfiguration);
        autoreload.init();
        autoreload.start();
        autoreload.reload();
    }

    exports.Autoreload = Autoreload;
})(window);
