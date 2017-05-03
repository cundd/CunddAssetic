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
    loaderTag.src = 'https://cdn.rawgit.com/cundd/CunddAssetic/master/Resources/Public/JavaScript/Assetic.js';
    loaderTag.integrity="sha512-N6KmO975a4B4Lc2m+BOg7R+gJypJlpdAycgDvb/B2gk/RulK5FL2dAF1RpnRkB5WAabZBWViLcZ9f3nBstJCSQ==";
    loaderTag.crossOrigin="anonymous";
    loaderTag.async = true;
    document.getElementsByTagName('head')[0].appendChild(loaderTag);
})();
```

To load the library via a `script` tag use:

```html
<script src="https://cdn.rawgit.com/cundd/CunddAssetic/master/Resources/Public/JavaScript/Assetic.js"
            integrity="sha512-N6KmO975a4B4Lc2m+BOg7R+gJypJlpdAycgDvb/B2gk/RulK5FL2dAF1RpnRkB5WAabZBWViLcZ9f3nBstJCSQ=="
            crossorigin="anonymous"
            async defer></script>
```
