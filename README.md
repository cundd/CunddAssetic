Cundd Assetic
=============

Integrate the [Assetic asset management framework](https://github.com/kriswallsmith/assetic) into your TYPO3 CMS installation.


Installation
------------

1. First you have to install the required [Cundd Composer extension](https://github.com/cundd/CunddComposer) and let [Composer](http://getcomposer.org/) install all the dependencies
2. Include the static TypoScript files
3. Configure the assets you want to be loaded


Usage
-----

### Basic

    plugin.CunddAssetic {
        stylesheets {
            custom_identifier_for_the_asset = fileadmin/stylesheets/style.css
        }
    }


### Multiple assets

    plugin.CunddAssetic {
        stylesheets {
            identifier_for_asset_1 = fileadmin/stylesheets/reset.css
            identifier_for_asset_2 = fileadmin/stylesheets/style.css
        }
    }


### With SASS

If your web server and web user are configured to be able to use the `sass` command line tool, from within your TYPO3 installation, you could use the following:

    plugin.CunddAssetic {
        stylesheets {
            custom_identifier_for_the_asset = fileadmin/bootstrap/lib/bootstrap.scss
        }
    }


### With SASS in PHP

If you have to use SASS without the original Ruby implementation you can use a PHP version of SASS written by [leafo](http://leafo.net/scssphp/). All you have to do is to make sure the library is installed (using Composer) and that the Scssphp filter is used for *.scss files:

    plugin.CunddAssetic {
        stylesheets {
            custom_identifier_for_the_asset = fileadmin/bootstrap/lib/bootstrap.scss
        }

        filter_for_type {
            scss = Assetic\Filter\ScssphpFilter
        }
    }


### With other CSS preprocessors

Analog you can use other CSS preprocessors. The [Assetic Github Page](https://github.com/kriswallsmith/assetic#filters) provides a (incomplete) list of all filters provided with the installed Assetic version.


Development mode
----------------

To make sure that the assets are compiled each time, the frontend page is refreshed you can enable the development mode:

    plugin.CunddAssetic {
    	development = 1
    }

By default Cundd Assetic is configured not to compile files if NO backend user is logged in. So a client will not see changes that you may have overlooked. To allow file compilation without a logged in backend user you can change the `allow_compile_without_login` configuration, in your TypoScript or the Constant editor.

    plugin.CunddAssetic {
        allow_compile_without_login = 1
    }


Advanced
--------

### Configure a filter

Some filters allow more customization. The ScssphpFilter i.e. provides the method `addImportPath` which enables you to add another path to look for imported scss files. Cundd Assetic provides an interface to invoke such functions through TypoScript.

    plugin.CunddAssetic.stylesheets.custom_identifier_for_the_asset {
        ### Add functions that will be called on the filter
        functions {
            addImportPath = fileadmin/local/sass/
        }
    }

If you want to invoke a method multiple times you can just add a numeric prefix to the function name:

    plugin.CunddAssetic.stylesheets.custom_identifier_for_the_asset {
        functions {
            # Add a numeric prefix to allow the function to called multiple times
            0-addImportPath = fileadmin/local/sass/
            1-addImportPath = fileadmin/core/sass/
        }
    }


### Configure the filter binary paths

In some cases you may have to specify the path to a CSS preprocessor to match your OS' configuration. This can be done through the `filter_binaries` directive in your TypoScript

    plugin.CunddAssetic {
        filter_binaries {
	    	# Change the path to the filter binaries. I.e. if node.js is installed
	    	# into /usr/local/bin/
    		lessFilter = /usr/local/bin/node
	    }
    }


Command line
------------

Assetic provides three different CLI commands:


### compile:run

Compiles the assets and exit.

```bash
typo3/cli_dispatch.phpsh extbase compile:run
```


### compile:watch

Watches for file changes in fileadmin/ and re-compiles the assets if needed.

```bash
typo3/cli_dispatch.phpsh extbase compile:watch
```

**Options:**

- `--interval`: Interval between file scans


### compile:livereload

Starts a [LiveReload](http://livereload.com/) compatible server that watches for file changes in fileadmin/ and re-compiles the assets if needed. The TypoScript constant `module.tx_assetic.settings.experimental` should be set to 1.

```bash
typo3/cli_dispatch.phpsh extbase compile:livereload
```

**Options:**

- `--address`: IP address to listen
- `--port`: Port to listen
- `--interval`: Interval between file scans



Additional tools
----------------

### Standalone auto-refresh tool

Cundd Assetic provides a JavaScript that observes the CSS and JavaScript assets on the page for changes and automatically reloads them. For more information and usage visit [https://github.com/cundd/CunddAssetic/tree/master/Resources/Public/JavaScript](https://github.com/cundd/CunddAssetic/tree/master/Resources/Public/JavaScript).
