<?php
return [
	'temp_file_dir'            => '/tmp/deploys/',
	'deploy_wp_content_dir'    => '/var/www/mywpapp.com/htdocs/wp-content/',
	'slack_channels'           => [ '#app-releases', '@adminuser' ],
	'slack_deploy_icon_emoji'  => ':unicorn:',
	'new_relic_webhook'        => 'https://api.newrelic.com/v2/applications/8888888/deployments.json',
	'new_relic_application_id' => '88888888',
	'new_relic_account_id'     => '7777777',
	'allowed_deployments'      => [
		//'wordpress-phoenix/wordpress-rest-cache'   => 'plugin',
		'repo-owner/repo-name'   => 'plugin/theme/something-else',
		'repo-owner/repo-name-2' => [
			'type'                     => 'plugin',
			'deploy_wp_content_dir'    => '/var/www/mywpapp2.com/htdocs/wp-content/',
			'slack_deploy_icon_emoji'  => ':github:',
			'slack_channels'           => [ '#repo-2-releases', '@myname' ],
			'new_relic_account_id'     => '22222222',
			'new_relic_application_id' => '1111111',
		],
	],
];
