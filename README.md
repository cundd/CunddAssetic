# Cundd Assetic

Integrate the [Assetic asset management framework](https://github.com/assetic-php/assetic) into your TYPO3 CMS installation.

## Installation

1. `composer require cundd/assetic`
2. Load the Site Set into the TYPO3 Site
3. Configure the assets you want to be loaded
4. Include the plugin the `page.headerData`
    ```typoscript
    // Example:
    page.headerData.40001 =< plugin.tx_assetic
    ```

## Usage

### Basic

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/main.css
```

### Multiple assets

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/reset.css
        - file: EXT:site_package/Resources/Private/Stylesheets/style.css
```

### With SASS

If your web server and web user are configured to be able to use the `sass` command line tool, from within your TYPO3 installation, you could use the following:

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/main.scss
```

### With other CSS preprocessors

Analog you can use other CSS preprocessors. The [Assetic Github Page](https://github.com/assetic-php/assetic?tab=readme-ov-file#filters) provides a (incomplete) list of all filters provided by the installed Assetic version.

## Development mode

To make sure that the assets are compiled each time the frontend page is refreshed, you can enable the development mode:

    plugin.tx_assetic {
    	development = 1
    }

By default Cundd Assetic is configured not to compile files if NO backend user is logged in. To allow file compilation without a logged in backend user you can change the `allowCompileWithoutLogin` configuration in Site Settings.

## Advanced

### Configure a filter

Some filters allow further customization. The Sass filter e.g. provides the method `addImportPath` which allows you to add another path to look for imported Sass files. Cundd Assetic provides an interface to invoke such functions.

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/main.scss
          functions:
              # Add functions that will be called on the filter
              addImportPath: ProjectPath:/vendor/
```

If you want to invoke a method multiple times you can just add a numeric prefix to the function name:

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/main.scss
          functions:
              # Add a numeric prefix to allow the function to called multiple times
              0-addImportPath: ProjectPath:/vendor/
              1-addImportPath: EXT:site_package/Resources/Private/Library/
```

#### Development flags

To apply functions only in development mode, entries can be added with key `developmentFunctions`:

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    stylesheets:
        - file: EXT:site_package/Resources/Private/Stylesheets/main.scss
          functions:
              setStyle: compressed

          developmentFunctions:
              setStyle: expanded
```

### Configure the filter binary paths

In some cases you may have to specify the path to a CSS preprocessor to match your system's configuration. This can be done through the `filterBinaries` configuration.

The configuration keys are determined by replacing `\` with `_` and converting
the string to lowercase:

```
1. \AsseticAdditions\Filter\DartSassFilter
2. AsseticAdditions_Filter_DartSassFilter
3. asseticadditions_filter_dartsassfilter
```

```yaml
# File: config/sites/my-site/settings.yaml or EXT:site_package/Configuration/Sets/Site/settings.yaml
assetic:
    settings:
        filterBinaries:
        	# Change the path to the filter binaries. I.e. if node.js is installed
        	# into /usr/local/bin/
            assetic_filter_lessfilter: /usr/local/bin/node

    		# The binary for filter class \AsseticAdditions\Filter\DartSassFilter
    		asseticadditions_filter_dartsassfilter: /usr/local/bin/sass
```

## Command line

Assetic provides three different CLI commands.

### assetic:compile

Compiles the assets and exit.

```bash
vendor/bin/typo3 assetic:compile
```

### assetic:watch

Watches for file changes in `EXT:client` and re-compiles the assets if needed.

```bash
vendor/bin/typo3 assetic:watch
```

### assetic:livereload

Starts a [LiveReload](http://livereload.com/) compatible server that watches for file changes in `EXT:client` and re-compiles the assets if needed. The necessary JavaScript can be loaded be enabling `addJavascript` in Site Settings.

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

Cundd Assetic provides a JavaScript tool that checks the CSS and JavaScript assets on the page for changes and automatically reloads them. For more information and usage visit [/Resources/Public/JavaScript](/Resources/Public/JavaScript).

## Sponsored by

[![](https://www.iresults.li/github-logo.png)](http://www.iresults.li)
