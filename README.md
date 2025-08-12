# Cundd Assetic

Integrate the [Assetic asset management framework](https://github.com/assetic-php/assetic) into your TYPO3 CMS installation.

## Installation

1. `composer require cundd/assetic`
2. Include the static TypoScript files
3. Configure the assets you want to be loaded

## Usage

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

### With other CSS preprocessors

Analog you can use other CSS preprocessors. The [Assetic Github Page](https://github.com/assetic-php/assetic?tab=readme-ov-file#filters) provides a (incomplete) list of all filters provided with the installed Assetic version.

## Development mode

To make sure that the assets are compiled each time the frontend page is refreshed, you can enable the development mode:

    plugin.CunddAssetic {
    	development = 1
    }

By default Cundd Assetic is configured not to compile files if NO backend user is logged in. To allow file compilation without a logged in backend user you can change the `allow_compile_without_login` configuration, in TypoScript or the Constant editor.

    plugin.CunddAssetic {
        allow_compile_without_login = 1
    }

## Advanced

### Configure a filter

Some filters allow futher customization. The Sass filter e.g. provides the method `addImportPath` which enables you to add another path to look for imported scss files. Cundd Assetic provides an interface to invoke such functions through TypoScript.

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

In some cases you may have to specify the path to a CSS preprocessor to match your system's configuration. This can be done through the `filter_binaries` directive in TypoScript:

    plugin.CunddAssetic {
        filter_binaries {
        	# Change the path to the filter binaries. I.e. if node.js is installed
        	# into /usr/local/bin/
    		assetic_filter_lessfilter = /usr/local/bin/node

    		# The binary for filter class \Assetic\Filter\Sass\ScssFilter
    		assetic_filter_sass_scssfilter = /usr/local/bin/sass
        }
    }

## Command line

Assetic provides three different CLI commands.

### assetic:run

Compiles the assets and exit.

```bash
vendor/bin/typo3 assetic:run
```

### assetic:watch

Watches for file changes in fileadmin/ and re-compiles the assets if needed.

```bash
vendor/bin/typo3 assetic:watch
```

### assetic:livereload

Starts a [LiveReload](http://livereload.com/) compatible server that watches for file changes in fileadmin/ and re-compiles the assets if needed. The TypoScript constant `assetic.settings.livereload.add_javascript` should be set to 1.

```bash
vendor/bin/typo3 assetic:livereload
```

## CSP & LiveReload

To use LiveReload with CSP enabled add `wss://your-domain.tld:35729/livereload` to `connect-src` in the site's CSP configuration file (`config/sites/your-site/csp.yaml`).

See the [TYPO3 documentation](https://docs.typo3.org/permalink/t3coreapi:content-security-policy-site) for further details.

### Example

```yaml
mutations:
    - mode: "extend"
      directive: "connect-src"
      sources:
          - "wss://your-domain.tld:35729/livereload"
```

## Additional tools

### Standalone auto-refresh tool

Cundd Assetic provides a JavaScript that observes the CSS and JavaScript assets on the page for changes and automatically reloads them. For more information and usage visit [/Resources/Public/JavaScript](/Resources/Public/JavaScript).

## Sponsored by

[![](https://www.iresults.li/github-logo.png)](http://www.iresults.li)
