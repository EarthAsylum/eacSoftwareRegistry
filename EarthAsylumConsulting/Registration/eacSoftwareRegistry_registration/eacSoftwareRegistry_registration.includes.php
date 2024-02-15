<?php
/**
 * EarthAsylum Consulting {eac} Software Registration - software registration includes
 *
 * includes the interfaces and traits used by the software registration API
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2021 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version		1.x
 */

/*
 *	class <yourclassname> [extends something] implements \EarthAsylumConsulting\eacSoftwareRegistry_registration_interface
 *	{
 *		use \EarthAsylumConsulting\Traits\eacSoftwareRegistry_registration_wordpress;
 *				- OR -
 *		use \EarthAsylumConsulting\Traits\eacSoftwareRegistry_registration_filebased;
 *		...
 *	}
 */

/*
 * include interface...
 */
	require "eacSoftwareRegistry_registration.interface.php";

/*
 * include traits ...
 */
	require "eacSoftwareRegistry_registration.interface.trait.php";

/*
 *	require "eacSoftwareRegistry_registration.wordpress.trait.php";
 *				- OR -
 *	require "eacSoftwareRegistry_registration.filebased.trait.php";
 */
 	require "eacSoftwareRegistry_registration.wordpress.trait.php";
