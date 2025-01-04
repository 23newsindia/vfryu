<?php
class MACP_CSS_Extractor {
    public static function extract_css_files($html) {
        preg_match_all('/<link[^>]+href=[\'"]([^\'"]+\.css(?:\?[^\'"]*)?)[\'"][^>]*>/i', $html, $matches);
        $css_files = array_filter($matches[1], function($url) {
            return strpos($url, '.css') !== false;
        });

        MACP_Debug::log("Found CSS files: " . print_r($css_files, true));
        return $css_files;
    }

    public static function extract_inline_styles($html) {
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches);
        return array_filter($matches[1]);
    }

    public static function get_css_content($url) {
        // Handle protocol-relative URLs
        if (strpos($url, '//') === 0) {
            $url = (MACP_URL_Helper::is_https() ? 'https:' : 'http:') . $url;
        }
        // Handle relative URLs
        elseif (strpos($url, 'http') !== 0) {
            $url = site_url($url);
        }

        MACP_Debug::log("Fetching CSS from: " . $url);

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            MACP_Debug::log("Failed to fetch CSS from: " . $url);
            return false;
        }

        $css = wp_remote_retrieve_body($response);
        if (empty($css)) {
            MACP_Debug::log("Empty CSS content from: " . $url);
            return false;
        }

        return $css;
    }

    public static function is_local_url($url) {
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        $css_host = parse_url($url, PHP_URL_HOST);
        return empty($css_host) || $css_host === $site_url;
    }
}