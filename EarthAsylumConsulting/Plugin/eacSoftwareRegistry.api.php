<?php
namespace EarthAsylumConsulting\Plugin;

/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * Application Program Interface
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 * @version		25.0726.1
 */

namespace EarthAsylumConsulting\Plugin;

trait eacSoftwareRegistry_api
{
	/**
	 * @var string api action
	 * create, activate, deactivate, revise, verify, update (admin)
	 */
	private $api_action;

	/**
	 * @var string api source (api, webhook)
	 */
	private $api_source 		= 'API';


	/**
	 * API Constructor.
	 * called from eacSoftwareRegistry constructor
	 *
	 * @return void
	 */
	protected function api_construct(array $header)
	{
		/**
		 * filter {classname}_get_registration.
		 * get a registration mata value array (registry_*)
		 * @param	string registration key
		 * @param	array select parameters
		 * @return	array registration
		 */
		$this->add_filter('get_registration', function($registration,$metaQuery=[])
			{
				$post = $this->getRegistrationPostByKey($registration,true,$metaQuery);
				return (is_wp_error($post)) ? $post : $this->getRegistrationMeta($post);
			}
		);

		add_action( 'rest_api_init', array($this, 'api_register_routes') );
	}


	/**
	 * Register a WP REST api
	 *
	 * @return void
	 */
	public function api_register_routes($restServer)
	{
		$rest_route = self::CUSTOM_POST_TYPE . self::API_VERSION;

		if ($this->is_option('registrar_endpoints','create'))
		{
			register_rest_route( $rest_route, '/create', array(
					array(
						'methods'             => 'POST, PUT',
						'callback'            => array( $this, 'create_registration_key' ),
						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
		if ($this->is_option('registrar_endpoints','activate'))
		{
			register_rest_route( $rest_route, '/activate', array(
					array(
						'methods'             => 'GET, POST',
						'callback'            => array( $this, 'activate_registration_key' ),
						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
		if ($this->is_option('registrar_endpoints','deactivate'))
		{
			register_rest_route( $rest_route, '/deactivate', array(
					array(
						'methods'             => 'GET, POST, DELETE',
						'callback'            => array( $this, 'deactivate_registration_key' ),
						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
		if ($this->is_option('registrar_endpoints','verify'))
		{
			register_rest_route( $rest_route, '/verify', array(
					array(
						'methods'             => 'GET, HEAD, POST',
						'callback'            => array( $this, 'verify_registration_key' ),
						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
		if ($this->is_option('registrar_endpoints','refresh'))
		{
			register_rest_route( $rest_route, '/refresh', array(
					array(
						'methods'             => 'GET, POST',
						'callback'            => array( $this, 'refresh_registration_key' ),
						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
		if ($this->is_option('registrar_endpoints','revise'))
		{
			register_rest_route( $rest_route, '/revise', array(
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'revise_registration_key' ),

						'permission_callback' => array( $this, 'api_rest_authentication' ),
					),
			));
		}
	}


	/**
	 * REST API Authentication
	 *
	 * @param 	object	$request - WP_REST_Request Request object.
	 * @return 	bool
	 */
	public function api_rest_authentication($request)
	{
		// bearer authentication may require...
		//		RewriteCond %{HTTP:Authorization} ^(.*)
		// or
		//		RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
		// in .htaccess

		if (! ($authKey = $request->get_header( 'Authorization' )) )
		{
			if (! ($apiKey = $request->get_param( 'apikey' )) )
			{
				return false;
			}
		}
		else
		{
			list ($authType,$authKey) = explode(' ',$authKey);
			switch (strtolower($authType))
			{
				case 'basic':
				case 'bearer':
					$apiKey = base64_decode( trim($authKey) );
					break;
				default:
					$apiKey =  false;
			}
		}

		if ($apiKey)
		{
			$route = explode('/',$request->get_route());
			$this->setApiAction( end($route) );
			$isAllowed = false;

			switch ($this->api_action)
			{
				case 'create':
					if ($this->is_option('registrar_create_key',$apiKey)) $isAllowed = true;
					break;
				case 'activate':
					if ($this->is_option('registrar_update_key',$apiKey)) $isAllowed = true;
					break;
				case 'deactivate':
					if ($this->is_option('registrar_update_key',$apiKey)) $isAllowed = true;
					break;
				case 'verify':
					if ($this->is_option('registrar_read_key',$apiKey)) $isAllowed = true;
					break;
				case 'refresh':
					if ($this->is_option('registrar_update_key',$apiKey)) $isAllowed = true;
					break;
				case 'revise':
					if ($this->is_option('registrar_update_key',$apiKey)) $isAllowed = true;
					break;
			}

			if ($isAllowed)
			{
				// allow origin in CORS
				if (method_exists($this,'getRequestOrigin')) {
					$origin = $this->getRequestOrigin();
					add_filter( 'http_origin', function() use ($origin) {
						return $origin;
					});
					add_filter( 'allowed_http_origins', function ($allowed) use ($origin) {
						$allowed[] = $origin;
						return $allowed;
					});
				}
				return true;
			}
		}

		http_response_code(401);
		die( wp_json_encode( $this->api_restResponse( $this->rest_error(401) )->get_data() ) );
		return false;
	}


	/**
	 * rest formatted response
	 *
	 * @param 	object	JSON object or WP_Error
	 * @return 	object	WP_REST_Response
	 */
	public function api_restResponse($response)
	{
		if (is_wp_error($response))
		{
			return new \WP_REST_Response( [
				'status' =>
					[
						'code'		=> $response->get_error_code(),
						'message'	=> $response->get_error_message(),
					],
				'error'				=> $response->get_error_data()
			], intval($response->get_error_code()) ?: 500);
		}
		else
		{
			return new \WP_REST_Response( array_merge([
				'status' =>
					[
						'code'		=> '200',
						'message'	=> $this->api_action.' ok',
					]
				],
				$response
			),200);
		}
	}


	/*
	 *
	 * Software Registration API Routes
	 *
	 */


	/**
	 * API create_registration_key
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function create_registration_key($rest)
	{
		$request = $this->getRequestParameters($rest);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(400,"invalid request parameters",$request->get_error_message()) );
		}

		// check for existing key
		if (isset($request['registry_key']))
		{
			if (! is_wp_error( $this->getRegistrationPostByKey($request['registry_key'])) )
			{
				return $this->api_restResponse( $this->rest_error(406,"registration key already exists") );
			}
		}

		// set the registry key
		if (! isset($request['registry_key']) || ! $this->isRegistrarOption('allow_set_key'))
		{
			$request['registry_key'] 	= $this->newRegistryKey();
		}

		/* supported registry values */
		$defaults = $this->apply_filters('registry_api_defaults',array_merge(self::REGISTRY_DEFAULTS, [
			'registry_product'		=> $request['registry_product'],
			'registry_title'		=> $request['registry_product'],
			'registry_status'		=> $this->getRegistrarSetting('status',$request['registry_product']),
			'registry_license'		=> $this->getRegistrarSetting('license',$request['registry_product']),
		]),$request,$this->api_action);

		// sanitize the input array
		$request = $this->sanitizeRequest($request, $defaults, true);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $request );
		}

		// validate the input array
		$request = $this->validateRequest($request, $defaults);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $request );
		}

		// check for existing key with email & product (& domain)
		if (isset($_SERVER['HTTP_REFERER']))
		{
			// allow new registration with email/product for new host
			if (! is_wp_error( $this->getRegistrationPostByEmail($request['registry_email'],false,
					[
						'registry_product'	=> $request['registry_product'],
						'registry_domain'	=> preg_replace('/^www\.(.+\.)/i', '$1',parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST))
					]
				))
			) {
				return $this->api_restResponse( $this->rest_error(406,"registration for this host with this email and product already exists") );
			}
		}
		else
		{
			if (! is_wp_error( $this->getRegistrationPostByEmail($request['registry_email'],false,
					[
						'registry_product'	=> $request['registry_product']
					]
				))
			) {
				return $this->api_restResponse( $this->rest_error(406,"registration with this email and product already exists") );
			}
		}

		$post_date = wp_date('Y-m-d'); //$this->getDateTimeInZone()->format('Y-m-d');
		$effective = wp_date('Y-m-d', strtotime($request['registry_effective']));
		if ($effective > $post_date)
		{
			$post_date = $effective;
		}

		$post_status = $this->POST_STATUS_CODES[$request['registry_status']] ?? 'publish';

		// convert input array to meta keys
		$meta = array();
		foreach ($request as $key => $value)
		{
			if (array_key_exists($key, $defaults) || array_key_exists($key, self::REGISTRY_OPTIONAL)) {
				$meta["_{$key}"] = wp_slash($value);
			} else {
				$meta[$key] = wp_slash($value);
			}
		}

		// https://developer.wordpress.org/reference/functions/wp_insert_post/
		$post = array (												// (array) (Required) An array of elements that make up a post to update or insert.
		//	'ID'		 			=> 0,							// (int) The post ID. If equal to something other than 0, the post with that ID will be updated. Default 0.
		//	'post_author'			=> 0,							// (int) The ID of the user who added the post. Default is the current user ID.
			'post_date'				=> $post_date,					// (string) The date of the post. Default is the current time.
			'post_date_gmt'			=> get_gmt_from_date($post_date), 	// (string) The date of the post in the GMT timezone. Default is the value of $post_date.
			'post_content'			=> $this->getPostHtml($request,'post'),	// (mixed) The post content. Default empty.
		//	'post_content_filtered'	=> '',							// (string) The filtered post content. Default empty.
			'post_title'			=> $request['registry_key'],	// (string) The post title. Default empty.
			'post_excerpt'			=> $this->getPostExcerpt($request),	// (string) The post excerpt. Default empty.
			'post_status'			=> $post_status,				// (string) The post status. Default 'draft'.
			'post_type'				=> self::CUSTOM_POST_TYPE,		// (string) The post type. Default 'post'.
			'comment_status'		=> 'closed',					// (string) Whether the post can accept comments. Accepts 'open' or 'closed'. Default is the value of 'default_comment_status' option.
			'ping_status'			=> 'closed',					// (string) Whether the post can accept pings. Accepts 'open' or 'closed'. Default is the value of 'default_ping_status' option.
			'post_password'			=> $request['registry_email'],	// (string) The password to access the post. Default empty.
			'post_name'				=> $request['registry_key'],	// (string) The post name. Default is the sanitized post title when creating a new post.
		//	'to_ping'				=> '',							// (string) Space or carriage return-separated list of URLs to ping. Default empty.
		//	'pinged'				=> '',							// (string) Space or carriage return-separated list of URLs that have been pinged. Default empty.
		//	'post_modified'			=> '',							// (string) The date when the post was last modified. Default is the current time.
		//	'post_modified_gmt'		=> '',							// (string) The date when the post was last modified in the GMT timezone. Default is the current time.
		//	'post_parent'			=> 0,							// (int) Set this for the post it belongs to, if any. Default 0.
		//	'menu_order'			=> 0,							// (int) The order the post should be displayed in. Default 0.
		//	'post_mime_type'		=> '',							// (string) The mime type of the post. Default empty.
			'guid'					=> site_url(self::CUSTOM_POST_TYPE.'/'.$request['registry_key']), // (string) Global Unique ID for referencing the post. Default empty.
		//	'import_id'				=> 0,							// (int) The post ID to be used when inserting a new post. If specified, must not match any existing post ID. Default 0.
		//	'post_category'			=> [],							// (int[]) Array of category IDs. Defaults to value of the 'default_category' option.
		//	'tags_input'			=> [],							// (array) Array of tag names, slugs, or IDs. Default empty.
		//	'tax_input'				=> [],							// (array) Array of taxonomy terms keyed by their taxonomy name. Default empty.
			'meta_input'			=> $meta,						// (array) Array of post meta values keyed by their post meta key. Default empty.
		);

		/**
		 * filter {classname}_api_create_registration
		 * filter registration creation
		 * @param	array post array to create WP_Post
		 * @param	array registration parameters
		 * @return	array post array to create WP_Post or wp_error
		 */
		$post = $this->apply_filters('api_create_registration', $post, $request);

		if (!is_wp_error($post))
		{
			$post = wp_insert_post( $post, true, true );
		}
		if (is_wp_error($post))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to create registration",$post->get_error_message()) );
		}

		return $this->api_restResponse( $this->apiRegistrationValues($request['registry_key'],get_post($post)) );
	}


	/**
	 * API activate_registration_key
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function activate_registration_key($rest)
	{
		return $this->revise_registration_key($rest);
	}


	/**
	 * API revise_registration_key (refresh, revise or activate)
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function revise_registration_key($rest)
	{
		$request = $this->getRequestParameters($rest);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(400,"invalid request parameters",$request->get_error_message()) );
		}

		$post = $this->getRegistrationPostByKey($request['registry_key'],true);
		if (is_wp_error($post))
		{
			return $this->api_restResponse($post);
		}

		/* supported registry values */
		$defaults = $this->apply_filters('registry_api_defaults',array_merge(self::REGISTRY_DEFAULTS, [
			'registry_product'		=> $post->meta_input['_registry_product'],
			'registry_title'		=> $post->meta_input['_registry_title'],
			'registry_description'	=> $post->meta_input['_registry_description'],
			'registry_license'		=> $post->meta_input['_registry_license'],
			'registry_status'		=> $post->meta_input['_registry_status'],
			'registry_effective'	=> $post->meta_input['_registry_effective'],
			'registry_expires'		=> $post->meta_input['_registry_expires'],
		]),$request,$this->api_action);

		if ( ($this->api_source != 'API' || $rest->get_method() == 'POST') &&
			($this->api_action != 'activate' || $this->isRegistrarOption('allow_activation_update')) )
		{
			// sanitize the input array
			$request = $this->sanitizeRequest($request,$defaults);

			// if previously deactivated, update status when activating
			if ($this->api_action == 'activate' && $defaults['registry_status'] == 'terminated')
			{
				$status = get_post_meta($post->ID,'_prior_status',true) ?: $this->getRegistrarSetting('status',$defaults['registry_product']);
				delete_post_meta($post->ID,'_prior_status');
				$defaults['registry_status'] 	= $status;
			}

			// auto-update, advances the expiration date by term
			$expires = $this->getDateTimeInZone($defaults['registry_expires'].' 23:59:59');
			if ($post && $expires < $this->getDateTimeInZone())
			{
				if (isset($post->meta_input['_registry_autoupdate'])
				&& $this->isTrue($post->meta_input['_registry_autoupdate'])
				&& in_array($defaults['registry_status'],['trial','active']))
				{
				//	$term = (in_array($request['registry_status'],['pending','trial']))
				//		? $this->getRegistrarSetting('term',$defaults['registry_product'])
				//		: $this->getRegistrarSetting('fullterm',$defaults['registry_product']);
					$term = $this->getRegistrarSetting('term',$defaults['registry_product']);
					$defaults['registry_expires'] 	=
					$request['registry_expires'] 	= $this->getDateTimeInZone($expires,"+{$term}")->format('d-M-Y');
					$defaults['registry_status'] 	=
					$request['registry_status'] 	= 'active';
				}
			}

			// set license level
			if (isset($request['registry_license']))
			{
				$request['registry_license'] = ucwords(strtolower($request['registry_license']));
				if ( array_key_exists($request['registry_license'],$this->REGISTRY_LICENSE_LEVEL))
				{
					$request['registry_license'] = $this->REGISTRY_LICENSE_LEVEL[ $request['registry_license'] ];
				}
				else if (! in_array($request['registry_license'],$this->REGISTRY_LICENSE_LEVEL))
				{
					$request['registry_license'] = $defaults['registry_license'];
				}
			}

			// validate the input array
			$request = $this->validateRequest($request, $defaults, $post);
			if (is_wp_error($request))
			{
				return $this->api_restResponse( $request );
			}

			$post->post_status = $this->POST_STATUS_CODES[$request['registry_status']] ?? 'publish';
		}
		else
		{
			$request = array('registry_key' => $request['registry_key']);
		}

		// custom field (not '_' prefixed)
		if ($this->api_source == 'API')
		{
			$request['registry_refreshed'] = $this->getDateTimeInZone()->format('d-M-Y H:i:s T');
		}

		/**
		 * filter {classname}_api_[activate|revise|refresh]_registration
		 * filter registration activation/revision
		 * @param	array registration parameters
		 * @param	object WP_Post
		 * @return	array registration parameters or wp_error
		 */
		$request = $this->apply_filters('api_'.$this->api_action.'_registration', $request, $post);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to ".$this->api_action." registration",$request->get_error_message()) );
		}

		$exclude = ['registry_key'];
		foreach ($request as $key => $value)
		{
			if (!in_array($key, $exclude) && !is_null($value))
			{
				if (array_key_exists($key, $defaults) || array_key_exists($key, self::REGISTRY_OPTIONAL)) {
					// update allowable keys
					$post->meta_input["_{$key}"] = wp_slash($value);
				} else {
					// update custom keys
					$post->meta_input["{$key}"] = wp_slash($value);
				}
			}
		}

		$result = wp_update_post(array(
			'ID'			=> $post->ID,
			'post_name'		=> $post->post_title,
			'post_status'	=> $post->post_status,
			'meta_input'	=> $post->meta_input,
		),true);

		if (is_wp_error($result))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to ".$this->api_action." registration",$result->get_error_message()) );
		}

		return $this->api_restResponse( $this->apiRegistrationValues($request['registry_key'],$post) );
	}


	/**
	 * API deactivate_registration_key
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function deactivate_registration_key($rest)
	{
		$request = $this->getRequestParameters($rest);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(400,"invalid request parameters",$request->get_error_message()) );
		}

		$post = $this->getRegistrationPostByKey($request['registry_key'],true);
		if (is_wp_error($post))
		{
			return $this->api_restResponse($post);
		}

		/**
		 * filter {classname}_api_deactivate_registration
		 * filter registration deactivation
		 * @param	array request parameters
		 * @param	object WP_Post
		 * @return	array request parameters or wp_error
		 */
		$request = $this->apply_filters('api_deactivate_registration', $request, $post);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to deactivate registration",$request->get_error_message()) );
		}

		$post->post_status = $this->POST_STATUS_CODES['terminated'];
		$post->meta_input['_prior_status'] = $post->meta_input['_registry_status'];
		$post->meta_input['_registry_status'] = 'terminated';

		$result = wp_update_post(array(
			'ID'			=> $post->ID,
			'post_name'		=> $post->post_title,
			'post_status'	=> $post->post_status,
			'meta_input'	=> $post->meta_input,
		),true);

		if (is_wp_error($result))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to deactivate registration",$result->get_error_message()) );
		}

		return $this->api_restResponse( $this->apiRegistrationValues($request['registry_key'],$post) );
	}


	/**
	 * API verify_registration_key
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function verify_registration_key($rest)
	{
		$request = $this->getRequestParameters($rest);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(400,"invalid request parameters",$request->get_error_message()) );
		}

		$post = $this->getRegistrationPostByKey($request['registry_key'],true);
		if (is_wp_error($post))
		{
			return $this->api_restResponse($post);
		}

		if (isset($request['registry_timezone']) && !empty($request['registry_timezone']))
		{
			$this->setClientTimezone($request);
		}

		if (isset($request['registry_locale']) && !empty($request['registry_locale']))
		{
			$this->setClientLocale($request);
		}

		// custom field (not '_' prefixed)
		$post->meta_input['registry_refreshed'] = $this->getDateTimeInZone()->format('d-M-Y H:i:s T');

		// check for expiration
		$status 	= $post->meta_input['_registry_status'];
		$expires 	= $this->getDateTimeInZone($post->meta_input['_registry_expires'].' 23:59:59');

		if ($post && $expires < $this->getDateTimeInZone())
		{
			// auto-update, advances the expiration date by term
			if (isset($post->meta_input['_registry_autoupdate'])
			&& $this->isTrue($post->meta_input['_registry_autoupdate'])
			&& in_array($status,['trial','active']))
			{
				$term = $this->getRegistrarSetting('term',$post->meta_input['_registry_product']);
				$post->meta_input['_registry_expires'] 	= $this->getDateTimeInZone($expires,"+{$term}")->format('d-M-Y');
				$post->meta_input['_registry_status'] 	= 'active';
				$post->post_status = $this->POST_STATUS_CODES['active'];
				wp_update_post(array(
					'ID'			=> $post->ID,
					'post_name'		=> $post->post_title,
					'post_status'	=> $post->post_status,
					'meta_input'	=> $post->meta_input,
				),true);
			}
			else
			// expired
			if (!in_array($status,['expired','terminated']))
			{
				$this->setApiAction('expired');
				$post->meta_input['_registry_status'] = 'expired';
				$post->post_status = $this->POST_STATUS_CODES['expired'];
				wp_update_post(array(
					'ID'			=> $post->ID,
					'post_name'		=> $post->post_title,
					'post_status'	=> $post->post_status,
					'meta_input'	=> $post->meta_input,
				),true);
			}
		}

		/**
		 * filter {classname}_api_verify_registration
		 * filter registration verification
		 * @param	array request parameters
		 * @return	array request parameters or wp_error
		 */
		$request = $this->apply_filters('api_verify_registration', $request, $post);
		if (is_wp_error($request))
		{
			return $this->api_restResponse( $this->rest_error(500,"failed to verify registration",$request->get_error_message()) );
		}

		update_post_meta($post->ID,"registry_refreshed", $post->meta_input['registry_refreshed']);

		return $this->api_restResponse( ($rest->get_method() == 'HEAD')
				? new WP_REST_Response()
				: $this->apiRegistrationValues($request['registry_key'],$post)
		);
	}


	/**
	 * API refresh_registration_key
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	object	JSON response - registration object or WP_Error.
	 */
	public function refresh_registration_key($rest)
	{
		return $this->revise_registration_key($rest);
	}


	/*
	 *
	 * Software Registration API helpers
	 *
	 */


	/**
	 * get request parameters from WP_REST_Request or array passed through webhooks.
	 *
	 * @param 	object|array $rest - WP_REST_Request object or name=>value array.
	 * @return 	array request parameters
	 */
	public function getRequestParameters($rest)
	{
		if (is_array($rest))
		{
			$params = $rest;
		}
		else
		{
			$params = ($rest->is_json_content_type())
					? $rest->get_json_params()
					: $rest->get_params();
		}

		if (!array_key_exists('registry_key',$params)) $params['registry_key'] = null;

		/**
		 * filter {classname}_api_request_parameters
		 * filter request parameters
		 * @param	array request parameters
		 * @param	string api context/action (create, activate, deactivate, verify, refresh, revise)
		 * @return	array request parameters
		 */
		$params = $this->apply_filters('api_request_parameters', $params, $this->api_action);

		// directives (prefixed with '_') passed in api call
		if (!is_wp_error($params) && isset($params['_email_to_client']))
		{
			$this->emailToClient( $this->isTrue($params['_email_to_client']) );
			unset($params['_email_to_client']);
		}
		return $params;
	}


	/**
	 * sanitize valid request parameters
	 *
	 * @param 	array	$request - request parameters
	 * @param 	array	$defaults - default/required parameters
	 * @param 	bool	$chkRequired - true=check required values (on create)
	 * @return 	array	request parameters
	 */
	public function sanitizeRequest(array $request, array $defaults, bool $chkRequired = false): array
	{
		foreach ($defaults as $name => $default)
		{
			if (isset($request[$name]))
			{
				if (is_array($default)) {
					if (!is_array($request[$name])) {
						$request[$name] = explode(',',trim($request[$name]));
					}
					foreach ($request[$name] as $key => &$value) {
						$value = sanitize_textarea_field(trim($value));
					}
					unset($value);
				} else {
					$request[$name] = sanitize_textarea_field(trim($request[$name]));
				}
				switch ($name) {
					case 'registry_product':
						$request[$name] = sanitize_text_field($request[$name]);
						$request[$name] = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/','_',$request[$name]);
						break;
					case 'registry_title':
						$request[$name] = sanitize_text_field($request[$name]);
						break;
					case 'registry_email':
						$request[$name] = sanitize_email($request[$name]);
						break;
				}
			}
			else if ($chkRequired)
			{
				if ($default === true) {
					return $this->rest_error(400,"{$name} is required for registration");
				} else {
					$request[$name] = $default;
				}
			}
		}
		return $request;
	}


	/**
	 * validate request parameters for activate/revise/refresh requests
	 *
	 * @param 	array	$request - request parameters
	 * @param 	array	$defaults - default/required parameters
	 * @param 	array	$wpPost - existing registration post
	 * @return 	array	request parameters
	 */
	public function validateRequest(array $request, array $defaults,  object $wpPost = null)
	{
		// check product
		if (isset($request['registry_product']))
		{
			if (sanitize_title($request['registry_product']) != sanitize_title($defaults['registry_product']))
			{
				return $this->rest_error(400,"registry_product does not match registration product");
			}
		}

		// check title
		if (!isset($request['registry_title']) || empty($request['registry_title']))
		{
			$request['registry_title'] = $defaults['registry_title'] ?: $defaults['registry_product'];
		}

		// check description
		if (!isset($request['registry_description']) || empty($request['registry_description']))
		{
			$request['registry_description'] = $defaults['registry_description'] ?: $request['registry_title'];
		}

		// set/check registrant email
		if (isset($request['registry_email']))
		{
			$request['registry_email'] = strtolower($request['registry_email']);
			if (! is_email($request['registry_email']))
			{
				return $this->rest_error(400,"valid registry_email is required for registration");
			}
		}

		// set status
		if (isset($request['registry_status']))
		{
			if (! $this->isRegistrarOption('allow_set_status') ||
				! in_array($request['registry_status'],$this->REGISTRY_STATUS_CODES))
			{
				$request['registry_status'] = $defaults['registry_status'];
			}
		}
		else
		{
			$request['registry_status'] 	= $defaults['registry_status'];
		}

		// set license level
		if (!isset($request['registry_license']))
		{
			$request['registry_license'] = $defaults['registry_license'];
		}

		$licenseLimitations = $this->apply_filters("api_license_limitations", [
			'count'			=> null,
			'variations'	=> null,
			'options'		=> null,
			'domains'		=> null,
			'sites'			=> null,
		], $request['registry_license'], $request);

		// set effective date
		$request['registry_effective'] 		= $this->setEffectiveDate($request,$defaults['registry_effective']);

 		// set expiration date
		$request['registry_expires'] 		= $this->setExpirationDate($request,$defaults['registry_expires']);

		// set status based on effective / expired
		if ($this->getDateTimeInZone($request['registry_expires'].' 23:59:59') < $this->getDateTimeInZone('now','-30 days'))
		{
			if (!empty($wpPost)) $wpPost->meta_input['_prior_status'] = $wpPost->meta_input['_registry_status'];
			$request['registry_status'] 	= 'terminated';
		}
		else if ($this->getDateTimeInZone($request['registry_expires'].' 23:59:59') < $this->getDateTimeInZone())
		{
			$request['registry_status'] 	= 'expired';
		}
		else if ($this->getDateTimeInZone($request['registry_effective'].' 00:00:00') > $this->getDateTimeInZone('00:00:00 today'))
		{
			$request['registry_status'] 	= 'future';
		}

		/*
		 * Remaining values may be updated unconditionally through the api
		 */

		// set variations
		if (isset($request['registry_variations']) && is_array($request['registry_variations']))
		{
			$current = (!empty($wpPost)) ? (array)$wpPost->meta_input['_registry_variations'] : [];
			$request['registry_variations'] = array_unique( array_merge($current,$request['registry_variations']) );

			if (isset($request['registry_license']) && ($limit = $licenseLimitations['variations']))
			{
				$request['registry_variations'] = array_slice($request['registry_variations'], 0, $limit);
			}
		}
		else
		{
			unset($request['registry_variations']);
		}

		// set options
		if (isset($request['registry_options']) && is_array($request['registry_options']))
		{
			$current = (!empty($wpPost)) ? (array)$wpPost->meta_input['_registry_options'] : [];
			$request['registry_options'] = array_unique( array_merge($current,$request['registry_options']) );

			if (isset($request['registry_license']) && ($limit = $licenseLimitations['options']))
			{
				$request['registry_options'] = array_slice($request['registry_options'], 0, $limit);
			}
		}
		else
		{
			unset($request['registry_options']);
		}

		// set domain name
		if (isset($request['registry_domains']))
		{
			$current = (!empty($wpPost)) ? (array)$wpPost->meta_input['_registry_domains'] : [];
			$request['registry_domains'] 	= $this->setDomainNames($request, $current);

			if (isset($request['registry_license']) && ($limit = $licenseLimitations['domains']))
			{
				$request['registry_domains'] = array_slice($request['registry_domains'], 0, $limit);
			}
		}

		// set site urls
		if (isset($request['registry_sites']))
		{
			$current = (!empty($wpPost)) ? (array)$wpPost->meta_input['_registry_sites'] : [];
			$request['registry_sites'] 		= $this->setSiteUrls($request, $current);

			if (isset($request['registry_license']) && ($limit = $licenseLimitations['sites']))
			{
				$request['registry_sites'] = array_slice($request['registry_sites'], 0, $limit);
			}
		}

		// count is empty or numeric
		if (isset($request['registry_count']))
		{
			$request['registry_count'] 		= /* intval($request['registry_count']) ?: */ $defaults['registry_count'];
		}
		if ($limit = $licenseLimitations['count'])
		{
			if (!isset($request['registry_count']) || empty($request['registry_count']) || $request['registry_count'] > $limit)
			{
				$request['registry_count'] = $limit;
			}
		}

		// client timezone
		if (isset($request['registry_timezone']) && !empty($request['registry_timezone']))
		{
			$this->setClientTimezone($request);
		}

		if (isset($request['registry_locale']) && !empty($request['registry_locale']))
		{
			$this->setClientLocale($request);
		}

		// automatically update (trial->active) and renew - set in admin only
		if (isset($request['registry_autoupdate']))
		{
			unset($request['registry_autoupdate']); // = $this->isTrue($request['registry_autoupdate']);
		}

		// see if transid is formatted the way we like it
		if (isset($request['registry_transid']))
		{
			$transid = explode('|',$request['registry_transid']);
			if (count($transid) == 1 && isset($_SERVER['HTTP_REFERER']))
			{
				$domain = preg_replace('/^www\.(.+\.)/i', '$1', parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST));
				$request['registry_transid'] = "{$transid}|{$domain}";
			}
		}

		// amount due
		if (isset($request['registry_paydue']) && floatval($request['registry_paydue']) > 0)
		{
			$request['registry_paydue'] 	= number_format(floatval($request['registry_paydue']),2,'.','');
		}
		else
		{
			unset($request['registry_paydue']);
		}

		// amount paid
		if (isset($request['registry_payamount']) && floatval($request['registry_payamount']) > 0)
		{
			$request['registry_payamount'] 	= number_format(floatval($request['registry_payamount']),2,'.','');
		}
		else
		{
			unset($request['registry_payamount']);
		}

		// payment date
		if (isset($request['registry_paydate']) && !empty($request['registry_paydate']))
		{
			try {
				$paydate = $this->getDateTimeInZone($request['registry_paydate'].' 23:59:59');
			} catch (\Throwable $e) { $paydate = false; }
			$request['registry_paydate'] = ($this->hasTimezone($paydate)) ? $paydate->format('d-M-Y') : null;
		}
		else
		{
			unset($request['registry_paydate']);
		}

		// next payment date
		if (isset($request['registry_nextpay']))
		{
			if (!empty($request['registry_nextpay']))
			{
				try {
					$paydate = $this->getDateTimeInZone($request['registry_nextpay'].' 23:59:59');
				} catch (\Throwable $e) { $paydate = false; }
			}
			$request['registry_nextpay'] = ($this->hasTimezone($paydate)) ? $paydate->format('d-M-Y') : '';
		}

		/**
		 * filter {classname}_validate_registration
		 * filter registration validation
		 * @param	array registration parameters
		 * @param	object WP_Post
		 * @return	array registration parameters or wp_error
		 */
		$request = $this->apply_filters('validate_registration', $request, $wpPost, $this->api_action);
		if (is_wp_error($request))
		{
			return $this->rest_error(400,"failed registration validation",$request->get_error_message());
		}

		return $request;
	}


	/**
	 * set effective date
	 *
	 * @param array $request api request parameters
	 * @param string $default default date
	 * @return string date d-M-Y
	 */
	private function setEffectiveDate(array $request, $default=null)
	{
		$effective = false;
		if (isset($request['registry_effective']) && !empty($request['registry_effective']) && $this->isRegistrarOption('allow_set_effective'))
		{
			try {
				$effective = $this->getDateTimeInZone($request['registry_effective']);
			} catch (\Throwable $e) { $effective = false; }
		}

		if (!$this->hasTimezone($effective) && !empty($default))
		{
			try {
				$effective = $this->getDateTimeInZone($default);
			} catch (\Throwable $e) { $effective = false; }
		}

		return ($this->hasTimezone($effective)) ? $effective->format('d-M-Y') : $this->getDateTimeInZone()->format('d-M-Y');
	}


	/**
	 * set Expiration date based on effective date
	 *
	 * @param array $request api request parameters
	 * @param string $default default term
	 * @return string date d-M-Y
	 */
	private function setExpirationDate(array $request, $default=null)
	{
		$expiry = false;
		if (isset($request['registry_expires']) && !empty($request['registry_expires']) && $this->isRegistrarOption('allow_set_expiration'))
		{
			try {
				if (! $expiry = $this->isValidDate($request['registry_expires'], 'd-M-Y', $this->currentTimezone) ?:
								$this->isValidDate($request['registry_expires'], 'Y-m-d', $this->currentTimezone)) {
					  $expiry = $this->getDateTimeInZone($request['registry_effective'],"+{$request['registry_expires']}");
				}
			} catch (\Throwable $e) { $expiry = false; }
		}

		if (!$this->hasTimezone($expiry) && !empty($default))
		{
			try {
				if (! $expiry = $this->isValidDate($default, 'd-M-Y', $this->currentTimezone) ?:
								$this->isValidDate($default, 'Y-m-d', $this->currentTimezone)) {
					  $expiry = $this->getDateTimeInZone($request['registry_effective'],"+{$default}");
				}
			} catch (\Throwable $e) { $expiry = false; }
		}

		if (!$this->hasTimezone($expiry))
		{
			$default = (in_array($request['registry_status'],['pending','trial']))
				? $this->getRegistrarSetting('term',$request['registry_product'])
				: $this->getRegistrarSetting('fullterm',$request['registry_product']);
			try {
				if (! $expiry = $this->isValidDate($default, 'd-M-Y', $this->currentTimezone) ?:
								$this->isValidDate($default, 'Y-m-d', $this->currentTimezone)) {
					  $expiry = $this->getDateTimeInZone($request['registry_effective'],"+{$default}");
				}
			} catch (\Throwable $e) { $expiry = false; }
		}

		return ($this->hasTimezone($expiry)) ? $expiry->format('d-M-Y') : $request['registry_effective'];
	}


	/**
	 * set domain names
	 *
	 * @param array $request api request parameters
	 * @return array
	 */
	private function setDomainNames(array $request, array $current = [])
	{
		if (!is_array($request['registry_domains']))
		{
			$request['registry_domains'] = array($request['registry_domains']);
		}
		foreach ($request['registry_domains'] as &$domain)
		{
			//$domain = str_replace('www.','',$domain);
			$domain = preg_replace('/^www\.(.+\.)/i', '$1', $domain);
		}

		if (!empty($current))
		{
			$request['registry_domains'] = array_unique( array_merge($current,$request['registry_domains']) );
		}

		return $request['registry_domains'];
	}


	/**
	 * set site URLs
	 *
	 * @param array $request api request parameters
	 * @return array
	 */
	private function setSiteUrls(array $request, array $current = [])
	{
		if (!is_array($request['registry_sites']))
		{
			$request['registry_sites'] = array($request['registry_sites']);
		}
		foreach ($request['registry_sites'] as &$site)
		{
			$site = sanitize_url($site);
		}

		if (!empty($current))
		{
			$request['registry_sites'] = array_unique( array_merge($current,$request['registry_sites']) );
		}

		return $request['registry_sites'];
	}


	/**
	 * check registrar_options array for specific option
	 *
	 * @param 	string 	option to check
	 * @return 	bool|string (truthy)
	 */
	public function isRegistrarOption($option)
	{
		/**
		 * filter {classname}_is_registrar_option
		 * filter registrar options
		 * @param	bool default option value
		 * @param	string option name
		 * @return	bool
		 */
		$bool = $this->apply_filters('is_registrar_option', $this->is_option('registrar_options',$option), $option);
		/**
		 * filter {classname}_registrar_{option}
		 * filter registrar option
		 * @param	bool default option value
		 * @return	bool
		 */
		return  $this->apply_filters("registrar_{$option}",$bool);
	}


	/**
	 * Overload get_option to apply filter.
	 *
	 * @param	string	$optionName option name
	 * @param	string	$product registration product name
	 * @return	mixed	option value
	 */
	public function getRegistrarSetting($optionName, $product)
	{
		$optionName = 'registrar_'.$optionName;
		/**
		 * filter {classname}_registrar_{option}
		 * filter registrar setting
		 * @param	string	$optionName setting name
		 * @param	mixed	option value
		 * @param	string	$product registration product name
		 * @return	mixed
		 */
		return $this->apply_filters($optionName,
									$this->get_option($optionName),
									$product
		);
	}


	/*
	 *
	 * Software Registration API Response Object
	 *
	 */


	/**
	 * api registration data - builds the API response
	 *
	 * @param string $registry_key the registration number (uuid)
	 * @param WP_Post $post the registration post
	 * @return array
	 */
	public function apiRegistrationValues($registry_key, $post=null)
	{
		if (empty($registry_key))
		{
			return $this->rest_error(400,"registration key is required");
		}

		if (empty($post))
		{
			$post = $this->getRegistrationPostByKey($registry_key,true);
			if (is_wp_error($post)) return $post;
		}

		$meta = $this->getRegistrationMeta($post);

		/**
		 * filter {classname}_api_registration_values
		 * filter registration values
		 * @param	array request parameters
		 * @param	object WP_Post
		 * @param	string api context/action (create, activate, deactivate, verify, refresh, revise)
		 * @return	array request parameters or wp_error
		 */
		$meta = $this->apply_filters('api_registration_values', $meta, $post, $this->api_action);
		if (is_wp_error($meta))
		{
			return $this->rest_error(400,"registration values missing or invalid",$meta->get_error_message());
		}

		$this->setClientTimezone($meta);

		$error = '';

		// verify referer domain/site
		if (isset($_SERVER['HTTP_REFERER']))
		{
			// verify host domain name (sans www.)
			//$domain = str_replace('www.','',parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST));
			$domain = preg_replace('/^www\.(.+\.)/i', '$1', parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST));

			if (isset($meta['registry_domains']) && ! in_array($domain,(array)$meta['registry_domains']))
			{
				$meta['registry_status'] 	= 'invalid';
				$meta['registry_valid']		=  false;
				$error = sprintf(__('Domain mismatch; %s not accepted','softwareregistry'),$domain);
			}
			// verify url site name (sans scheme)
			else if (isset($meta['registry_sites']) && !empty($meta['registry_sites']))
			{
				$scheme = parse_url($_SERVER['HTTP_REFERER'],PHP_URL_SCHEME);
				$domain = str_replace($scheme,'',$_SERVER['HTTP_REFERER']);

				$valid = false;
				foreach ($meta['registry_sites'] as $site)
				{
					$scheme = parse_url($site,PHP_URL_SCHEME);
					$site = str_replace($scheme,'',$site);
					if (strpos($domain,$site) !== false) {
						$valid = true;
						break;
					}
				}
				if (!$valid) {
					$meta['registry_status'] 	= 'invalid';
					$meta['registry_valid']		=  false;
					$error = __('Site mismatch; referring URL not accepted','softwareregistry');
				}
			}
		}

		if ($post->post_status == 'publish') // is active registration
		{
			if ($this->getDateTimeInZone($meta['registry_expires'].' 23:59:59') < $this->getDateTimeInZone())
			{
				$meta['registry_status'] = 'expired';
				$post->post_status = $this->POST_STATUS_CODES[$meta['registry_status']];
			}
			else if ($this->getDateTimeInZone($meta['registry_effective'].' 00:00:00') > $this->getDateTimeInZone('00:00:00 today'))
			{
				$meta['registry_status'] = 'pending';
				$post->post_status = $this->POST_STATUS_CODES[$meta['registry_status']];
			}
		}

		$refreshInterval = ($meta['registry_status'] == 'pending')
				? $this->getRegistrarSetting('pending_time',$meta['registry_product'])
				: $this->getRegistrarSetting('refresh_time',$meta['registry_product']);

		$valid = ($post->post_status == 'publish'); // active registration

		/**
		 * filter {classname}_is_valid_registration
		 * set validity of registration
		 * @param	bool is valid
		 * @param	array registration parameters
		 * @return	bool is valid
		 */
		$meta['registry_valid'] = $this->apply_filters('is_valid_registration', $valid, $meta, $post);

		/**
		 * filter {classname}_api_registration_notices
		 *
		 * @param	array notices
		 * @param	array registration
		 * @param	array post
		 * @param	string action
		 * @return	array notices
		 */
		$notices = $this->apply_filters('api_registration_notices',
			$this->getRegistrationNotices($meta,$post),
			$meta,
			$post,
			$this->api_action
		);

		/**
		 * filter {classname}_client_{$type}_notice
		 *
		 * @param	string message
		 * @param	array registration
		 * @param	string action
		 * @return	string message
		 */
		foreach ($notices as $type=>$notice)
		{
			// success only if no error
			if ($type == 'success' && !empty($notices['error'])) continue;
			// warning/error only if populated.
			if ($type == 'error' || $type == 'warning')
			{
				if (empty($notices[$type])) continue;
			}
			$notices[$type] = $this->clientMessageMerge(
				$this->apply_filters("client_{$type}_notice", $notice, $meta, $post),
				$meta,
				$this->api_action,
				$notices[$type]
			);
		}

		/**
		 * filter {classname}_api_registration_message
		 *
		 * @param	string message
		 * @param	array registration
		 * @param	array post
		 * @param	string action
		 * @return	string message
		 */
		$default = $this->apply_filters('api_registration_message', '',
			$meta,
			$post,
			$this->api_action
		);

		/**
		 * filter {classname}_client_api_message
		 *
		 * @param	string message
		 * @param	array registration
		 * @param	string action
		 * @return	string message
		 */
		$message = $this->clientMessageMerge(
			$this->apply_filters('client_api_message', $default, $meta, $post),
			$meta,
			$this->api_action,
			$default
		);

		/**
		 * filter {classname}_api_registration_supplemental
		 *
		 * @param	mixed supplemental
		 * @param	array registration
		 * @param	array post
		 * @param	string action
		 * @return	mixed supplemental
		 */
		$default = $this->apply_filters('api_registration_supplemental', '',
			$meta,
			$post,
			$this->api_action
		);

		/**
		 * filter {classname}_client_api_supplemental
		 *
		 * @param	mixed supplemental
		 * @param	array registration
		 * @param	string action
		 * @return	mixed supplemental
		 */
		$supplemental = $this->clientMessageMerge(
			$this->apply_filters('client_api_supplemental', $default, $meta, $post),
			$meta,
			$this->api_action,
			$default
		);

		$refreshSchedule = sanitize_key(array_search($refreshInterval,$this->REGISTRY_REFRESH_INTERVALS) ?: 'other');

		$registrar 	= $this->getRegistrarOptions('api',$meta);

		return [
			'registration'			=> $meta,
			'registrar'				=> [
				'contact'			=> $this->getRegistrarOptions('api',$meta),
				'timezone' 			=> $this->currentTimezone->getName(),
				'locale' 			=> \get_locale(),
				'cacheTime'			=> $this->getRegistrarSetting('cache_time',$meta['registry_product']),
				'refreshInterval' 	=> $refreshInterval,
				'refreshSchedule' 	=> $refreshSchedule,
				'options'			=> $this->getRegistrarSetting('options',$meta['registry_product']),
				'licenseCodes'		=> $this->apply_filters('settings_license_levels',
													array_flip($this->REGISTRY_LICENSE_LEVEL)
										),
				'notices'			=> $notices,
				'message'			=> $message,
			],
			'registryHtml'			=> $this->getPostHtml($meta,'api'),
			'supplemental' 			=> $this->plugin->wp_kses($supplemental),
		];
	}


	/**
	 * get registration notices
	 *
	 * @param array $meta curent registry array
	 * @param WP_Post $post the registration post
	 * @return array notices
	 */
	private function getRegistrationNotices(array $meta, \WP_Post $post): array
	{
		$error = $info = $warning = $success = '';

		$product = '<em>'.$meta['registry_title'].'</em>';
		if ($post->post_status != 'publish') // not active registration
		{
			$error = sprintf(__('Your %s registration is currently %s.','softwareregistry'),$product,strtolower($meta['registry_status']));
		}
		else
		{
			$today 	= $this->getDateTimeClientZone('23:59:59 today');
			$update = false;
			if (isset($meta['registry_nextpay']) && !empty($meta['registry_nextpay']))
			{
				try {
					$update = $this->getDateTimeClientZone($meta['registry_nextpay'].' 23:59:59');
					$action = 'renew';
				} catch (\Throwable $e) { $update = false; }
			}
			if (!$this->hasTimezone($update))
			{
				$update = $this->getDateTimeClientZone($meta['registry_expires'].' 23:59:59');
				$action = (isset($meta['registry_autoupdate']) && $this->isTrue($meta['registry_autoupdate']))
					? 'update' : 'expire';
			}
			if ($update && $update >= $today)
			{
				$days = $today->diff($update)->format("%a");
				$date = $update->format("d-M-Y");
				$time = $update->format("g:ia (T)");
				if ($days < 1) {
					$error 		= sprintf(__('Your %s registration is expected to %s at %s.','softwareregistry'),$product,$action,$time);
				} else if ($days < 8) {
					$warning 	= sprintf(__('Your %s registration is expected to %s at %s on %s.','softwareregistry'),$product,$action,$time,$date);
				} else if ($days < 15) {
					$info 		= sprintf(__('Your %s registration is expected to %s at %s on %s.','softwareregistry'),$product,$action,$time,$date);
				}
			}
		}

		return [
			'info'		=> $info,
			'warning'	=> $warning,
			'error'		=> $error,
			'success'	=> $success,
		];
	}


	/*
	 *
	 * Software Registration get registration post
	 *
	 */


	/**
	 * get a post by name (registration key)
	 *
	 * @param string $key the registry_key
	 * @param bool $includeMeta get post_meta in meta_input
	 * @param array $metaQuery meta key=>value to match
	 * @return object WP_Post
	 */
	private function getRegistrationPostByKey($key, bool $includeMeta = false, array $metaQuery = [])
	{
		if (empty($key))
		{
			return $this->rest_error(400,"registration key is required");
		}

		return $this->getRegistrationPost('post_title',$key,$metaQuery,$includeMeta);
	}


	/**
	 * get a post by email
	 *
	 * @param string $key the registry_key
	 * @param bool $includeMeta get post_meta in meta_input
	 * @param array $metaQuery meta key=>value to match
	 * @return object WP_Post
	 */
	private function getRegistrationPostByEmail($email, bool $includeMeta = false, array $metaQuery = [])
	{
		if (empty($email))
		{
			return $this->rest_error(400,"registration email is required");
		}
		// change from post_excerpt to post_password
		return $this->getRegistrationPost('post_password',$email,$metaQuery,$includeMeta);
	}


	/**
	 * select a post by post field and meta value(s)
	 *
	 * @param string $postKey post field name
	 * @param string $postValue post field value
	 * @param array $metaQuery meta key=>value to match
	 * @param bool $includeMeta get post_meta in meta_input
	 * @return object WP_Post
	 */
	private function getRegistrationPost(string $postKey, $postValue, array $metaQuery = [], bool $includeMeta = false)
	{
		$join = $where = [];

		foreach ($metaQuery as $key => $value)
		{
			$join[] =
				"INNER JOIN ".$this->wpdb->postmeta." AS meta_{$key} ON ( posts.ID = meta_{$key}.post_id )\n" ;
			$where[] =
				"meta_{$key}.meta_key = '_{$key}' AND meta_{$key}.meta_value LIKE '%{$value}%'\n";

		}
		$join 	= (!empty($join)) ? ' '.implode(' ',$join) : '';
		$where 	= (!empty($where)) ? ' AND '.implode(' AND ',$where) : '';

		$sql =
			"SELECT * FROM ".$this->wpdb->posts." AS posts\n" .
			$join .
			" WHERE posts.post_type = '".self::CUSTOM_POST_TYPE."'\n" .
			" AND posts.{$postKey} = '{$postValue}'\n" .
			" AND posts.post_status NOT IN ('inherit','auto-draft')\n" .
			$where .
			" ORDER BY posts.post_modified DESC";

		$posts = $this->wpdb->get_results( $sql, OBJECT_K );

		//$this->logDebug([$sql,$posts],__METHOD__.' ('.current_action().')');

		if (! is_wp_error($posts) && ! empty($posts))
		{
			$post = new \WP_Post( reset($posts) );
			// not re-activating a terminated registration
			if ($this->api_action != 'activate' && $post->post_status == 'trash')
			{
				return $this->rest_error(410,"registration terminated");
			}
			if ($this->api_action == 'create' && $post->post_status == 'private')
			{
				return $this->rest_error(410,"registration inactive");
			}
			if ($includeMeta) {
				$post->meta_input = $this->getPostMetaValues($post->ID);
			}
			return $post;
		}

		return $this->rest_error(404,"registration post for '{$postValue}' not found");
	}


	/*
	 *
	 * Software Registration API source/action
	 *
	 */


	/**
	 * set api_source
	 *
	 * @param 	string $source
	 * @return 	string api_source
	 */
	public function setApiSource(string $source=null): string
	{
		if ($source) $this->api_source = $source;
		return $this->api_source;
	}


	/**
	 * set api_action
	 *
	 * @param 	string $action
	 * @return 	string api_action
	 */
	public function setApiAction(string $action=null): string
	{
		if ($action) $this->api_action = $action;
		return $this->api_action;
	}


	/**
	 * get api_action past-tense
	 *
	 * @param 	string $action
	 * @return 	string action past-tense
	 */
	public function getApiAction(string $action=null): string
	{
		if (!$action) $action = $this->api_action;
		switch ($action)
		{
			case 'created':
			case 'activated':
			case 'deactivated':
			case 'revised':
			case 'renewed':
			case 'refreshed':
			case 'verified':
			case 'updated':
			case 'expired':
				return $action;
			case 'verify':
				return 'verified';
			case 'renew':
			case 'refresh':
				return $action.'ed';
			default:
				return $action.'d';
		}
	}


	/*
	 *
	 * Software Registration API Error
	 *
	 */


	/**
	 * API error response
	 *
	 * @return object WP_Error
	 */
	private function rest_error(int $status, string $message=null, string $error=null)
	{
		if (empty($message)) {
			$message = strtolower(get_status_header_desc($status));
		}
		if (!empty($error)) {
			$message .=' -> '.$error;
		}
		$this->logError('Status: '.$status.' '.$message,__METHOD__);
		return new \wp_error(
			(string)$status,
			__(get_status_header_desc($status),'eacSoftwareRegistry'),
			['code'=>(string)$status,'message'=>__($message,'eacSoftwareRegistry')]
		);
	}
}
