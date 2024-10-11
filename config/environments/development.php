<?php
/**
 * Configuration overrides for WP_ENV === 'development'
 */

use Roots\WPConfig\Config;
use function Env\env;

Config::define('SAVEQUERIES', true);

define('WP_DEBUG', true);  // Enable debugging
define('WP_DEBUG_LOG', true);  // Enable logging to a file
define('WP_DEBUG_DISPLAY', false);  // Prevent errors from showing on the frontend
@ini_set('display_errors', 0);  // Suppress PHP error display


Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);

Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);

Config::define('DISALLOW_INDEXING', true);


// Enable plugin and theme updates and installation from the admin
Config::define('DISALLOW_FILE_MODS', false);
