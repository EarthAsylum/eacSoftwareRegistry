<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * customer email notification template
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		24.0415.1
 */

defined( 'ABSPATH' ) or exit;

/*
 * Copy this file to the /eacSoftwareRegistry/templates/ folder of your child theme.
 *
 * @variables (formatted text)
 * 		$stylesheet 	- inline css, default or eacSoftwareRegistry_admin_email_style filter
 * 		$message		- administrator message, default or eacSoftwareRegistry_administrator_email_message filter
 * 		$registration	- registration html table
 * 		$source 		- 'Requested from {host}'
 * 		$footer			- administrator footer, default
 * 		$api_source 	- 'api'|'webhook'
 * 		$emailSent		- 'Email notification was sent to client'
 * 		$context 		- 'created'|'activated'|'deactivated'|'revised'|'renewed'|'refreshed'|'verified'|'updated'
 *		$editPostURL	- WordPress URL to post editor
 * @variables (structured)
 * 		$post			- registration post
 * 		$meta			- registration post meta data
 * 		$registrar		- array of registrar options
 * 		$headers 		- array of wp_mail headers (from,to,subject,content-type)
 */

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<title><?php echo get_bloginfo( 'name', 'display' ); ?></title>
	<style type="text/css" media="all">
		<?php echo $stylesheet ?>
	</style>
</head>

<body marginwidth='0' topmargin='0' marginheight='0' class='softwareregistry-email'>
	<div id="softwareregistry-wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

		<section class='message'>
			<?php echo wp_kses_post($message) ?>
		</section>

		<p>
			<span class='alignLeft'>Registration Details:</span>
			<span class='alignRight'><a href='<?php echo $editPostURL ?>'>Edit Registration</a></span>
		</p>

		<section class='registry'>
			<?php echo wp_kses_post($registration) ?>
		</section>

		<div class='notices'>
			<p><?php echo esc_html($source) ?> via <?php echo esc_html($api_source) ?></p>
			<p><?php echo esc_html($emailSent) ?></p>
		</div>

		<footer>
			<?php echo wp_kses_post($footer) ?>
		</footer>

	</div>
</body>
</html>
