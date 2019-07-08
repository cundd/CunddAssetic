Using Assetic auto-refresh standalone
=====================================

If you want to use the Assetic auto-refresh script standalone you can simply execute the following:

```javascript
/* Optional settings */
window.Assetic = {
	reloadInterval: 2000,
	monitor: ['js']
};
(function() { /* Load the library */
    var loaderTag = document.createElement('script');
    loaderTag.src = 'https://cdn.jsdelivr.net/gh/cundd/CunddAssetic@1.5.0/Resources/Public/JavaScript/Assetic.js';
    loaderTag.integrity="sha384-TChsBgMJhlSGsfZcqG2eCvdS3SQlZkThllFNWKyi7WG0MlMT4zxuX0RhQu9ndevH";
    loaderTag.crossOrigin="anonymous";
    loaderTag.async = true;
    document.getElementsByTagName('head')[0].appendChild(loaderTag);
})();
```

To load the library via a `script` tag use:

```html
<script src="https://cdn.jsdelivr.net/gh/cundd/CunddAssetic@1.5.0/Resources/Public/JavaScript/Assetic.js"
            integrity="sha384-TChsBgMJhlSGsfZcqG2eCvdS3SQlZkThllFNWKyi7WG0MlMT4zxuX0RhQu9ndevH"
            crossorigin="anonymous"
            async defer></script>
```
