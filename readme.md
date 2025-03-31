## {eac}SoftwareRegistry Software Registration Server  
[![EarthAsylum Consulting](https://img.shields.io/badge/EarthAsylum-Consulting-0?&labelColor=6e9882&color=707070)](https://earthasylum.com/)
[![WordPress](https://img.shields.io/badge/WordPress-Plugins-grey?logo=wordpress&labelColor=blue)](https://wordpress.org/plugins/search/EarthAsylum/)
[![eacDoojigger](https://img.shields.io/badge/Requires-%7Beac%7DDoojigger-da821d)](https://eacDoojigger.earthasylum.com/)

<details><summary>Plugin Header</summary>

Plugin URI:             https://swregistry.earthasylum.com/  
Author:                 [EarthAsylum Consulting](https://www.earthasylum.com)  
Stable tag:             1.4.3  
Last Updated:           31-Mar-2025  
Requires at least:      5.8  
Tested up to:           6.8  
Requires EAC:           3.1  
Requires PHP:           7.4  
Contributors:           [earthasylum](https://github.com/earthasylum),[kevinburkholder](https://profiles.wordpress.org/kevinburkholder)  
License:                EarthAsylum Consulting Proprietary License - {eac}PLv1  
License URI:            https://swregistry.earthasylum.com/end-user-license-agreement/  
Tags:                   software registration, software registry, software license, license manager, registration API  
GitHub URI:             https://github.com/EarthAsylum/eacSoftwareRegistry  

</details>

> {eac}SoftwareRegistry - A feature-rich and easily customized software registration and licensing server for WordPress.

### Description

#### Summary

**{eac}SoftwareRegistry** is a WordPress software licensing and registration server with an easy to use API for
creating, activating, deactivating, and verifying software registration keys.

Registration keys may be created and updated through the administrator pages in WordPress,
but the system is far more complete when your software package implements the {eac}SoftwareRegistry API
to manage the registration.

One of two scenarios typically occur when a client receives your software:

1.  The client purchases your software, registers your software, then installs your software.

    With {eac}SoftwareRegistry, a new registration key may be created through the purchase process
    (or manually by the administrator) and then the client may enter the registration key
    and activate the registration when installing the software.

2.  The client downloads your software, installs your software, and then registers your software.

    The client is presented with a "new registration" screen when installing the software and may request
    a new registration key through the API which will automatically generate the key and activated the registration.

Registration keys may be verified via API on a scheduled basis so that any updates made by the administrator, via
other transaction, or due to renewal or expiration, are updated in the client software.

Registration status may be:

+   Pending (awaiting approval)
+   Trial (limited time trial period)
+   Active
+   Inactive
+   Expired
+   Terminated

Registrations may include (but do not require):

+   Number of users/sites/devices.
+   License level (i.e. 'basic', 'pro').
+   Valid domain(s).
+   Valid site URL(s).
+   Software product-specific options and variations.


#### {eac}SoftwareRegistry Administration

Through the {eac}SoftwareRegistry Administration screen in WordPress, you, the administrator, can create new registration keys, set or change the registration status, set effective and expiration dates, and manage other details of the registration.

#### {eac}SoftwareRegistry API

The built-in Application Program Interface (API) is a relatively simple method for your software package to communicate with your software registration server ({eac}SoftwareRegistry), automating nearly all aspects of the software registration life-cycle.

See the [API Details](#api-details) section.

#### {eac}SoftwareRegistry Extensions

Several extension plugins are available for {eac}SoftwareRegistry making it a complete and custom solution for your software registration needs. These extension plugins are *free* to all {eac}SoftwareRegistry users. Simply choose the extensions you need for your licensing and registration server.

*   **{eac}SoftwareRegistry Distribution SDK**

The added [{eac}SoftwareRegistry Software Distribution Development Kit (SDK)](https://swregistry.earthasylum.com/software-registry-sdk/) extension makes it even easier to implement software registrations in your software package.

With the inclusion of the SDK generated for your package, you may be able to implement the APIs for {eac}SoftwareRegistry in minutes with only a small amount of code.

The SDK includes everything needed to create, activate, deactivate, revise, and refresh a registration.
It also includes the storing of the registration key, the caching of the registration data, and the scheduling of verification requests.

Both WordPress and non-WordPress projects are supported by the SDK.


*   **{eac}SoftwareRegistry Software Taxonomy**

The [{eac}SoftwareRegistry Software Taxonomy](https://swregistry.earthasylum.com/software-taxonomy/) extension
is a simple plugin extension that allows you to set and override {eac}SoftwareRegistry options for specific software products. It both defines the software product as well as the server parameters used when that product is registered via your software registration api. Additionally, you may customize client emails and notifications as well as license-level restrictions. Version 2.0+ supports "self-hosted" plugins on Github providing plugin information and automated updates in WordPress built directly from the plugin `readme.txt` file and the Github repository.

*   **{eac}SoftwareRegistry Custom Hooks**

With the [{eac}SoftwareRegistry Custom Hooks](https://swregistry.earthasylum.com/software-registry-hooks/) extension,
you can add custom PHP code for the many hooks (filters and actions) available in the server software.
With these hooks you can customize the registry server options, incoming API requests, outgoing API responses, and client emails and notifications.


*   **{eac}SoftwareRegistry and WooCommerce**

With the added [{eac}SoftwareRegistry WebHooks for WooCommerce](https://swregistry.earthasylum.com/webhooks-for-woocommerce/)
extension, software registrations can be created, updated, and/or terminated via [WebHooks](https://woocommerce.com/document/webhooks/) from WooCommerce.

Simply create the needed webhooks in the WooCommerce administration (order created, order updated, order deleted, order restored) and enable the corresponding end-points in {eac}SoftwareRegistry.

When an order placed on your WooCommerce site is created, the registration will be created on your software registration server. If the customer updates or cancels their order, their registration will also be updated or cancelled.

Your WooCommerce and registration server do not need to be the same server and neither needs to be running the other software. In fact, you can have multiple WooCommerce sites all sending webhook updates to your registration server.


*   **{eac}SoftwareRegistry Subscriptions for WooCommerce**

Go one step further by adding the [{eac}SoftwareRegistry Subscriptions for WooCommerce](https://swregistry.earthasylum.com/subscriptions-for-woocommerce/)
plugin to your WooCommerce store site and subscription updates will also be passed to your registration server keeping your registrations updated by your WooCommerce subscription renewals.


### API Details

First, from the *Distribution* tab of the *Software Registry* Settings, you will need your *API keys* and *Endpoint URL*.

#### API parameters

API parameters are passed as an array:

    $apiParams =
    [
        'registry_key'          => 'unique ID',                     // * registration key (assigned by registry server)
        'registry_name'         => 'Firstname Lastname',            //   registrant's full name
        'registry_email'        => 'email@domain.com',              // * registrant's email address
        'registry_company'      => 'Comapny/Organization Name',     //   registrant's company name
        'registry_address'      => 'Street\n City St Zip',          //   registrant's full address (textarea)
        'registry_phone'        => 'nnnnnnnnnn',                    //   registrant's phone
        'registry_product'      => 'productId',                     // * your product name/id ((your_productid))
        'registry_title'        => 'Product Title',                 //   your product title
        'registry_description'  => 'Product Description',           //   your product description
        'registry_version'      => 'M.m.p',                         //   your product version
        'registry_license'      => 'Lx',                            //   'L1'(Lite), 'L2'(Basic), 'L3'(Standard), 'L4'(Professional), 'L5'(Enterprise), 'LD'(Developer), 'LU'(Unlimited)
        'registry_count'        => int,                             //   Number of licenses (users/seats/devices)
        'registry_variations'   => array('name'=>'value',...),      //   array of name/value pairs
        'registry_options'      => array('value',...),              //   array of registry options
        'registry_domains'      => array('domain',...),             //   array of valid/registered domains
        'registry_sites'        => array('url',...),                //   array of valid/registered sites/uris
        'registry_transid'      => '',                              //   external transaction id
        'registry_timezone'     => '',                              //   standard timezone string (client timezone)
    ];

#### API Remote Request

Example code to execute the remote request

    /**
     * remote API request - builds request array and calls api_remote_request
     *
     * @param   string  $endpoint create, activate, deactivate, revise, verify
     * @param   array   $params api parameters
     * @return  object api response (decoded)
     */
    public function registryApiRequest($endpoint,$params)
    {
        $endpoint = strtolower($endpoint);
        switch ($endpoint)
        {
            case 'create':
                $apiKey = "<Registration Creation Key>";
                $method = 'PUT';
                break;
            case 'deactivate':
                $apiKey = "<Registration Update Key>";
                $method = 'DELETE';
                break;
            case 'verify':
                $apiKey = "<Registration Read Key>";
                $method = (count($params) > 1) ? 'POST' : 'GET';
                break;
            default:
                $apiKey = $this->getApiUpdateKey();  
                $method = (count($params) > 1) ? 'POST' : 'GET';
                break;
        }

        $request = [
            'method'        => $method,
        ];

        $request['headers'] = [
            'Accept'        => 'application/json',
            'Referer'       =>  sprintf('%s://%s%s', isset($_SERVER['HTTPS']) ? 'https' : 'http', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']),
            'Authorization' => 'Bearer '.base64_encode($apiKey),
        ];
        if (in_array($method,['GET','HEAD','DELETE'])) {
            $request['headers']['Content-Type'] = 'text/plain';
            $remoteUrl = "<API Endpoint URL>".'/'.$endpoint .'?'. http_build_query($params);
        } else {
            $request['headers']['Content-Type'] = 'application/json';
            $request['body'] = json_encode($params);
            $remoteUrl = "<API Endpoint URL>".'/'.$endpoint;
        }

        $response =  $this->api_remote_request($endpoint,$remoteUrl,$request);

        if ($response->status->code == '200') && $endpoint != 'deactivate' && isset($response->registration))
        {
            // update the current registration cache (save the registration object and key)
            $this->setRegistrationCache($response);
            // schedule the next refresh event
            $this->scheduleRegistryRefresh($response->registrar->refreshInterval,$response->registrar->refreshSchedule,$response->registration);
        }
        return $response;
    }


    /**
     * API remote request - remote http request (wp_remote_request or curl)
     *
     * @param   string  $endpoint create, activate, deactivate, verify
     * @param   string  $remoteUrl remote Url
     * @param   array   $request api request
     * @return  object api response (decoded)
     */
    public function api_remote_request($endpoint,$remoteUrl,$request)
    {
        $result = wp_remote_request($remoteUrl,$request);
        $body   = json_decode(wp_remote_retrieve_body($result));
        if (!empty($body) && isset($body->code) && isset($body->message)) {
            $result = new \wp_error($body->code,$body->message,$body->data);
        }

        if (is_wp_error($result))
        {
            $code   = $result->get_error_data() ?: [];
            $code   = $code->status ?? $result->get_error_code();
            $msg    = $result->get_error_message();
            $error  = json_decode('{"status":{"code":"'.$code.'","message":"'.addslashes($msg).'"},'.
                                 '"error":{"code":"'.$code.'","message":"'.addslashes($msg).'"}}');
            return $error;
        }

        return $body;
    }

#### Using registryApiRequest()

Create/request a new registration...

    $response = $this->registryApiRequest('create',$apiParams);

Activate an existing registration...

    $response = $this->registryApiRequest('activate',['registry_key' => "<registration_key>"]);

Deactivate an existing registration...

    $response = $this->registryApiRequest('deactivate',['registry_key' => "<registration_key>"]);

Verify or Refresh an existing registration...

    $response = $this->registryApiRequest('verify',['registry_key' => "<registration_key>"]);
    $response = $this->registryApiRequest('refresh',$apiParams);

Revise an existing registration...

    $response = $this->registryApiRequest('revise',$apiParams);

#### API Remote Response

The API response is a standard object. status->code is an http status, 200 indicating success.

    status      ->
    (
        'code'                  -> 200,             // HTTP status code
        'message'               -> '(action) ok'    // (action) = 'create', 'activate', 'deactivate', 'verify', 'revise'
    ),
    registration ->
    (
        'registry_key'          -> string           // UUID,
        'registry_status'       -> string,          // 'pending', 'trial', 'active', 'inactive', 'expired', 'terminated', 'invalid'
        'registry_effective'    -> string,          // DD-MMM-YYYY effective date
        'registry_expires'      -> string,          // DD-MMM-YYYY expiration date
        'registry_name'         -> string,
        'registry_email'        -> string,
        'registry_company'      -> string,
        'registry_address'      -> string,
        'registry_phone'        -> string,
        'registry_product'      -> string,
        'registry_title'        -> string,
        'registry_description'  -> string,
        'registry_version'      -> string,
        'registry_license'      -> string,
        'registry_count'        -> int,
        'registry_variations'   -> array,
        'registry_options'      -> array,
        'registry_domains'      -> array,
        'registry_sites'        -> array,
        'registry_transid'      -> string,
        'registry_timezone'     -> string,
        'registry_valid'        -> bool,            // true/false
    ),
    registrar ->
    (
        'contact'               -> object(
            'name'              -> string           // Registrar Name
            'email'             -> string           // Registrar Support Email
            'phone'             -> string           // Registrar Telephone
            'web'               -> string           // Registrar Web Address
        ),
        'cacheTime'             -> int,             // in seconds, time to cache the registration response (Default Cache Time)
        'refreshInterval'       -> int,             // in seconds, time before refreshing the registration (Default Refresh Time)
        'refreshSchedule'       -> string,          // 'hourly','twicedaily','daily','weekly' corresponding to refreshInterval
        'options'               -> array(           // from settings page, registrar_options (Allow API to...)
            'allow_set_key',
            'allow_set_status',
            'allow_set_effective',
            'allow_set_expiration',
            'allow_activation_update'
        ),
        'notices'               -> object(
            'info'              -> string,          // information message text
            'warning'           -> string,          // warning message text
            'error'             -> string,          // error message text
            'success'           -> string,          // success message text
        ),
        'message'               -> string,          // html message
    ),
    registryHtml                -> string,          // html (table) of human-readable registration values
    supplemental                -> mixed,           // supplemental data/html assigned via filters (developer's discretion).

#### Software Distribution Development Kit

All of this code, and more, is included in the [{eac}SoftwareRegistry Distribution SDK](https://swregistry.earthasylum.com/software-registry-sdk/)
making it very easy to implement the API into your WordPress plugin, theme, or any PHP software package.


### Installation

{eac}SoftwareRegistry is a derivative plugin of and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

#### Automatic Plugin Installation

Due to the nature of this plugin, it is NOT available from the WordPress Plugin Repository and can not be installed from the WordPress Dashboard » *Plugins* » *Add New* » *Search* feature.

#### Upload via WordPress Dashboard

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacSoftwareRegistry.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

#### Manual Plugin Installation

You can install the plugin manually by extracting the eacSoftwareRegistry.zip file and uploading the 'eacSoftwareRegistry' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

#### Activation

On activation, custom tables and default settings/options are created. Be sure to visit the 'Settings' page to ensure proper configuration.

_{eac}SoftwareRegistry should NOT be Network Activated on multi-site installations._

#### Updates

Updates are managed from the WordPress Dashboard » 'Plugins' » 'Installed Plugins' page. When a new version is available, a notice is presented under this plugin. Clicking on the 'update now' link will install the update; clicking on the 'View details' will provide more information on the update from which you can click on the 'Install Update Now' button.

When updated, any custom tables and/or option changes are applied. Be sure to visit the 'Settings' page.

#### Deactivation

On deactivation, the plugin makes no changes to the system but will not be loaded until reactivated.

#### Uninstall

When uninstalled, the plugin will delete custom tables, settings, and transient data based on the options selected in the general settings. If settings have been backed up, the backup is retained and can be restored if/when re-installed. Tables are not backed up.


### Screenshots

1. {eac}SoftwareRegistry General Settings
![{eac}SoftwareRegistry General Settings](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-1.png)

2. {eac}SoftwareRegistry Tools
![{eac}SoftwareRegistry Tools](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-2.png)

3. {eac}SoftwareRegistry Distribution
![{eac}SoftwareRegistry Distribution](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-3.png)

4. {eac}SoftwareRegistry Hooks
![{eac}SoftwareRegistry Hooks](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-4.png)

5. {eac}SoftwareRegistry Woocommerce
![{eac}SoftwareRegistry Woocommerce](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-5.png)

6. {eac}SoftwareRegistry Registration
![{eac}SoftwareRegistry Registration](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-6.png)

7. {eac}SoftwareRegistry Add New Registration
![{eac}SoftwareRegistry Add New Registration](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-7.png)

8. {eac}SoftwareRegistry All Registration
![{eac}SoftwareRegistry All Registrations](https://swregistry.earthasylum.com/software-updates/eacsoftwareregistry/assets/screenshot-8.png)


### Other Notes

#### Additional Information

{eac}SoftwareRegistry is a derivative plugin of and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

#### See Also

+   [Implementing the Software Registry SDK](https://swregistry.earthasylum.com/software-registry-sdk/)
+   [{eac}SoftwareRegistry Software Taxonomy](https://swregistry.earthasylum.com/software-taxonomy/)
+   [{eac}SoftwareRegistry Custom Hooks](https://swregistry.earthasylum.com/software-registry-hooks/)
+   [{eac}SoftwareRegistry WebHooks for WooCommerce](https://swregistry.earthasylum.com/webhooks-for-woocommerce/)
+   [{eac}SoftwareRegistry Subscriptions for WooCommerce](https://swregistry.earthasylum.com/subscriptions-for-woocommerce/)


### Upgrade Notice

Requires {eac}Doojigger version 3.1+


