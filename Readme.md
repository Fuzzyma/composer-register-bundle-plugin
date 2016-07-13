#ComposerRegisterBundlePlugin

A composer plugin to register your symfony packages to the AppKernel.

## Installation

```bash
composer require fuzzyma/composer-register-bundle-plugin
```

## Usage

To register a bundle simply execute `composer register packageName` e.g.

```bash
composer require fuzzyma/contao-database-commands-bundle
composer register fuzzyma/contao-database-commands-bundle
```

You can also pass the fully qualified namespace instead but make sure to pass the `ns` option in this case:

```bash
composer register Fuzzyma/Contao/DatabaseCommandsBundle/ContaoDatabaseCommandsBundle --ns
// or
composer register Fuzzyma\\Contao\\DatabaseCommandsBundle\\ContaoDatabaseCommandsBundle --ns
```

The plugin comes with a method to register bundles on events e.g. the post-package-install event.

Just add the following to your composer.json to immediately register a bundle after installation:

```json
"post-package-install": [
    "Fuzzyma\\Composer\\RegisterBundlePlugin\\Commands\\RegisterCommand::registerBundle"
]
```