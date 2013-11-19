#Using Assetic auto-refresh standalone

If you want to use the Assetic auto-refresh script standalone and you have jQuery installed you can simply execute the following:
	
	jQuery.getScript(
		'https://raw.github.com/cundd/CunddAssetic/feature/auto-refresh/Resources/Public/JavaScript/Assetic.js', 
		function() {
			setTimeout(function() {
				Assetic.init();
				Assetic.start();
			}, 700)
		}
	)
	

Or the one-liner:

	jQuery.getScript('https://raw.github.com/cundd/CunddAssetic/feature/auto-refresh/Resources/Public/JavaScript/Assetic.js', function() { setTimeout(function() { Assetic.init(); Assetic.start(); }, 700) })