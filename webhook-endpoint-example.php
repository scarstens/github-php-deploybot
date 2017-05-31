<?php
// debug level determines how much output to echo during execution
$debug_level = 2;

// You can manually include the class library file or use composer autoloader
//include_once __DIR__ . 'vendor/sethcarstens/github-php-deploybot/src/github-php-deploybot.php';
include_once __DIR__ . 'vendor/autoload.php';

// This assumes you place the config file in your root directory, but easily changed as needed
$config_file = __DIR__ . 'config.php';

// currently script assumes that anything you echo is logged into a file
echo( ':::Github Release Event Hooked on ' . date( "Y-m-d H:i:s" ) . ':::' . PHP_EOL );
$deploy_bot = new Github_Php_Deploybot\Deployment( json_decode( fgets( STDIN ) ), $debug_level, $config_file );
$deploy_bot->deploy_repo();
