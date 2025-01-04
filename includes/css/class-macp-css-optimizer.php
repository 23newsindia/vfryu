<?php
class MACP_CSS_Optimizer {
    private $cache_dir;
    private $placeholder_svg = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/macp/css/';
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        if (get_option('macp_remove_unused_css', 0)) {
            add_filter('style_loader_tag', [$this, 'process_stylesheet'], 10, 4);
            add_action('wp_footer', [$this, 'add_optimizer_script'], 99999);
        }
    }

    public function process_stylesheet($tag, $handle, $href, $media) {
        if (!$this->should_process_css($href)) {
            return $tag;
        }

        // Generate unique ID for the stylesheet
        $style_id = 'macp-style-' . md5($href);
        
        // Add data attributes for the optimizer
        return str_replace('<link', '<link id="' . $style_id . '" data-macp-optimize="1"', $tag);
    }

    public function add_optimizer_script() {
        ?>
<script>
// CSS Optimizer
const macpCSSOptimizer = {
    init() {
        this.processStylesheets();
    },

    async processStylesheets() {
        const stylesheets = document.querySelectorAll('link[data-macp-optimize="1"]');
        
        for (const sheet of stylesheets) {
            try {
                const css = await this.fetchCSS(sheet.href);
                const optimizedCSS = this.optimizeCSS(css);
                this.replaceStylesheet(sheet, optimizedCSS);
            } catch (err) {
                console.error('MACP CSS Optimization error:', err);
            }
        }
    },

    async fetchCSS(url) {
        const response = await fetch(url);
        return response.text();
    },

    optimizeCSS(css) {
        const usedSelectors = new Set();
        const rules = this.parseCSS(css);
        
        rules.forEach(rule => {
            if (rule.selectorText && document.querySelector(rule.selectorText)) {
                usedSelectors.add(rule.selectorText);
            }
        });

        return this.buildOptimizedCSS(rules, usedSelectors);
    },

    parseCSS(css) {
        const style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
        
        const rules = [...style.sheet.cssRules];
        document.head.removeChild(style);
        
        return rules;
    },

    buildOptimizedCSS(rules, usedSelectors) {
        let optimizedCSS = '';
        
        rules.forEach(rule => {
            if (rule.type === CSSRule.STYLE_RULE) {
                if (usedSelectors.has(rule.selectorText)) {
                    optimizedCSS += rule.cssText + '\n';
                }
            } else {
                // Keep non-style rules (like @media, @keyframes)
                optimizedCSS += rule.cssText + '\n';
            }
        });

        return optimizedCSS;
    },

    replaceStylesheet(oldSheet, newCSS) {
        const style = document.createElement('style');
        style.id = oldSheet.id;
        style.textContent = newCSS;
        oldSheet.parentNode.replaceChild(style, oldSheet);
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => macpCSSOptimizer.init());
} else {
    macpCSSOptimizer.init();
}
</script>
        <?php
    }

    private function should_process_css($url) {
        if (!get_option('macp_process_external_css', 0) && !$this->is_local_url($url)) {
            return false;
        }

        foreach (MACP_CSS_Config::get_excluded_patterns() as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    private function is_local_url($url) {
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        $css_host = parse_url($url, PHP_URL_HOST);
        return empty($css_host) || $css_host === $site_url;
    }

    public function clear_css_cache() {
        array_map('unlink', glob($this->cache_dir . '*'));
        MACP_Debug::log("CSS cache cleared");
    }
}