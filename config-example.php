<?php
return [
	'gh_access_token'          => '',
	'temp_file_dir'            => '/tmp/deploys/',
	'deploy_wp_content_dir'    => '/var/www/mywpapp.com/htdocs/wp-content/',
	'slack_webhook'            => 'https://hooks.slack.com/services/CUSTOM-ENDPOINT-FROM-SLACK',
	//'slack_channels'          => [ '#app-releases', '@adminuser' ],
	'slack_channel'            => '#app-releases',
	'slack_deploy_icon_emoji'  => ':unicorn:',
	'new_relic_webhook'        => 'https://api.newrelic.com/v2/applications/8888888/deployments.json',
	'new_relic_application_id' => '88888888',
	'new_relic_account_id'     => '7777777',
	'new_relic_api_key'        => '',
	'allowed_deployments'      => [
		//'wordpress-phoenix/wordpress-rest-cache'   => 'plugin',
		'repo-owner/repo-name'   => 'plugin/theme/something-else',
	],
];
