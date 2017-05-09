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
    loaderTag.src = 'https://cdn.rawgit.com/cundd/CunddAssetic/15a168ffb62e8b0a02acd2d1838c741961379215/Resources/Public/JavaScript/Assetic.js';
    loaderTag.integrity="sha512-prz6lZ/LelOqCp2B0D3gpRG7PUwTQ4IVeBwwTM/SZlnsSNPpFvi/Yc2brRUagd3iW0K3XaGI5+pnJyrAY/U5bA==";
    loaderTag.crossOrigin="anonymous";
    loaderTag.async = true;
    document.getElementsByTagName('head')[0].appendChild(loaderTag);
})();
```

To load the library via a `script` tag use:

```html
<script src="https://cdn.rawgit.com/cundd/CunddAssetic/15a168ffb62e8b0a02acd2d1838c741961379215/Resources/Public/JavaScript/Assetic.js"
            integrity="sha512-prz6lZ/LelOqCp2B0D3gpRG7PUwTQ4IVeBwwTM/SZlnsSNPpFvi/Yc2brRUagd3iW0K3XaGI5+pnJyrAY/U5bA=="
            crossorigin="anonymous"
            async defer></script>
```
