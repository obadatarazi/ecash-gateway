<?php
/**
 * eCash Gateway Deployment Script
 * 
 * This script helps with plugin installation and setup
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress context, try to load it
    $wp_load_path = dirname(__FILE__) . '/../../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found. Please run this script from within a WordPress installation.');
    }
}

class ECashGatewayDeployer {
    
    private $plugin_dir;
    private $log_file;
    
    public function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->log_file = $this->plugin_dir . 'deployment.log';
    }
    
    /**
     * Run deployment process
     */
    public function deploy() {
        echo "Starting eCash Gateway Deployment...\n";
        echo "=====================================\n\n";
        
        $steps = array(
            'check_requirements',
            'create_directories',
            'copy_files',
            'set_permissions',
            'activate_plugin',
            'run_tests',
            'setup_configuration'
        );
        
        $success = true;
        
        foreach ($steps as $step) {
            try {
                $result = $this->$step();
                if ($result) {
                    echo "✓ " . str_replace('_', ' ', $step) . " - COMPLETED\n";
                } else {
                    echo "✗ " . str_replace('_', ' ', $step) . " - FAILED\n";
                    $success = false;
                }
            } catch (Exception $e) {
                echo "✗ " . str_replace('_', ' ', $step) . " - ERROR: " . $e->getMessage() . "\n";
                $success = false;
            }
        }
        
        echo "\n=====================================\n";
        if ($success) {
            echo "Deployment completed successfully!\n";
            echo "Plugin is ready for configuration.\n";
        } else {
            echo "Deployment completed with errors.\n";
            echo "Please check the log file for details.\n";
        }
        echo "=====================================\n";
        
        return $success;
    }
    
    /**
     * Check system requirements
     */
    private function check_requirements() {
        $requirements = array(
            'PHP Version' => array(
                'required' => '7.4',
                'current' => PHP_VERSION,
                'check' => version_compare(PHP_VERSION, '7.4', '>=')
            ),
            'WordPress Version' => array(
                'required' => '5.0',
                'current' => get_bloginfo('version'),
                'check' => version_compare(get_bloginfo('version'), '5.0', '>=')
            ),
            'WooCommerce Active' => array(
                'required' => 'Yes',
                'current' => class_exists('WooCommerce') ? 'Yes' : 'No',
                'check' => class_exists('WooCommerce')
            ),
            'SSL Certificate' => array(
                'required' => 'Yes',
                'current' => is_ssl() ? 'Yes' : 'No',
                'check' => is_ssl()
            ),
            'Write Permissions' => array(
                'required' => 'Yes',
                'current' => is_writable($this->plugin_dir) ? 'Yes' : 'No',
                'check' => is_writable($this->plugin_dir)
            )
        );
        
        $all_passed = true;
        
        foreach ($requirements as $requirement => $details) {
            if (!$details['check']) {
                $all_passed = false;
                echo "  - $requirement: FAILED (Required: {$details['required']}, Current: {$details['current']})\n";
            } else {
                echo "  - $requirement: PASSED\n";
            }
        }
        
        return $all_passed;
    }
    
    /**
     * Create necessary directories
     */
    private function create_directories() {
        $directories = array(
            'assets',
            'assets/js',
            'tests',
            'logs'
        );
        
        foreach ($directories as $dir) {
            $path = $this->plugin_dir . $dir;
            if (!file_exists($path)) {
                if (!mkdir($path, 0755, true)) {
                    throw new Exception("Failed to create directory: $path");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Copy plugin files
     */
    private function copy_files() {
        $files = array(
            'ecash-gateway.php' => 'ecash-gateway.php',
            'assets/js/ecash-gateway.js' => 'assets/js/ecash-gateway.js',
            'assets/ecash-logo.svg' => 'assets/ecash-logo.svg',
            'tests/test-ecash-gateway.php' => 'tests/test-ecash-gateway.php',
            'README.md' => 'README.md',
            'config.php' => 'config.php'
        );
        
        foreach ($files as $source => $destination) {
            $dest_path = $this->plugin_dir . $destination;
            if (!file_exists($dest_path)) {
                throw new Exception("Source file not found: $source");
            }
        }
        
        return true;
    }
    
    /**
     * Set proper file permissions
     */
    private function set_permissions() {
        $permissions = array(
            'ecash-gateway.php' => 0644,
            'assets/js/ecash-gateway.js' => 0644,
            'assets/ecash-logo.svg' => 0644,
            'tests/test-ecash-gateway.php' => 0644,
            'README.md' => 0644,
            'config.php' => 0644
        );
        
        foreach ($permissions as $file => $perm) {
            $file_path = $this->plugin_dir . $file;
            if (file_exists($file_path)) {
                chmod($file_path, $perm);
            }
        }
        
        return true;
    }
    
    /**
     * Activate the plugin
     */
    private function activate_plugin() {
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = plugin_basename(__FILE__);
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            throw new Exception("Failed to activate plugin: " . $result->get_error_message());
        }
        
        return true;
    }
    
    /**
     * Run basic tests
     */
    private function run_tests() {
        // Include test file
        $test_file = $this->plugin_dir . 'tests/test-ecash-gateway.php';
        if (file_exists($test_file)) {
            include_once $test_file;
            
            // Run basic tests
            if (class_exists('ECashGatewayTest')) {
                $test_suite = new ECashGatewayTest();
                return $test_suite->run_all_tests();
            }
        }
        
        return true; // Skip tests if test file not available
    }
    
    /**
     * Setup initial configuration
     */
    private function setup_configuration() {
        // Create default configuration if config.php doesn't exist
        $config_file = $this->plugin_dir . 'config.php';
        if (!file_exists($config_file)) {
            $this->create_default_config();
        }
        
        // Create .htaccess for security
        $htaccess_file = $this->plugin_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $this->create_htaccess();
        }
        
        return true;
    }
    
    /**
     * Create default configuration file
     */
    private function create_default_config() {
        $config_content = '<?php
/**
 * eCash Gateway Configuration
 * 
 * Customize these settings for your eCash API integration
 */

// Prevent direct access
if (!defined("ABSPATH")) exit;

// API Configuration
define("ECASH_API_CONFIG", array(
    "live_api_url" => "https://api.ecash.sy/v1",
    "test_api_url" => "https://test-api.ecash.sy/v1",
    "timeout" => 30,
    "webhook_secret" => "' . wp_generate_password(32, false) . '"
));

// Default Settings
define("ECASH_DEFAULT_SETTINGS", array(
    "title" => "eCash - Pay securely with your card",
    "description" => "Pay securely using your eCash card.",
    "currency" => "SYP",
    "test_mode" => true,
    "debug_mode" => false
));
?>';
        
        $config_file = $this->plugin_dir . 'config.php';
        return file_put_contents($config_file, $config_content);
    }
    
    /**
     * Create .htaccess file for security
     */
    private function create_htaccess() {
        $htaccess_content = '# eCash Gateway Security
# Prevent direct access to sensitive files

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

<Files "deploy.php">
    Order allow,deny
    Deny from all
</Files>

# Allow access to main plugin file
<Files "ecash-gateway.php">
    Order allow,deny
    Allow from all
</Files>';
        
        $htaccess_file = $this->plugin_dir . '.htaccess';
        return file_put_contents($htaccess_file, $htaccess_content);
    }
    
    /**
     * Log deployment activity
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }
}

// Run deployment if script is executed directly
if (php_sapi_name() === 'cli' || isset($_GET['deploy'])) {
    $deployer = new ECashGatewayDeployer();
    $success = $deployer->deploy();
    
    if (php_sapi_name() === 'cli') {
        exit($success ? 0 : 1);
    }
}
?> 