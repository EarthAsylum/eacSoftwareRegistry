<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server
 *
 * email notification inline css
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
 * @variables
 * 		$context 		- 'client'|'admin'
 *
 */

// Default colors, override in Appearance->Customize->Additonal CSS...
?>
:root {
 	--eac-email-bg: #f7f7f7;
	--eac-email-body: #fff;
	--eac-email-base: #6e9882;
	--eac-email-text: #3c3c3c;
}
<?php

// get WooCommerce or default colors
$bg        = get_option( 'woocommerce_email_background_color', 		'var(--eac-email-bg)' );
$body      = get_option( 'woocommerce_email_body_background_color', 'var(--eac-email-body)' );
$base      = get_option( 'woocommerce_email_base_color', 			'var(--eac-email-base)' );
$text      = get_option( 'woocommerce_email_text_color', 			'var(--eac-email-text)' );

?>

* {
	font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; color: <?php echo $text ?>;
}
body {
	width: 85%; max-width: 720px;
	margin: .5em auto;
	background-color: <?php echo $bg ?>;
	padding: 1em;
	line-height: 1.5;
}
section {
	background-color: <?php echo $body ?>;
	padding: .5em; margin: 1em 0;
}
section.message {
	border-bottom: 1px solid <?php echo $base ?>;
}
section address, section address a[href] {
	color: <?php echo $base ?>; font-weight: 400;
}
section.registry {
	border-radius: 4px; border: 1px solid #ccc; border-left: 10px solid <?php echo $base ?>; border-right: 10px solid <?php echo $base ?>;
}
section.registry table, section.registry td, section.registry td em {
	font-family: Consolas,Monaco,'Andale Mono','Ubuntu Mono',monospace; color: #3c3c3c;
	border: none; text-align: left;
}
div.notices {
	font-style: italic;
}
p.notice:before {
	content: '• ';
}
.alignLeft {
	display: inline-block;
	text-align: left;
}
.alignRight {
	display: inline-block;
	text-align: right;
	float: right;
}
.alignRight a[href] {
	color: <?php echo $base ?>;
	font-weight: 400; font-size: 0.9em;
	vertical-align: middle;
}
.icon {
	font-family: 'Material Icons';
	display: inline-block;
	vertical-align: middle;
	width: 3em;
}
footer {
	margin-top: 3em;
	border-top: 1px solid <?php echo $base ?>;
}
code {
	color: <?php echo $base ?>;
	font-weight: 600;
	letter-spacing: 1px;
}
var {
	color: <?php echo $base ?>;
}
