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
 * @version		24.1125.1
 */

defined( 'ABSPATH' ) or exit;

/*
 * Copy this file to the /eacSoftwareRegistry/templates/ folder of your child theme.
 *
 * @variables (formatted text)
 * 		$stylesheet 	- inline css, default or eacSoftwareRegistry_client_email_style filter
 * 		$message		- customer message, default or eacSoftwareRegistry_client_email_message filter
 * 		$signature		- registrar contact/signature block
 * 		$notices		- registration notices [error,warning,notice,success]
 * 		$registration	- registration html table
 * 		$footer			- customer footer, default or eacSoftwareRegistry_client_email_footer filter
 * 		$context 		- 'created'|'activated'|'deactivated'|'revised'|'renewed'|'refreshed'|'verified'|'updated'
 * @variables (structured)
 *		$this			- eacSoftwareRegistry plugin
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
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<style type="text/css" media="all">
		<?php echo $stylesheet ?>
	</style>
</head>

<body marginwidth='0' topmargin='0' marginheight='0' class='softwareregistry-email'>
	<div id="softwareregistry-wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

		<section class='message'>
			<?php echo wptexturize($this->wp_kses($message)) ?>
			<address>
				<?php echo wptexturize($signature) ?>
			</address>
		</section>

		<p>Registration Details:</p>

		<div class='notices'>
			<?php echo $this->wp_kses($notices) ?>
		</div>

		<section class='registry'>
			<?php echo $this->wp_kses($registration) ?>
		</section>

		<footer>
			<?php echo wptexturize($this->wp_kses($footer)) ?>
		</footer>

	</div>
</body>
</html>
