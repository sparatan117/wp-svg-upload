<?php
/**
 * Plugin Name: WordPress SVG Upload
 * Plugin URI: https://github.com/sparatan117/wp-svg-upload
 * Description: Enables SVG upload support in WordPress media library
 * Version: 1.0.1
 * Author: Austin Ross
 * Author URI: https://rossworks.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-svg-upload
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Add SVG support to WordPress media uploader
 */
function add_svg_support($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'add_svg_support');

/**
 * Fix SVG display in media library
 */
function fix_svg_thumb_display() {
    echo '<style>
        td.media-icon img[src$=".svg"], img[src$=".svg"].attachment-post-thumbnail { 
            width: 100% !important; 
            height: auto !important; 
        }
    </style>';
}
add_action('admin_head', 'fix_svg_thumb_display');

/**
 * Sanitize SVG uploads
 */
function sanitize_svg($file) {
    if ($file['type'] === 'image/svg+xml') {
        if (!function_exists('simplexml_load_file')) {
            return $file;
        }

        // Load the SVG file
        $file_content = file_get_contents($file['tmp_name']);
        
        // Check if the file is actually an SVG
        if (strpos($file_content, '<svg') === false) {
            $file['error'] = __('This file is not a valid SVG.', 'wordpress-svg-upload');
            return $file;
        }

        // Basic sanitization
        $file_content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $file_content);
        $file_content = preg_replace('#<onclick(.*?)>(.*?)</onclick>#is', '', $file_content);
        $file_content = preg_replace('#<onload(.*?)>(.*?)</onload>#is', '', $file_content);
        
        // Save the sanitized content
        file_put_contents($file['tmp_name'], $file_content);
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'sanitize_svg'); 