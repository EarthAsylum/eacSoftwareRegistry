<?php
namespace EarthAsylumConsulting\Extensions;

/**
 * software registration extension - {eac}Doojigger for WordPress
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2022 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version		1.x
 * @see 		https://eacDoojigger.earthasylum.com/phpdoc/
 * @uses 		\EarthAsylumConsulting\Traits\swRegistrationUI;
 */

include "eacSoftwareRegistry_registration/eacSoftwareRegistry_registration.includes.php";

class eacSoftwareRegistry_registration extends \EarthAsylumConsulting\abstract_extension
	implements \EarthAsylumConsulting\Interfaces\eacSoftwareRegistry_registration
{
	use \EarthAsylumConsulting\Traits\swRegistrationUI;
	use \EarthAsylumConsulting\Traits\eacSoftwareRegistry_registration_wordpress;
//	use \EarthAsylumConsulting\Traits\eacSoftwareRegistry_registration_filebased;

	/**
	 * @var string extension version
	 */
	const VERSION	= '25.0419.1';

	/**
	 * @var ALIAS constant ($this->Registration->...)
	 */
	const ALIAS		= 'Registration';

	/**
	 * @var string|array|bool to set (or disable) default group display/switch
	 * 		false 		disable the 'Enabled'' option for this group
	 * 		string 		the label for the 'Enabled' option
	 * 		array 		override options for the 'Enabled' option (label,help,title,info, etc.)
	 */
	const ENABLE_OPTION	= false;


	/**
	 * constructor method
	 *
	 * @param 	object	$plugin main plugin object
	 * @return 	void
	 */
	public function __construct($plugin)
	{
		parent::__construct($plugin, self::ALLOW_ALL);

		if ($this->is_admin())
		{
			// load UI (last) from swRegistrationUI trait
			$this->add_action( 'options_settings_page', [$this,'swRegistrationUI'], PHP_INT_MAX );
		}

		// allow external extensions if license is L3 (standard) or better
		$this->add_filter( 'allow_external_extensions', function()
			{
				return $this->isRegistryvalue('license', 'L3', 'ge');
			}
		);

		$this->add_filter('api_create_registration',	array($this, 'api_create_registration'), 10, 2);
	}


	/**
	 * Called after instantiating, loading extensions and initializing
	 *
	 * @return	void
	 */
	public function addActionsAndFilters(): void
	{
		parent::addActionsAndFilters();

		// from registration_wordpress trait
		if (method_exists($this, 'addSoftwareRegistryHooks'))
		{
			$this->addSoftwareRegistryHooks();
		}
		// from swRegistrationUI trait (backend)
		if (method_exists($this, 'swRegistrationActionsAndFilters'))
		{
			$this->swRegistrationActionsAndFilters();
		}
	}


	/**
	 * api_create_registration handler
	 * Check registration limits before allowing new registration
	 *
	 */
	public function api_create_registration(array $postValues, array $requestParams)
	{
		global $wp, $wpdb;

		$post_limit = intval($this->isRegistryValue( 'count' ));

		// unlimited
		if (empty($post_limit)) return $postValues;

		$sql =
			"SELECT count(*) FROM ".$wpdb->posts." AS posts" .
			" WHERE posts.post_type = '".$this->plugin::CUSTOM_POST_TYPE."'" .
			" AND posts.post_status = 'publish'";

		$post_count = intval($wpdb->get_var( $sql ));

		// within limit
		if ($post_count < $post_limit) return $postValues;

		// limit exceeded
		return new \wp_error('license_count_exceeded',__('license count exceeded','eacSoftwareRegistry'));
	}


	/**
	 * destructor method
	 *
	 */
	public function __destruct()
	{
		/* make sure we're not checking the registration during a registration refresh */
		// if (!defined('REST_REQUEST') && !defined('XMLRPC_REQUEST'))
		// {
		// 	/* if necessary, set HOME and/or TMP/TMPDIR/TEMP directories */
		// 	// putenv('HOME={your home directory}');   // where the registration key is stored, otherwise use $_SERVER['DOCUMENT_ROOT']
		// 	// putenv('TMP={your temp directory}');    // where the registration data is stored, otherwise use sys_get_temp_dir()
		// 	$this->checkRegistryRefreshEvent();
		// }
	}


	/*
	 *
	 * interface implementation through swRegistrationUI & softwareregistry traits
	 *
	 */
}
/**
 * return a new instance of this class
 */
return new eacSoftwareRegistry_registration($this);
?>
