<?php
namespace EarthAsylumConsulting\Extensions;

/**
 * EarthAsylum Consulting {eac} Software Registration Distribution SDK
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2021 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 */

class SoftwareRegistry_distribution extends \EarthAsylumConsulting\abstract_extension
{
	/**
	 * @var string extension version
	 */
	const VERSION	= '23.0419.1';


	/**
	 * constructor method
	 *
	 * @param 	object	$plugin main plugin object
	 * @return 	void
	 */
	public function __construct($plugin)
	{
		parent::__construct($plugin, self::ALLOW_ADMIN|self::ONLY_ADMIN);

		/* Register this extension with [group name, tab name] and settings array */
		$this->registerExtension( false );

		$this->registerExtensionOptions( [ $this->className, 'distribution' ],
			[
				'_api_display'	=> array(
									'type'		=> 	'display',
									'label'		=> 	'API Keys',
									'default'	=>	'Your API keys are unique to your registration server and must be used when creating, updating, or verifying a registration. '.
													'Once generated (and in use), they must not be changed.',
									'info'		=>	'See: <a href="https://swregistry.earthasylum.com/software-registration-server/#api-details" target="_blank">{eac}SoftwareRegistry API</a>'
								),
				'registrar_create_key'	=> array(
									'type'		=> 	'disabled',
									'label'		=> 	'Registration Creation Key',
									'default'	=>	hash('md5', uniqid(), false),
									'info'		=> 	'Required API key when creating a new registration through your server\'s API.'
								),
				'registrar_update_key'	=> array(
									'type'		=> 	'disabled',
									'label'		=> 	'Registration Update Key',
									'default'	=>	hash('md5', uniqid(), false),
									'info'		=> 	'Required API key when updating an existing registration through your server\'s API.'
								),
				'registrar_read_key'	=> array(
									'type'		=> 	'disabled',
									'label'		=> 	'Registration Read Key',
									'default'	=>	hash('md5', uniqid(), false),
									'info'		=> 	'Required API key when reading/verifying an existing registration through your server\'s API.'
								),
				'_registrarUrls' 		=> array(
									'type'		=> 'disabled',
									'label'		=> 'API Endpoint',
									'default'	=>	home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION),
									'info'		=>	'Your server\'s API endpoint URL',
									'help'		=>	'<details><summary>[info]</summary>'.
													'<table>'.
													'<tr><td>Create&nbsp;New<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/create").'</code></td></tr>'.
													'<tr><td>Activate&nbsp;Existing<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/activate").'</code></td></tr>'.
													'<tr><td>Deactivate&nbsp;Existing<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/deactivate").'</code></td></tr>'.
													'<tr><td>Verify&nbsp;Current<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/verify").'</code></td></tr>'.
													'<tr><td>Refresh&nbsp;Current<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/refresh").'</code></td></tr>'.
													'<tr><td>Revise&nbsp;Current<br/>&nbsp;Registration</td><td><code>'.
														home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/revise").'</code></td></tr>'.
													'</table></details>',
								),
			]
		);
	}


	/**
	 * Add filters and actions - called from main plugin
	 *
	 * @return	void
	 */
	public function addActionsAndFilters()
	{
		if ($this->plugin->isSettingsPage('distribution'))
		{
			\add_action('admin_print_styles', 	function(){
				echo "<style type='text/css'>#option_submit {display: none;}</style>\n";
			}, 99 );
		}
	}
}
/**
 * return a new instance of this class
 */
return new SoftwareRegistry_distribution($this);
?>
