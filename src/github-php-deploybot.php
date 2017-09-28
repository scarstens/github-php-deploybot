<?php
/**
 * Github PHP Deploybot is a helper library for automating deployment associated with Github webhooks.
 *
 * @author  Seth Carstens
 * @package github-php-deploybot
 * @version 0.1.1
 * @license GPL 2.0 - please retain comments that express original build of this file by the author.
 */

namespace Github_Php_Deploybot;

use stdClass;

/**
 * Class Deployment
 */
class Deployment {

	/**
	 * @var string
	 */
	public $action;

	/**
	 * @var array
	 */
	public $allowed_deployments = [];

	/**
	 * @var array
	 * See: /config-example.php
	 */
	public $config;

	/**
	 * @var array
	 */
	public $config_file;

	/**
	 * @var int
	 */
	public $debug_level;

	/**
	 * @var string
	 */
	public $dir;

	/**
	 * @var mixed
	 */
	public $deploy_pkg;

	/**
	 * @var stdClass
	 */
	public $notify_data;

	/**
	 * @var mixed
	 */
	public $payload;

	/**
	 * @var string
	 */
	public $release_notes;

	/**
	 * @var string
	 */
	public $release_title;

	/**
	 * Repo is the representation of the git repository from github that is being deployed.
	 * @var mixed
	 */
	public $repo;

	/**
	 * @var array
	 */
	public $security_file;

	/**
	 * @var string
	 */
	public $tag;

	/**
	 * @var string
	 */
	public $type;

	/**
	 * Constructor only sets up the deployment object and calculates its params.
	 *
	 * @param           $payload
	 * @param int       $debug_level
	 * @param           $dir
	 * @param bool|null $config_file
	 * @param bool      $security_file
	 */
	public function __construct( $payload, $debug_level = 1, $dir, $config_file = false, $security_file = false ) {
		$this->debug_level = $debug_level;
		// Exit early if payload isn't valid
		if ( ! isset( $payload ) || ! is_object( $payload ) ) {
			$this->cli_out( 'Payload is invalid, discarding hook action.' );
			$this->exit_deployment( 0 );
		}
		$this->dir         = $dir;
		$this->config_file = $config_file;
		// Setup current object properties
		$this->set_config_from_files( $this->dir, $this->config_file );
		$this->set_deploy_type( $payload );
		$this->allowed_deployments = $this->config['allowed_deployments'];

		//If release exists in object, setup a production release
		if ( isset( $payload->release->tag_name ) ) {
			if ( ! empty( $this->allowed_deployments ) ) {
				$this->action = $payload->action;
				// $this->
				$this->repo = $payload->repository->full_name;
				$this->release_title = static::cli_safe_string( $payload->release->name );
				$this->tag           = static::cli_safe_string( $payload->release->tag_name );
				$this->release_notes = static::cli_safe_string( $payload->release->body );
				// deploy_pkg->env
				$this->deploy_pkg       = new stdClass();
				$this->deploy_pkg->zip  = $payload->release->zipball_url;
				$this->deploy_pkg->name = static::cli_safe_string( $payload->repository->name );
				//extra for notifications
				$this->notify_data               = new stdClass();
				$this->notify_data->author_title = $payload->organization->login . ' - ' . $payload->sender->login;
				$this->notify_data->author_link  = $payload->sender->html_url;
				$this->notify_data->author_icon  = $payload->sender->avatar_url;
				$this->notify_data->tag_link     = $payload->release->html_url;

				$this->cli_out( 'Found Deployment for ' . $this->repo . ' ' . $this->tag );
				if ( $debug_level >= 4 ) {
					$this->payload = $payload;
				}
			}
		}

	}

	/**
	 * Used to pull in the configuration settings
	 *
	 * @param string      $dir
	 * @param bool|string $config_file
	 * @param bool|string $security_file
	 */
	public function set_config_from_files( $dir, $config_file = false, $security_file = false ) {
		// Setup config file or default
		if ( ! $config_file ) {
			$config_file = $dir . '/deploy/config.php';
		}
		// Setup security file or default
		if ( ! $security_file ) {
			$security_file = $dir . '/deploy/config-security.php';
		}

		// Exit early if config file is not defined or does not exist
		if ( ! file_exists( $config_file ) ) {
			$this->cli_out( 'Required config file location is not defined, exiting.' );
			$this->exit_deployment( 1 );
		} elseif ( ! file_exists( $security_file ) ) {
			$this->cli_out( 'Required security file location is not defined, exiting.' );
			$this->exit_deployment( 1 );
		} else {
			$this->config = require_once( $config_file );
			$security     = require_once( $security_file );
			$this->config = array_merge( $this->config, $security );
		}
	}

	/**
	 * Uses logic to determine the release type
	 *
	 * @param $payload
	 * TODO: rename this to release_type
	 */
	public function set_deploy_type( $payload ) {
		if ( isset( $payload->release ) ) {
			$this->type = 'release';
		} else {
			$this->type = 'other';
			static::cli_out( 'Hook event is invalid, discarding hook action.' );
			$this->exit_deployment( 0 );
		}
	}

	public static function configure_deployment( $deploy_pkg, $config ) {
		$configured_deploy_pkg = $deploy_pkg->config;
		// Pull config parameters in as defaults
		foreach ( $config as $key => $config_value ) {
			$deploy_pkg->$key = $config_value;
		}
		unset( $deploy_pkg->allowed_deployments );
		unset( $deploy_pkg->config );
		// Backwards compatible with string value in config
		if ( ! is_array( $configured_deploy_pkg ) && is_string( $configured_deploy_pkg ) ) {
			$deploy_pkg->type = $configured_deploy_pkg;
		} elseif ( is_array( $configured_deploy_pkg ) ) {
			echo 'Dynamic repo config detected...' . PHP_EOL;
			// Override any config params with repo specific params
			foreach ( $configured_deploy_pkg as $key => $override ) {
				$deploy_pkg->$key = $override;
			}
		} else {
			print_r( 'Deployment config not configured properly. L' . __LINE__ );
			exit;
		}

		return $deploy_pkg;
	}

	/**
	 * Executes the actual deployment automation
	 */
	public function deploy_repo() {
		// Confirm current repo is valid
		if ( isset( $this->allowed_deployments[ $this->repo ] ) ) {
			$this->deploy_pkg->config = $this->allowed_deployments[ $this->repo ];
		} else {
			$this->cli_out( 'Group/Repo not in allowed deployments, exiting.' );
			$this->exit_deployment( 1 );
		}

		$this->deploy_pkg = $this::configure_deployment( $this->deploy_pkg, $this->config );

		if ( ! isset( $this->config['gh_access_token'] ) ) {
			$this->cli_out( 'Gitub Access Token missing from config file, exiting.' );
			$this->exit_deployment( 1 );
		}

		// Setup deployment parameters.
		$access_token = $this->deploy_pkg->gh_access_token;
		$deploy_pkg   = $this->deploy_pkg;
		$temp_folder  = $this->deploy_pkg->temp_file_dir;
		$temp_file    = $temp_folder . $deploy_pkg->name . '.zip';

		if ( is_array( $this->deploy_pkg->deploy_wp_content_dir ) ) {
			$deploy_to = current( $this->deploy_pkg->deploy_wp_content_dir ) . $this->deploy_pkg->type . 's';
		} else {
			$deploy_to = $this->deploy_pkg->deploy_wp_content_dir . $this->deploy_pkg->type . 's';
		}

		$zip_folder_search  = str_ireplace( '/', '-', $this->repo ) . '*';
		$zip_folder_desired = $temp_folder . $deploy_pkg->name;

		// Execute deployment commands.
		$code = $this->cli_cmd( 'mkdir -p ' . $this->config['temp_file_dir'] );
		$this->cli_out( "curl -H \"Authorization: token HIDDEN\" -L $deploy_pkg->zip > $temp_file" );
		passthru( "curl -s -H \"Authorization: token $access_token\" -L $deploy_pkg->zip > $temp_file", $code );
		$this->cli_cmd( "unzip -qo $temp_file -d $temp_folder; rm $temp_file;" );
		$this->cli_cmd( "find $temp_folder -maxdepth 1 -type d -name $zip_folder_search -exec mv {} $zip_folder_desired \\;" );
		$this->cli_cmd( "find $temp_folder -type d -name \* -exec chmod 775 {} \;" );
		// TODO: mkdir -pre-ver
		$this->cli_cmd( 'mkdir -p ' . $deploy_to . "-prev-ver/$deploy_pkg->name/ " );
		// first backup the original plugin (old version)
		$this->cli_cmd( 'rm -rf ' . $deploy_to . "-prev-ver/$deploy_pkg->name/ " );
		$this->cli_cmd( 'mv ' . $deploy_to . "/$deploy_pkg->name/ " . $deploy_to . "-prev-ver/$deploy_pkg->name/ " );
		// deploy new plugin version by moving from temp to plugins/themes directory
		$this->cli_cmd( 'mv ' . $temp_folder . "/$deploy_pkg->name/ " . $deploy_to . "/$deploy_pkg->name/ " );
		// TODO: Build check to make sure it downloaded the file.

		//TODO: maybe wp cache flush

		if ( 0 == $code ) {
			$this->after_deploy_notifications();
		}
		$this->exit_deployment( $code );
	}

	/**
	 * Initializes all notifications
	 * TODO: needs a configuration setting (on off switch) for each notification type
	 */
	public function after_deploy_notifications() {
		$this->cli_out( 'Sending Deployment to New Relic' );
		$this->new_relic_notification();
		$this->cli_out( 'Sending Release notes to Slack for ' . $this->release_title );
		if ( is_string( $this->deploy_pkg->slack_channels ) ) {
			$this->slack_notification( $this->deploy_pkg->slack_channels );
		} elseif ( is_array( $this->deploy_pkg->slack_channels ) ) {
			foreach ( $this->deploy_pkg->slack_channels as $channel ) {
				$this->slack_notification( $channel );
			}
		}
	}

	/**
	 * Create a new relic deployment event on new relic
	 * TODO: needs to bail if it does not have required information
	 */
	public function new_relic_notification() {
		$data = [
			"deployment" => [
				"revision"    => $this->repo . ' v' . $this->tag,
				"changelog"   => $this->notify_data->tag_link,
				"description" => $this->release_notes,
				"user"        => $this->notify_data->author_link,
			],

		];
		// Setup curl cli command data
		$payload = print_r( json_encode( $data ), true );
//		$endpoint = $this->config['new_relic_webhook'];
		$endpoint = 'https://api.newrelic.com/v2/applications/' . $this->deploy_pkg->new_relic_application_id . '/deployments.json';
		$api_key  = $this->deploy_pkg->new_relic_api_key;
		// Send notification to slack
		sleep( 1 );
		$this->cli_cmd( "curl -s -X POST '$endpoint' -H 'X-Api-Key:$api_key' -i -H 'Content-Type: application/json' -d '$payload' ;", 2 );
		sleep( 2 );
		// Add spacer line after curl outputs "ok"
		$this->cli_out( '' );
	}

	/**
	 * Sends a slack notification based on the config variables
	 * TODO: bail out if required data not configured.
	 *
	 * @param $channel
	 */
	public function slack_notification( $channel ) {
		$data = [
			'channel'     => $channel,
			'username'    => "GiddyUp",
			'icon_emoji'  => $this->deploy_pkg->slack_deploy_icon_emoji,
			'link_names'  => 1,
			'attachments' => [
				[
					'fallback'    => 'Released ' . $this->repo . ' version ' . $this->tag,
					'color'       => '#36a64f',
					'author_name' => $this->notify_data->author_title,
					'author_link' => $this->notify_data->author_link,
					'author_icon' => $this->notify_data->author_icon,
					'title'       => 'Released ' . $this->repo . ' version ' . $this->tag,
					'title_link'  => $this->notify_data->tag_link,
					// 		// 'pretext'  => "",
					'text'        => $this->release_notes,
					// 		// 'image_url'   => "http://my-website.com/path/to/image.jpg",
					// 		// 'thumb_url'   => "http://example.com/path/to/thumb.png",
					'footer'      => 'Pagely Production Deployment',
					'footer_icon' => 'https://s3-us-west-2.amazonaws.com/slack-files2/avatars/2016-03-15/26963954738_9e0d7b2047b49f4121c9_68.png',
					'ts'          => time(),
				],
				[ "text" => 'https://rpm.newrelic.com/accounts/' . $this->deploy_pkg->new_relic_account_id . '/applications/' . $this->deploy_pkg->new_relic_application_id ],
			],
		];
		// Setup curl cli command data
		$payload  = print_r( json_encode( $data ), true );
		$endpoint = $this->deploy_pkg->slack_webhook;
		// Send notification to slack
		sleep( 1 );
		$this->cli_cmd( "curl -s -X POST -H 'Content-type: application/json' --data '$payload' $endpoint ;", 2 );
		sleep( 2 );
		// Add spacer line after curl outputs "ok"
		$this->cli_out( '' );
	}

	/**
	 * Handle the final steps of any deployment, regardless of how far the deployment bot made it.
	 *
	 * @param $code
	 */
	public function exit_deployment( $code ) {
		if ( $this->debug_level > 2 ) {
			$this->config['gh_access_token'] = "HIDDEN";
			$this->cli_out( $this );
		}
		if ( 0 != $code ) {
			$this->cli_out( 'Deploy failed' );
			$this->cli_out( '------------------------------------------------------------------------' . PHP_EOL . PHP_EOL );
			exit( 1 );
		} else {
			$this->cli_out( 'Deploy Success ->' );
			$this->cli_out( '------------------------------------------------------------------------' . PHP_EOL . PHP_EOL );
			exit( 0 );
		}
	}

	/**
	 * Utility function to standardize printing messages or logging
	 *
	 * @param     $message
	 * @param int $message_level
	 */
	public function cli_out( $message, $message_level = 1 ) {
		if ( $message_level > $this->debug_level ) {
			echo 'blocked_cli_out_not_enough_debug_level' . PHP_EOL;

			return;
		}
		if ( ! is_string( $message ) ) {
			print_r( $message );
			print( PHP_EOL );
		} else {
			echo( $message );
			print( PHP_EOL );
		}
	}

	/**
	 * Utility function that standardizes the way CLI commands are passed through PHP
	 *
	 * @param     $command
	 * @param int $debug_level_out
	 *
	 * @return int
	 */
	public function cli_cmd( $command, $debug_level_out = 1 ) {
		$code = 1;
		$this->cli_out( $command, $debug_level_out );

		if ( defined( 'TEST_MODE' ) ) {
			$this->cli_out( 'TEST MODE, skipping command executions.' );
		} else {
			passthru( $command, $code );
		}

		return $code;
	}

	/**
	 * Utility function that ensures string is safe to print in CLI
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public static function cli_safe_string( $value ) {
		$new_value = str_replace( "'", '\u0027', $value );

		return $new_value;
	}

}
