<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * administrator help
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		24.0414.1
 */

defined( 'ABSPATH' ) or exit;

ob_start();
?>
	{eac}SoftwareRegistry is a WordPress software licensing and registration server with an easy to use API
	for creating, activating, deactivating, and verifying software registration keys.

	Registration keys may be created and updated through the administrator pages in WordPress,
	but the system is far more complete when your software package implements the {eac}SoftwareRegistry API to manage the registration.

	The built-in Application Program Interface (API) is a relatively simple method for your software package to communicate with your software registration server.
<?php
$content = ob_get_clean();

$this->addPluginHelpTab($tab,$content,'About');

ob_start();
?>
	Should you need help using or customizing {eac}SoftwareRegistry, please review this help content and read our online
	<a href='https://swregistry.earthasylum.com/software-registration-server/' target='_blank'>documentation</a>. If necessary,
	email us with your questions, problems, or bug reports at <a href='mailto:support@earthasylum.com'>support@earthasylum.com</a>.

	We recommend checking your <a href='site-health.php'>Site Health</a> report occasionally, especially when problems arise.
<?php
$content = ob_get_clean();

$this->addPluginHelpTab($tab,$content,['Getting Help','open']);

$this->addPluginSidebarText('<h4>For more information:</h4>');

$this->addPluginSidebarLink(
	"<span class='dashicons dashicons-info-outline eac-logo-green'></span>About This Plugin",
	"/wp-admin/plugin-install.php?tab=plugin-information&plugin=eacSoftwareRegistry&TB_iframe=true&width=600&height=550",
	$this->getPluginValue('Title')." Plugin Information Page"
);
$this->addPluginSidebarLink(
	"<span class='dashicons dashicons-rest-api eac-logo-green'></span>API Details",
	$this->getDocumentationURL(true,'/software-registration-server/#api-details'),
	"Application Program Interface"
);
