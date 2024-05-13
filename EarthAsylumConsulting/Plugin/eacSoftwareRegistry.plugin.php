<?php
namespace EarthAsylumConsulting\Plugin;

/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * Primary plugin file for {eac}SoftwareRegistry
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		24.0513.1
 */

require "eacSoftwareRegistry.trait.php";
require "eacSoftwareRegistry.api.php";

class eacSoftwareRegistry extends \EarthAsylumConsulting\abstract_context
{
	/**
	 * @trait methods for administration
	 */
	use \EarthAsylumConsulting\Plugin\eacSoftwareRegistry_administration;

	/**
	 * @trait methods for api
	 */
	use \EarthAsylumConsulting\Plugin\eacSoftwareRegistry_api;

	/**
	 * @trait methods for date/time
	 */
	use \EarthAsylumConsulting\Traits\datetime;

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
			'Unlimited'			=> 'LU',
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
			$this->admin_construct($header);
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

		// only on admin/dashboard
		if ($this->is_admin())
		{
			$this->admin_addActionsAndFilters();
		}
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
					"<span class='eac-logo-orange'>{<span class='eac-logo-green'>eac</span>}$1</span>",
					$content
				);
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
		// maintain standard key order
		$meta = array_fill_keys(array_keys(self::REGISTRY_DEFAULTS), null);

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
	 * @param 	string	$default 	original/default message
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

			$stylesheet = $this->apply_filters('client_email_style', $this->getEmailStyle('client'), $meta, $post);
			$stylesheet.= "\n.eac-logo-gray {color: #8c8f94;} .eac-logo-green {color: #6e9882;} .eac-logo-orange {color: #da821d;}\n";

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

			$notices = '';
			foreach  ($this->getRegistrationNotices($meta,$post) as $type=>$notice) {
				if  (!empty($notice)) {
					$notices  .= "<p class='notice notice-{$type}'>{$notice}</p>";
				}
			}

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

			// no 'client_email_footer' unless manually entered
			$default =	$this->get_option('client_email_footer',
				"<a href='".$this->plugin->pluginHeader('PluginURI')."'/>".$this->formatPluginHelp($this->plugin->pluginHeader('Title'))."</a> powered by ".
				"<a href='https://eacdoojigger.earthasylum.com'/>".$this->formatPluginHelp('{eac}Doojigger')."</a> &copy; ".
				"<a href='".$this->plugin->pluginHeader('AuthorURI')."'/>".$this->plugin->pluginHeader('Author')."</a>"
			);

			/**
			 * filter {classname}_client_email_footer
			 *
			 * @param	string message
			 * @param	array registration
			 * @param	object wp_post
			 */
			$footer = $this->clientMessageMerge(
				$this->apply_filters('client_email_footer', $default, $meta, $post),
				$meta,
				$context,
				$default
			);

			$registration = $post->post_content;

			$template_name = 'customer-notification-email.php';
			ob_start();
			if ( ! $template_path = locate_template("eacSoftwareRegistry/templates/{$template_name}") ) {
				$template_path = $this->plugin->pluginHeader('PluginDir')."/templates/{$template_name}";
			}
			require $template_path;
			$content = ob_get_clean();

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

			$stylesheet = rtrim($this->apply_filters('admin_email_style', $this->getEmailStyle('admin'), $meta, $post));
			$stylesheet.= "\n.eac-logo-gray {color: #8c8f94;} .eac-logo-green {color: #6e9882;} .eac-logo-orange {color: #da821d;}\n";

			$editPostURL = admin_url("/post.php?post={$post->ID}&action=edit");
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

			$registration 	= $post->post_content;
			$api_source 	= $this->api_source;
			$footer 		=
				"<a href='".$this->plugin->pluginHeader('PluginURI')."'/>".$this->formatPluginHelp($this->plugin->pluginHeader('Title'))."</a> powered by ".
				"<a href='https://eacdoojigger.earthasylum.com'/>".$this->formatPluginHelp('{eac}Doojigger')."</a> &copy; ".
				"<a href='".$this->plugin->pluginHeader('AuthorURI')."'/>".$this->plugin->pluginHeader('Author')."</a>".
				"<div class='eac-gray'>Business Software Development & Information Technology Management</div>";

			$template_name 	= 'administrator-notification-email.php';
			ob_start();
			if ( ! $template_path = locate_template("eacSoftwareRegistry/templates/{$template_name}") ) {
				$template_path = $this->plugin->pluginHeader('PluginDir')."/templates/{$template_name}";
			}
			require $template_path;
			$content = ob_get_clean();

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
		$template_name 	= 'notification-email-css.php';
		ob_start();
		if ( ! $template_path = locate_template("eacSoftwareRegistry/templates/{$template_name}") ) {
			$template_path = $this->plugin->pluginHeader('PluginDir')."/templates/{$template_name}";
		}
		require $template_path;
		$stylesheet = ob_get_clean();

		// allow for customization from Appearance->Customize->Additional CSS
		$css = wp_get_custom_css();
		if ($css) $stylesheet .= $css;
		return $stylesheet;
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
			'query_var'				=> self::CUSTOM_POST_TYPE,
		//	'rewrite' 				=> array( 'slug' => self::CUSTOM_POST_TYPE.'/%registry_key%' ),

		);

		// Registering the Custom Post Type
		\register_post_type( self::CUSTOM_POST_TYPE, $args );
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
	//	return $this->apply_filters('registry_value',false,'license', 'L3', 'ge');
	}


	/**
	 * is license L4 (professional) or better
	 *
	 * @return	bool
	 */
	public function isProfessionalLicense(): bool
	{
		return $this->Registration->isRegistryValue('license', 'L4', 'ge');
	//	return $this->apply_filters('registry_value',false,'license', 'L4', 'ge');
	}


	/**
	 * is license L5 (enterprise) or better
	 *
	 * @return	bool
	 */
	public function isEnterpriseLicense(): bool
	{
		return $this->Registration->isRegistryValue('license', 'L5', 'ge');
	//	return $this->apply_filters('registry_value',false,'license', 'L5', 'ge');
	}
}
