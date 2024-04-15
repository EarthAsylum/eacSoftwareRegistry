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
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		24.0414.1
 */

if ( ! \EarthAsylumConsulting\is_admin_request() )
{
	trait eacSoftwareRegistry_administration {}
}
else require "eacSoftwareRegistry.admin.php";

