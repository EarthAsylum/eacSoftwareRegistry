<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * Primary plugin file for {eac}SoftwareRegistry
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 */

namespace EarthAsylumConsulting\Plugin;
include('eacSoftwareRegistry.api.php');

class eacSoftwareRegistry extends \EarthAsylumConsulting\abstract_context
{
	/**
	 * @trait methods for html fields used externally
	 */
	use \EarthAsylumConsulting\Traits\html_input_fields;

	/**
	 * @trait methods for api
	 */
	use \EarthAsylumConsulting\Plugin\eacSoftwareRegistry_api;

	/**
	 * @trait methods for date/time
	 */
	use \EarthAsylumConsulting\Traits\datetime;

	/**
	 * @trait methods for contextual help tabs
	 */
 	use \EarthAsylumConsulting\Traits\plugin_help;

	/**
	 * @var string our custom post type
	 */
	const CUSTOM_POST_TYPE 		= 'softwareregistry';

	/**
	 * @var string api version endpoint
	 */
	const API_VERSION 			= '/v1';

	/**
	 * @var array status description => value
	 */
	public $REGISTRY_STATUS_CODES = [
			'Pending'			=> 'pending',
			'Trial'				=> 'trial',			// is valid (published)
			'Active'			=> 'active',		// is valid (published)
			'Inactive'			=> 'inactive',
			'Expired'			=> 'expired',
			'Terminated'		=> 'terminated'
	];

	/**
	 * @var array initial terms
	 */
	public $REGISTRY_INITIAL_TERMS = [
			'7 days',
			'14 days',
			'30 days',
			'60 days',
			'90 days',
			'6 months',
			'1 year'
	];

	/**
	 * @var array full terms
	 */
	public $REGISTRY_FULL_TERMS = [
			'30 days',
			'60 days',
			'90 days',
			'6 months',
			'1 year',
			'3 years',
			'5 years',
			'10 years',
			'100 years'
	];

	public $REGISTRY_REFRESH_INTERVALS = [
			'Hourly'			=> HOUR_IN_SECONDS,
			'Twice Daily'		=> DAY_IN_SECONDS / 2,
			'Daily'				=> DAY_IN_SECONDS,
			'Twice Weekly'		=> WEEK_IN_SECONDS / 2,
			'Weekly'			=> WEEK_IN_SECONDS,
			'Twice Monthly'		=> MONTH_IN_SECONDS / 2,
			'Monthly'			=> MONTH_IN_SECONDS,
	];

	/**
	 * @var array software license level description => value
	 */
	public $REGISTRY_LICENSE_LEVEL = [
			'Lite'				=> 'L1',
			'Basic'				=> 'L2',
			'Standard'			=> 'L3',
			'Professional'		=> 'L4',
			'Enterprise'		=> 'L5',
			'Developer'			=> 'LD',
	];

	/**
	 * @var array status to post status
	 */
	public $POST_STATUS_CODES = [
			'future'			=> 'future',
			'pending'			=> 'draft',
			'trial'				=> 'publish',		// is valid (published)
			'active'			=> 'publish',		// is valid (published)
			'inactive'			=> 'private',
			'expired'			=> 'private',
			'terminated'		=> 'trash'
	];

	/**
	 * @var array registration field defaults or required
	 */
	const REGISTRY_DEFAULTS = [
			'registry_key'			=> true,	// required
			'registry_product'		=> true,	// required
			'registry_title'		=> '',
			'registry_description'	=> '',
			'registry_version'		=> '',
			'registry_license'		=> '',
			'registry_count'		=> '',
			'registry_status'		=> '',
			'registry_effective'	=> '',
			'registry_expires'		=> '',
			'registry_name'			=> true,	// required
			'registry_email'		=> true,	// required
			'registry_company'		=> '',
			'registry_address'		=> '',
			'registry_phone'		=> '',
			'registry_variations'	=> array(),	// force array
			'registry_options'		=> array(),	// force array
			'registry_domains'		=> array(),	// force array
			'registry_sites'		=> array(),	// force array
			// - saved as post meta and returned in api, sent in email
			//'registry_refreshed'	=> 'dd-Mmm-yyyy (tz),
			// - not saved as post meta but returned in api, sent in email
			//'registry_valid'		=> bool,
	];

	/**
	 * @var array optional registration field defaults
	 */
	const REGISTRY_OPTIONAL = [
			'registry_transid'		=> '',
			'registry_paydue'		=> '',
			'registry_payamount'	=> '',
			'registry_paydate'		=> '',
			'registry_payid'		=> '',
			'registry_nextpay'		=> '',
			'registry_timezone'		=> '',
	];

	/**
	 * @var object server timezone
	 */
	private $currentTimezone;

	/**
	 * @var object client timezone
	 */
	private $clientTimezone;

	/**
	 * @var string flag set to trigger email to client on update
	 */
	private $email_to_client = false;

	/**
	 * @var prior values when updating post
	 */
	private $prior_meta;


	/**
	 * constructor method
	 *
	 * @access public
	 * @param array header passed from loader script
	 * @return void
	 */
	public function __construct(array $header)
	{
		parent::__construct($header);

		$this->logAlways('version '.$this->getVersion().' '.wp_date('Y-m-d H:i:s',filemtime(__FILE__)),__CLASS__);

		// register the custom  post type
		add_action( 'init', 							array($this, 'register_custom_post_type') );

		// exclude our custom post comments from coment queries
		add_filter( 'comments_clauses', 				array($this, 'exclude_post_comments' ));
		add_filter( 'comment_feed_where', 				array($this, 'exclude_feed_comments' ));

		if ($this->is_admin())
		{
			$this->defaultTabs = ['general','tools'];

			register_activation_hook($header['PluginFile'],		'flush_rewrite_rules' );
			register_deactivation_hook($header['PluginFile'],	'flush_rewrite_rules' );

			// add settings menu to custom post type menu
			add_action( 'admin_menu',					array($this, 'admin_add_settings_menu') );

			// used by optionExport in standard_options trait
			add_action( "admin_post_{$this->pluginName}_settings_export",
														array($this, 'stdOptions_post_optionExport') );
			// When this plugin is updated
			$this->add_action( 'version_updated', 		array($this, 'admin_plugin_updated'), 10, 2 );

			// Register plugin options
			$this->add_action( 'options_settings_page', array($this, 'admin_options_settings'),1 );
			// Add contextual help
			$this->add_action( 'options_settings_help', array($this, 'admin_options_help'), 10, 0 );
		}

		$this->api_construct($header);
	}


	/**
	 * Called after instantiating and loading extensions
	 *
	 * @return	void
	 */
	public function initialize(): void
	{
		$this->currentTimezone = new \DateTimeZone( $this->get_option('registrar_timezone','UTC') );
		parent::initialize();

		/**
		 * filter {classname}_settings_{option}
		 * filter array used in option selection
		 * @param	array option array
		 * @return	array
		 */
		$this->REGISTRY_STATUS_CODES 		= array_flip($this->apply_filters(
													'settings_status_codes',
													array_flip($this->REGISTRY_STATUS_CODES)
												));

		$this->POST_STATUS_CODES 			= $this->apply_filters(
													'settings_post_status',
													$this->POST_STATUS_CODES
												);

		$this->REGISTRY_INITIAL_TERMS 		= $this->apply_filters(
													'settings_initial_terms',
													$this->REGISTRY_INITIAL_TERMS
												);

		$this->REGISTRY_FULL_TERMS 			= $this->apply_filters(
													'settings_full_terms',
													$this->REGISTRY_FULL_TERMS
												);

		$this->REGISTRY_REFRESH_INTERVALS	= $this->apply_filters(
													'settings_refresh_intervals',
													$this->REGISTRY_REFRESH_INTERVALS
												);

		$this->REGISTRY_LICENSE_LEVEL 		= array_flip($this->apply_filters(
													'settings_license_levels',
													array_flip($this->REGISTRY_LICENSE_LEVEL)
												));
	}


	/**
	 * Called after instantiating, loading extensions and initializing
	 *
	 * @return	void
	 */
	public function addShortCodes(): void
	{
		parent::addShortCodes();
	}


	/**
	 * Called after instantiating, loading extensions and initializing
	 *
	 * @return	void
	 */
	public function addActionsAndFilters(): void
	{
		parent::addActionsAndFilters();

		// when updating custom post via edit, api, or  webhook
		add_action( 'pre_post_update', 					array($this, 'pre_update_custom_post'), 10,2 );

		add_action( 'save_post_'.self::CUSTOM_POST_TYPE,array($this, 'update_custom_post'), 10,3 );

		/*
		 * Only on admin/dashboard
		 */
		if (!is_admin()) return;

		// when on our custom post page list or add/edit...
		add_action( 'current_screen', function($currentScreen)
		{
			if (!$this->isSettingsPage() && strpos($currentScreen->id, self::CUSTOM_POST_TYPE) !== false)
			{
				$this->admin_options_help('Software Registry');
				$this->plugin_help_render($currentScreen);

				// add css & js
				add_action( 'admin_enqueue_scripts', 	array($this, 'add_inline_scripts') );

				// define columns for custom post type 'All Registrations' list
				add_filter( 'manage_'.self::CUSTOM_POST_TYPE.'_posts_columns',
														array($this, 'custom_post_columns'), 10, 2);

				add_action( 'manage_'.self::CUSTOM_POST_TYPE.'_posts_custom_column',
														array($this, 'custom_post_column_value'), 99, 2);

				add_filter( 'manage_edit-'.self::CUSTOM_POST_TYPE.'_sortable_columns',
														array($this, 'custom_post_sortable_columns') );

				add_action( 'pre_get_posts', 			array($this, 'custom_post_sorting_columns') );

				add_filter( 'post_row_actions', 		array($this, 'custom_post_column_actions'), 10, 2 );

				// filter post status in list display
				add_filter( 'display_post_states', 		function($post_states, $post)
					{
						if ( $post->post_type == self::CUSTOM_POST_TYPE )
						{
							if (array_key_exists('protected', $post_states)) {
								unset($post_states['protected']); // = '<span class="dashicons dashicons-lock"></span>';
							}
						}
						return $post_states;
					}, 10, 2);

				// when updating custom post in 'Add/Edit Registration'
				add_filter( 'default_title', 			array($this, 'set_post_registry_key'), 10, 2);

				// set order/location of meta-boxes in 'Add/Edit Registration'
				add_filter( 'get_user_option_meta-box-order_'.self::CUSTOM_POST_TYPE,
														array($this,'order_custom_post_metabox'));
			}
		});

		// add documentation link on plugins page
		add_filter( (is_network_admin() ? 'network_admin_' : '').'plugin_action_links_' . $this->PLUGIN_SLUG,
			function($pluginLinks, $pluginFile, $pluginData)
			{
				return array_merge(
					['documentation'=>$this->getDocumentationLink($pluginData)],
					$pluginLinks
				);
			},20,3
		);

		//so we can upload our software packages
		if (current_user_can('manage_options'))
		{
			add_filter('upload_mimes', function($types)
				{
					$types['zip'] = 'application/zip';
					$types['gz'] = 'application/x-gzip';
					return $types;
				}
			);
		}
	}


	/**
	 * register options on options_settings_page
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options_settings()
	{
		// format the h1 title, add documentation link buttons

		$this->add_filter("options_form_h1_html", function($h1)
			{
				return 	"<div id='settings_banner'>".
						$this->formatPluginHelp($h1).
						"<div id='settings_info'>".

						$this->getDocumentationLink(true,'/software-registration-server',"<span class='dashicons dashicons-editor-help button eac-green'></span>").
						"&nbsp;&nbsp;&nbsp;".

						"<a href='".network_admin_url('/plugin-install.php?s=earthasylum&tab=search&type=term')."' title='Plugins from EarthAsylum Consulting'>".
						"<span class='dashicons dashicons-admin-plugins button eac-green'></span></a>".
						"&nbsp;&nbsp;&nbsp;".

						"<a href='https://earthasylum.com' title='About EarthAsylum Consulting'>".
						"<span class='dashicons dashicons-admin-site-alt3 button eac-green'></span></a>".

						"</div></div>";
			}
		);

		if (is_multisite())
		{
			$this->registerNetworkOptions('software_registration_server',
				[
					'_display'				=> array(
						'type'		=> 	'display',
						'label'		=> 	'Network/MultiSite',
						'default'	=>	'This plugin is not intended to be activated or used by the network administrator.',
					),
				]
			);
		}

		$this->registerPluginOptions('registrar_contact',
			[
				'registrar_email'			=> array(
						'type'		=> 	'email',
						'label'		=> 	'Registrar Admin Email',
						'default'	=>	get_bloginfo('admin_email'),
						'info'		=> 	'Send administrator notifications of registration updates to this address.'
				),
				'registrar_name'			=> array(
						'type'		=> 	'text',
						'label'		=> 	'Registrar Name',
						'default'	=>	get_bloginfo('name'),
						'info'		=>	'When sending client email, send from this name.',
				),
				'registrar_phone'			=> array(
						'type'		=> 	'tel',
						'label'		=> 	'Registrar Telephone',
						'info'		=> 	'Include telephone in client notifications.',
				),
				'registrar_contact'			=> array(
						'type'		=> 	'email',
						'label'		=> 	'Registrar Support Email',
						'default'	=>	wp_get_current_user()->user_email,
						'info'		=> 	'Include support email in client notifications -and- send client email from this address.'
				),
				'registrar_web'				=> array(
						'type'		=> 	'url',
						'label'		=> 	'Registrar Web Address',
						'default'	=>	home_url(),
						'info'		=> 	'Include web address in client notifications.',
				),
			]
		);

		$this->registerPluginOptions('registration_defaults',
			[
				'registrar_timezone'		=> array(
						'type'		=> 	'select',
						'label'		=> 	'Registry Server Timezone',
						'options'	=>	$this->apply_filters( 'settings_timezones',
											array_unique(['UTC',wp_timezone_string()])
										),
						'default'	=>	'UTC',
						'info'		=> 	'The timezone used for registration times.'
				),
				'registrar_status'			=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default Status',
						'options'	=>	$this->REGISTRY_STATUS_CODES,
						'default'	=>	'pending',
						'info'		=> 	'The default status to assign to newly created registrations.'
				),
				'registrar_term'			=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default Initial Term',
						'options'	=>	$this->REGISTRY_INITIAL_TERMS,
						'default'	=>	'30 days',
						'info'		=> 	"The initial term when creating a new registration (pending or trial)."
				),
				'registrar_fullterm'		=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default Full Term',
						'options'	=>	$this->REGISTRY_FULL_TERMS,
						'default'	=>	'1 year',
						'info'		=> 	"The full term when activating a registration."
				),
				'registrar_license'			=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default License',
						'options'	=>	$this->REGISTRY_LICENSE_LEVEL,
						'default'	=>	'L3',
						'info'		=> 	'The default license level to assign to newly created registrations.'
				),
			]
		);

		$this->registerPluginOptions('registration_options',
			[
				'registrar_cache_time'		=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default Cache Time',
						'options'	=>	[
											'1 Day'		=> DAY_IN_SECONDS,
											'2 Days'	=> 2 * DAY_IN_SECONDS,
											'1 Week'	=> WEEK_IN_SECONDS,
											'2 Weeks'	=> 2 * WEEK_IN_SECONDS,
											'1 Month'	=> MONTH_IN_SECONDS,
										],
						'default'	=>	WEEK_IN_SECONDS,
						'info'		=> 	"Length of time that the client registrant <em>should</em> store/cache the registration."
				),
				'registrar_pending_time'	=> array(
						'type'		=> 	'select',
						'label'		=> 	'Pending Refresh Time',
						'options'	=>	$this->REGISTRY_REFRESH_INTERVALS,
						'default'	=>	$this->REGISTRY_REFRESH_INTERVALS['Hourly'],
						'info'		=> 	"How often the client registrant <em>should</em> refresh (re-validate) the registration when status is 'pending'.".
										"<br/><small>Must be less than cache time</small>"
				),
				'registrar_refresh_time'	=> array(
						'type'		=> 	'select',
						'label'		=> 	'Default Refresh Time',
						'options'	=>	$this->REGISTRY_REFRESH_INTERVALS,
						'default'	=>	$this->REGISTRY_REFRESH_INTERVALS['Daily'],
						'info'		=> 	"How often the client registrant <em>should</em> refresh (re-validate) the registration when status is 'active'.".
										"<br/><small>Must be less than cache time</small>"
				),
				'registrar_options'			=> array(
						'type'		=> 	'checkbox',
						'label'		=> 	'Allow API To ...',
						'options'	=>	[
											['Set registration key'	=> 'allow_set_key'],
											['Set initial status'	=> 'allow_set_status'],
											['Set effective date'	=> 'allow_set_effective'],
											['Set expiration date'	=> 'allow_set_expiration'],
											['Update on Activation'	=> 'allow_activation_update'],
										],
						'info'		=> 	'Allow API requests to pass and set values normally set by the registration server.',
						'style'		=>	'display: block;',
				),
				'registrar_endpoints'		=> array(
						'type'		=> 	'checkbox',
						'label'		=> 	'Allow API Endpoints',
						'options'	=>	[
											['Create New Registration'			=> 'create'],
											['Activate Existing Registration'	=> 'activate'],
											['Deactivate Existing Registration'	=> 'deactivate'],
											['Verify Current Registration'		=> 'verify'],
											['Refresh Current Registration'		=> 'refresh'],
											['Revise Current Registration'		=> 'revise'],
										],
						'default'	=> 	['create','activate','deactivate','verify','refresh','revise'],
						'info'		=> 	'Enable end-points to allow access via the Application Program Interface (API).',
						'style'		=>	'display: block;',
				),
			]
		);

		// from standard_options trait - now in tools extension
		//$this->registerPluginOptions('plugin_options',$this->standard_options(['backupOptions','restoreOptions','optionExport','optionImport']));
	}


	/**
	 * Add help tab on admin page
	 *
	 * @return	void
	 */
 	public function admin_options_help($tab='General')
	{
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
			"<span class='dashicons dashicons-info-outline eac-green'></span>About This Plugin",
			"/wp-admin/plugin-install.php?tab=plugin-information&plugin=eacSoftwareRegistry&TB_iframe=true&width=600&height=550",
			$this->getPluginValue('Title')." Plugin Information Page"
		);
		$this->addPluginSidebarLink(
			"<span class='dashicons dashicons-rest-api eac-green'></span>API Details",
			$this->getDocumentationURL(true,'/software-registration-server/#api-details'),
			"Application Program Interface"
		);
	}


	/**
	 * Additional formatting of the help content.
	 * overload plugin_help trait method
	 *
	 * @param string $content tab content
	 * @return string
	 */
	public function formatPluginHelp(string $content): string
	{
		return preg_replace(
					"/{eac}(\w+)/",
					"<span class='eac-orange'>{<span class='eac-green'>eac</span>}$1</span>",
					$content
				);
	}


	/**
	 * custom style and script
	 *
	 * @return void
	 */
	public function add_inline_scripts()
	{
		$this->plugin->html_input_style();

		ob_start();
		?>
			*::placeholder {text-align: right;}
			#minor-publishing {display: none;}
			#local-storage-notice {display: none !important;}
			.settings-grid-item {padding: .5em 0;}
			.column-title {width: 23%;}
			.column-comments {width: 3em !important;}
			.column-registry_email {width: 20%;}
			.column-registry_product {width: 15%;}
			.column-registry_transid {width: 4em;}
			.column-registry_status {width: 6.5em;}
		<?php
		$style = ob_get_clean();
		$styleId = self::CUSTOM_POST_TYPE.'-style';
		wp_register_style( $styleId, false );
		wp_enqueue_style( $styleId );
		wp_add_inline_style( $styleId, $style );

		ob_start();
		?>
			document.addEventListener('DOMContentLoaded', function()
				{
					document.getElementById('title').setAttribute('disabled','disabled');
				}
			);
		<?php
		$script = ob_get_clean();
		$scriptId = self::CUSTOM_POST_TYPE.'-script';
		wp_register_script( $scriptId, false );
		wp_enqueue_script( $scriptId );
		wp_add_inline_script( $scriptId, $this->minifyString($script) );
	}


	/*
	 *
	 * Software Registration Methods
	 *
	 */


	/**
	 * get registration meta array
	 *
	 * @param array $post custom post
	 * @return array
	 */
	private function getRegistrationMeta(\WP_Post $post)
	{
		$meta = array('registry_key'=>'');
		// maintain standard key order
		foreach(array_keys(self::REGISTRY_DEFAULTS) as $name) $meta[$name] = null;

		$postmeta = (isset($post->meta_input)) ? $post->meta_input : $this->getPostMetaValues($post->ID);

		foreach( $postmeta as $key => $value)
		{
			$key = ltrim($key,'_');
			if (substr($key,0,9) == 'registry_') {
				$meta[$key] = $value;
			}
		}

		return (array)json_decode(json_encode( array_filter($meta) ));
	}


	/**
	 * get post meta single values
	 *
	 * @param string $post_id the post id
	 * @return array meta values
	 */
	private function getPostMetaValues($post_id)
	{
		$meta = (array)get_post_meta($post_id);
		foreach ($meta as $key => &$fieldValue)
		{
			$fieldValue = maybe_unserialize( end($fieldValue) );
		}
		if (empty($meta['_registry_title'])) $meta['_registry_title'] = $meta['_registry_product'] ?? '';
		return $meta;
	}


	/*
	 *
	 * When a registration post is updated - via API or admin
	 *
	 */


	/**
	 * create registry key
	 *
	 * @return string
	 */
	private function newRegistryKey()
	{
		/**
		 * filter {classname}_new_registry_key
		 * create a new registration key
		 * @param	string	default key (UUID)
		 * @return	string	registry key
		 */
		return $this->apply_filters('new_registry_key', $this->createUniqueId());
	}


	/**
	 * default_title filter
	 *
	 * @param string $post_title
	 * @param WP_Post $post
	 * @return string
	 */
	public function set_post_registry_key($post_title, $post)
	{
		if ( $post->post_type == self::CUSTOM_POST_TYPE && empty($post_title))
		{
			$post_title = $this->newRegistryKey();
		}
		return $post_title;
	}


	/**
	 * pre_post_update action fires before post updated (within wp_update_post)
	 *
	 * @param int $post_ID Post ID.
	 * @param array $post Post data to be updated.
	 * @return void
	 */
	public function pre_update_custom_post($post_id, $post)
	{
		if ($post['post_type'] == self::CUSTOM_POST_TYPE)
		{
			// get the prior data before updating
			$this->prior_meta = $this->getPostMetaValues($post_id);
		}
	}


	/**
	 * save_post_{post_type} action fires after post created or updated (within wp_update_post)
	 *
	 * @param int $post_ID Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool $isUpdate Whether this is an existing post being updated.
	 * @return void
	 */
	public function update_custom_post($post_id, $post, $isUpdate)
	{
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (!isset($post->meta_input))
		{
			$post->meta_input = $this->getPostMetaValues($post_id);
		}

		$request = array();

		// updating custom fields through wp admin quick links
		if (isset($_GET['action']) && isset($_GET['_wpnonce']))
		{
			if ($_GET['action'] == 'trash')
			{
				update_post_meta($post_id,"_registry_status",'terminated');
				update_post_meta($post_id,'_prior_status',$post->meta_input['_registry_status']);
				$post->post_status = $this->POST_STATUS_CODES['terminated'];
			}
			else if ($_GET['action'] == 'untrash')
			{
				$status = get_post_meta($post_id,'_prior_status',true) ?: $this->get_option('registrar_status');
				update_post_meta($post_id,"_registry_status",$status);
				delete_post_meta($post_id,'_prior_status');
				$post->post_status = $this->POST_STATUS_CODES[$status];
			}
		}

		// updating custom fields through wp admin edit form
		if ( !empty($_POST) && isset($_POST['_wpnonce']) )
		{
			if (isset($_POST['_email_to_client'])) // 'email to client' button clicked
			{
				$this->emailToClient(true);
			}

			foreach ($_POST as $key => $newValue)
			{
				$key = ltrim($key,'_');
				if (substr($key,0,9) == 'registry_')
				{
					if (isset(self::REGISTRY_DEFAULTS[$key]) && is_array(self::REGISTRY_DEFAULTS[$key])) {
						$newValue = $this->explode_with_keys("\n",trim($newValue));
						$request[ $key ] = (array)json_decode(json_encode($newValue));
					} else {
						$request[ $key ] = $newValue;
					}
				}
			}

			$defaults = $this->apply_filters('registry_api_defaults',array_merge(self::REGISTRY_DEFAULTS, [
				'registry_key'			=> ($isUpdate) ? $post->meta_input['_registry_key'] : $post->post_title,
				'registry_status'		=> ($isUpdate) ? $post->meta_input['_registry_status'] : '',
				'registry_effective'	=> ($isUpdate) ? $post->meta_input['_registry_effective'] : '',
				'registry_expires'		=> ($isUpdate) ? $post->meta_input['_registry_expires'] : '',
			]),$request,'update');

			$request = $this->sanitizeRequest($request,$defaults);
			$this->logDebug($request,__METHOD__);

			if (!isset($request['registry_key']))
			{
				$request['registry_key'] = $defaults['registry_key'];
			}

			// check title
			if (empty($request['registry_title']))
			{
				$request['registry_title'] = $defaults['registry_title'] ?: $defaults['registry_product'];
			}

			// check description
			if (empty($request['registry_description']))
			{
				$request['registry_description'] = $defaults['registry_description'] ?: $request['registry_title'];
			}

			if (isset($request['registry_email']))
			{
				$request['registry_email'] = strtolower($request['registry_email']);
			}

			if (! in_array($request['registry_status'],['expired','terminated']))
			{
				if ($request['registry_status'] != $defaults['registry_status'] && $request['registry_status'] != 'inactive')
				{
					// set effective date
					if ($request['registry_effective'] == $defaults['registry_effective']) {
						unset($request['registry_effective']);
						$request['registry_effective'] 	= $this->setEffectiveDate($request,$this->getDateTimeInZone()->format('Y-m-d'));
					}
					// set expiration date
					if ($request['registry_expires'] == $defaults['registry_expires']) {
						unset($request['registry_expires']);
						$request['registry_expires'] 	= $this->setExpirationDate($request,false);
					}
				}
			}

			// check/set status - expired, terminated, future
			//if ($this->getDateTimeInZone($request['registry_expires'].' 23:59:59') < $this->getDateTimeInZone('now','-30 days'))
			//{
			//	$request['registry_status'] = 'terminated';	// don't do this from admin, we can't get out of trash
			//}
			//else
			if ($this->getDateTimeInZone($request['registry_expires'].' 23:59:59') < $this->getDateTimeInZone())
			{
				$request['registry_status'] = 'expired';
			}
			else if ($this->getDateTimeInZone($request['registry_effective'].' 00:00:00') > $this->getDateTimeInZone('00:00:00 today'))
			{
				$request['registry_status'] = 'future';
			}

			$post->post_status = $this->POST_STATUS_CODES[$request['registry_status']] ?? 'publish';
/*
			if ($post->post_status == 'trash')
			{
				$request['registry_status'] = 'terminated';
			}
			else
			{
				$post->post_status = $this->POST_STATUS_CODES[$request['registry_status']] ?? 'publish';
			}
*/

			/**
			 * filter {classname}_validate_registration
			 * filter registration validation
			 * @param	array registration parameters
			 * @param	object WP_Post
			 * @return	array registration parameters
			 */
			$valid = $this->apply_filters('validate_registration', $request, $post, 'update');
			if (!is_wp_error($valid) && is_array($valid))
			{
				$request = $valid;
			}

			// updated the meta fields
			foreach ($request as $key => $newValue)
			{
				update_post_meta($post_id,"_{$key}",$newValue);
				$post->meta_input["_{$key}"] = $newValue;
			}
		}

		$postHasChanged = (!$this->prior_meta || $this->prior_meta != $post->meta_input);
		if ($postHasChanged) $this->logDebug([$this->prior_meta,$post->meta_input],__METHOD__);

		$request = $this->getRegistrationMeta($post);

		// keep post fields in sync with meta fields
		if ($registryKey = get_post_meta($post_id,'_registry_key',true))
		{
			// incase filters invoke another post update
			\remove_action( 'save_post_'.self::CUSTOM_POST_TYPE, array($this,'update_custom_post'), 10,3 );
			$post->post_content = $this->getPostHtml($request,'post');
			$updateArray = [
			//	'ID'			=> $post_id,
				'post_status'	=> $post->post_status,
				'post_title'	=> $registryKey,
				'post_name'		=> $registryKey,
				'post_content'	=> $post->post_content,
				'post_excerpt'	=> $this->getPostExcerpt($request),
				'post_password' => $request['registry_email'],
				'guid'			=> site_url(self::CUSTOM_POST_TYPE.'/'.$registryKey),
			];

			/**
			 * filter {classname}_update_registration_post
			 * updating the registration post
			 * @param	array	post fields to update
			 * @return	array	post fields to update
			 */
			$updateArray = $this->apply_filters('update_registration_post', $updateArray, $request, ($this->api_action ?: 'update'));
			$this->wpdb->update($this->wpdb->posts, $updateArray, ['ID' =>  $post_id]);
			\add_action( 'save_post_'.self::CUSTOM_POST_TYPE, array($this,'update_custom_post'), 10,3 );
		}

		$this->emailToClient( $this->emailToClient() && ($postHasChanged || isset($_POST['_email_to_client'])) );

		$emailAction = $this->api_action ?: ($isUpdate ? 'update' : 'create');
		if ($this->emailToClient())
		{
			$this->clientNotificationEmail($post, $request, $emailAction);
		}

		// updating from api request
		if (!empty($this->api_action))
		{
			$now 		= $this->getDateTimeInZone();

			$source 	= (isset($_SERVER['HTTP_REFERER']))
						? "\n".__('Requested from','softwareregistry').': '.parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST)
						: "";

			$emailSent 	= ($this->emailToClient())
						? "\n".__('Email notification was sent to client','softwareregistry')
						: "";

			$context	= $this->getApiAction($emailAction);

			// add a note to the registration
			$this->add_registration_note($post,
				sprintf(
					__("Registration {$context} via {$this->api_source} at %s on %s.%s%s", 'softwareregistry'),
						$now->format($this->time_format), $now->format($this->date_format.' (T)'), $source, $emailSent
				)
			);

			// send an update notification (administrator/manager)
			$this->adminNotificationEmail($post, $request, $emailAction, $source, $emailSent);
		}
	}


	/**
	 * set post content html
	 *
	 * @param 	array	$registry registry_ values
	 * @param 	string	$context 'post' | 'api'
	 * @return 	string html content
	 */
	public function getPostHtml($registry, $context='post')
	{
		$translate = array(
			'registry_key'			=> __('Registration Key','eacSoftwareRegistry'),
			'registry_product'		=> __('Registered Product Id','eacSoftwareRegistry'),
			'registry_title'		=> __('Registered Product Name','eacSoftwareRegistry'),
			'registry_description'	=> __('Product Description','eacSoftwareRegistry'),
			'registry_version'		=> __('Product Version','eacSoftwareRegistry'),
			'registry_license'		=> __('Product License','eacSoftwareRegistry'),
			'registry_count'		=> __('License Count','eacSoftwareRegistry'),
			'registry_status'		=> __('Registration Status','eacSoftwareRegistry'),
			'registry_effective'	=> __('Effective Date','eacSoftwareRegistry'),
			'registry_expires'		=> __('Expiration Date','eacSoftwareRegistry'),
			'registry_name'			=> __('Registrant\'s Name','eacSoftwareRegistry'),
			'registry_email'		=> __('Registrant\'s Email','eacSoftwareRegistry'),
			'registry_company'		=> __('Registrant\'s Organization','eacSoftwareRegistry'),
			'registry_address'		=> __('Registrant\'s Address','eacSoftwareRegistry'),
			'registry_phone'		=> __('Registrant\'s Telephone','eacSoftwareRegistry'),
		//	'registry_variations'	=> __('Product Variation','eacSoftwareRegistry'),
		//	'registry_options'		=> __('Product Options','eacSoftwareRegistry'),
		//	'registry_domains'		=> __('Registered Domains','eacSoftwareRegistry'),
		//	'registry_sites'		=> __('Registered Sites','eacSoftwareRegistry'),
			'registry_paydue'		=> __('Payment Due','eacSoftwareRegistry'),
			'registry_payamount'	=> __('Payment Received','eacSoftwareRegistry'),
			'registry_paydate'		=> __('Payment Date','eacSoftwareRegistry'),
			'registry_nextpay'		=> __('Next Payment Date','eacSoftwareRegistry'),
			'registry_refreshed'	=> __('Last Refreshed','eacSoftwareRegistry'),
		//	'registry_valid'		=> __('Valid Registration','eacSoftwareRegistry'),
		);

		// don't send payment data via api
		if ($context == 'api')
		{
			unset(	$translate['registry_paydue'],
					$translate['registry_payamount'],
					$translate['registry_paydate'],
					$translate['registry_payid'],
					$translate['registry_nextpay'] );
		}

		$translate = $this->apply_filters('client_registry_translate', $translate, $registry);

		if ($registry['registry_title'] == $registry['registry_product']) {
			unset($translate['registry_title']);
		}
		if ($registry['registry_description'] == $registry['registry_title']) {
			unset($translate['registry_description']);
		}

		$this->setClientTimezone($registry);

		$html = "<table id='".self::CUSTOM_POST_TYPE."-table' style='border: none; text-align: left;'>";
		foreach($translate as $keyId => $keyName)
		{
			if (! isset($registry[$keyId])) continue;
			$value = $registry[$keyId];
			if (is_bool($value)) {
				$value = ($value) ? 'yes' : 'no';
			}
			if (empty($value)) continue;
			if (is_array($value)) {
				$value = implode(', ',$value);
			} else if (is_object($value)) {
				$value = $this->plugin->implode_with_keys(', ',(array)$value);
			} else {
				switch ($keyId) {
					case 'registry_effective':
						$value = $this->getDateTimeClientZone( $value.' 00:00:00' )->format('d-M-Y g:i a (T)');
						break;
					case 'registry_expires':
					case 'registry_paydate':
					case 'registry_nextpay':
						$value = $this->getDateTimeClientZone( $value.' 23:59:59' )->format('d-M-Y g:i a (T)');
						break;
					case 'registry_refreshed':
						$value = $this->getDateTimeClientZone( $value )->format('d-M-Y g:i a (T)');
						break;
					case 'registry_status':
						$value = array_search($value,$this->REGISTRY_STATUS_CODES) ?: $value;
						break;
					case 'registry_license':
						$value = array_search($value,$this->REGISTRY_LICENSE_LEVEL) ?: $value;
						break;
				}
			}
			$html .= "<tr><td>".str_replace(' ','&nbsp;',$keyName)."&nbsp;</td>";
			if ($this->prior_meta && array_key_exists("_{$keyId}", $this->prior_meta) && $this->prior_meta["_{$keyId}"] != $registry[$keyId]) {
				$value = "<em>{$value}</em>";
			}
			$html .= "<td>{$value}</td></tr>";
		}
		$html .= "</table>";

		$html = $this->apply_filters('client_registry_html', $html, $translate, $registry);

		return $html;
	}


	/**
	 * set post excerpt
	 *
	 * @param 	array	registry_ values
	 * @return 	string excerpt content
	 */
	public function getPostExcerpt($registry)
	{
		$name 		= ltrim( ($registry['registry_name'] ?? '')."\n" );
		$company 	= ltrim( ($registry['registry_company'] ?? '')."\n" );
		$email 		= ltrim( ($registry['registry_email'] ?? '')."\n" );
		$phone 		= $registry['registry_phone'] ?? '';

		return trim($name.$company.$email.$phone);
	}


	/**
	 * add registration note
	 *
	 * @param 	string	$note
	 * @return 	int comment id
	 */
	public function add_registration_note($post,$note)
	{
		return wp_insert_comment(
			[
				'comment_post_ID'      => $post->ID,
				'comment_author'       => $this->className,
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_content'      => $note,
				'comment_agent'        => $this->className,
				'comment_type'         => self::CUSTOM_POST_TYPE,
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			]
		);
	}


	/*
	 *
	 * Email Notifications
	 *
	 */


	/**
	 * replace shortcode-like values in messages
	 *
	 * @param 	string	$message	message html
	 * @param 	array	$registration	registration meta
	 * @param 	string 	$apiAction	One of 'create', 'activate', 'revise', 'deactivate', 'verify' or 'update' (non-api)
	 *  @param 	string	$default 	original/default message
	 * @return 	void
	 */
	private function clientMessageMerge($message, $registration, $apiAction=null, $default='')
	{
		if (empty($message)) return $message;

		$context 	= $this->getApiAction($apiAction);
		$registrar 	= $this->getRegistrarOptions('all',$registration);

		$replace = array_filter($registration,function($v,$k){return is_scalar($v);},ARRAY_FILTER_USE_BOTH);

		$message = str_replace(
			array_merge(
				array_map(function($k){return "[{$k}]";},array_keys($replace)),
				array_map(function($k){return "[{$k}]";},array_keys($registrar)),
				['[update_context]','[default_message]']
			),
			array_merge(
				array_values($replace),
				array_values($registrar),
				[$context,$default],
			),
			$message
		);
		return $message;
	}


	/**
	 * send client notification email
	 *
	 * @param 	object	WP_Post
	 * @param 	array	registration
	 * @param 	string	update or create
	 * @return 	void
	 */
	public function clientNotificationEmail($post, $meta, $context)
	{
		$registrar = $this->getRegistrarOptions('all',$meta);

		if ( ($email = $meta['registry_email']) && ($from = $registrar['registrar_contact']) )
		{
			$context = $this->getApiAction($context);

			$headers = $this->apply_filters('client_email_headers', [
				'from'			=> $registrar['registrar_name'].' <'.$from.'>',
				'to'			=> ($meta['registry_name']) ? $meta['registry_name'].' <'.$email.'>' : $email,
				'subject'		=> sprintf(__("Your %s registration has been {$context}",'eacSoftwareRegistry'),$meta['registry_title']),
				'Content-type'	=> 'text/html'
			], $meta, $post);
			if (!$headers) return;

			$style = $this->apply_filters('client_email_style', $this->getEmailStyle('client'), $meta, $post);
			if (empty($style)) return;

			if ($sName 	= $registrar['registrar_name']) {
				$eName	= str_replace(' ','%20',$sName);
			}
			if ($sEmail	= $registrar['registrar_contact']) {
				$sEmail = "<span class='icon'>email</span><a href='mailto:$eName%20<{$sEmail}>?subject=Registration:%20{$meta['registry_key']}'>{$sEmail}</a>";
			}
			if ($sPhone	= $registrar['registrar_phone']) {
				$sPhone = "<span class='icon'>phone</span><a href='tel:{$sPhone}'>$sPhone</a>";
			}
			if ($sWeb	= $registrar['registrar_web']) {
				$sWeb	= "<span class='icon'>link</span><a href='{$sWeb}'>{$sWeb}</a>";
			}

			$signature = nl2br(
				ltrim($sName."\n").
				ltrim($sEmail."\n").
				ltrim($sPhone."\n").
				ltrim($sWeb."\n")
			);

			// no 'client_email_message' unless manually entered
			$default =	$this->get_option('client_email_message',
				"<p>[registry_name],</p>\n".
				"<p>Your product registration for <var>[registry_title]</var> has been [update_context].<br>\n".
				"	Your registration key is: <code>[registry_key]</code>\n".
				"</p>\n"
			);

			/**
			 * filter {classname}_client_email_message
			 *
			 * @param	string message
			 * @param	array registration
			 * @param	object wp_post
			 */
			$message = $this->clientMessageMerge(
				$this->apply_filters('client_email_message', $default, $meta, $post),
				$meta,
				$context,
				$default
			);
			if (empty($message)) return;

			$notices = '';
			foreach  ($this->getRegistrationNotices($meta,$post) as $type=>$notice) {
				if  (!empty($notice)) {
					$notices  .= "<p class='notice notice-{$type}'>{$notice}</p>";
				}
			}

			$content =
				"<!DOCTYPE html>".
				"<head>".
				"<link rel='stylesheet' href='https://fonts.googleapis.com/icon?family=Material+Icons'>".
				"<style type='text/css' media='all'>{$style}</style>".
				"</head>".
				"<body marginwidth='0' topmargin='0' marginheight='0' class='".self::CUSTOM_POST_TYPE."-email'>".
				"<div class='container'>".
				"{$message}".
				"<address>{$signature}</address>".
				"</div>".
				"<hr>".
				"<p>Registration Details:</p>".
				"<div class='notices'>{$notices}</div>".
				"<div class='registry'>{$post->post_content}</div>";
				"</body>".
				"</html>";

			$_headers = [];
			foreach ($headers as $name=>$value) {
				if (!in_array($name,['to','subject'])) $_headers[] = "{$name}: {$value}";
			}
			wp_mail( $headers['to'], $headers['subject'], $content, $_headers );
		}
	}


	/**
	 * send admin notification email
	 *
	 * @param 	object	WP_Post
	 * @return 	void
	 */
	public function adminNotificationEmail($post, $meta, $context, $source, $emailSent)
	{
		$registrar = $this->getRegistrarOptions('all',$meta);

		if ($email = $registrar['registrar_email'])
		{
			$context	= $this->getApiAction($context);

			$headers 	= $this->apply_filters('admin_email_headers', [
				'from'			=> get_bloginfo('name').' <'.get_bloginfo('admin_email').'>',
				'to'			=> $registrar['registrar_name'].' <'.$email.'>',
				'subject'		=> '['.get_bloginfo('name')."] Registration {$context} on ".$_SERVER['HTTP_HOST'],
				'Content-type'	=> 'text/html'
			], $meta, $post);
			if (!$headers) return;

			$style = $this->apply_filters('admin_email_style', $this->getEmailStyle('admin'), $meta, $post);
			if (empty($style)) return;

			// no 'admin_email_message' unless manually entered
			$default =	$this->get_option('admin_email_message',
				"<p>To: Software Registrar,</p>".
				"<p>A product registration for <var>[registry_title]</var> has been [update_context].".
				"	The details of this registration are below.</p>"
			);

			/**
			 * filter {classname}admin_email_message
			 *
			 * @param	string message
			 * @param	array registration
			 * @param	object wp_post
			 */
			$message = $this->clientMessageMerge(
				$this->apply_filters('admin_email_message', $default, $meta, $post),
				$meta,
				$context,
				$default
			);
			if (!$message) return;

			$content =
				"<!DOCTYPE html>".
				"<head><style type='text/css' media='all'>{$style}</style></head>".
				"<body marginwidth='0' topmargin='0' marginheight='0' class='".self::CUSTOM_POST_TYPE."-email'>".
				"<div class='container'>".
				"{$message}".
				"</div>".
				"<hr>".
				"<p>Registration Details:</p>".
				"<div class='registry'>{$post->post_content}</div>".
				"<p>{$source} Via {$this->api_source}</p>";
				"<p>{$emailSent}</p>";
				"<footer style='padding-top: 5em;'>".
				"	<p><a href='".home_url()."'/>".$this->plugin->getPluginValue('Title')."</a><br/>".
				"	<a href='".$this->plugin->getPluginValue('AuthorURI')."'/>".$this->plugin->getPluginValue('Author')."</a></p>".
				"<footer>".
				"</body>".
				"</html>";

			$_headers = [];
			foreach ($headers as $name=>$value) {
				if (!in_array($name,['to','subject'])) $_headers[] = "{$name}: {$value}";
			}
			wp_mail( $headers['to'], $headers['subject'], $content, $_headers );
		}
	}


	/**
	 * notification email css
	 *
	 * @param string $context 'client' | 'admin'
	 * @return 	string
	 */
	public function getEmailStyle($context='client')
	{
		$bg        = get_option( 'woocommerce_email_background_color', 'var(--eac-email-bg)' );
		$body      = get_option( 'woocommerce_email_body_background_color', 'var(--eac-email-body)' );
		$base      = get_option( 'woocommerce_email_base_color', 'var(--eac-email-base)' );
		$text      = get_option( 'woocommerce_email_text_color', 'var(--eac-email-text)' );

		$style =
			"* {font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; color: $text;}".
			"body 	{width: 80%; max-width: 640px; margin: .5em auto; background-color: $bg; padding: 2em; line-height: 1.5;}".
			"code 	{color: $base; font-weight: 700; letter-spacing: 1px;}".
			"var 	{color: $base;}".
			".container {".
			"	background-color: $body; padding: .5em".
			"}".
			".registry {".
			"	background-color: $body;".
			"	margin: 1em auto; padding: 5px;".
			"	border-radius: 4px; border: 1px solid #ccc; border-left: 10px solid $base; border-right: 10px solid $base;".
			"}".
			".registry table, .registry td, .registry td em {".
			"	font-family: Consolas,Monaco,'Andale Mono','Ubuntu Mono',monospace; color: #3c3c3c;".
			"	border: none; text-align: left;".
			"}".
			".icon {font-family: 'Material Icons'; display: inline-block; vertical-align: middle; width: 3em;}".
			".notice {font-style: italic;}".
			"address, address a[href] {color: $base; font-weight: 400;}".
			"\n";

		// allow for customization from Appearance->Customize->Additional CSS
		$css = wp_get_custom_css();
		if ($css) $style .= $css;
		return $style;
	}


	/*
	 *
	 * Custom Post Type - 'softwareregistry'
	 *
	 */


	/**
	 * Creating custom post type for software registrations
	 */
	public function register_custom_post_type()
	{
	// Set UI labels for Custom Post Type
		$labels = array(
			'name'					=> _x( 'Software Registrations', 'Post Type General Name', 'eacSoftwareRegistry' ),
			'singular_name'			=> _x( 'Software Registration', 'Post Type Singular Name', 'eacSoftwareRegistry' ),
			'menu_name'				=> __( 'Software Registry', 'eacSoftwareRegistry' ),
			'all_items'				=> __( 'All Registrations', 'eacSoftwareRegistry' ),
			'view_item'				=> false, //__( 'View Registration', 'eacSoftwareRegistry' ),
			'add_new_item'			=> __( 'Add New Registration', 'eacSoftwareRegistry' ),
			'add_new'				=> __( 'Add New', 'eacSoftwareRegistry' ),
			'edit_item'				=> __( 'Edit Software Registration', 'eacSoftwareRegistry' ),
			'update_item'			=> __( 'Update Software Registration', 'eacSoftwareRegistry' ),
			'search_items'			=> __( 'Search Registrations', 'eacSoftwareRegistry' ),
			'not_found'				=> __( 'Registration Not Found', 'eacSoftwareRegistry' ),
			'not_found_in_trash'	=> __( 'Registration Not found in Trash', 'eacSoftwareRegistry' ),
		);

	// Set other options for Custom Post Type

		$args = array(
			'label'					=> __( 'Registration', 'eacSoftwareRegistry' ),
			'description'			=> __( 'Software Registration', 'eacSoftwareRegistry' ),
			'labels'				=> $labels,
			// Features this CPT supports in Post Editor
		//	'supports'				=> array( 'title', 'author', 'editor', 'revisions', 'custom-fields' ),
			'supports'				=> array( 'title', 'comments', 'custom-fields' ),
			'hierarchical'			=> false,
			'public'				=> false,
			'show_ui'				=> true,
			'show_in_menu'			=> true,
			'show_in_nav_menus'		=> false,
			'show_in_admin_bar'		=> false,
		//	'menu_position'			=> 80,
			'menu_icon'				=> 'dashicons-vault',
			'can_export'			=> true,
			'has_archive'			=> false,
			'exclude_from_search' 	=> true,
			'publicly_queryable'	=> false,		// enables archive list and permalink access
			'capability_type'		=> 'post',
			'show_in_rest'			=> false,
			'register_meta_box_cb'	=> array($this,'custom_post_metabox'),
		//	'query_var'				=> true,
		//	'rewrite' 				=> array( 'slug' => self::CUSTOM_POST_TYPE.'/%registry_key%' ),

		);

		// Registering the Custom Post Type
		\register_post_type( self::CUSTOM_POST_TYPE, $args );
	}


	/**
	 * Custom columns in the 'All Registrations' list
	 */
	public function custom_post_columns($columns,$cpt=null)
	{
		unset( $columns['author'] );
		unset( $columns['date'] );
		return array_merge($columns,
			[
				'title' 			=> __('Registry Key', 'eacSoftwareRegistry'),
			//	'date' 				=> __('Last Updated', 'eacSoftwareRegistry'),
				'registry_email' 	=> __('EMail', 'eacSoftwareRegistry'),
				'registry_product' 	=> __('Product', 'eacSoftwareRegistry'),
				'registry_transid' 	=> '<span class="dashicons dashicons-external" title="External Transaction"></span>',// __('Trans', 'eacSoftwareRegistry'),
			//	'registry_license' 	=> __('License', 'eacSoftwareRegistry'),
				'registry_status' 	=> __('Status', 'eacSoftwareRegistry'),
				'registry_effective'=> __('Effective', 'eacSoftwareRegistry'),
				'registry_expires' 	=> __('Expires', 'eacSoftwareRegistry'),
			],
		);
	}


	/**
	 * Custom actions in the 'All Registrations' list
	 *
	 * @param array	 	$actions 	array of actions.
	 * @param object 	$post 		post object.
	 * @return array
	 */
	public function custom_post_column_actions( $actions, $post )
	{
		if ( $post->post_type == self::CUSTOM_POST_TYPE )
		{
			if (array_key_exists('inline hide-if-no-js', $actions)) {
				unset($actions['inline hide-if-no-js']); // quick edit
			}
			if (array_key_exists('trash', $actions)) {
				$actions['trash'] = preg_replace('/>Trash</','>Deactivate<',$actions['trash']);
			}
			$actions = array_merge(['postid'=>'<span>ID: '.$post->ID.'</span>'],$actions);
		}
		return $actions;
	}


	/**
	 * Custom values in the 'All Registrations' list
	 */
	public function custom_post_column_value($column, $post_id)
	{
		switch ($column)
		{
			case 'registry_product':
				$value = get_post_meta($post_id, "_registry_license", true);
				echo '<span title="'.get_post_meta($post_id, "_registry_description", true).'">'.
					get_post_meta($post_id, "_{$column}", true).' ('.
					str_replace(' ','&nbsp;',(array_search($value,$this->REGISTRY_LICENSE_LEVEL) ?: $value)).')'.
					'</span>';
				break;
			case 'registry_email':
			//	$name 	= ltrim(get_post_meta($post_id, "_registry_name", true)."\n");
			//	$company = ltrim(get_post_meta($post_id, "_registry_company", true)."\n");
			//	$email 	= ltrim(get_post_meta($post_id, "_{$column}", true)."\n");
			//	$phone 	= get_post_meta($post_id, "_registry_phone", true);
				$excerpt = get_post($post_id)->post_excerpt;
				echo "<span title='{$excerpt}'>".get_post_meta($post_id, "_{$column}", true);
				$value = get_post_meta($post_id, "_registry_company", true) ?: get_post_meta($post_id, "_registry_name", true);
				echo ' ('.str_replace(' ','&nbsp;',$value).')</span>';
				break;
		//	case 'registry_license':
		//		$value = get_post_meta($post_id, "_{$column}", true);
		//		echo array_search($value,$this->REGISTRY_LICENSE_LEVEL) ?: $value;
		//		break;
			case 'registry_transid':
				$value = get_post_meta($post_id, "_{$column}", true);
		//		$short = ($value) ? preg_replace('/^(.*):(\d+)(:.*)?$/i','$2',$value) : '';
				$short  = current(explode('|',$value));
				echo "<span title='".str_replace("|","\n",$value)."'>{$short}</span>";
				break;
			case 'registry_status':
				$value = get_post_meta($post_id, "_{$column}", true);
				echo array_search($value,$this->REGISTRY_STATUS_CODES) ?: $value;
				break;
			case 'registry_effective':
				echo $this->getDateTimeInZone( get_post_meta($post_id, "_{$column}", true) )->format('d-M-Y');
				break;
			case 'registry_expires':
				$next = get_post_meta($post_id, "_registry_nextpay", true);
				$next = ($next) ? 'Next Payment: '.$this->getDateTimeInZone($next)->format('d-M-Y') : '';
				echo "<span title='{$next}'>".$this->getDateTimeInZone( get_post_meta($post_id, "_{$column}", true) )->format('d-M-Y')."</span>";
				break;
			default:
				echo get_post_meta($post_id, "_{$column}", true);
		}
	}


	/**
	 * Custom sortable columns in the 'All Registrations' list
	 */
	public function custom_post_sortable_columns($columns)
	{
		$columns['registry_email'] 		= 'registry_email';
		$columns['registry_product'] 	= 'registry_product';
	//	$columns['registry_license'] 	= 'registry_license';
		$columns['registry_transid'] 	= 'registry_transid';
		$columns['registry_status'] 	= 'registry_status';
		$columns['registry_effective'] 	= 'registry_effective';
		$columns['registry_expires'] 	= 'registry_expires';
		return $columns;
	}


	/**
	 * Custom sorting in the 'All Registrations' list
	 */
	public function custom_post_sorting_columns($query)
	{
		$orderby = $query->get( 'orderby' );

		switch($orderby)
		{
			case 'registry_email':
			case 'registry_product':
			case 'registry_transid':
			case 'registry_status':
				$query->set( 'meta_key', "_{$orderby}" );
				$query->set( 'meta_type', 'CHAR' );
				$query->set( 'orderby', 'meta_value' );
				break;
			case 'registry_effective':
			case 'registry_expires':
				$query->set( 'meta_key', "_{$orderby}" );
				$query->set( 'meta_type', 'DATE' );
				$query->set( 'orderby', 'meta_value' );
				break;
			default:
				break;
		}
	}


	/**
	 * Add custom meta box(es)
	 */
	public function custom_post_metabox()
	{
		add_meta_box(
			self::CUSTOM_POST_TYPE.'_fields',		// Unique ID
			esc_html__( 'Registration Details', 'eacSoftwareRegistry' ),    // Title
			[$this,'custom_post_metabox_fields'],	// Callback function
			self::CUSTOM_POST_TYPE, 				// Admin page (or post type)
			'normal',								// Context normal, side, advanced
			'high'									// Priority 'high', 'core', 'default', or 'low'
		);

		add_meta_box(
			self::CUSTOM_POST_TYPE.'_status',		// Unique ID
			esc_html__( 'Registration Status', 'eacSoftwareRegistry' ),    // Title
			[$this,'custom_post_metabox_status'],	// Callback function
			self::CUSTOM_POST_TYPE, 				// Admin page (or post type)
			self::CUSTOM_POST_TYPE.'-metabox',		// Context normal, side, advanced
			'high'									// Priority 'high', 'core', 'default', or 'low'
		);

		add_meta_box(
			self::CUSTOM_POST_TYPE.'_payment',		// Unique ID
			esc_html__( 'Registration Payment', 'eacSoftwareRegistry' ),    // Title
			[$this,'custom_post_metabox_payment'],	// Callback function
			self::CUSTOM_POST_TYPE, 				// Admin page (or post type)
			self::CUSTOM_POST_TYPE.'-metabox',		// Context normal, side, advanced
			'high'									// Priority 'high', 'core', 'default', or 'low'
		);

		remove_meta_box('commentsdiv',self::CUSTOM_POST_TYPE,'normal');
		add_meta_box(
			'commentsdiv',
			esc_html__( 'Registration Notes', 'eacSoftwareRegistry' ),
			'post_comment_meta_box',				// Callback function
			self::CUSTOM_POST_TYPE, 				// Admin page (or post type)
			'normal',								// Context normal, side, advanced
			'high'									// Priority 'high', 'core', 'default', or 'low'
		);

		remove_meta_box('commentstatusdiv',self::CUSTOM_POST_TYPE,'normal');
		remove_meta_box('slugdiv',self::CUSTOM_POST_TYPE,'normal');
	}


	/**
	 * Add custom meta box(es) status box
	 */
	public function custom_post_metabox_status($post)
	{
		$fields = array(
			'registry_status'		=> [
				'type'		=>	'select',
				'label'		=>	__('Status','eacSoftwareRegistry'),
				'default'	=>	$this->get_option('registrar_status'),
				'options'	=>	array_filter($this->REGISTRY_STATUS_CODES,function($v){return $v!='terminated';}),
				'info'		=>	'<small>change may alter dates</small>',
				'help'		=> false,
			],
			'registry_effective'	=> [
				'type'		=>	'date',
				'label'		=>	__('Effective','eacSoftwareRegistry'),
				'default'	=>	$this->getDateTimeInZone('now')->format('Y-m-d'),
				'attributes'=>	['required=true']
			],
			'registry_expires'		=> [
				'type'		=>	'date',
				'label'		=>	__('Expires','eacSoftwareRegistry'),
				'default'	=>	$this->getDateTimeInZone('now','+'.$this->get_option('registrar_term'))->format('Y-m-d'),
				'attributes'=>	['required=true']
			],
			'email_to_client'		=> [
				'type'		=>	'button',
				'label'		=>	'<span class="dashicons dashicons-email-alt"></span>',
				'default'	=>	'Update &amp; Send to Client'
			],
		);

		echo "<div class='settings-grid-container' style='grid-template-columns: 5.5em auto;'>\n";
		$this->add_metabox_fields($fields,$post,12);
		echo "</div>";
  	}


	/**
	 * Add custom meta box(es) payment box
	 */
	public function custom_post_metabox_payment($post)
	{
		$fields = array(
			'registry_paydue'		=> [
				'type'		=>	'number',
				'label'		=>	__('Amount Due','eacSoftwareRegistry'),
				'attributes'=>	['min=0.00','step=.01','max=9999999.99']
			],
			'registry_payamount'	=> [
				'type'		=>	'number',
				'label'		=>	__('Amount Paid','eacSoftwareRegistry'),
				'attributes'=>	['min=0.00','step=.01','max=9999999.99']
			],
			'registry_paydate'		=> [
				'type'		=>	'date',
				'label'		=>	__('Payment Date','eacSoftwareRegistry')
			],
			'registry_payid'		=> [
				'type'		=>	'text',
				'label'		=>	__('Payment Id/#','eacSoftwareRegistry')
			],
			'registry_nextpay'		=> [
				'type'		=> 	'date',
				'label'		=>	__('Next Payment','eacSoftwareRegistry')
			],
		);

		echo "<div class='settings-grid-container' style='grid-template-columns: 7.5em auto;'>\n";
		$this->add_metabox_fields($fields,$post,12);
		echo "</div>";
  	}


	/**
	 * Add custom meta box(es) html
	 */
	public function custom_post_metabox_fields($post)
	{
		$fields = array(
		//	'registry_help'			=> [
		//		'type'		=>	'help',
		//		'label'		=>	'<strong>Registration Details</strong>',
		//		'help'		=>	'<hr>'
		//	],
			'registry_key'			=> [
				'type'		=>	'hidden',
				'default'	=>	$post->post_title
			],
			'registry_product'		=> [
				'type'		=>	'text',
				'label'		=>	__('Registered Product Id','eacSoftwareRegistry'),
				'info'		=>	__('Registered product Id (alpha-numeric)','eacSoftwareRegistry'),
				'attributes'=>	['required=required',"pattern='[a-zA-Z0-9_\\x7f-\\xff]*'"]
			],
			'registry_title'		=> [
				'type'		=>	'text',
				'label'		=>	__('Registered Product Name','eacSoftwareRegistry'),
				'info'		=>	__('Short product name/title (text)','eacSoftwareRegistry'),
				'attributes'=>	['required=required']
			],
			'registry_description'	=> [
				'type'		=>	'textarea',
				'label'		=>	__('Product Description','eacSoftwareRegistry'),
				'info'		=>	__('Registered product description','eacSoftwareRegistry')
			],
			'registry_version'		=> [
				'type'		=>	'text',
				'label'		=>	__('Product Version','eacSoftwareRegistry'),
				'info'		=>	__('Registered product version','eacSoftwareRegistry')
			],
			'registry_license'		=> [
				'type'		=>	'select',
				'label'		=>	__('Product License','eacSoftwareRegistry'),
				'default'	=>	$this->get_option('registrar_license'),
				'options'	=>	$this->REGISTRY_LICENSE_LEVEL,
				'info'		=>	__('Registered product license','eacSoftwareRegistry')
			],
			'registry_count'		=> [
				'type'		=>	'number',
				'label'		=>	__('License Count','eacSoftwareRegistry'),
				'info'		=>	__('Number of licenses (users/seats/devices) included','eacSoftwareRegistry'),
				'attributes'=>	["placeholder='unlimited'"]
			],
			'registry_name'			=> [
				'type'		=>	'text',
				'label'		=>	__('Registrant\'s Name','eacSoftwareRegistry'),
				'info'		=>	__('Registrant\'s full name','eacSoftwareRegistry')
			],
			'registry_email'		=> [
				'type'		=>	'email',
				'label'		=>	__('Registrant\'s Email','eacSoftwareRegistry'),
				'info'		=>	__('Registrant\'s email address','eacSoftwareRegistry'),
				'attributes'=>	['required=required']
			],
			'registry_company'		=> [
				'type'		=>	'text',
				'label'		=>	__('Registrant\'s Organization','eacSoftwareRegistry'),
				'info'		=>	__('Registrant\'s company/organization name','eacSoftwareRegistry')
			],
			'registry_address'		=> [
				'type'		=>	'textarea',
				'label'		=>	__('Registrant\'s Address','eacSoftwareRegistry'),
				'info'		=>	__('Registrant\'s full postal address','eacSoftwareRegistry')
			],
			'registry_phone'		=> [
				'type'		=>	'text',
				'label'		=>	__('Registrant\'s Phone','eacSoftwareRegistry'),
				'info'		=>	__('Registrant\'s telephone number','eacSoftwareRegistry')
			],
			'registry_variations'	=> [
				'type'		=>	'textarea',
				'label'		=>	__('Product Variation(s)','eacSoftwareRegistry'),
				'info'		=>	__('List of [name=value] product variations (one per line)','eacSoftwareRegistry')
			],
			'registry_options'		=> [
				'type'		=>	'textarea',
				'label'		=>	__('Product Option(s)','eacSoftwareRegistry'),
				'info'		=>	__('List of product options (one per line)','eacSoftwareRegistry')
			],
			'registry_domains'		=> [
				'type'		=>	'textarea',
				'label'		=>	__('Registered Domains','eacSoftwareRegistry'),
				'info'		=>	__('List of registered domain names (one per line) <small>If empty, all domains are allowed</small>','eacSoftwareRegistry'),
				'attributes'=>	["placeholder='allow any domain'"]
			],
			'registry_sites'		=> [
				'type'		=>	'textarea',
				'label'		=>	__('Registered Sites','eacSoftwareRegistry'),
				'info'		=>	__('List of registered site URLs (one per line) <small>If empty, all URLs are allowed</small>','eacSoftwareRegistry'),
				'attributes'=>	["placeholder='allow any site url'"]
			],
			'registry_transid'		=> [
				'type'		=>	'readonly',
				'label'		=>	__('Transaction ID','eacSoftwareRegistry'),
				'info'		=>	__('External order transaction','eacSoftwareRegistry')
			],
		);

		// if software_taxonomy set "force" flag, and is active
		if ($this->is_option('registrar_taxonomy_product') && defined('EAC_SOFTWARE_TAXONOMY'))
		{
			$options = [];
			$terms = get_terms(
				['taxonomy' => EAC_SOFTWARE_TAXONOMY, 'orderby' => 'name', 'hide_empty' => false]
			);
			foreach ($terms as $term)
			{
				$options[$term->slug] = $term->slug;
			}
			$fieldValue = sanitize_title( get_post_meta($post->ID,"_registry_product",true) );
			$options[$fieldValue] = $fieldValue; // maybe registered before taxonomy added
			$fields['registry_product']['type'] 	= 'select';
			$fields['registry_product']['options'] 	= $options;
			unset($fields['registry_title']['attributes']);
		}

		echo "<div class='settings-grid-container' style='grid-template-columns: 15em auto;'>\n";
		$this->add_metabox_fields($fields,$post,50);
		echo "</div>";
  	}


	/**
	 * Add custom meta fields
	 */
	private function add_metabox_fields($fields,$post,$maxWidth=50)
	{
		foreach ($fields as $key => $fieldMeta)
		{
			$fieldValue = maybe_unserialize( get_post_meta($post->ID,"_{$key}",true) );
			if (empty($fieldValue) && isset($fieldMeta['default']))
			{
				$fieldValue = $fieldMeta['default'];
			}
			if (is_array($fieldValue))
			{
				$fieldValue = json_decode(json_encode($fieldValue));
				if (is_array($fieldValue)) {
					$fieldValue = implode("\n",$fieldValue);
				} else if (is_object($fieldValue)) {
					$fieldValue = $this->implode_with_keys("\n",(array)$fieldValue);
				}
			}
			if ($key == 'registry_product' && $fieldMeta['type'] == 'select')
			{
				$fieldValue = sanitize_title($fieldValue);
			}
			if (empty($fieldMeta['label']))
			{
				$fieldMeta['label'] = ucwords(str_replace('_',' ',$key));
			}

			$this->html_input_help('', $key, $fieldMeta);
			echo $this->html_input_block("_{$key}", $fieldMeta, $fieldValue, $maxWidth);
		}
  	}


	/**
	 * Order (layout) meta boxes
	 */
	public function order_custom_post_metabox()
	{
		return array(
			'normal'   => implode(',', [
				self::CUSTOM_POST_TYPE.'_fields',
				'postcustom',
				'commentsdiv',
			//	'postexcerpt',
			//	'formatdiv',
			//	'trackbacksdiv',
			//	'postimagediv',
			//	'commentstatusdiv',
			//	'slugdiv',
			//	'authordiv',
			]),
			'side'     => implode(',', [
				'submitdiv',
				self::CUSTOM_POST_TYPE.'_status',
				self::CUSTOM_POST_TYPE.'_payment',
			//	'tagsdiv-post_tag',
			//	'categorydiv',
			]),
			'advanced' => '',
		);
  	}


	/**
	 * exclude our comments from posts
	 * only when not looking at a specific post
	 *
	 * @param 	string	$note
	 * @return 	int comment id
	 */
	public function exclude_post_comments($clauses)
	{
		if (strpos($clauses['where'],'comment_post_ID') === false)
		{
			$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != '".self::CUSTOM_POST_TYPE."'";
		}
		return $clauses;
	}


	/**
	 * exclude our comments from feeds
	 *
	 * @param 	string	$note
	 * @return 	int comment id
	 */
	public function exclude_feed_comments($where)
	{
		return $where . ( $where ? ' AND ' : '' ) . " comment_type != '".self::CUSTOM_POST_TYPE."' ";
	}


	/*
	 *
	 * Miscellaneous Methods
	 *
	 */


	/**
	 * get registrar option
	 *
	 * @param 	string 	$option option 'all' | 'api' or option name to get
	 * @param 	array	$registration registration array
	 * @return 	array|string
	 */
	public function getRegistrarOptions($option='all',$registration=[])
	{
		static $registrar;
		if (is_null($registrar))
		{
			$registrar = [
				'registrar_email' 	=> $this->apply_filters('registrar_email',
											$this->get_option('registrar_email'),
											$registration['registry_product']
										),
				'registrar_name' 	=> $this->apply_filters('registrar_name',
											$this->get_option('registrar_name'),
											$registration['registry_product']
										),
				'registrar_contact' => $this->apply_filters('registrar_contact',
											$this->get_option('registrar_contact'),
											$registration['registry_product']
										),
				'registrar_phone' 	=> $this->apply_filters('registrar_phone',
											$this->get_option('registrar_phone'),
											$registration['registry_product']
										),
				'registrar_web' 	=> $this->apply_filters('registrar_web',
											$this->get_option('registrar_web'),
											$registration['registry_product']
										),
			];
		}
		switch ($option)
		{
			case 'all':
				return $registrar;
			case 'api':
				return [
					'name' 	=> $registrar['registrar_name'],
					'email' => $registrar['registrar_contact'],
					'phone' => $registrar['registrar_phone'],
					'web' 	=> $registrar['registrar_web'],
				];
			default:
				return $registrar["registrar_{$option}"];
		}
	}


	/**
	 * Puts the settings page in the 'Software Registration' menu - on 'admin_menu' action
	 *
	 * @return	void
	 */
	public function admin_add_settings_menu(): void
	{
		add_submenu_page('edit.php?post_type='.self::CUSTOM_POST_TYPE,
						 'Settings',
						 'Settings',
						 'manage_options',
						 $this->getSettingsSlug(),
						 $this->getSettingsCallback()
		);
	}


	/**
	 * set the client timezone
	 *
	 * @param array $meta curent registry array
	 * @return void
	 */
	private function setClientTimezone(array $meta): void
	{
		$timezone =  false;
		if (isset($meta['registry_timezone']))
		{
			try {
				$timezone = new \DateTimeZone($meta['registry_timezone']);
			} catch (\Throwable $e) { $timezone = false; }
		}
		$this->clientTimezone = (is_a($timezone,'DateTimeZone')) ? $timezone : $this->currentTimezone;
	}


	/**
	 * date/time
	 *
	 * @param 	string 	$datetime date/time string
	 * @param	string	$modify time to add or subtract (+1 day)
	 * @return 	object 	DateTime object or false on invalid
	 */
	public function getDateTimeInZone($datetime = 'now', $modify = null)
	{
		return $this->getDateTime($datetime, $modify, $this->currentTimezone);
	}


	/**
	 * date/time client timezone
	 *
	 * @param 	string 	date/time string
	 * @param	int		Seconds to add (subtract) to time
	 * @return 	object 	DateTime object or false on invalid
	 */
	public function getDateTimeClientZone($datetime = 'now', $modify = null)
	{
		$datetime = $this->getDateTimeInZone($datetime);	// registry default timezone
		return $this->getDateTime($datetime, $modify, $this->clientTimezone);
	}


	/**
	 * set/get email to client
	 *
	 * @param 	bool $action
	 * @return 	bool
	 */
	public function emailToClient($action=null): bool
	{
		if (is_bool($action)) $this->email_to_client = $action;
		return $this->email_to_client;
	}


	/*
	 *
	 * To check registration license
	 *
	 */


	/**
	 * is license L3 (standard) or better
	 *
	 * @return	bool
	 */
	public function isStandardLicense(): bool
	{
	 	return $this->Registration->isRegistryValue('license', 'L3', 'ge');
	}


	/**
	 * is license L4 (professional) or better
	 *
	 * @return	bool
	 */
	public function isProfessionalLicense(): bool
	{
	 	return $this->Registration->isRegistryValue('license', 'L4', 'ge');
	}


	/*
	 *
	 * When this plugin is updated
	 *
	 */


	/**
	 * version updated (action {classname}_version_updated)
	 *
	 * May be called more than once on a given site (once as network admin).
	 *
	 * @param	string|null	$curVersion currently installed version number
	 * @param	string		$newVersion version being installed/updated
	 * @return	void
	 */
	public function admin_plugin_updated($curVersion,$newVersion)
	{
	}
}
