<?php
if (!defined('ABSPATH')) exit;

$settings = new SAP_Settings();
$all_settings = $settings->get_all();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post">
        <?php wp_nonce_field('sap_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="pm_quality_threshold">PM Quality Threshold</label></th>
                <td>
                    <input type="number" id="pm_quality_threshold" name="sap_settings[pm_quality_threshold]" value="<?php echo esc_attr($all_settings['pm_quality_threshold']); ?>" min="0" max="100" />
                    <p class="description">Minimum quality score for auto-approval (0-100). Default: 80</p>
                </td>
            </tr>
            
            <tr>
                <th><label>Auto Features</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sap_settings[auto_fix_enabled]" value="1" <?php checked($all_settings['auto_fix_enabled']); ?> />
                        Enable auto-fix
                    </label><br>
                    <label>
                        <input type="checkbox" name="sap_settings[enable_content_research]" value="1" <?php checked($all_settings['enable_content_research']); ?> />
                        Enable content research before generation
                    </label><br>
                    <label>
                        <input type="checkbox" name="sap_settings[auto_audit_enabled]" value="1" <?php checked($all_settings['auto_audit_enabled'] ?? false); ?> />
                        Enable automatic daily audits
                    </label><br>
                    <label>
                        <input type="checkbox" name="sap_settings[auto_ai_optimization_enabled]" value="1" <?php checked($all_settings['auto_ai_optimization_enabled'] ?? false); ?> />
                        Enable automatic AI optimization (weekly)
                    </label>
                </td>
            </tr>
            
            <tr>
                <th><label for="notification_email">Notification Email</label></th>
                <td>
                    <input type="email" id="notification_email" name="sap_settings[notification_email]" value="<?php echo esc_attr($all_settings['notification_email']); ?>" class="regular-text" />
                    <p class="description">Email for critical alerts. Defaults to admin email.</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="max_fixes_per_run">Max Fixes Per Run</label></th>
                <td>
                    <input type="number" id="max_fixes_per_run" name="sap_settings[max_fixes_per_run]" value="<?php echo esc_attr($all_settings['max_fixes_per_run']); ?>" min="1" max="100" />
                    <p class="description">Maximum number of fixes to apply in one batch. Default: 50</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="thin_content_threshold">Thin Content Threshold</label></th>
                <td>
                    <input type="number" id="thin_content_threshold" name="sap_settings[thin_content_threshold]" value="<?php echo esc_attr($all_settings['thin_content_threshold']); ?>" min="100" max="1000" />
                    <p class="description">Word count below which content is considered "thin". Default: 300</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="target_word_count">Target Word Count</label></th>
                <td>
                    <input type="number" id="target_word_count" name="sap_settings[target_word_count]" value="<?php echo esc_attr($all_settings['target_word_count']); ?>" min="300" max="3000" />
                    <p class="description">Target word count for content expansion. Default: 800</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="sap_settings_submit" class="button button-primary">Save Settings</button>
        </p>
    </form>
    
    <hr>
    
    <h2>API Credentials</h2>
    <p><em>Configure your API keys for AI features.</em></p>
    
    <table class="form-table">
        <tr>
            <th>Claude API (Anthropic)</th>
            <td>
                <?php if ($this->check_api_configured('claude')): ?>
                    <span style="color: #27ae60;">✓ Configured</span>
                <?php else: ?>
                    <span style="color: #e74c3c;">✗ Not configured</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Gemini API (Google)</th>
            <td>
                <?php if ($this->check_api_configured('gemini')): ?>
                    <span style="color: #27ae60;">✓ Configured</span>
                <?php else: ?>
                    <span style="color: #e74c3c;">✗ Not configured</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    
    <p><em>Note: API configuration UI coming soon. For now, use the REST API or database to set credentials.</em></p>
</div>

<?php
// Helper method for checking API config
function check_api_configured($service) {
    $api_manager = new SAP_API_Manager();
    return $api_manager->is_service_configured($service);
}
?>
