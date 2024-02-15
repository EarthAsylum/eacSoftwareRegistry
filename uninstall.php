<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * Plugin uninstaller
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2021 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 */


namespace EarthAsylumConsulting\uninstall;

defined( 'WP_UNINSTALL_PLUGIN' ) or exit;

class eacSoftwareRegistry
{
	use \EarthAsylumConsulting\Traits\plugin_uninstall;
}
eacSoftwareRegistry::uninstall();
