<?php
namespace EarthAsylumConsulting\Extensions;

/**
 * Extension: admin_tools - tools/utility functions
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 American Telecast Products
 * @version		1.x
 */


if (class_exists('\EarthAsylumConsulting\Extensions\admin_tools_extension') )
{
	/**
	 * return a new instance of this class
	 */
	return new \EarthAsylumConsulting\Extensions\admin_tools_extension($this);
}
?>
