<?php
/**
 * Plugin Name: WordPress SVG Upload
 * Plugin URI: https://github.com/sparatan117/wp-svg-upload
 * Description: Enables SVG upload support in WordPress media library
 * Version: 1.0.2
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
            $file['error'] = __('This file is not a valid SVG.', 'wp-svg-upload');
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

/**
 * Add settings page
 */
function wp_svg_upload_settings_page() {
    add_options_page(
        'SVG Upload Settings',
        'SVG Upload',
        'manage_options',
        'wp-svg-upload',
        'wp_svg_upload_settings_page_content'
    );
}
add_action('admin_menu', 'wp_svg_upload_settings_page');

/**
 * Settings page content
 */
function wp_svg_upload_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="svg-upload-container">
            <div class="svg-upload-preview">
                <div id="svg-preview"></div>
            </div>
            <div class="svg-upload-controls">
                <input type="file" id="svg-upload-input" accept=".svg" style="display: none;">
                <button type="button" class="button button-primary" id="svg-upload-button">Select SVG File</button>
                <button type="button" class="button" id="svg-upload-submit" style="display: none;">Upload SVG</button>
            </div>
            <div id="svg-upload-message"></div>
        </div>
    </div>
    <style>
        .svg-upload-container {
            max-width: 600px;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .svg-upload-preview {
            margin-bottom: 20px;
            min-height: 200px;
            border: 2px dashed #b4b9be;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .svg-upload-controls {
            margin-bottom: 20px;
        }
        #svg-upload-message {
            margin-top: 10px;
            padding: 10px;
        }
        #svg-upload-message.success {
            background: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        #svg-upload-message.error {
            background: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
    </style>
    <script>
    jQuery(document).ready(function($) {
        const uploadButton = $('#svg-upload-button');
        const uploadInput = $('#svg-upload-input');
        const submitButton = $('#svg-upload-submit');
        const preview = $('#svg-preview');
        const message = $('#svg-upload-message');
        let selectedFile = null;

        uploadButton.on('click', function() {
            uploadInput.click();
        });

        uploadInput.on('change', function(e) {
            selectedFile = e.target.files[0];
            if (selectedFile) {
                if (selectedFile.type === 'image/svg+xml') {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.html(e.target.result);
                        submitButton.show();
                        message.removeClass('success error').text('');
                    };
                    reader.readAsText(selectedFile);
                } else {
                    message.removeClass('success').addClass('error')
                        .text('Please select a valid SVG file.');
                    submitButton.hide();
                }
            }
        });

        submitButton.on('click', function() {
            if (!selectedFile) return;

            const formData = new FormData();
            formData.append('action', 'upload_svg');
            formData.append('svg_file', selectedFile);
            formData.append('security', '<?php echo wp_create_nonce('upload_svg_nonce'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        message.removeClass('error').addClass('success')
                            .text('SVG uploaded successfully!');
                        submitButton.hide();
                        uploadInput.val('');
                        selectedFile = null;
                    } else {
                        message.removeClass('success').addClass('error')
                            .text(response.data.message || 'Upload failed.');
                    }
                },
                error: function() {
                    message.removeClass('success').addClass('error')
                        .text('Upload failed. Please try again.');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Handle SVG upload via AJAX
 */
function handle_svg_upload() {
    check_ajax_referer('upload_svg_nonce', 'security');

    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'You do not have permission to upload files.'));
    }

    if (!isset($_FILES['svg_file'])) {
        wp_send_json_error(array('message' => 'No file was uploaded.'));
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file = $_FILES['svg_file'];
    $upload = wp_handle_upload($file, array('test_form' => false));

    if (isset($upload['error'])) {
        wp_send_json_error(array('message' => $upload['error']));
    }

    $attachment_id = wp_insert_attachment(array(
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit'
    ), $upload['file']);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
    }

    wp_send_json_success(array(
        'message' => 'SVG uploaded successfully!',
        'attachment_id' => $attachment_id
    ));
}
add_action('wp_ajax_upload_svg', 'handle_svg_upload'); 