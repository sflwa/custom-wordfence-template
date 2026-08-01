<?php
/**
 * Plugin Name:       Custom Wordfence Block Page Manager
 * Plugin URI:        https://github.com/your-username/custom-wordfence-template
 * Description:       Manage your Wordfence 503 block page with a visual Wizard Mode or Advanced Code Editor.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       custom-wordfence-template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_WF_Block_Page_Manager {

    private string $option_key = 'cwf_block_settings';
    private string $target_file;
    private string $backup_file;

    public function __construct() {
        $this->target_file = WP_PLUGIN_DIR . '/wordfence/lib/wf503.php';
        $this->backup_file = plugin_dir_path( __FILE__ ) . 'wf503.original.php';

        add_action( 'admin_init', array( $this, 'check_dependencies' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_preview' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        if ( $this->is_wordfence_active() ) {
            $this->backup_original_file_once();
        }

        add_action( 'upgrader_process_complete', array( $this, 'restore_template_on_update' ), 10, 2 );
        register_deactivation_hook( __FILE__, array( $this, 'restore_original_file_on_disable' ) );
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

    private function backup_original_file_once(): void {
        if ( ! file_exists( $this->backup_file ) && file_exists( $this->target_file ) ) {
            copy( $this->target_file, $this->backup_file );
        }
    }

    public function restore_original_file_on_disable(): void {
        if ( file_exists( $this->backup_file ) && file_exists( dirname( $this->target_file ) ) ) {
            copy( $this->backup_file, $this->target_file );
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

        $editor_settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
        if ( false !== $editor_settings ) {
            wp_add_inline_script(
                'code-editor',
                sprintf( 'jQuery( function() { if(jQuery("#cwf_code_editor").length) { wp.codeEditor.initialize( "cwf_code_editor", %s ); } } );', wp_json_encode( $editor_settings ) )
            );
        }
    }

    public function register_settings(): void {
        register_setting( 'cwf_settings_group', $this->option_key, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_and_build_template' ),
            'default'           => $this->get_default_settings()
        ) );
    }

    private function get_default_settings(): array {
        return array(
            'mode'           => 'wizard',
            'show_logo'      => 0,
            'logo_url'       => '',
            'logo_width'     => '200',
            'show_heading'   => 1,
            'heading_text'   => 'Your access to this site has been temporarily limited by the site owner',
            'show_reason'    => 1,
            'reason_text'    => 'Your access to this service has been temporarily limited. Please try again in a few minutes. (HTTP response code 503)',
            'show_custom'    => 1,
            'custom_message' => '<p>If you think you have been blocked in error, please contact the site owner for assistance.</p>',
            'return_text'    => 'Return to Site Home Page',
            'advanced_code'  => file_exists( $this->target_file ) ? file_get_contents( $this->target_file ) : ''
        );
    }

    public function sanitize_and_build_template( $input ): array {
        $input = is_array( $input ) ? $input : array();
        $settings = wp_parse_args( $input, $this->get_default_settings() );

        $settings['custom_message'] = wp_kses_post( $settings['custom_message'] );

        if ( $settings['mode'] === 'advanced' ) {
            $final_code = $settings['advanced_code'];
        } else {
            $final_code = $this->generate_wizard_php_template( $settings );
        }

        if ( file_exists( dirname( $this->target_file ) ) ) {
            file_put_contents( $this->target_file, $final_code );
        }

        return $settings;
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
        .container { max-width: 700px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .logo { text-align: center; margin-bottom: 25px; }
        .logo img { max-width: <?php echo esc_attr($s['logo_width']); ?>px; height: auto; }
        h1 { font-size: 24px; color: #23282d; margin-top: 0; }
        .section { margin-bottom: 20px; line-height: 1.6; }
        .btn-return { display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; transition: background 0.2s ease; }
        .btn-return:hover { background-color: #005177; }
        .tech-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #f9f9f9; }
        .tech-table td { padding: 10px; border: 1px solid #e5e5e5; font-size: 13px; }
        .tech-table td.label { font-weight: bold; width: 30%; color: #900; }
        .badge-box { background: #0073aa; color: #fff; padding: 15px; border-radius: 4px; display: flex; gap: 15px; align-items: center; margin-top: 25px; }
        .badge-box a { color: #fff; text-decoration: underline; }
        hr { border: 0; border-top: 1px solid #eee; margin: 25px 0; }
        input[type="email"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60%; }
        input[type="submit"] { padding: 8px 15px; background: #0073aa; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
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

    <!-- Section 5: Return Button (Always rendered in Wizard Mode) -->
    <div class="section" style="margin-top: 25px;">
        <a href="<?php echo '<?php echo esc_url( home_url( "/" ) ); ?>'; ?>" class="btn-return">&laquo; <?php echo esc_html( $s['return_text'] ); ?></a>
    </div>

    <!-- Section 6: Admin Unlock Box (Always rendered in Wizard Mode) -->
    <hr>
    <div class="section">
        <p>If you are a WordPress user with administrative privileges on this site, enter your email address below to receive an unlock email.</p>
        <form method="post" action="<?php echo '<?php echo esc_url(wfUtils::getSiteBaseURL()); ?>'; ?>">
            <input type="hidden" name="wordfence_syncAttackData" value="<?php echo '<?php echo esc_attr(wfUtils::getSiteBaseURL()); ?>'; ?>">
            <input type="email" name="email" placeholder="email@example.com" required>
            <input type="submit" value="Send Unlock Email">
        </form>
    </div>

    <!-- Section 7: Tech Data (Always rendered in Wizard Mode) -->
    <hr>
    <h3>Block Technical Data</h3>
    <table class="tech-table">
        <tr>
            <td class="label">Block Reason:</td>
            <td><?php echo '<?php echo isset($reason) ? esc_html($reason) : "Locked out by security policy."; ?>'; ?></td>
        </tr>
        <tr>
            <td class="label">Time:</td>
            <td><?php echo '<?php echo gmdate("D, d M Y H:i:s GMT"); ?>'; ?></td>
        </tr>
    </table>

    <!-- Section 8: Wordfence Badge (Always rendered in Wizard Mode) -->
    <div class="badge-box">
        <div>
            <strong>Protected by Wordfence</strong><br>
            <small>Wordfence is a security plugin installed on over 5 million WordPress sites.</small>
        </div>
    </div>

</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    public function handle_preview(): void {
        if ( isset( $_GET['cwf_preview'] ) && $_GET['cwf_preview'] === '1' && current_user_can( 'manage_options' ) ) {
            $reason     = "You have been temporarily locked out for attempting to log in with an invalid username.";
            $customText = "<p><strong>NOTE:</strong> If you are an administrator, email support@example.com.</p>";

            if ( file_exists( $this->target_file ) ) {
                include $this->target_file;
            }
            exit;
        }
    }

    public function restore_template_on_update( $upgrader_object, array $options ): void {
        if ( isset( $options['action'], $options['type'] ) && $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
                foreach ( $options['plugins'] as $plugin ) {
                    if ( str_contains( $plugin, 'wordfence.php' ) ) {
                        $saved_settings = get_option( $this->option_key );
                        $this->sanitize_and_build_template( $saved_settings );
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
                
                <h2 class="nav-tab-wrapper">
                    <label class="nav-tab <?php echo $s['mode'] === 'wizard' ? 'nav-tab-active' : ''; ?>">
                        <input type="radio" name="<?php echo $this->option_key; ?>[mode]" value="wizard" <?php checked( $s['mode'], 'wizard' ); ?> style="display:none;" onclick="this.form.submit();"> Wizard Mode
                    </label>
                    <label class="nav-tab <?php echo $s['mode'] === 'advanced' ? 'nav-tab-active' : ''; ?>">
                        <input type="radio" name="<?php echo $this->option_key; ?>[mode]" value="advanced" <?php checked( $s['mode'], 'advanced' ); ?> style="display:none;" onclick="this.form.submit();"> Advanced (Code) Mode
                    </label>
                </h2>

                <?php if ( $s['mode'] === 'wizard' ) : ?>
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

                <?php else : ?>
                    <p style="margin-top: 20px;">Directly edit raw HTML/PHP file. Saving writes directly into <code>wf503.php</code>.</p>
                    <textarea id="cwf_code_editor" name="<?php echo $this->option_key; ?>[advanced_code]" rows="30" class="large-text code"><?php echo esc_textarea( $s['advanced_code'] ); ?></textarea>
                <?php endif; ?>

                <p class="submit" style="display: flex; gap: 10px; align-items: center;">
                    <?php submit_button( 'Save Template & Compile File', 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-secondary">Preview Template ↗</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
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
        });
        </script>
        <?php
    }
}

new Custom_WF_Block_Page_Manager();
