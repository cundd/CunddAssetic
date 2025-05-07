// @ts-check
/**
 * Loading:
 *
 * ```js
 (function() {
        const loaderTag = document.createElement('script');
        loaderTag.src = '/Autoreload.js?' + (+ new Date);
        loaderTag.crossOrigin="anonymous";
        loaderTag.async = true;
        loaderTag.dataset.autostart = true
        loaderTag.onload = function() {
            // do something
        };
        document.getElementsByTagName('head')[0].appendChild(loaderTag);
        return loaderTag;
    })()
 * ```
 */
(function (exports) {
    /**
     * @enum string
     */
    const Monitor = {
        CunddAssetic: "cundd_assetic",
        Js: "js",
        Css: "css",
    };
    /**
     * @typedef {Object} Configuration
     * @property {Monitor[]} monitor
     * @property {number} reloadInterval
     * @property {number} sleepInterval
     * @property {HTMLLinkElement[]} stylesheetAssets
     * @property {string[]} stylesheetAssetsOriginalUrls
     * @property {HTMLScriptElement[]} javaScriptAssets
     * @property {string[]} javaScriptAssetsOriginalUrls
     * @property {boolean} debugMode
     * @property {boolean} autostart
     */

    /* @ts-ignore */
    const tempAssetic = window.Assetic || {};
    /** @type {Configuration} */
    const asseticConfiguration = {};

    const currentScriptDataset =
        (document.currentScript && document.currentScript.dataset) || {};
    asseticConfiguration.reloadInterval =
        tempAssetic.reloadInterval ||
        currentScriptDataset.reloadInterval ||
        1750;
    asseticConfiguration.monitor = tempAssetic.monitor ||
        currentScriptDataset.monitor || [
            Monitor.CunddAssetic,
            Monitor.Js,
            Monitor.Css,
        ];
    asseticConfiguration.sleepInterval =
        tempAssetic.sleepInterval ||
        currentScriptDataset.sleepInterval ||
        10000;
    asseticConfiguration.autostart =
        tempAssetic.autostart || currentScriptDataset.autostart || false;
    asseticConfiguration.debugMode = currentScriptDataset.debugMode === "true";

    class Autoreload {
        /**
         * @param {Configuration} configuration
         */
        constructor(configuration) {
            /* BIND METHODS */
            this.reload = this.reload.bind(this);
            this.run = this.run.bind(this);
            this.start = this.start.bind(this);
            this.stop = this.stop.bind(this);
            this.pageVisibilityChanged = this.pageVisibilityChanged.bind(this);
            this.reloadCunddAsseticStylesheetAssets =
                this.reloadCunddAsseticStylesheetAssets.bind(this);
            this.reloadRecentlyChangedStylesheetAsset =
                this.reloadRecentlyChangedStylesheetAsset.bind(this);
            this.reloadStylesheetAssets =
                this.reloadStylesheetAssets.bind(this);
            this.reloadJavaScriptAssets =
                this.reloadJavaScriptAssets.bind(this);

            /* ASSIGN CONFIGURATION */
            this.monitor = configuration.monitor;
            this.reloadInterval = configuration.reloadInterval;
            this.sleepInterval = configuration.sleepInterval;
            this.isWatching = false;
            this.isVisible = true;

            /* Cundd assetic assets */
            this.cunddAsseticStylesheetAssets = [];
            this.cunddAsseticStylesheetAssetsOriginalUrls = [];

            /* All assets */
            this.stylesheetAssets = configuration.stylesheetAssets || [];
            this.stylesheetAssetsOriginalUrls =
                configuration.stylesheetAssetsOriginalUrls || [];
            this.javaScriptAssets = configuration.javaScriptAssets || [];
            this.javaScriptAssetsOriginalUrls =
                configuration.javaScriptAssetsOriginalUrls || [];

            this.recentlyChangedStylesheetAsset = null;

            this.runCounter = 0;
            this.reloadJavaScriptEach = 5;
            this.reloadStylesheetsEach = 5;

            this.lastIds = {};
            this.startTime = +new Date();
            this.lostFocusTime = null;

            this.debugMode = !!configuration.debugMode;
        }

        init() {
            if (
                this.monitor.includes("cundd_assetic") ||
                this.monitor.includes("css")
            ) {
                this.#findCunddAsseticStylesheetAssets();
            }

            if (this.monitor.indexOf("js") !== -1) {
                this.#findJavaScriptAssets();
            }

            if (this.monitor.indexOf("css") !== -1) {
                this.#findStylesheetAssets();
            }

            addEventListenerForPageVisibilityChange(this.pageVisibilityChanged);
        }

        /**
         * Find style assets with cundd_assetic in their URL
         */
        #findCunddAsseticStylesheetAssets() {
            this.cunddAsseticStylesheetAssets = [
                ...document.querySelectorAll("link[href*=cundd_assetic]"),
            ];

            this.cunddAsseticStylesheetAssetsOriginalUrls =
                this.cunddAsseticStylesheetAssets.map((element) => {
                    /* Get the URL without any query parameters */
                    const url = new URL(element.href);

                    return url.protocol + "//" + url.host + url.pathname;
                });
        }

        #findJavaScriptAssets() {
            this.javaScriptAssets = /** @type {HTMLScriptElement[]} */ ([
                ...document.querySelectorAll("script[src]"),
            ]).filter(this.#isLocalAsset);

            this.javaScriptAssetsOriginalUrls = this.javaScriptAssets.map(
                (element) => element.src,
            );
        }

        #findStylesheetAssets() {
            this.stylesheetAssets = /** @type {HTMLLinkElement[]} */ ([
                ...document.querySelectorAll(
                    "link[rel='stylesheet']:not([href*=cundd_assetic])",
                ),
            ]).filter(this.#isLocalAsset);

            this.stylesheetAssetsOriginalUrls = this.stylesheetAssets.map(
                (element) => element.href,
            );
        }

        run() {
            this.reload();
        }

        reload() {
            const _runCounter = this.runCounter++;
            this.reloadCunddAsseticStylesheetAssets();

            /* Reload the JavaScript assets each nth time */
            if (_runCounter % this.reloadJavaScriptEach === 0) {
                this.reloadJavaScriptAssets();
            }

            /* Reload the Stylesheet assets each nth time or
             * if recentlyChangedStylesheetAsset is not defined */
            if (
                _runCounter % this.reloadStylesheetsEach === 0 ||
                !this.recentlyChangedStylesheetAsset
            ) {
                this.reloadStylesheetAssets();
            } else {
                this.#debug(
                    "reload recent css",
                    this.recentlyChangedStylesheetAsset,
                );
                this.reloadRecentlyChangedStylesheetAsset();
            }
        }

        start() {
            this.asseticIntervalCallback = window.setInterval(
                this.run,
                this.reloadInterval,
            );
            this.isWatching = true;
        }

        stop() {
            this.#stopTimer();
            this.isWatching = false;
        }

        reloadCunddAsseticStylesheetAssets() {
            const stylesheets = this.cunddAsseticStylesheetAssets;
            const length = stylesheets.length;
            const cunddAsseticStylesheetAssetsOriginalUrls =
                this.cunddAsseticStylesheetAssetsOriginalUrls;
            const timestamp = +new Date();

            for (let i = 0; i < length; i++) {
                const stylesheet = stylesheets[i];
                const originalUrl = cunddAsseticStylesheetAssetsOriginalUrls[i];
                const newUrl = originalUrl + "?reload=" + timestamp;
                this.#reloadStylesheets(newUrl, originalUrl, stylesheet);
            }
        }

        reloadRecentlyChangedStylesheetAsset() {
            const _recentlyChangedStylesheetAsset =
                this.recentlyChangedStylesheetAsset;
            const assets = this.stylesheetAssets;
            const cunddAsseticStylesheetAssetsOriginalUrls =
                this.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = +new Date();

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                if (asset === _recentlyChangedStylesheetAsset) {
                    const originalUrl =
                        cunddAsseticStylesheetAssetsOriginalUrls[i];
                    const newUrl = originalUrl + "?reload=" + timestamp;
                    this.#reloadStylesheets(newUrl, originalUrl, asset);
                }
            }
        }

        reloadStylesheetAssets() {
            const assets = this.stylesheetAssets;
            const cunddAsseticStylesheetAssetsOriginalUrls =
                this.stylesheetAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = +new Date();

            for (let i = 0; i < length; i++) {
                const asset = assets[i];
                const originalUrl = cunddAsseticStylesheetAssetsOriginalUrls[i];
                const newUrl = originalUrl + "?reload=" + timestamp;
                this.#reloadStylesheets(newUrl, originalUrl, asset);
            }
        }

        reloadJavaScriptAssets() {
            const assets = this.javaScriptAssets;
            const cunddAsseticStylesheetAssetsOriginalUrls =
                this.javaScriptAssetsOriginalUrls;
            const length = assets.length;
            const timestamp = +new Date();

            for (let i = 0; i < length; i++) {
                const originalUrl = cunddAsseticStylesheetAssetsOriginalUrls[i];
                const newUrl = originalUrl + "?reload=" + timestamp;
                load(newUrl).then((response) => {
                    const contentType = response.headers.get("Content-Type");
                    const responseHeaderKey = "Last-Modified"; /* Or "Etag" */
                    const responseHeaderValue =
                        response.headers.get(responseHeaderKey);
                    const didUpdate =
                        contentType?.startsWith("application/javascript") &&
                        +new Date("" + responseHeaderValue) > this.startTime;
                    this.#debug(
                        `Did JavaScript source update? ${didUpdate ? "yes" : "no"} (${responseHeaderKey}:${responseHeaderValue}, URL:${newUrl})`,
                    );

                    if (didUpdate) {
                        /* this.log(xhr.getResponseHeader("Last-Modified")); */
                        /* this.log(xhr.getResponseHeader("ETag")); */
                        location.reload();
                    }
                });
            }
        }

        /**
         * @param {boolean} pageHidden
         */
        pageVisibilityChanged(pageHidden) {
            if (this.isWatching) {
                if (pageHidden) {
                    this.#log("Pause refresh");
                    this.#stopTimer();
                } else {
                    this.#log("Restart refresh");
                    this.start();
                }
                this.isVisible = !pageHidden;
            } else this.#log("nowo");
        }

        /**
         * @param {string} newUrl
         * @param {string} originalUrl
         * @param {HTMLLinkElement} asset
         */
        #reloadStylesheets(newUrl, originalUrl, asset) {
            load(newUrl).then((response) => {
                const contentType = response.headers.get("Content-Type");
                const responseHeaderKey = "Last-Modified"; /* Or "Etag" */
                const responseHeaderValue =
                    response.headers.get(responseHeaderKey);
                const didUpdate =
                    contentType?.startsWith("text/css") &&
                    responseHeaderValue !== this.lastIds[originalUrl];
                this.#debug(
                    `Did stylesheet update? ${didUpdate ? "yes" : "no"} (${responseHeaderKey}:${responseHeaderValue}, URL:${newUrl})`,
                );

                if (didUpdate) {
                    const dateString = this.#getCurrentDateString();
                    this.lastIds[originalUrl] = responseHeaderValue;
                    this.#log("Reload at " + dateString + " -> " + originalUrl);
                    asset.href = newUrl;

                    if (this.runCounter > 1) {
                        this.recentlyChangedStylesheetAsset = asset;
                    }
                }
            });
        }

        #getCurrentDateString() {
            const leadingZero = (/** @type {number} */ value) =>
                value < 10 ? "0" + value : value;
            const date = new Date();

            return (
                leadingZero(date.getHours()) +
                ":" +
                leadingZero(date.getMinutes()) +
                ":" +
                leadingZero(date.getSeconds())
            );
        }

        #stopTimer() {
            window.clearInterval(this.asseticIntervalCallback);
        }

        /**
         * @param {HTMLLinkElement|HTMLScriptElement} asset
         * @return {boolean}
         */
        #isLocalAsset(asset) {
            const loc = document.location;
            const reg = new RegExp(
                "^\\.|^/(?!/)|^[\\w]((?!://).)*$|" +
                    loc.protocol +
                    "//" +
                    loc.host,
            );
            const url =
                asset instanceof HTMLLinkElement ? asset.href : asset.src;

            return reg.test(url);
        }

        /**
         * @param {string} message
         */
        #log(message) {
            console.log(
                "%c Assetic ",
                "background: blue; color: #fff",
                message,
            );
        }

        /**
         * @param {string} message
         * @param {any} rest
         */
        #debug(message, ...rest) {
            if (this.debugMode) {
                console.debug(
                    "%c Assetic ",
                    "background: #8500ff; color: #fff",
                    message,
                    ...rest,
                );
            }
        }
    }

    /**
     * @param {string} url
     */
    const load = function (url) {
        return fetch(url, {
            method: "HEAD",
            mode: "no-cors",
            cache: "no-cache",
            credentials: "include",
            redirect: "follow",
            referrerPolicy: "no-referrer",
        });
    };

    /**
     * @param {function} callback
     */
    const addEventListenerForPageVisibilityChange = function (callback) {
        let hidden;
        let visibilityChange;
        /* @ts-ignore */
        if (typeof document.hidden !== "undefined") {
            /* Opera 12.10 and Firefox 18 and later support */
            hidden = "hidden";
            visibilityChange = "visibilitychange";
        } else if (typeof document["msHidden"] !== "undefined") {
            hidden = "msHidden";
            visibilityChange = "msvisibilitychange";
        } else if (typeof document["webkitHidden"] !== "undefined") {
            hidden = "webkitHidden";
            visibilityChange = "webkitvisibilitychange";
        } else {
            console.warn("No visibility change API found");
            return;
        }

        const handleVisibilityChange = function () {
            if (document[hidden]) {
                callback(true);
            } else {
                callback(false);
            }
        };

        document.addEventListener(
            visibilityChange,
            handleVisibilityChange,
            false,
        );
    };

    if (asseticConfiguration.autostart) {
        const autoreload = new Autoreload(asseticConfiguration);
        autoreload.init();
        autoreload.start();
        autoreload.reload();
    }

    exports["Autoreload"] = Autoreload;
})(window);
