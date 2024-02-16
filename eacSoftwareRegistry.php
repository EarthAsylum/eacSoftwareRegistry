<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * Plugin Loader
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 * @uses		EarthAsylumConsulting\Traits\plugin_loader
 *
 * @wordpress-plugin
 * Plugin Name:			{eac}SoftwareRegistry
 * Description:			Software Registration Server - A feature-rich and easily customized software registration and licensing server for WordPress.
 * Version:				1.3.2
 * Requires at least:	5.5.0
 * Tested up to: 		6.4
 * Requires PHP:		7.2
 * Plugin URI:			https://swregistry.earthasylum.com/
 * Update URI: 			https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry.json
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 * License: 			EarthAsylum Consulting Proprietary License - {eac}PLv1
 * License URI:			https://swregistry.earthasylum.com/end-user-license-agreement/
 * Text Domain:			eacSoftwareRegistry
 * Domain Path:			/languages
 * Network: 			false
 */


namespace EarthAsylumConsulting
{
	if (!defined('EAC_DOOJIGGER_VERSION'))
	{
		\add_action( 'all_admin_notices', function()
			{
				echo '<div class="notice notice-error is-dismissible">'.
					 '<h4>{eac}SoftwareRegistry requires installation & activation of <em>{eac}Doojigger</em>.</h4>'.
					 '</div>';
			}
		);
		return;
	}


	/**
	 * loader/initialization class
	 */
	class eacSoftwareRegistry
	{
		use \EarthAsylumConsulting\Traits\plugin_loader;
		use \EarthAsylumConsulting\Traits\plugin_environment;

		/*
		 * @var array $plugin_detail
		 * 	'PluginFile' 	- the file path to this file (__FILE__)
		 * 	'NameSpace' 	- the root namespace of our plugin class (__NAMESPACE__)
		 * 	'PluginClass' 	- the full classname of our plugin (to instantiate)
		 */
		protected static $plugin_detail =
			[
				'PluginFile'		=> __FILE__,
				'NameSpace'			=> __NAMESPACE__,
				'PluginClass'		=> __NAMESPACE__.'\\Plugin\\eacSoftwareRegistry',
				'RequiresWP'		=> '5.5',			// WordPress
				'RequiresPHP'		=> '7.2',			// PHP
				'RequiresEAC'		=> '2.3',			// eacDoojigger
				'NetworkActivate'	=>	false,			// require (or forbid) network activation
				'AutoUpdate'		=> 'self',			// automatic update 'self' or 'wp'
			];
	} // eacSoftwareRegistry
} // namespace


namespace // global scope
{
	defined( 'ABSPATH' ) or exit;

	/**
	 * Run the plugin loader - only for php files
	 */
 	\EarthAsylumConsulting\eacSoftwareRegistry::loadPlugin(true);

	/**
	 * Load registration extension
	 */
	add_filter( 'eacSoftwareRegistry_required_extensions', function($extensionDirectories)
		{
			$extensionDirectories[ plugin_basename( __DIR__.'/Registration' ) ] = [plugin_dir_path( __FILE__ ).'EarthAsylumConsulting/Registration'];
			return $extensionDirectories;
		}
	);
}
