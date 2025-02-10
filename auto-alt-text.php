<?php
/*
Plugin Name: Auto ALT Text Generator
Description: Automatically generates ALT text for images using Azure Computer Vision AI
Version: 1.0
Author: Tomi + AI
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoAltTextGenerator {
    private $azure_endpoint;
    private $azure_key;
    private $language;
    private $translator_key;
    private $translator_endpoint;
    private $translator_region;

    // Add debug_log method at the start of the class
    public function debug_log($message, $type = 'info') {
        // Only output debug messages if we're not in an AJAX call
        if (!wp_doing_ajax()) {
            echo "<script>
                if (typeof debugConsole !== 'undefined') {
                    debugConsole.log(" . json_encode($message) . ", " . json_encode($type) . ");
                }
            </script>";
            ob_flush();
            flush();
        }
    }

    public function __construct() {
        // Initialize credentials
        $this->azure_endpoint = get_option('auto_alt_azure_endpoint');
        $this->azure_key = get_option('auto_alt_azure_key');
        $this->translator_key = get_option('auto_alt_translator_key');
        $this->translator_endpoint = get_option('auto_alt_translator_endpoint', 'https://api.cognitive.microsofttranslator.com/');
        $this->language = get_option('auto_alt_language', 'en');
        $this->translator_region = get_option('auto_alt_translator_region', 'westeurope');

        // Add hooks
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_attachment', array($this, 'generate_alt_text'));
        add_filter('wp_generate_attachment_metadata', array($this, 'process_uploaded_images'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_get_all_images', 'get_all_images');
    }

    public function add_plugin_page() {
        add_options_page(
            'Auto ALT Text Settings',
            'Auto ALT Text',
            'manage_options',
            'auto-alt-text',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h2>Auto ALT Text Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('auto_alt_settings_group');
                do_settings_sections('auto-alt-text');
                ?>
                <div class="azure-test-connection">
                    <button type="button" id="test-azure-connection" class="button button-secondary">
                        Test Azure Connection
                    </button>
                    <span id="test-connection-result" style="margin-left: 10px; display: none;"></span>
                </div>
                <?php
                submit_button();
                ?>
            </form>
            
            <div class="alt-text-manager">
                <h3>Image ALT Text Manager</h3>
                <div id="scan-status" style="display: none;">
                    <div class="progress-bar">
                        <div id="progress-inner" style="width: 0%"></div>
                    </div>
                    <p id="status-text"></p>
                </div>
                
                <div id="scan-results" style="display: none;">
                    <p>Total images: <span id="total-images">0</span></p>
                    <p>Images with ALT text: <span id="with-alt">0</span></p>
                    <p>Images without ALT text: <span id="without-alt">0</span></p>
                </div>
                
                <div class="action-buttons">
                    <button id="scan-images" class="button">Scan Images</button>
                    <button id="process-missing" class="button button-primary" style="display: none;">Generate Missing ALT Text</button>
                    <button id="process-all" class="button" style="display: none;">Regenerate All ALT Text</button>
                </div>
                
                <div id="image-list" style="display: none; margin-top: 20px;">
                    <h4>Images without ALT text:</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Preview</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="images-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="debug-console" style="display: none;">
            <div class="debug-header">
                <h3>Debug Console</h3>
                <button class="clear-logs button">Clear Logs</button>
                <button class="close-debug button">Close</button>
            </div>
            <div class="debug-content"></div>
        </div>
        <button class="toggle-debug button">Show Debug Console</button>
        
        <style>
            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            #progress-inner {
                height: 100%;
                background-color: #0073aa;
                transition: width 0.3s ease;
            }
            .image-preview {
                width: 40px;
                height: 40px;
                object-fit: cover;
            }
            .status-pending {
                color: #f0ad4e;
            }
            .status-processing {
                color: #5bc0de;
            }
            .status-complete {
                color: #5cb85c;
            }
            .status-error {
                color: #d9534f;
            }
            
            .debug-console {
                margin-top: 20px;
                background: #1e1e1e;
                color: #fff;
                border-radius: 5px;
                font-family: monospace;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .debug-header {
                padding: 10px;
                background: #333;
                display: flex;
                align-items: center;
                position: sticky;
                top: 0;
            }
            
            .debug-header h3 {
                margin: 0;
                flex-grow: 1;
                color: #fff;
            }
            
            .debug-header button {
                margin-left: 10px;
            }
            
            .debug-content {
                padding: 10px;
                font-size: 13px;
                line-height: 1.4;
            }
            
            .debug-content .log-entry {
                margin: 5px 0;
                padding: 5px;
                border-bottom: 1px solid #333;
            }
            
            .debug-content .log-info { color: #63b4ff; }
            .debug-content .log-success { color: #4caf50; }
            .debug-content .log-warning { color: #ff9800; }
            .debug-content .log-error { color: #f44336; }
            
            .toggle-debug {
                margin-top: 20px;
            }
        </style>
        <?php
    }

    public function register_settings() {
        register_setting('auto_alt_settings_group', 'auto_alt_azure_endpoint');
        register_setting('auto_alt_settings_group', 'auto_alt_azure_key');
        register_setting('auto_alt_settings_group', 'auto_alt_language');
        register_setting('auto_alt_settings_group', 'auto_alt_translator_key');
        register_setting('auto_alt_settings_group', 'auto_alt_translator_endpoint');
        register_setting('auto_alt_settings_group', 'auto_alt_translator_region');

        add_settings_section(
            'auto_alt_settings_section',
            'Azure API Settings',
            array($this, 'settings_section_callback'),
            'auto-alt-text'
        );

        add_settings_field(
            'azure_endpoint',
            'Azure Endpoint',
            array($this, 'endpoint_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );

        add_settings_field(
            'azure_key',
            'Azure API Key',
            array($this, 'key_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );

        add_settings_field(
            'language',
            'Alt Text Language',
            array($this, 'language_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );

        add_settings_field(
            'translator_endpoint',
            'Azure Translator Endpoint',
            array($this, 'translator_endpoint_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );

        add_settings_field(
            'translator_key',
            'Azure Translator Key',
            array($this, 'translator_key_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );

        add_settings_field(
            'translator_region',
            'Azure Translator Region',
            array($this, 'translator_region_callback'),
            'auto-alt-text',
            'auto_alt_settings_section'
        );
    }

    public function settings_section_callback() {
        echo 'Enter your Azure Computer Vision API credentials below:';
    }

    public function endpoint_callback() {
        printf(
            '<input type="text" id="azure_endpoint" name="auto_alt_azure_endpoint" value="%s" class="regular-text" />',
            esc_attr($this->azure_endpoint)
        );
    }

    public function key_callback() {
        printf(
            '<input type="password" id="azure_key" name="auto_alt_azure_key" value="%s" class="regular-text" />',
            esc_attr($this->azure_key)
        );
    }

    public function language_callback() {
        $languages = array(
            'en' => 'English',
            'fi-FI' => 'Finnish'
        );
        echo '<select id="language" name="auto_alt_language">';
        foreach ($languages as $code => $name) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($code),
                selected($this->language, $code, false),
                esc_html($name)
            );
        }
        echo '</select>';
    }

    public function translator_endpoint_callback() {
        printf(
            '<input type="text" id="translator_endpoint" name="auto_alt_translator_endpoint" value="%s" class="regular-text" />',
            esc_attr($this->translator_endpoint)
        );
        echo '<p class="description">Default: https://api.cognitive.microsofttranslator.com/</p>';
    }

    public function translator_key_callback() {
        printf(
            '<input type="password" id="translator_key" name="auto_alt_translator_key" value="%s" class="regular-text" />',
            esc_attr($this->translator_key)
        );
    }

    public function translator_region_callback() {
        $region = get_option('auto_alt_translator_region', 'westeurope');
        ?>
        <select id="translator_region" name="auto_alt_translator_region">
            <option value="westeurope" <?php selected($region, 'westeurope'); ?>>West Europe</option>
            <option value="northeurope" <?php selected($region, 'northeurope'); ?>>North Europe</option>
            <option value="eastus" <?php selected($region, 'eastus'); ?>>East US</option>
            <option value="westus" <?php selected($region, 'westus'); ?>>West US</option>
            <option value="westus2" <?php selected($region, 'westus2'); ?>>West US 2</option>
            <option value="eastus2" <?php selected($region, 'eastus2'); ?>>East US 2</option>
            <option value="southeastasia" <?php selected($region, 'southeastasia'); ?>>Southeast Asia</option>
        </select>
        <p class="description">Select the region where your Azure Translator resource was created</p>
        <?php
    }

    public function generate_alt_text($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) return;

        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) return;

        // Get image URL
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) return;

        // Generate ALT text using Azure
        $alt_text = $this->analyze_image($image_url);
        if ($alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
    }

    public function analyze_image($image_url) {
        try {
            if (empty($this->azure_endpoint) || empty($this->azure_key)) {
                $this->debug_log('Azure credentials missing', 'error');
                return false;
            }

            $this->debug_log('Starting image analysis...', 'info');
            
            // Request more detailed analysis
            $url = rtrim($this->azure_endpoint, '/') . "/vision/v3.2/analyze?visualFeatures=Description,Tags,Objects,Color,Faces&language=en&detail=landmarks";
            
            $this->debug_log('Azure Vision URL: ' . $url, 'info');
            $this->debug_log('Analyzing image: ' . $image_url, 'info');
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Ocp-Apim-Subscription-Key' => $this->azure_key
                ),
                'body' => json_encode(array('url' => $image_url)),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                $this->debug_log('Azure API error: ' . $response->get_error_message(), 'error');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $this->debug_log('Azure Vision Raw Response: ' . print_r($body, true), 'info');

            // Get all available information
            $caption = isset($body['description']['captions'][0]['text']) ? $body['description']['captions'][0]['text'] : '';
            $tags = isset($body['tags']) ? array_column($body['tags'], 'name') : array();
            $objects = isset($body['objects']) ? array_column($body['objects'], 'object') : array();
            $colors = isset($body['color']['dominantColors']) ? $body['color']['dominantColors'] : array();
            $faces = isset($body['faces']) ? count($body['faces']) : 0;

            // Format a more detailed description
            $alt_text = $this->format_alt_text($caption, $tags, $objects, $colors, $faces);

            // Translate if needed
            if ($this->language === 'fi-FI' && !empty($this->translator_key)) {
                $translated = $this->translate_text($alt_text, 'fi');
                if ($translated !== $alt_text) {
                    $alt_text = $translated;
                }
            }

            return $alt_text;
        } catch (Exception $e) {
            $this->debug_log('Exception in analyze_image: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function format_alt_text($caption, $tags, $objects, $colors = array(), $faces = 0) {
        $description_parts = array();

        // Start with the main caption if available
        if (!empty($caption)) {
            $description_parts[] = ucfirst($caption);
        }

        // Add color information if available
        if (!empty($colors)) {
            $color_desc = 'The image features ' . implode(' and ', array_slice($colors, 0, 3)) . ' colors';
            $description_parts[] = $color_desc;
        }

        // Add object details
        if (!empty($objects)) {
            $unique_objects = array_unique($objects);
            if (count($unique_objects) > 1) {
                $last_object = array_pop($unique_objects);
                $object_list = implode(', ', $unique_objects) . ' and ' . $last_object;
                $description_parts[] = "Contains " . $object_list;
            }
        }

        // Add face information if detected
        if ($faces > 0) {
            $description_parts[] = $faces === 1 ? "Shows one person" : "Shows $faces people";
        }

        // Add relevant tags that aren't already mentioned
        $mentioned_words = strtolower(implode(' ', $description_parts));
        $additional_tags = array_filter($tags, function($tag) use ($mentioned_words) {
            return stripos($mentioned_words, $tag) === false;
        });

        if (!empty($additional_tags)) {
            $relevant_tags = array_slice($additional_tags, 0, 3);
            $description_parts[] = "Also features " . implode(', ', $relevant_tags);
        }

        // Combine all parts
        $alt_text = implode('. ', $description_parts);

        // Ensure the text ends with a period
        if (substr($alt_text, -1) !== '.') {
            $alt_text .= '.';
        }

        return $alt_text;
    }

    public function translate_text($text, $target_language) {
        if (empty($this->translator_key)) {
            $this->debug_log('Translator key missing', 'error');
            return $text;
        }

        $region = $this->get_translator_region();
        $this->debug_log('Using translator region: ' . $region, 'info');

        // Use the global translator endpoint with region
        $url = 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&to=' . $target_language . '&from=en';
        
        $this->debug_log('Translating text: ' . $text, 'info');
        $this->debug_log('Translation URL: ' . $url, 'info');
        $this->debug_log('Using region: ' . $region, 'info');
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Ocp-Apim-Subscription-Key' => $this->translator_key,
            'Ocp-Apim-Subscription-Region' => $region  // This is important!
        );

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode(array(
                array('text' => $text)
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $this->debug_log('Translation error: ' . $response->get_error_message(), 'error');
            return $text;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $this->debug_log('Translation status code: ' . $status_code, 'info');
        $this->debug_log('Translation response: ' . print_r($body, true), 'info');

        if ($status_code !== 200) {
            $this->debug_log('Translation API error. Status: ' . $status_code . ', Response: ' . print_r($body, true), 'error');
            return $text;
        }
        
        if (isset($body[0]['translations'][0]['text'])) {
            $translated = $body[0]['translations'][0]['text'];
            $this->debug_log('Successfully translated text to: ' . $translated, 'success');
            
            // Ensure first letter is capitalized and ends with period
            $translated = ucfirst(trim($translated));
            if (!preg_match('/[.!?]$/', $translated)) {
                $translated .= '.';
            }
            return $translated;
        }

        $this->debug_log('Translation failed - no translation in response', 'warning');
        return $text;
    }

    public function process_uploaded_images($metadata, $attachment_id) {
        $this->generate_alt_text($attachment_id);
        return $metadata;
    }

    // Add this new method to handle AJAX scan request
    public static function scan_images() {
        try {
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif'),
                'posts_per_page' => -1,
                'post_status' => 'any'
            );

            $images = get_posts($args);
            
            if (is_wp_error($images)) {
                wp_send_json_error('Error fetching images: ' . $images->get_error_message());
                return;
            }

            $result = array(
                'total' => count($images),
                'with_alt' => 0,
                'without_alt' => 0,
                'images' => array()
            );

            foreach ($images as $image) {
                $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
                $thumb = wp_get_attachment_image_src($image->ID, 'thumbnail');
                
                if (!empty($alt_text)) {
                    $result['with_alt']++;
                } else {
                    $result['without_alt']++;
                    $result['images'][] = array(
                        'id' => $image->ID,
                        'title' => $image->post_title,
                        'thumbnail' => $thumb ? $thumb[0] : '',
                        'alt_text' => $alt_text
                    );
                }
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_auto-alt-text' !== $hook) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'auto-alt-text-admin',
            plugins_url('auto-alt-text-admin.css', __FILE__),
            array(),
            '1.0.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'auto-alt-text-admin',
            plugins_url('auto-alt-text.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        // Add WordPress AJAX URL to JavaScript
        wp_localize_script('auto-alt-text-admin', 'autoAltText', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    // Add this new method to the AutoAltTextGenerator class
    public function test_azure_connection() {
        $results = array();
        
        // Test Computer Vision API credentials
        if (empty($this->azure_endpoint) || empty($this->azure_key)) {
            wp_send_json_error('Azure Computer Vision credentials are missing. Please enter both endpoint and API key.');
            return;
        }

        // Test Computer Vision API connection
        $url = rtrim($this->azure_endpoint, '/') . '/vision/v3.2/analyze';
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Ocp-Apim-Subscription-Key' => $this->azure_key
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Computer Vision API connection failed: ' . $response->get_error_message());
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 401) {
            wp_send_json_error('Computer Vision API Error: Invalid API key');
            return;
        }

        $results[] = '✅ Computer Vision API connection successful';

        // If Finnish is selected, test Translator credentials and connection
        if ($this->language === 'fi-FI') {
            if (empty($this->translator_key)) {
                wp_send_json_error('Finnish translation is enabled but Translator API key is missing.');
                return;
            }

            // Test Translator API connection
            $translator_url = rtrim($this->translator_endpoint, '/') . '/languages?api-version=3.0';
            $translator_response = wp_remote_get($translator_url, array(
                'headers' => array(
                    'Ocp-Apim-Subscription-Key' => $this->translator_key
                )
            ));

            if (is_wp_error($translator_response)) {
                wp_send_json_error('Translator API connection failed: ' . $translator_response->get_error_message());
                return;
            }

            $translator_status = wp_remote_retrieve_response_code($translator_response);
            if ($translator_status === 401) {
                wp_send_json_error('Translator API Error: Invalid API key');
                return;
            }

            $results[] = '✅ Translator API connection successful';
        }

        // Send success message with all test results
        wp_send_json_success(implode('<br>', $results) . '<br>✅ Ready to process images!');
    }

    // Add these getter methods to the AutoAltTextGenerator class
    public function get_azure_endpoint() {
        return $this->azure_endpoint;
    }

    public function get_azure_key() {
        return $this->azure_key;
    }

    // Add this getter method
    public function get_language() {
        return $this->language;
    }

    // Add this getter method to the AutoAltTextGenerator class
    public function get_translator_key() {
        return $this->translator_key;
    }

    // Add this getter method to the AutoAltTextGenerator class
    public function get_translator_region() {
        return $this->translator_region;
    }

    // Add this getter method to the AutoAltTextGenerator class
    public function get_translator_endpoint() {
        return $this->translator_endpoint;
    }
}

// Initialize the plugin
$auto_alt_text_generator = new AutoAltTextGenerator();

// Add AJAX handlers
add_action('wp_ajax_scan_images', array('AutoAltTextGenerator', 'scan_images'));
add_action('wp_ajax_process_single_image', 'process_single_image');
add_action('wp_ajax_test_azure_connection', array($auto_alt_text_generator, 'test_azure_connection'));
add_action('wp_ajax_get_all_images', 'get_all_images');

function process_single_image() {
    try {
        if (!isset($_POST['image_id'])) {
            wp_send_json_error('No image ID provided');
        }

        $image_id = intval($_POST['image_id']);
        
        // Create new instance with proper initialization
        $generator = new AutoAltTextGenerator();
        
        $generator->debug_log('Starting process for image ID: ' . $image_id, 'info');
        $generator->debug_log('Language setting: ' . $generator->get_language(), 'info');
        
        // Check Azure Vision credentials
        if (empty($generator->get_azure_endpoint()) || empty($generator->get_azure_key())) {
            $generator->debug_log('Azure Vision credentials missing', 'error');
            wp_send_json_error('Azure Vision credentials not configured. Please check your settings.');
            return;
        }

        // Check Translator credentials if Finnish is selected
        if ($generator->get_language() === 'fi-FI') {
            if (empty($generator->get_translator_key())) {
                $generator->debug_log('Translator key missing for Finnish translation', 'error');
                wp_send_json_error('Translator key required for Finnish translation');
                return;
            }
            $generator->debug_log('Finnish translation enabled, translator key present', 'info');
        }

        // Get image URL
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            $generator->debug_log('Could not get image URL for ID: ' . $image_id, 'error');
            wp_send_json_error('Could not get image URL');
            return;
        }

        $generator->debug_log('Translator settings:', 'info');
        $generator->debug_log('Key: ' . (empty($generator->get_translator_key()) ? 'Missing' : 'Present'), 'info');
        $generator->debug_log('Region: ' . $generator->get_translator_region(), 'info');
        $generator->debug_log('Endpoint: ' . $generator->get_translator_endpoint(), 'info');

        // Make sure the image is accessible
        $test_response = wp_remote_get($image_url);
        if (is_wp_error($test_response)) {
            $generator->debug_log('Image not accessible: ' . $test_response->get_error_message(), 'error');
            wp_send_json_error('Image not accessible: ' . $test_response->get_error_message());
            return;
        }

        $test_status = wp_remote_retrieve_response_code($test_response);
        if ($test_status !== 200) {
            $generator->debug_log('Image not accessible. Status code: ' . $test_status, 'error');
            wp_send_json_error('Image not accessible. Status code: ' . $test_status);
            return;
        }

        // Generate ALT text
        $generator->debug_log('Calling Azure Vision API...', 'info');
        $alt_text = $generator->analyze_image($image_url);
        
        if ($alt_text === false) {
            $generator->debug_log('Failed to generate ALT text', 'error');
            wp_send_json_error('Failed to generate ALT text');
            return;
        }

        $generator->debug_log('Generated ALT text: ' . $alt_text, 'info');

        // Update the alt text
        $update_result = update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
        if ($update_result === false) {
            $generator->debug_log('Failed to save ALT text to database', 'error');
            wp_send_json_error('Failed to save ALT text to database');
            return;
        }
        
        // Verify the update
        $new_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        if (empty($new_alt)) {
            $generator->debug_log('ALT text not saved correctly', 'error');
            wp_send_json_error('ALT text not saved correctly');
            return;
        }
        
        $generator->debug_log('Successfully saved ALT text: ' . $new_alt, 'success');
        
        wp_send_json_success(array(
            'id' => $image_id,
            'alt_text' => $new_alt
        ));

    } catch (Exception $e) {
        $generator->debug_log('Exception caught: ' . $e->getMessage(), 'error');
        $generator->debug_log('Stack trace: ' . $e->getTraceAsString(), 'error');
        wp_send_json_error('Error: ' . $e->getMessage());
    } catch (Error $e) {
        $generator->debug_log('PHP Error caught: ' . $e->getMessage(), 'error');
        $generator->debug_log('Stack trace: ' . $e->getTraceAsString(), 'error');
        wp_send_json_error('PHP Error: ' . $e->getMessage());
    }
}

function get_all_images() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif'),
        'posts_per_page' => -1,
        'post_status' => 'any'
    );

    $images = get_posts($args);
    $result = array(
        'total' => count($images),
        'images' => array()
    );

    foreach ($images as $image) {
        $thumb = wp_get_attachment_image_src($image->ID, 'thumbnail');
        $result['images'][] = array(
            'id' => $image->ID,
            'title' => $image->post_title,
            'thumbnail' => $thumb ? $thumb[0] : '',
            'alt_text' => get_post_meta($image->ID, '_wp_attachment_image_alt', true)
        );
    }

    wp_send_json_success($result);
} 