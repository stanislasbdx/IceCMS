<?php 

/*
* IceCMS 1.2
* Autheur : Nicolas Bechelot
* Contributeur : Stanislas Castaybert
*/

/*
 * Paramètres de base
 */
$config['site_title'] = 's';    // Titre du site
$config['base_url'] = '';    // Url de base
$config['rewrite_url'] = false;   // Une indication booléenne qui force la réecriture d'url

/*
 * Paramètre des thème
 */
$config['theme'] = 'SquareCeption';   // Thème actif (Arch ; MaterializeSlim ; SimpleTwo ; SquareCeption)
$config['twig_config'] = array(   // Paramètre de Twig
	'cache' => false,   // Activer le cache Twig vers un répertoire inscriptible
	'autoescape' => false,   // Activer l'Auto-escape Twig
	'debug' => false   // Activer le debug Twig
);

/*
 * Paramètre du contenu
 */
$config['date_format'] = '%D %T';    // Définit le format de date PHP
$config['pages_order_by'] = 'alpha';    // Trier les page par "alpha" ou "date"
$config['pages_order'] = 'asc';    // Trier les page par "asc" ou "desc"
$config['content_dir'] = 'content/';    // Répertoire de contenu (des pages)
$config['content_ext'] = '.md';    // Extension des fichiers de contenu

/*
 * Paramètre du temps
 */
$config['timezone'] = 'Europe/Paris';    // TimeZone (nécessaire en php5) [https://secure.php.net/manual/fr/timezones.php]

/*
 * Paramètre des plugin
 */
$config['IceEditor'] = array(
    'enabled'   => true,    // Active ou non la page administrateur
    'password'  => '',    // Mot de passe d'accès au site [Encodage en SHA 512]
    'url'       => 'admin'
);

$config['ice_minify'] = array(
    'minify' => true,
    'compress_css' => true,
    'compress_js' => true,
    'remove_comments' => true
);

/*
 * Paramètre custom
 */

// Configuration du thème
$config['background_image'] = '';
$config['favicon'] = '';

// Configuration des reseaux sociaux
$config['social_twitter_link'] = '';  
$config['social_facebook_link'] = '';
$config['social_twitch_link'] = ''; 
$config['social_github_link'] = '';  
