<?php

/**
 * Plugin Name: Mini Plugin
 * Plugin URI: https://github.com/guilamu/mini-plugin
 * Description: A minimal WordPress plugin to demonstrate proper plugin description and changelog display for GitHub-hosted plugins.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: miniplugin
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/mini-plugin
 *
 * @package MiniPlugin
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Plugin version constant.
define('MINIPLUGIN_VERSION', '1.0.0');

// Plugin directory path.
define('MINIPLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Plugin directory URL.
define('MINIPLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the Plugin Info class for GitHub-hosted plugin details.
require_once MINIPLUGIN_PLUGIN_DIR . 'includes/class-plugin-info.php';

// Initialize the Plugin Info handler.
new MiniPlugin_Plugin_Info();

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function miniplugin_init()
{
    // Load text domain for translations.
    load_plugin_textdomain('miniplugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'miniplugin_init');

/**
 * Display admin notice on activation.
 *
 * @since 1.0.0
 */
function miniplugin_activation_notice()
{
    if (get_transient('miniplugin_activated')) {
?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Mini Plugin has been activated successfully!', 'miniplugin'); ?></p>
        </div>
<?php
        delete_transient('miniplugin_activated');
    }
}
add_action('admin_notices', 'miniplugin_activation_notice');

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 */
function miniplugin_activate()
{
    set_transient('miniplugin_activated', true, 30);
}
register_activation_hook(__FILE__, 'miniplugin_activate');

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 */
function miniplugin_deactivate()
{
    // Cleanup if needed.
}
register_deactivation_hook(__FILE__, 'miniplugin_deactivate');
