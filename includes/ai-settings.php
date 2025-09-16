<?php
/**
 * AI Settings Page
 * Manage AI provider API keys and configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AI settings
 */
function zdm_register_ai_settings() {
    // Browser subscription settings
    register_setting('zdm_ai_settings', 'zdm_use_browser_ai');
    register_setting('zdm_ai_settings', 'zdm_browser_ai_provider');
    register_setting('zdm_ai_settings', 'zdm_include_full_conversation');
    register_setting('zdm_ai_settings', 'zdm_conversation_limit');

    // API subscription settings (deprecated)
    register_setting('zdm_ai_settings', 'zdm_use_subscription');
    register_setting('zdm_ai_settings', 'zdm_subscription_key');
    register_setting('zdm_ai_settings', 'zdm_subscription_email');
    register_setting('zdm_ai_settings', 'zdm_subscription_plan');
    register_setting('zdm_ai_settings', 'zdm_subscription_status');
    register_setting('zdm_ai_settings', 'zdm_subscription_credits');

    // OpenAI settings
    register_setting('zdm_ai_settings', 'zdm_openai_api_key');
    register_setting('zdm_ai_settings', 'zdm_openai_model');
    register_setting('zdm_ai_settings', 'zdm_openai_enabled');

    // Claude/Anthropic settings
    register_setting('zdm_ai_settings', 'zdm_claude_api_key');
    register_setting('zdm_ai_settings', 'zdm_claude_model');
    register_setting('zdm_ai_settings', 'zdm_claude_enabled');

    // Google Gemini settings
    register_setting('zdm_ai_settings', 'zdm_gemini_api_key');
    register_setting('zdm_ai_settings', 'zdm_gemini_model');
    register_setting('zdm_ai_settings', 'zdm_gemini_enabled');

    // Default AI provider
    register_setting('zdm_ai_settings', 'zdm_default_ai_provider');

    // AI generation settings
    register_setting('zdm_ai_settings', 'zdm_ai_max_tokens');
    register_setting('zdm_ai_settings', 'zdm_ai_temperature');
    register_setting('zdm_ai_settings', 'zdm_ai_system_prompt');
}
add_action('admin_init', 'zdm_register_ai_settings');

/**
 * Render AI Settings Page
 */
function zdm_ai_settings_page() {
    // Handle test API action
    if (isset($_POST['test_ai_provider']) && isset($_POST['provider'])) {
        check_admin_referer('zdm_test_ai');
        $test_result = zdm_test_ai_provider($_POST['provider']);
    }

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'subscription';
    ?>
    <div class="wrap">
        <h1>AI Settings</h1>

        <?php if (isset($test_result)): ?>
            <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?> is-dismissible">
                <p><?php echo esc_html($test_result['message']); ?></p>
            </div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper">
            <a href="?page=zoho-desk-ai&tab=subscription" class="nav-tab <?php echo $active_tab == 'subscription' ? 'nav-tab-active' : ''; ?>">
                Browser AI
            </a>
            <a href="?page=zoho-desk-ai&tab=providers" class="nav-tab <?php echo $active_tab == 'providers' ? 'nav-tab-active' : ''; ?>">
                API Keys
            </a>
            <a href="?page=zoho-desk-ai&tab=prompts" class="nav-tab <?php echo $active_tab == 'prompts' ? 'nav-tab-active' : ''; ?>">
                Prompts
            </a>
        </nav>

        <form method="post" action="options.php">
            <?php settings_fields('zdm_ai_settings'); ?>

            <?php if ($active_tab == 'subscription'): ?>
                <div class="zdm-browser-ai-settings">
                    <h2>Use Your ChatGPT or Claude Subscription</h2>
                    <p>Use your existing ChatGPT Plus or Claude Pro subscription to generate responses. No API keys needed!</p>

                    <?php
                    $use_browser_ai = get_option('zdm_use_browser_ai');
                    $browser_provider = get_option('zdm_browser_ai_provider', 'chatgpt');
                    ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Use Browser AI</th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="zdm_use_browser_ai"
                                           value="1"
                                           <?php checked(get_option('zdm_use_browser_ai'), '1'); ?>
                                           onchange="zdmToggleBrowserAI(this)">
                                    Enable browser-based AI (ChatGPT Plus or Claude Pro)
                                </label>
                                <p class="description">Use your existing ChatGPT or Claude subscription through your browser. No API keys required.</p>
                            </td>
                        </tr>
                    </table>

                    <div id="zdm-browser-ai-details" style="<?php echo $use_browser_ai ? '' : 'display:none;'; ?>">
                        <div class="card" style="max-width: 800px; margin: 20px 0;">
                            <h3>Select AI Provider</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Provider</th>
                                    <td>
                                        <label style="margin-right: 20px;">
                                            <input type="radio"
                                                   name="zdm_browser_ai_provider"
                                                   value="chatgpt"
                                                   <?php checked($browser_provider, 'chatgpt'); ?>>
                                            ChatGPT (OpenAI)
                                        </label>
                                        <label>
                                            <input type="radio"
                                                   name="zdm_browser_ai_provider"
                                                   value="claude"
                                                   <?php checked($browser_provider, 'claude'); ?>>
                                            Claude (Anthropic)
                                        </label>
                                        <p class="description">Choose which AI service to use for generating responses.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Conversation Context</th>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="zdm_include_full_conversation"
                                                   value="1"
                                                   <?php checked(get_option('zdm_include_full_conversation', '1'), '1'); ?>>
                                            Include complete conversation history
                                        </label>
                                        <p class="description">When enabled, the AI prompt will include all customer messages and agent responses for better context.</p>

                                        <div style="margin-top: 10px;">
                                            <label>
                                                Message limit:
                                                <input type="number"
                                                       name="zdm_conversation_limit"
                                                       value="<?php echo esc_attr(get_option('zdm_conversation_limit', '20')); ?>"
                                                       min="1" max="100" style="width: 70px;">
                                                messages
                                            </label>
                                            <p class="description">Maximum number of messages to include (to prevent prompts from becoming too long)</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="card" style="max-width: 800px; margin: 20px 0;">
                            <h3>📋 Step-by-Step Workflow</h3>

                            <!-- Visual Workflow -->
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; text-align: center;">
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="background: #007cba; color: white; padding: 10px; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">1</div>
                                        <strong>Ticket View</strong><br>
                                        <small>Open ticket</small>
                                    </div>
                                    <div style="color: #999;">→</div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="background: #007cba; color: white; padding: 10px; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">2</div>
                                        <strong>Generate</strong><br>
                                        <small>Click button</small>
                                    </div>
                                    <div style="color: #999;">→</div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="background: #007cba; color: white; padding: 10px; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">3</div>
                                        <strong>Copy Prompt</strong><br>
                                        <small>From popup</small>
                                    </div>
                                    <div style="color: #999;">→</div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="background: #007cba; color: white; padding: 10px; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">4</div>
                                        <strong>Paste in AI</strong><br>
                                        <small>ChatGPT/Claude</small>
                                    </div>
                                    <div style="color: #999;">→</div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="background: #007cba; color: white; padding: 10px; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-weight: bold;">5</div>
                                        <strong>Use Response</strong><br>
                                        <small>In draft</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Steps -->
                            <h4>Detailed Instructions:</h4>
                            <ol style="margin-left: 20px; line-height: 1.8;">
                                <li><strong>Open Support Ticket:</strong> Navigate to any ticket in your Zoho Desk Manager dashboard</li>
                                <li><strong>Click "Generate AI Response":</strong> Find this button in the draft section of the ticket view</li>
                                <li><strong>Review Custom Prompt:</strong> A popup window shows a prompt specifically created for this ticket</li>
                                <li><strong>Copy the Prompt:</strong> Click "📋 Copy Prompt" button (it will show "✓ Copied!")</li>
                                <li><strong>Open AI Service:</strong> Click "🔗 Open ChatGPT" or "🔗 Open Claude" to open in a new tab</li>
                                <li><strong>Paste & Generate:</strong> In ChatGPT/Claude, paste your prompt and press Enter</li>
                                <li><strong>Copy AI's Response:</strong> Select and copy the entire response from the AI</li>
                                <li><strong>Return to Plugin:</strong> Go back to the popup window in Zoho Desk Manager</li>
                                <li><strong>Paste Response:</strong> Paste the AI response in the text area provided</li>
                                <li><strong>Insert to Draft:</strong> Click "Use This Response" to add it to your ticket draft</li>
                            </ol>

                            <div style="background: #e7f5ff; padding: 12px; margin-top: 15px; border-radius: 5px; border-left: 4px solid #007cba;">
                                <strong>💡 Pro Tips:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <li>Keep ChatGPT/Claude open in a tab for faster processing of multiple tickets</li>
                                    <li>You can edit the AI response before using it in your draft</li>
                                    <li>The prompt includes all ticket context automatically</li>
                                </ul>
                            </div>
                        </div>

                        <div class="card" style="max-width: 800px; margin: 20px 0;">
                            <h3>Requirements</h3>
                            <div style="display: flex; gap: 20px;">
                                <div style="flex: 1;">
                                    <h4>ChatGPT</h4>
                                    <ul style="list-style: disc; margin-left: 20px;">
                                        <li>ChatGPT Plus subscription ($20/month)</li>
                                        <li>Or free account with GPT-3.5</li>
                                        <li>Active session at chat.openai.com</li>
                                    </ul>
                                </div>
                                <div style="flex: 1;">
                                    <h4>Claude</h4>
                                    <ul style="list-style: disc; margin-left: 20px;">
                                        <li>Claude Pro subscription ($20/month)</li>
                                        <li>Or free account (limited messages)</li>
                                        <li>Active session at claude.ai</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="max-width: 800px; margin: 20px 0; background: #e8f5e9;">
                            <h3>✨ Benefits</h3>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li>No API keys or complex setup needed</li>
                                <li>Use your existing AI subscriptions</li>
                                <li>Access to latest models (GPT-4, Claude 3)</li>
                                <li>Full control over responses before sending</li>
                                <li>Works with free tiers (with limitations)</li>
                            </ul>
                        </div>
                    </div>

                    <div id="zdm-own-keys-notice" style="<?php echo !$use_browser_ai ? '' : 'display:none;'; ?>">
                        <div class="card" style="max-width: 800px; margin: 20px 0; background: #f0f8ff;">
                            <h3>Not Using Browser AI?</h3>
                            <p>You can also configure direct API access using your own API keys. Switch to the "Own API Keys" tab to set up OpenAI, Claude, or Gemini API keys.</p>
                            <p><strong>Browser AI is recommended if you:</strong></p>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li>Already have ChatGPT Plus or Claude Pro</li>
                                <li>Don't want to manage API keys and billing</li>
                                <li>Prefer a simpler setup process</li>
                                <li>Want to review responses before using them</li>
                            </ul>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab == 'providers'): ?>
                <div class="zdm-ai-providers">
                    <h2>API Keys Configuration</h2>
                    <p>Configure your AI provider API keys. Enable the providers you have access to.</p>

                    <!-- Quick Setup Guide -->
                    <div class="card" style="max-width: 800px; margin: 20px 0; background: #f0f8ff;">
                        <h3 style="margin-top: 0;">⚡ Quick Setup</h3>
                        <ol style="margin-left: 20px;">
                            <li>Choose your preferred AI provider below</li>
                            <li>Get an API key from the provider's dashboard</li>
                            <li>Enter the key and select a model</li>
                            <li>Click "Test Connection" to verify</li>
                        </ol>
                    </div>

                    <!-- Default Provider Selection -->
                    <table class="form-table">
                        <tr>
                            <th scope="row">Active Provider</th>
                            <td>
                                <select name="zdm_default_ai_provider" id="zdm_default_ai_provider" style="min-width: 200px;">
                                    <option value="">-- Select Provider --</option>
                                    <option value="openai" <?php selected(get_option('zdm_default_ai_provider'), 'openai'); ?>>OpenAI</option>
                                    <option value="claude" <?php selected(get_option('zdm_default_ai_provider'), 'claude'); ?>>Anthropic Claude</option>
                                    <option value="gemini" <?php selected(get_option('zdm_default_ai_provider'), 'gemini'); ?>>Google Gemini</option>
                                </select>
                                <p class="description">Choose which AI provider to use for generating responses.</p>
                            </td>
                        </tr>
                    </table>

                    <!-- OpenAI Configuration -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3>
                            <label>
                                <input type="checkbox" name="zdm_openai_enabled" value="1" <?php checked(get_option('zdm_openai_enabled'), '1'); ?>>
                                OpenAI
                            </label>
                        </h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row">API Key</th>
                                <td>
                                    <input type="password"
                                           name="zdm_openai_api_key"
                                           id="zdm_openai_api_key"
                                           value="<?php echo esc_attr(get_option('zdm_openai_api_key')); ?>"
                                           class="regular-text"
                                           placeholder="sk-...">
                                    <button type="button" class="button" onclick="zdmTestProvider('openai')">Test</button>
                                    <p class="description">Get from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard →</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Model</th>
                                <td>
                                    <select name="zdm_openai_model" id="zdm_openai_model">
                                        <optgroup label="Latest Models">
                                            <option value="gpt-4o" <?php selected(get_option('zdm_openai_model'), 'gpt-4o'); ?>>GPT-4o (Recommended)</option>
                                            <option value="gpt-4o-mini" <?php selected(get_option('zdm_openai_model'), 'gpt-4o-mini'); ?>>GPT-4o Mini (Fast)</option>
                                        </optgroup>
                                        <optgroup label="Previous Generation">
                                            <option value="gpt-4-turbo" <?php selected(get_option('zdm_openai_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                            <option value="gpt-3.5-turbo" <?php selected(get_option('zdm_openai_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                        </optgroup>
                                    </select>
                                    <p class="description">GPT-4o offers best quality, GPT-4o Mini is fastest and cheapest</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Claude Configuration -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3>
                            <label>
                                <input type="checkbox" name="zdm_claude_enabled" value="1" <?php checked(get_option('zdm_claude_enabled'), '1'); ?>>
                                Anthropic Claude
                            </label>
                        </h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row">API Key</th>
                                <td>
                                    <input type="password"
                                           name="zdm_claude_api_key"
                                           id="zdm_claude_api_key"
                                           value="<?php echo esc_attr(get_option('zdm_claude_api_key')); ?>"
                                           class="regular-text"
                                           placeholder="sk-ant-...">
                                    <button type="button" class="button" onclick="zdmTestProvider('claude')">Test</button>
                                    <p class="description">Get from <a href="https://console.anthropic.com/account/keys" target="_blank">Anthropic Console →</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Model</th>
                                <td>
                                    <select name="zdm_claude_model" id="zdm_claude_model">
                                        <optgroup label="Claude 3.5">
                                            <option value="claude-3-5-sonnet-20241022" <?php selected(get_option('zdm_claude_model'), 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (Latest)</option>
                                            <option value="claude-3-5-haiku-20241022" <?php selected(get_option('zdm_claude_model'), 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (Fast)</option>
                                        </optgroup>
                                        <optgroup label="Claude 3">
                                            <option value="claude-3-opus-20240229" <?php selected(get_option('zdm_claude_model'), 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                                            <option value="claude-3-sonnet-20240229" <?php selected(get_option('zdm_claude_model'), 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
                                        </optgroup>
                                    </select>
                                    <p class="description">Claude 3.5 Sonnet offers best balance of quality and speed</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Google Gemini Configuration -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3>
                            <label>
                                <input type="checkbox" name="zdm_gemini_enabled" value="1" <?php checked(get_option('zdm_gemini_enabled'), '1'); ?>>
                                Google Gemini
                            </label>
                        </h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row">API Key</th>
                                <td>
                                    <input type="password"
                                           name="zdm_gemini_api_key"
                                           id="zdm_gemini_api_key"
                                           value="<?php echo esc_attr(get_option('zdm_gemini_api_key')); ?>"
                                           class="regular-text"
                                           placeholder="AIza...">
                                    <button type="button" class="button" onclick="zdmTestProvider('gemini')">Test</button>
                                    <p class="description">Get from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio →</a></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Model</th>
                                <td>
                                    <select name="zdm_gemini_model" id="zdm_gemini_model">
                                        <optgroup label="Latest Models">
                                            <option value="gemini-1.5-pro" <?php selected(get_option('zdm_gemini_model'), 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro (Recommended)</option>
                                            <option value="gemini-1.5-flash" <?php selected(get_option('zdm_gemini_model'), 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash (Fast)</option>
                                        </optgroup>
                                        <optgroup label="Legacy">
                                            <option value="gemini-pro" <?php selected(get_option('zdm_gemini_model'), 'gemini-pro'); ?>>Gemini 1.0 Pro</option>
                                        </optgroup>
                                    </select>
                                    <p class="description">Gemini 1.5 Pro supports up to 1M tokens context window</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            <?php elseif ($active_tab == 'prompts'): ?>
                <div class="zdm-ai-prompts">
                    <h2>Response Configuration</h2>
                    <p>Customize how AI generates support ticket responses.</p>

                    <!-- Quick Settings -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">Quick Settings</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Response Length</th>
                                <td>
                                    <input type="number"
                                           name="zdm_ai_max_tokens"
                                           id="zdm_ai_max_tokens"
                                           value="<?php echo esc_attr(get_option('zdm_ai_max_tokens', '800')); ?>"
                                           min="200"
                                           max="2000"
                                           step="100"
                                           style="width: 100px;">
                                    <label>tokens</label>
                                    <p class="description">Typical: 200-300 (brief), 500-800 (standard), 1000+ (detailed)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Response Style</th>
                                <td>
                                    <input type="range"
                                           name="zdm_ai_temperature"
                                           id="zdm_ai_temperature"
                                           value="<?php echo esc_attr(get_option('zdm_ai_temperature', '0.7')); ?>"
                                           min="0"
                                           max="1"
                                           step="0.1"
                                           style="width: 200px;"
                                           oninput="document.getElementById('temp-value').textContent = this.value">
                                    <span id="temp-value"><?php echo esc_html(get_option('zdm_ai_temperature', '0.7')); ?></span>
                                    <p class="description">0 = Consistent/Formal, 0.7 = Balanced, 1 = Creative/Friendly</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- System Prompt -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">System Instructions</h3>
                        <textarea name="zdm_ai_system_prompt"
                                  id="zdm_ai_system_prompt"
                                  rows="12"
                                  style="width: 100%; font-family: monospace; font-size: 13px;"><?php echo esc_textarea(get_option('zdm_ai_system_prompt', zdm_get_default_system_prompt())); ?></textarea>
                        <p class="description">
                            <strong>Available variables:</strong>
                            <code>{customer_name}</code>, <code>{ticket_subject}</code>, <code>{ticket_description}</code>,
                            <code>{tone}</code>, <code>{response_type}</code>
                        </p>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button" onclick="zdmResetSystemPrompt()">Reset to Default</button>
                        </div>
                    </div>

                    <!-- Template Library -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">Quick Templates</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">🎯 Efficient Support</h4>
                                <p style="font-size: 13px; color: #666;">Direct, solution-focused responses. Gets to the point quickly.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('efficient')">Apply</button>
                            </div>

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">💝 Empathetic Support</h4>
                                <p style="font-size: 13px; color: #666;">Warm, understanding tone. Shows care for customer frustration.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('empathetic')">Apply</button>
                            </div>

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">🔧 Technical Expert</h4>
                                <p style="font-size: 13px; color: #666;">Detailed technical solutions with step-by-step instructions.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('technical')">Apply</button>
                            </div>

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">💼 Enterprise Support</h4>
                                <p style="font-size: 13px; color: #666;">Formal, professional tone for business customers.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('enterprise')">Apply</button>
                            </div>

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">🛍️ Sales & Billing</h4>
                                <p style="font-size: 13px; color: #666;">Handle pricing, refunds, and account questions.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('sales')">Apply</button>
                            </div>

                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                <h4 style="margin-top: 0;">🚀 Product Updates</h4>
                                <p style="font-size: 13px; color: #666;">Announce features and handle feedback positively.</p>
                                <button type="button" class="button button-small" onclick="zdmUseTemplate('product')">Apply</button>
                            </div>

                        </div>
                    </div>

                    <!-- Response Examples -->
                    <div class="card" style="max-width: 800px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">Response Structure Guide</h3>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <p style="margin-top: 0;"><strong>Effective support responses typically include:</strong></p>
                            <ol style="margin-left: 20px;">
                                <li><strong>Greeting & Acknowledgment</strong> - "Hi [Name], thank you for reaching out..."</li>
                                <li><strong>Problem Understanding</strong> - "I understand you're experiencing..."</li>
                                <li><strong>Solution Steps</strong> - Clear, numbered instructions</li>
                                <li><strong>Additional Resources</strong> - Links to docs or tutorials</li>
                                <li><strong>Next Steps</strong> - What happens next or follow-up</li>
                                <li><strong>Closing</strong> - Invitation for further questions</li>
                            </ol>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php submit_button('Save Settings'); ?>
        </form>

        <!-- Test Form (Hidden) -->
        <form id="zdm-test-ai-form" method="post" style="display: none;">
            <?php wp_nonce_field('zdm_test_ai'); ?>
            <input type="hidden" name="test_ai_provider" value="1">
            <input type="hidden" name="provider" id="test-provider" value="">
        </form>
    </div>

    <style>
    .zdm-ai-providers .card,
    .zdm-prompt-templates .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }
    .zdm-ai-providers h3 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    </style>

    <script>
    function zdmTestProvider(provider) {
        document.getElementById('test-provider').value = provider;
        document.getElementById('zdm-test-ai-form').submit();
    }

    function zdmResetSystemPrompt() {
        if (confirm('Reset system prompt to default?')) {
            document.getElementById('zdm_ai_system_prompt').value = <?php echo json_encode(zdm_get_default_system_prompt()); ?>;
        }
    }

    function zdmUseTemplate(template) {
        var prompts = {
            efficient: "You are an efficient support agent focused on solving problems quickly and directly. Skip unnecessary pleasantries and get straight to the solution. Be clear, concise, and action-oriented.\n\nStructure:\n1. Brief acknowledgment\n2. Direct solution with clear steps\n3. Quick closing\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}",

            empathetic: "You are a caring support representative who understands customer frustration. Show genuine empathy and understanding while providing helpful solutions. Use warm, reassuring language.\n\nStructure:\n1. Warm greeting with empathy\n2. Acknowledge their frustration\n3. Gentle, detailed solution\n4. Reassuring follow-up offer\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}",

            technical: "You are a technical support expert providing detailed, accurate solutions. Use precise terminology, include code examples when relevant, and provide comprehensive troubleshooting steps.\n\nStructure:\n1. Technical problem identification\n2. Root cause analysis\n3. Step-by-step technical solution\n4. Prevention tips\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}",

            enterprise: "You are an enterprise support specialist handling high-value business accounts. Maintain formal, professional communication while demonstrating expertise and reliability.\n\nStructure:\n1. Professional greeting\n2. Business impact acknowledgment\n3. Comprehensive solution plan\n4. SLA and escalation information\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}",

            sales: "You are a sales support specialist handling billing, pricing, and account inquiries. Be helpful with financial matters while identifying upsell opportunities when appropriate.\n\nStructure:\n1. Friendly greeting\n2. Clear explanation of billing/pricing\n3. Solution or adjustment offered\n4. Value reinforcement\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}",

            product: "You are a product specialist announcing updates and gathering feedback. Be enthusiastic about improvements while being receptive to customer input.\n\nStructure:\n1. Exciting announcement tone\n2. Clear feature explanation\n3. Benefits to customer\n4. Feedback invitation\n\nVariables: {customer_name}, {ticket_subject}, {ticket_description}"
        };

        if (prompts[template]) {
            document.getElementById('zdm_ai_system_prompt').value = prompts[template];
        }
    }

    function zdmToggleBrowserAI(checkbox) {
        var details = document.getElementById('zdm-browser-ai-details');
        var notice = document.getElementById('zdm-own-keys-notice');

        if (checkbox.checked) {
            details.style.display = 'block';
            notice.style.display = 'none';
        } else {
            details.style.display = 'none';
            notice.style.display = 'block';
        }
    }

    function zdmToggleSubscription(checkbox) {
        // Legacy function for compatibility
        zdmToggleBrowserAI(checkbox);
    }

    function zdmValidateSubscription() {
        var key = document.getElementById('zdm_subscription_key').value;
        var email = document.getElementById('zdm_subscription_email').value;

        if (!key || !email) {
            alert('Please enter both subscription key and email');
            return;
        }

        // Make AJAX call to validate subscription
        jQuery.post(ajaxurl, {
            action: 'zdm_validate_subscription',
            key: key,
            email: email,
            nonce: '<?php echo wp_create_nonce('zdm_subscription_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Subscription validated successfully!');
                location.reload();
            } else {
                alert('Validation failed: ' + response.data.message);
            }
        });
    }
    </script>
    <?php
}

/**
 * Get default system prompt
 */
function zdm_get_default_system_prompt() {
    return "You are a professional customer support agent helping customers with their inquiries. Your goal is to provide helpful, accurate, and empathetic responses that solve customer problems efficiently.

CONTEXT:
- Customer Name: {customer_name}
- Ticket Subject: {ticket_subject}
- Issue Description: {ticket_description}
- Response Type: {response_type}
- Desired Tone: {tone}

INSTRUCTIONS:
1. Start with a personalized greeting using the customer's name
2. Acknowledge their specific issue to show understanding
3. Provide a clear, actionable solution with numbered steps if needed
4. Include any relevant warnings or important notes
5. Offer additional help and provide a warm closing
6. Keep the response concise but thorough

TONE GUIDELINES:
- Be professional yet friendly
- Show empathy for any frustration
- Use clear, simple language
- Avoid technical jargon unless necessary
- Be positive and solution-focused

Generate a response that addresses the customer's needs effectively.";
}

/**
 * Test AI provider connection
 */
function zdm_test_ai_provider($provider) {
    $api_key = get_option('zdm_' . $provider . '_api_key');

    if (empty($api_key)) {
        return array(
            'success' => false,
            'message' => 'API key is not configured for ' . ucfirst($provider)
        );
    }

    // Test with a simple prompt
    $test_prompt = "Hello, this is a test. Please respond with 'Connection successful'.";

    try {
        switch ($provider) {
            case 'openai':
                $result = zdm_test_openai($api_key, get_option('zdm_openai_model', 'gpt-3.5-turbo'));
                break;
            case 'claude':
                $result = zdm_test_claude($api_key, get_option('zdm_claude_model', 'claude-3-haiku-20240307'));
                break;
            case 'gemini':
                $result = zdm_test_gemini($api_key, get_option('zdm_gemini_model', 'gemini-pro'));
                break;
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown provider: ' . $provider
                );
        }

        return $result;
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Error testing ' . ucfirst($provider) . ': ' . $e->getMessage()
        );
    }
}

/**
 * Test OpenAI connection
 */
function zdm_test_openai($api_key, $model) {
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection. Reply with: OK'
                )
            ),
            'max_tokens' => 10
        )),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message()
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        return array(
            'success' => false,
            'message' => 'OpenAI Error: ' . $body['error']['message']
        );
    }

    return array(
        'success' => true,
        'message' => 'OpenAI connection successful! Model: ' . $model
    );
}

/**
 * Test Claude connection
 */
function zdm_test_claude($api_key, $model) {
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model,
            'max_tokens' => 10,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection. Reply with: OK'
                )
            )
        )),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message()
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        return array(
            'success' => false,
            'message' => 'Claude Error: ' . $body['error']['message']
        );
    }

    return array(
        'success' => true,
        'message' => 'Claude connection successful! Model: ' . $model
    );
}

/**
 * Test Gemini connection
 */
function zdm_test_gemini($api_key, $model) {
    $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key, array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => 'Test connection. Reply with: OK'
                        )
                    )
                )
            )
        )),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message()
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        return array(
            'success' => false,
            'message' => 'Gemini Error: ' . $body['error']['message']
        );
    }

    return array(
        'success' => true,
        'message' => 'Google Gemini connection successful! Model: ' . $model
    );
}