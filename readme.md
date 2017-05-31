# Github PHP Deploybot

## Alpha release

The master branch is still in alpha release. The code is untested since we did a lot of refactoring while moving it from its current private repository to a public generic release.

## Table of Contents:
- [Installation](#installation)
- [Usage](#usage)

# Installation

## Composer style (recommended)

Via composer command line like
```
composer require sethcarstens/github-php-deploybot && composer install
```

## Manual Installation
1. Download the most updated copy of this repository from `https://api.github.com/repos/scarstens/github-php-deploybot/zipball`
2. Extract the zip file, and copy the src PHP file into your plugin project.
3. Use SSI (Server Side Includes) to include the file into your plugin.

# Usage
See the [webhook-endpoint-example.php](webhook-endpoint-example.php) file for detailed information.

## Simple example

custom-endpoint.php
```php
<?php
$debug_level = 2;
include_once __DIR__ . 'vendor/autoload.php';
$config_file = __DIR__ . 'config.php';
$deploy_bot = new Github_Php_Deploybot\Deployment( json_decode( fgets( STDIN ) ), $debug_level, $config_file );
$deploy_bot->deploy_repo();

```
