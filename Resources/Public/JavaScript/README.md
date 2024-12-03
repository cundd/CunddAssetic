# Using Assetic auto-refresh standalone

If you want to use the Assetic auto-refresh script standalone you can simply execute the following:

```javascript
(function () {
    /* Optional settings */
    window.Assetic = {
        reloadInterval: 2000,
        monitor: ["js"],
    };
    /* Load the library */
    var loaderTag = document.createElement("script");
    loaderTag.src =
        "https://cdn.jsdelivr.net/gh/cundd/CunddAssetic@834d6c0b86cff082c3875350d6b04d7d0fb95803/Resources/Public/JavaScript/Autoreload.js";
    loaderTag.integrity =
        "sha384-PM5oeNAryQyMalqZPdav7HVc/lanPctwoVsih6QemB8qOlua79GDi/tej3hiQRxF";
    loaderTag.crossOrigin = "anonymous";
    loaderTag.async = true;
    loaderTag.dataset.autostart = true;
    document.getElementsByTagName("head")[0].appendChild(loaderTag);
})();
```

To load the library via a `script` tag use:

```html
<script
    src="https://cdn.jsdelivr.net/gh/cundd/CunddAssetic@834d6c0b86cff082c3875350d6b04d7d0fb95803/Resources/Public/JavaScript/Autoreload.js"
    integrity="sha384-PM5oeNAryQyMalqZPdav7HVc/lanPctwoVsih6QemB8qOlua79GDi/tej3hiQRxF"
    crossorigin="anonymous"
    data-autostart="true"
    async
    defer
></script>
```

To include the library with TypoScript use:

```TypoScript
page.includeJSFooter {
    assetic-autoreload = EXT:assetic/Resources/Public/JavaScript/Autoreload.js
    assetic-autoreload.data.data-autostart = true
}
```
