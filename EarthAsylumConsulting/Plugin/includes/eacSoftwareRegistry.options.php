<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * administrator options/settings
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		24.0414.1
 */

defined( 'ABSPATH' ) or exit;

// format the h1 title, add documentation link buttons

$this->add_filter("options_form_h1_html", function($h1)
	{
		return 	"<div id='settings_banner'>".
				$this->formatPluginHelp($h1).
				"<div id='settings_info'>".

				$this->getDocumentationLink(true,'/software-registration-server',"<span class='dashicons dashicons-editor-help button eac-logo-green'></span>").
				"&nbsp;&nbsp;&nbsp;".

				"<a href='".network_admin_url('/plugin-install.php?s=earthasylum&tab=search&type=term')."' title='Plugins from EarthAsylum Consulting'>".
				"<span class='dashicons dashicons-admin-plugins button eac-logo-green'></span></a>".
				"&nbsp;&nbsp;&nbsp;".

				"<a href='https://earthasylum.com' title='About EarthAsylum Consulting'>".
				"<span class='dashicons dashicons-admin-site-alt3 button eac-logo-green'></span></a>".

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
