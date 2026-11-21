<?php
namespace EarthAsylumConsulting\Plugin;

/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * load administrator traits
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2026 EarthAsylum Consulting <www.earthasylum.com>
 * @version		26.1120.1
 */

if (! defined( 'ABSPATH' )) exit;

if ( ! \EarthAsylumConsulting\is_admin_request() )
{
	trait eacSoftwareRegistry_administration {}
}
else require "eacSoftwareRegistry.admin.php";

