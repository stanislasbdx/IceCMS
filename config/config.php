<?php 

/*
* IceCMS 1.3
* Author : Nicolas Bechelot
* Contributor : Stanislas Castaybert
*/

/*
 * General settings
 */
$config['site_title'] = 'IceCMS';			// Website title
$config['base_url'] = '';					// Base URL
$config['rewrite_url'] = false;				// A boolean indication that forces the rewriting of url

/*
 * Themes settings
 */
$config['theme'] = 'SquareCeption';			// Active Thème (Arch ; MaterializeSlim ; SimpleTwo ; SquareCeption)
$config['twig_config'] = array(   			// Twig settings
    'cache' => false,   					// Enable Twig cache to a writable directory
    'autoescape' => false,   				// Activating Twig auto-escape
    'debug' => false   						// Enable Twig debugging
);

/*
 * Content settings
 */
$config['date_format'] = '%D %T';    		// Sets the PHP date format
$config['pages_order_by'] = 'alpha';    	// Sort pages by "alpha" or "date".
$config['pages_order'] = 'asc';    			// Sort pages by "asc" or "desc".
$config['content_dir'] = 'content/';   		// Content directory (of pages)
$config['content_ext'] = '.md';    			// File extension of content files

/*
 * Time settings
 */
$config['timezone'] = '';    				// TimeZone [https://secure.php.net/manual/fr/timezones.php]

/*
 * Paramètre des plugin
 */
$config['IceEditor'] = array(
    'enabled'   => false,    				// Enable or disable the administrator page
    'password'  => '',   					// Site access password [Encoding in SHA 512].
    'url'       => 'admin'					// Access URL to the administrator page
);

$config['ice_minify'] = array(
    'minify' => true,
    'compress_css' => true,
    'compress_js' => true,
    'remove_comments' => true
);

/*
 * Custom settings
 */

// Configuration du thème
$config['background_image'] = '';
$config['favicon'] = '';

// Configuration des reseaux sociaux
$config['social_twitter_link'] = '';  
$config['social_facebook_link'] = '';
$config['social_twitch_link'] = ''; 
$config['social_github_link'] = '';  
