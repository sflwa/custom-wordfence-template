<?php
/**
 * Plugin Name:       Custom Wordfence Block Page Manager
 * Plugin URI:        https://github.com/sflwa/custom-wordfence-template/
 * Description:       Manage your Wordfence 503 block page with a visual Wizard Mode or Advanced Code Editor.
 * Version:           2.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            South Florida Web Advisors
 * License:           GPL-2.0-or-later
 * Text Domain:       custom-wordfence-template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_WF_Block_Page_Manager {

    private string $option_key = 'cwf_block_settings';
    private array $target_files = array();
    private array $backup_files = array();

    public function __construct() {
        // Target both WAF views (generic 503 and 503-lockout)
        $waf_dir = WP_PLUGIN_DIR . '/wordfence/vendor/wordfence/wf-waf/src/views/';
        
        $this->target_files = array(
            '503'         => $waf_dir . '503.php',
            '503-lockout' => $waf_dir . '503-lockout.php',
        );

        $plugin_dir = plugin_dir_path( __FILE__ );
        $this->backup_files = array(
            '503'         => $plugin_dir . '503.original.php',
            '503-lockout' => $plugin_dir . '503-lockout.original.php',
        );

        add_action( 'admin_init', array( $this, 'check_dependencies' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_preview' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // FORCE WRITE: Guarantees file compilation when settings are saved in WP Admin
        add_action( 'update_option_' . $this->option_key, array( $this, 'force_file_compile' ), 10, 2 );

        if ( $this->is_wordfence_active() ) {
            $this->backup_original_files_once();
        }

        add_action( 'upgrader_process_complete', array( $this, 'restore_template_on_update' ), 10, 2 );
        register_deactivation_hook( __FILE__, array( $this, 'restore_original_files_on_disable' ) );
    }

    private function is_wordfence_active(): bool {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        return is_plugin_active( 'wordfence/wordfence.php' );
    }

    public function check_dependencies(): void {
        if ( ! $this->is_wordfence_active() ) {
            add_action( 'admin_notices', array( $this, 'missing_wordfence_notice' ) );
        }
    }

    public function missing_wordfence_notice(): void {
        echo '<div class="notice notice-error"><p><strong>Custom Wordfence Block Page Manager:</strong> Wordfence Security is not active. Please install and activate Wordfence to use this plugin.</p></div>';
    }

    private function backup_original_files_once(): void {
        foreach ( $this->target_files as $key => $target_file ) {
            $backup_file = $this->backup_files[ $key ];
            if ( ! file_exists( $backup_file ) && file_exists( $target_file ) ) {
                @copy( $target_file, $backup_file );
            }
        }
    }

    public function restore_original_files_on_disable(): void {
        foreach ( $this->target_files as $key => $target_file ) {
            $backup_file = $this->backup_files[ $key ];
            if ( file_exists( $backup_file ) && file_exists( dirname( $target_file ) ) ) {
                @copy( $backup_file, $target_file );
            }
        }
    }

    public function register_admin_menu(): void {
        if ( ! $this->is_wordfence_active() ) return;

        add_options_page(
            'Wordfence 503 Template',
            'Wordfence 503 Page',
            'manage_options',
            'wordfence-503-template',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( $hook !== 'settings_page_wordfence-503-template' ) return;

        wp_enqueue_media();
        wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
        wp_enqueue_script( 'wp-theme-plugin-editor' );
        wp_enqueue_style( 'wp-codemirror' );
    }

    public function register_settings(): void {
        register_setting( 'cwf_settings_group', $this->option_key, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_and_build_template' ),
            'default'           => $this->get_default_settings()
        ) );
    }

    public function force_file_compile( $old_value, $new_value ): void {
        $this->compile_template_to_disk( $new_value );
    }

    private function compile_template_to_disk( array $settings ): bool {
        if ( isset( $settings['mode'] ) && $settings['mode'] === 'advanced' ) {
            $final_code = $settings['advanced_code'];
        } else {
            $final_code = $this->generate_wizard_php_template( $settings );
        }

        $all_written = true;

        foreach ( $this->target_files as $target_file ) {
            if ( file_exists( dirname( $target_file ) ) && is_writable( dirname( $target_file ) ) ) {
                $success = file_put_contents( $target_file, $final_code );
                if ( false === $success ) {
                    $all_written = false;
                }
            } else {
                $all_written = false;
            }
        }

        return $all_written;
    }

    public function sanitize_and_build_template( $input ): array {
        $input = is_array( $input ) ? $input : array();
        $settings = wp_parse_args( $input, $this->get_default_settings() );

        $settings['custom_message'] = wp_kses_post( $settings['custom_message'] );

        $this->compile_template_to_disk( $settings );

        return $settings;
    }

    private function get_default_settings(): array {
        $default_code = '';
        if ( file_exists( $this->target_files['503-lockout'] ) ) {
            $default_code = file_get_contents( $this->target_files['503-lockout'] );
        } elseif ( file_exists( $this->target_files['503'] ) ) {
            $default_code = file_get_contents( $this->target_files['503'] );
        }

        return array(
            'mode'           => 'wizard',
            'show_logo'      => 0,
            'logo_url'       => '',
            'logo_width'     => '300',
            'show_heading'   => 1,
            'heading_text'   => 'Your access to this site has been temporarily limited by the site owner',
            'show_reason'    => 1,
            'reason_text'    => 'Your access to this service has been temporarily limited. Please try again in four hours. (HTTP response code 503)',
            'show_custom'    => 1,
            'custom_message' => '<p>Please contact the site owner to have this block removed.</p>',
            'return_text'    => '« Return to Site Home Page',
            'advanced_code'  => $default_code,
        );
    }

    private function generate_wizard_php_template( array $s ): string {
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Access Limited (503 Service Unavailable)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f1f1f1; color: #444; margin: 0; padding: 40px 20px; }
        .container { max-width: 680px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .logo { text-align: center; margin-bottom: 25px; }
        .logo img { max-width: <?php echo esc_attr($s['logo_width']); ?>px; height: auto; }
        h1 { font-size: 22px; color: #23282d; margin-top: 0; line-height: 1.4; }
        .section { margin-bottom: 20px; line-height: 1.6; }
        .btn-return { display: inline-block; padding: 10px 18px; background-color: #0073aa; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; transition: background 0.2s ease; }
        .btn-return:hover { background-color: #005177; }
        .tech-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .tech-table td { padding: 10px; border: 1px solid #e5e5e5; font-size: 13px; }
        .tech-table td.label { font-weight: bold; width: 30%; color: #a00; background: #f9f9f9; }
        .badge-box { background: #0073aa; color: #fff; padding: 15px 20px; border-radius: 4px; margin-top: 25px; }
        .badge-box h4 { margin: 0 0 4px 0; font-size: 15px; }
        .badge-box p { margin: 0; color: #e0f0f8; font-size: 12px; }
        hr { border: 0; border-top: 1px solid #eee; margin: 30px 0; }
        input[type="email"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60%; font-size: 14px; }
        input[type="submit"] { padding: 9px 15px; background: #0073aa; color: #fff; border: 0; border-radius: 4px; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">

    <?php if ( ! empty( $s['show_logo'] ) && ! empty( $s['logo_url'] ) ) : ?>
        <div class="logo"><img src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="Logo"></div>
    <?php endif; ?>

    <?php if ( ! empty( $s['show_heading'] ) ) : ?>
        <h1><?php echo esc_html( $s['heading_text'] ); ?></h1>
    <?php endif; ?>

    <?php if ( ! empty( $s['show_reason'] ) ) : ?>
        <div class="section"><p><?php echo esc_html( $s['reason_text'] ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $s['show_custom'] ) ) : ?>
        <div class="section">
            <?php echo wp_kses_post( $s['custom_message'] ); ?>
            <?php echo '<?php if (isset($reason) && !empty($reason)) { echo "<p><strong>Block Reason:</strong> " . esc_html($reason) . "</p>"; } ?>'; ?>
        </div>
    <?php endif; ?>

    <!-- Section 5: Return Button -->
    <div class="section" style="margin-top: 25px;">
        <a href="<?php echo '<?php echo esc_url( home_url( "/" ) ); ?>'; ?>" class="btn-return"><?php echo esc_html( $s['return_text'] ); ?></a>
    </div>

    <!-- Section 6: Admin Unlock Box -->
    <hr>
    <?php echo '<?php if (isset($canUnlock) && $canUnlock): ?>'; ?>
    <div class="section">
        <p>If you are a WordPress user with administrative privileges on this site, enter your email address below to receive an unlock email.</p>
        <form method="post" action="<?php echo '<?php echo esc_url($unlockUrl); ?>'; ?>">
            <input type="email" name="email" placeholder="email@example.com" required>
            <input type="submit" value="Send Unlock Email">
        </form>
    </div>
    <hr>
    <?php echo '<?php endif; ?>'; ?>

    <!-- Section 7: Tech Data -->
    <h3>Block Technical Data</h3>
    <table class="tech-table">
        <tr>
            <td class="label">Block Reason:</td>
            <td><?php echo '<?php echo isset($reason) ? esc_html($reason) : "Locked out by security policy."; ?>'; ?></td>
        </tr>
        <tr>
            <td class="label">Time:</td>
            <td><?php echo '<?php echo gmdate("D, d M Y H:i:s") . " GMT"; ?>'; ?></td>
        </tr>
    </table>

    <!-- Section 8: Wordfence Badge -->
    <div class="badge-box">
        <h4>Protected by Wordfence</h4>
        <p>Wordfence is a security plugin installed on over 5 million WordPress sites.</p>
    </div>

</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    public function handle_preview(): void {
        if ( isset( $_GET['cwf_preview'] ) && $_GET['cwf_preview'] === '1' && current_user_can( 'manage_options' ) ) {
            $settings = wp_parse_args( get_option( $this->option_key, array() ), $this->get_default_settings() );
            
            if ( $settings['mode'] === 'advanced' ) {
                $code = $settings['advanced_code'];
            } else {
                $code = $this->generate_wizard_php_template( $settings );
            }

            header('Content-Type: text/html; charset=utf-8');
            echo $code;
            exit;
        }
    }

    public function restore_template_on_update( $upgrader_object, array $options ): void {
        if ( isset( $options['action'], $options['type'] ) && $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
                foreach ( $options['plugins'] as $plugin ) {
                    if ( str_contains( $plugin, 'wordfence.php' ) ) {
                        $saved_settings = get_option( $this->option_key, $this->get_default_settings() );
                        $this->compile_template_to_disk( $saved_settings );
                    }
                }
            }
        }
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $s = wp_parse_args( get_option( $this->option_key, array() ), $this->get_default_settings() );
        $preview_url = add_query_arg( array( 'cwf_preview' => '1' ), admin_url() );
        ?>
        <div class="wrap">
            <h1>Custom Wordfence 503 Template Manager</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'cwf_settings_group' ); ?>
                
                <input type="hidden" id="cwf_mode_field" name="<?php echo $this->option_key; ?>[mode]" value="<?php echo esc_attr( $s['mode'] ); ?>">

                <h2 class="nav-tab-wrapper">
                    <a href="#wizard" class="nav-tab cwf-tab-btn <?php echo $s['mode'] === 'wizard' ? 'nav-tab-active' : ''; ?>" data-mode="wizard">Wizard Mode</a>
                    <a href="#advanced" class="nav-tab cwf-tab-btn <?php echo $s['mode'] === 'advanced' ? 'nav-tab-active' : ''; ?>" data-mode="advanced">Advanced (Code) Mode</a>
                </h2>

                <!-- WIZARD PANEL -->
                <div id="cwf_panel_wizard" class="cwf-panel" style="<?php echo $s['mode'] === 'wizard' ? '' : 'display:none;'; ?>">
                    <table class="form-table">
                        <tr>
                            <th>1. Logo / Image</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo $this->option_key; ?>[show_logo]" value="1" <?php checked( $s['show_logo'], 1 ); ?>> Enable Logo Header</label><br><br>
                                <input type="text" id="cwf_logo_url" name="<?php echo $this->option_key; ?>[logo_url]" value="<?php echo esc_attr( $s['logo_url'] ); ?>" class="regular-text">
                                <button type="button" class="button" id="cwf_upload_logo_btn">Select Image</button><br><br>
                                Max Width (px): <input type="number" name="<?php echo $this->option_key; ?>[logo_width]" value="<?php echo esc_attr( $s['logo_width'] ); ?>" style="width: 80px;">
                            </td>
                        </tr>
                        <tr>
                            <th>2. Heading</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo $this->option_key; ?>[show_heading]" value="1" <?php checked( $s['show_heading'], 1 ); ?>> Show Heading</label><br><br>
                                <input type="text" name="<?php echo $this->option_key; ?>[heading_text]" value="<?php echo esc_attr( $s['heading_text'] ); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th>3. Reason (Single Line)</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo $this->option_key; ?>[show_reason]" value="1" <?php checked( $s['show_reason'], 1 ); ?>> Show Reason Notice</label><br><br>
                                <input type="text" name="<?php echo $this->option_key; ?>[reason_text]" value="<?php echo esc_attr( $s['reason_text'] ); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th>4. Custom Instructions</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo $this->option_key; ?>[show_custom]" value="1" <?php checked( $s['show_custom'], 1 ); ?>> Show Custom Message</label><br><br>
                                <?php 
                                wp_editor( $s['custom_message'], 'cwf_custom_message_editor', array(
                                    'textarea_name' => $this->option_key . '[custom_message]',
                                    'media_buttons' => false,
                                    'textarea_rows' => 6,
                                ) );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>5. Return Button Label</th>
                            <td>
                                <input type="text" name="<?php echo $this->option_key; ?>[return_text]" value="<?php echo esc_attr( $s['return_text'] ); ?>" class="regular-text"><br>
                                <span class="description">Rendered as a styled button at the end of content.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Core Support Sections</th>
                            <td>
                                <p class="description"><strong>Note:</strong> Admin Unlock Form, Block Technical Data, and Wordfence Badge are strictly enforced in Wizard Mode for safety and support compliance. To remove these, switch to <strong>Advanced Mode</strong>.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ADVANCED PANEL -->
                <div id="cwf_panel_advanced" class="cwf-panel" style="<?php echo $s['mode'] === 'advanced' ? '' : 'display:none;'; ?>">
                    <p style="margin-top: 20px;">Directly edit raw HTML/PHP file. Saving writes directly into Wordfence block view templates (<code>503.php</code> &amp; <code>503-lockout.php</code>).</p>
                    <textarea id="cwf_code_editor" name="<?php echo $this->option_key; ?>[advanced_code]" rows="30" class="large-text code"><?php echo esc_textarea( $s['advanced_code'] ); ?></textarea>
                </div>

                <p class="submit" style="display: flex; gap: 10px; align-items: center;">
                    <?php submit_button( 'Save Template & Compile File', 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-secondary">Preview Template ↗</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Tab Toggle
            $('.cwf-tab-btn').click(function(e) {
                e.preventDefault();
                var selectedMode = $(this).data('mode');
                
                $('.cwf-tab-btn').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('#cwf_mode_field').val(selectedMode);
                $('.cwf-panel').hide();
                $('#cwf_panel_' + selectedMode).show();
            });

            // Media Selector
            $('#cwf_upload_logo_btn').click(function(e) {
                e.preventDefault();
                var custom_uploader = wp.media({
                    title: 'Select Logo',
                    button: { text: 'Use Logo' },
                    multiple: false
                }).on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#cwf_logo_url').val(attachment.url);
                }).open();
            });

            // CodeMirror Auto-Init
            if ($('#cwf_code_editor').length && wp.codeEditor) {
                wp.codeEditor.initialize('cwf_code_editor', {
                    lineNumbers: true,
                    mode: 'htmlmixed'
                });
            }
        });
        </script>
        <?php
    }
}

new Custom_WF_Block_Page_Manager();
