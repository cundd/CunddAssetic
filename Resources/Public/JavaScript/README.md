#Using Assetic auto-refresh standalone

If you want to use the Assetic auto-refresh script standalone and you have jQuery installed you can simply execute the following:

```javascript	
window.Assetic = {
	reloadInterval: 2000,
	monitor: ['js']
};
jQuery.getScript('https://raw.githubusercontent.com/cundd/CunddAssetic/master/Resources/Public/JavaScript/Assetic.js');
```

Or the one-liner:

```javascript	
jQuery.getScript('https://raw.githubusercontent.com/cundd/CunddAssetic/master/Resources/Public/JavaScript/Assetic.js');
```
	
Or with additional options:

```javascript	
window.Assetic = { reloadInterval: 2000, monitor: ['js'] }; jQuery.getScript('https://raw.githubusercontent.com/cundd/CunddAssetic/master/Resources/Public/JavaScript/Assetic.js');
```
