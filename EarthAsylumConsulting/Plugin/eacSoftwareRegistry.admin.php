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
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 * @version		25.0331.1
 */

trait eacSoftwareRegistry_administration
{
	/**
	 * @trait methods for html fields used externally
	 */
	use \EarthAsylumConsulting\Traits\html_input_fields;

	/**
	 * @trait standard options
	 */
	use \EarthAsylumConsulting\Traits\standard_options;

	/**
	 * @trait methods for contextual help tabs
	 */
	use \EarthAsylumConsulting\Traits\plugin_help;


	/**
	 * constructor method (for admin/backend)
	 *
	 * @access public
	 * @param array header passed from loader script
	 * @return void
	 */
	public function admin_construct(array $header)
	{
		$this->defaultTabs = ['general','tools'];

		register_activation_hook($header['PluginFile'],		'flush_rewrite_rules' );
		register_deactivation_hook($header['PluginFile'],	'flush_rewrite_rules' );

		// add settings menu to custom post type menu
		add_action( 'admin_menu',					array($this, 'admin_add_settings_menu') );

		// When this plugin is updated
		$this->add_action( 'version_updated', 		array($this, 'admin_plugin_updated'), 10, 2 );

		// Register plugin options
		$this->add_action( 'options_settings_page', array($this, 'admin_options_settings') );
		// Add contextual help
		$this->add_action( 'options_settings_help', array($this, 'admin_options_help'), 10, 0 );
	}


	/**
	 * Called after instantiating, loading extensions and initializing
	 *
	 * @return	void
	 */
	public function admin_addActionsAndFilters(): void
	{
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
	 * custom style and script
	 *
	 * @return void
	 */
	public function add_inline_scripts()
	{
		$styleId = $this->plugin->html_input_style();

		ob_start();
		?>
			*::placeholder {text-align: right;}
			#minor-publishing {display: none;}
			#local-storage-notice {display: none !important;}
			.settings-grid-item {padding: .5em 1.25em .5em 0}
			.column-title {width: 23%;}
			.column-comments {width: 3em !important;}
			.column-registry_email {width: 20%;}
			.column-registry_product {width: 15%;}
			.column-registry_transid {width: 4.2em;}
			.column-registry_transid span.text {display:none}
			.column-registry_transid span.dashicons {display: inline; padding: 0;}
			.column-registry_status {width: 6.5em;}
		<?php
		$style = ob_get_clean();
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


	/**
	 * register options on options_settings_page
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options_settings()
	{
		require "includes/eacSoftwareRegistry.options.php";
	}


	/**
	 * Add help tab on admin page
	 *
	 * @return	void
	 */
 	public function admin_options_help($tab='General')
	{
		require "includes/eacSoftwareRegistry.help.php";
	}


	/*
	 *
	 * Custom Post Type - 'softwareregistry'
	 *
	 */


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
	 * Custom columns in the 'All Registrations' list
	 */
	public function custom_post_columns($columns,$cpt=null)
	{
		unset( $columns['author'] );
		unset( $columns['date'] );
		return array_merge($columns,
			[
				'title' 			=> __('Registry Key', 'eacSoftwareRegistry'),
				'registry_email' 	=> __('EMail', 'eacSoftwareRegistry'),
				'registry_product' 	=> __('Product', 'eacSoftwareRegistry'),
				'registry_transid' 	=> '<span class="text">'.__('External Transaction', 'eacSoftwareRegistry').'</span>'.
									   '<span class="dashicons dashicons-external" title="External Transaction"></span>',
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
				$excerpt = get_post($post_id)->post_excerpt;
				echo "<span title='{$excerpt}'>".get_post_meta($post_id, "_{$column}", true);
				$value = get_post_meta($post_id, "_registry_company", true) ?: get_post_meta($post_id, "_registry_name", true);
				echo ' ('.str_replace(' ','&nbsp;',$value).')</span>';
				break;
			case 'registry_transid':
				$value = get_post_meta($post_id, "_{$column}", true);
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
				'info'		=>	'<small style="margin-left:-4ch">change may alter dates</small>',
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
				'default'	=>	'Update &amp; Send to Client',
				'style'		=>	'text-wrap: wrap; text-wrap: pretty; line-height: 1.2;',
				'class'		=>	'button-primary',
			],
		);

		echo "<div class='settings-grid-container' style='grid-template-columns: 8em auto;'>\n";
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

		echo "<div class='settings-grid-container' style='grid-template-columns: 8em auto;'>\n";
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

		echo "<div class='settings-grid-container' style='grid-template-columns: 30% 70%;'>\n";
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

			$fieldMeta['tooltip'] = false; // don't allow auto info-> tooltip
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
		foreach ( ['hourly_event','daily_event','weekly_event'] as $eventName)
		{
			$eventName = $this->prefixHookName($eventName);
			wp_unschedule_hook($eventName);
		}
	}

}
