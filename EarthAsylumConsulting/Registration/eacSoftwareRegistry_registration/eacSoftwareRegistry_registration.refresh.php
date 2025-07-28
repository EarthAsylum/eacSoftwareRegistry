<?php
/**
 * EarthAsylum Consulting {eac} Software Registration - software registration command line refresh
 *
 * scheduled to run via crontab :
 * /path/to/php/php /path/to/html/classfolder/eacSoftwareRegistry_registration.refresh.php {registration_key}
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version		25.0725.1
 */

include "eacSoftwareRegistry_registration.includes.php";

class eacSoftwareRegistryRegistrationRefresh implements \EarthAsylumConsulting\Interfaces\eacSoftwareRegistry_registration
{
	use \EarthAsylumConsulting\Traits\eacSoftwareRegistry_registration_wordpress;

	/**
	 * constructor method
	 *
	 * @param string current registration key
	 * @return 	void
	 */
	public function __construct(string $registrationKey = null)
	{
		return $this->refreshRegistration($registrationKey);
	}
}
return new eacSoftwareRegistryRegistrationRefresh($argv[0] ?: null);
