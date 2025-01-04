<?php
class MACP_Admin {
    private $redis;
    private $settings_handler;
    private $assets_handler;
    private $metrics_display;

    public function __construct($redis) {
        $this->redis = $redis;
        $this->settings_handler = new MACP_Admin_Settings();
        $this->assets_handler = new MACP_Admin_Assets();
        $this->metrics_display = new MACP_Metrics_Display(new MACP_Metrics_Calculator($redis));
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this->assets_handler, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        // Add main menu
        add_menu_page(
            'Cache Settings',
            'Cache Settings',
            'manage_options',
            'macp-settings',
            [$this, 'render_settings_page'],
            'dashicons-performance',
            100
        );

        // Add Cache Metrics submenu
        $this->metrics_display->add_metrics_page();

        // Add Debug Info submenu
        add_submenu_page(
            'macp-settings',
            'Debug Information',
            'Debug Info',
            'manage_options',
            'macp-debug',
            [$this, 'render_debug_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings_handler->get_all_settings();
        $metrics = (new MACP_Metrics_Calculator($this->redis))->get_all_metrics();
        
        include MACP_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status = MACP_Debug_Utility::check_plugin_status();
        include MACP_PLUGIN_DIR . 'templates/debug-page.php';
    }
}