<?php

/**
 * Plugin Info class for handling GitHub-hosted plugin details.
 *
 * This class hooks into WordPress plugins_api to provide plugin information
 * from the README.md file when users click "View details" in the plugins list.
 *
 * @package MiniPlugin
 * @since   1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class MiniPlugin_Plugin_Info
 *
 * Handles plugin information display and update checking for GitHub-hosted plugins.
 *
 * @since 1.0.0
 */
class MiniPlugin_Plugin_Info
{

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug = 'miniplugin';

    /**
     * Plugin file path relative to plugins directory.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * GitHub repository owner.
     *
     * @var string
     */
    private $github_owner = 'guilamu';

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $github_repo = 'mini-plugin';

    /**
     * Cached plugin data.
     *
     * @var array|null
     */
    private $plugin_data = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Dynamically determine the plugin file path.
        $this->plugin_file = plugin_basename(MINIPLUGIN_PLUGIN_DIR . 'miniplugin.php');

        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_thickbox'));
    }

    /**
     * Get plugin data from the main plugin file.
     *
     * @since 1.0.0
     * @return array Plugin data.
     */
    private function get_plugin_data()
    {
        if (null === $this->plugin_data) {
            if (! function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->plugin_data = get_plugin_data(MINIPLUGIN_PLUGIN_DIR . 'miniplugin.php');
        }
        return $this->plugin_data;
    }

    /**
     * Filter the plugins_api response to provide plugin information.
     *
     * @since 1.0.0
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public function plugin_info($result, $action, $args)
    {
        // Only handle plugin_information requests for our plugin.
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (! isset($args->slug) || $this->plugin_slug !== $args->slug) {
            return $result;
        }

        $plugin_data = $this->get_plugin_data();
        $readme_data = $this->parse_readme();

        $info = new stdClass();

        // Basic plugin info.
        $info->name           = $plugin_data['Name'];
        $info->slug           = $this->plugin_slug;
        $info->version        = $plugin_data['Version'];
        $info->author         = $plugin_data['Author'];
        $info->author_profile = $plugin_data['AuthorURI'];
        $info->requires       = $plugin_data['RequiresWP'];
        $info->tested         = get_bloginfo('version'); // Tested up to current WP version.
        $info->requires_php   = $plugin_data['RequiresPHP'];
        $info->homepage       = $plugin_data['PluginURI'];

        // Use description from README, fallback to plugin header description.
        $description = ! empty($readme_data['description'])
            ? $readme_data['description']
            : '<p>' . esc_html($plugin_data['Description']) . '</p>';

        // Sections from README.
        $info->sections = array(
            'description'  => $description,
            'installation' => $readme_data['installation'],
            'changelog'    => $readme_data['changelog'],
            'faq'          => $readme_data['faq'],
        );

        // Remove empty sections.
        $info->sections = array_filter($info->sections);

        // Download link (GitHub releases).
        $info->download_link = sprintf(
            'https://github.com/%s/%s/releases/latest/download/%s.zip',
            $this->github_owner,
            $this->github_repo,
            $this->github_repo
        );

        // Additional info.
        $info->banners = array(
            'low'  => '',
            'high' => '',
        );

        $info->icons = array(
            '1x' => '',
            '2x' => '',
        );

        // Last updated.
        $info->last_updated = gmdate('Y-m-d H:i:s');

        // Number of active installs (unknown for GitHub plugins).
        $info->active_installs = 0;

        // Mark as external to prevent wordpress.org API calls.
        $info->external = true;

        // Check if plugin is installed and active - this controls the button display.
        if (function_exists('is_plugin_active')) {
            $info->installed = true;
            $info->active    = is_plugin_active($this->plugin_file);
        }

        return $info;
    }

    /**
     * Parse the README.md file to extract sections.
     *
     * @since 1.0.0
     * @return array Parsed sections.
     */
    private function parse_readme()
    {
        $sections = array(
            'description'  => '',
            'installation' => '',
            'changelog'    => '',
            'faq'          => '',
        );

        $readme_file = MINIPLUGIN_PLUGIN_DIR . 'README.md';

        if (! file_exists($readme_file)) {
            return $sections;
        }

        $content = file_get_contents($readme_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if (empty($content)) {
            return $sections;
        }

        // Convert Markdown to HTML.
        $content = $this->markdown_to_html($content);

        // Extract sections.
        $sections['description']  = $this->extract_section($content, 'Description');
        $sections['installation'] = $this->extract_section($content, 'Installation');
        $sections['changelog']    = $this->extract_section($content, 'Changelog');
        $sections['faq']          = $this->extract_section($content, 'Frequently Asked Questions');

        return $sections;
    }

    /**
     * Extract a section from the parsed content.
     *
     * @since 1.0.0
     *
     * @param string $content      Full content.
     * @param string $section_name Section name to extract.
     * @return string Section content.
     */
    private function extract_section($content, $section_name)
    {
        // Match section starting with ## Section Name.
        $pattern = '/<h2[^>]*>' . preg_quote($section_name, '/') . '<\/h2>(.*?)(?=<h2|$)/is';

        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Convert Markdown to HTML.
     *
     * Basic Markdown to HTML conversion for README files.
     *
     * @since 1.0.0
     *
     * @param string $markdown Markdown content.
     * @return string HTML content.
     */
    private function markdown_to_html($markdown)
    {
        // Normalize line endings (Windows CRLF to Unix LF).
        $html = str_replace("\r\n", "\n", $markdown);
        $html = str_replace("\r", "\n", $html);

        // Headers.
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold.
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Italic.
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Inline code.
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Links.
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html);

        // Unordered lists.
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);

        // Ordered lists.
        $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);

        // Paragraphs (lines not starting with HTML tags).
        $lines = explode("\n", $html);
        $in_paragraph = false;
        $result = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                if ($in_paragraph) {
                    $result[] = '</p>';
                    $in_paragraph = false;
                }
                continue;
            }

            // Skip lines that are already HTML elements.
            if (preg_match('/^<(h[1-6]|ul|ol|li|p|div|pre|code|blockquote)/', $line)) {
                if ($in_paragraph) {
                    $result[] = '</p>';
                    $in_paragraph = false;
                }
                $result[] = $line;
                continue;
            }

            // Start a new paragraph if needed.
            if (! $in_paragraph) {
                $result[] = '<p>' . $line;
                $in_paragraph = true;
            } else {
                $result[] = ' ' . $line;
            }
        }

        if ($in_paragraph) {
            $result[] = '</p>';
        }

        return implode("\n", $result);
    }

    /**
     * Enqueue thickbox scripts for the plugin details modal.
     *
     * @since 1.0.0
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_thickbox($hook)
    {
        if ('plugins.php' === $hook) {
            add_thickbox();
            add_action('admin_head', array($this, 'plugin_modal_styles'));
        }
    }

    /**
     * Output custom styles for the plugin details modal.
     *
     * @since 1.0.0
     */
    public function plugin_modal_styles()
    {
?>
        <style>
            .plugin-install-php h3 {
                margin: 0 0 8px !important;
            }
        </style>
<?php
    }

    /**
     * Add custom links to the plugin row meta (right side, under description).
     *
     * @since 1.0.0
     *
     * @param array  $links Plugin row meta links.
     * @param string $file  Plugin file.
     * @return array Modified links.
     */
    public function plugin_row_meta($links, $file)
    {
        if (plugin_basename(MINIPLUGIN_PLUGIN_DIR . 'miniplugin.php') !== $file) {
            return $links;
        }

        // View details link with thickbox modal.
        $details_link = sprintf(
            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
            esc_url(
                admin_url(
                    'plugin-install.php?tab=plugin-information&plugin=' . $this->plugin_slug .
                        '&TB_iframe=true&width=600&height=550'
                )
            ),
            esc_attr(sprintf(__('More information about %s', 'miniplugin'), 'Mini Plugin')),
            esc_attr('Mini Plugin'),
            esc_html__('View details', 'miniplugin')
        );

        $custom_links = array(
            $details_link,
            sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url('https://github.com/' . $this->github_owner . '/' . $this->github_repo),
                esc_html__('GitHub', 'miniplugin')
            ),
            sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url('https://github.com/' . $this->github_owner . '/' . $this->github_repo . '/issues'),
                esc_html__('Support', 'miniplugin')
            ),
        );

        return array_merge($links, $custom_links);
    }

    /**
     * Check for plugin updates from GitHub.
     *
     * @since 1.0.0
     *
     * @param object $transient Update transient.
     * @return object Modified transient.
     */
    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get the latest release from GitHub.
        $remote_version = $this->get_github_latest_version();

        if (! $remote_version) {
            return $transient;
        }

        $plugin_data = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];

        // Compare versions.
        if (version_compare($current_version, $remote_version, '<')) {
            $transient->response[$this->plugin_file] = (object) array(
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_file,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . $this->github_owner . '/' . $this->github_repo,
                'package'     => sprintf(
                    'https://github.com/%s/%s/releases/download/v%s/%s.zip',
                    $this->github_owner,
                    $this->github_repo,
                    $remote_version,
                    $this->github_repo
                ),
                'icons'       => array(),
                'banners'     => array(),
                'banners_rtl' => array(),
            );
        }

        return $transient;
    }

    /**
     * Get the latest version from GitHub releases.
     *
     * @since 1.0.0
     * @return string|false Latest version or false on failure.
     */
    private function get_github_latest_version()
    {
        $transient_key = 'miniplugin_github_version';
        $cached_version = get_transient($transient_key);

        if (false !== $cached_version) {
            return $cached_version;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_owner,
            $this->github_repo
        );

        $response = wp_remote_get(
            $api_url,
            array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
                'timeout' => 10,
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data->tag_name)) {
            return false;
        }

        // Remove 'v' prefix if present.
        $version = ltrim($data->tag_name, 'v');

        // Cache for 12 hours.
        set_transient($transient_key, $version, 12 * HOUR_IN_SECONDS);

        return $version;
    }
}
