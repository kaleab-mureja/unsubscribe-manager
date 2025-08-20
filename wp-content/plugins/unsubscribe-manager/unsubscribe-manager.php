<?php
/**
 * Plugin Name: Unsubscribe Manager
 * Description: Manages user email preferences and unsubscriptions.
 * Version: 2.9
 * Author: Kaleab Mureja
 * Author URI: https://yourwebsite.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global variable for the custom table name.
global $mindplex_db_table_name;
$mindplex_db_table_name = 'mindplex_email_subscriptions';

/* --- Database Table Creation --- */
function mindplex_create_db_table() {
    global $wpdb;
    global $mindplex_db_table_name;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $mindplex_db_table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_email varchar(255) NOT NULL,
        email_type varchar(50) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'subscribed',
        PRIMARY KEY  (id),
        UNIQUE KEY user_type (user_email, email_type)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'mindplex_create_db_table' );

/* --- Enqueue external CSS file with conditional loading --- */
function mindplex_enqueue_styles() {
    // Check if on the front-end page with the unsubscribe form or admin tool
    // Change '9' and '11' to your specific page IDs
    if ( is_page(9) || is_page(11) ) { 
        wp_enqueue_style( 'mindplex-styles', plugins_url( 'mindplex-styles.css', __FILE__ ) );
    }
}
add_action( 'wp_enqueue_scripts', 'mindplex_enqueue_styles' );

/* --- Helper Function to get Unsubscribe Link with Nonce --- */
function mindplex_get_unsubscribe_link($email, $action_type = 'preferences') {
    $unsubscribe_page_id = 9; // Change this to your Unsubscribe page ID
    $nonce_action = ($action_type === 'one_click') ? 'mindplex_one_click_unsubscribe' : 'mindplex_unsubscribe_action';

    return add_query_arg( array(
        'page_id' => $unsubscribe_page_id,
        'email'   => urlencode( $email ),
        'action'  => $action_type,
        '_wpnonce' => wp_create_nonce($nonce_action)
    ), get_home_url() );
}

/* --- Unsubscribe Action Handler for One-Click Unsubscribe --- */
function mindplex_one_click_unsubscribe_action() {
    if ( isset($_GET['action']) && $_GET['action'] === 'one_click' ) {
        if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'mindplex_one_click_unsubscribe') ) {
            wp_die('Invalid security token.');
        }

        $user_email = sanitize_email($_GET['email']);
        $email_type = 'all'; // Unsubscribe from all lists in this case

        if ( ! empty($user_email) ) {
            mindplex_unsubscribe_user($user_email, $email_type);
            wp_die('You have been successfully unsubscribed. Thank you.');
        } else {
            wp_die('Invalid email address.');
        }
    }
}
add_action('init', 'mindplex_one_click_unsubscribe_action');

/* --- Helper function to unsubscribe a user from all lists --- */
function mindplex_unsubscribe_user($user_email, $email_type) {
    global $wpdb;
    global $mindplex_db_table_name;

    $all_types = ['publications', 'notifications', 'interactions', 'weekly_digest', 'mindplex_updates'];
    foreach ($all_types as $type) {
        $wpdb->replace(
            $mindplex_db_table_name,
            ['user_email' => $user_email, 'email_type' => $type, 'status' => 'unsubscribed'],
            ['%s', '%s', '%s']
        );
    }
}

/* --- Helper Function to Check Subscription Status --- */
function mindplex_is_subscribed($user_email, $email_type) {
    global $wpdb;
    global $mindplex_db_table_name;
    
    // A user is "subscribed" to a type if they do NOT have an "unsubscribed" entry for it.
    $subscription_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $mindplex_db_table_name WHERE user_email = %s AND email_type = %s",
            $user_email,
            $email_type
        )
    );
    
    return $subscription_exists == 0;
}

/* --- Shortcode for Unsubscribe Form --- */
function mindplex_unsubscribe_form_shortcode() {
    ob_start();

    global $wpdb;
    global $mindplex_db_table_name;
    
    $user_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

    if ( empty($user_email) || ! wp_verify_nonce($nonce, 'mindplex_unsubscribe_action') ) {
        return '<p class="mindplex-error-message">Invalid or expired security link. Please use a new link from a recent email.</p>';
    }

    if (isset($_POST['update_preferences'])) {
        $selected_types = isset($_POST['email_types']) ? (array) $_POST['email_types'] : [];
        $all_types = ['publications', 'notifications', 'interactions', 'weekly_digest', 'mindplex_updates'];
        
        foreach ($all_types as $type) {
            if (in_array($type, $selected_types)) {
                $wpdb->delete(
                    $mindplex_db_table_name,
                    ['user_email' => $user_email, 'email_type' => $type]
                );
            } else {
                $wpdb->replace(
                    $mindplex_db_table_name,
                    ['user_email' => $user_email, 'email_type' => $type, 'status' => 'unsubscribed'],
                    ['%s', '%s', '%s']
                );
            }
        }
        echo '<p class="mindplex-success-message">Your preferences have been updated successfully!</p>';
    }

    $unsubscribed_types = $wpdb->get_col(
        $wpdb->prepare("SELECT email_type FROM $mindplex_db_table_name WHERE user_email = %s", $user_email)
    );

    $email_types = [
        'publications'     => 'Mindplex Publications: Receive timely emails about new publications.',
        'notifications'    => 'Notification Emails: Receive emails when someone you follow publishes content.',
        'interactions'     => 'Interaction Emails: Receive emails when someone interacts with your content.',
        'weekly_digest'    => 'Weekly Digest: A summary of popular and recommended content.',
        'mindplex_updates' => 'Mindplex Updates: Important announcements and platform updates.'
    ];

    ?>
    <div class="mindplex-wrapper">
        <div class="mindplex-form-card">
            <div class="mindplex-header">
                <h1 style="color: white; margin: 0;">Email Preferences</h1>
            </div>
            <div class="mindplex-content">
                <h2>Your Email Preferences</h2>
                <p>Hello! On this page, you can easily manage the types of emails you receive from us. Simply check or uncheck the boxes below to update your subscription settings.</p>
                <form method="post">
                    <?php foreach ($email_types as $type => $description) : ?>
                        <div style="margin-bottom: 15px;">
                            <label>
                                <input type="checkbox" name="email_types[]" value="<?php echo esc_attr($type); ?>" <?php checked(!in_array($type, $unsubscribed_types)); ?>>
                                <?php echo esc_html($description); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="update_preferences">Update Preferences</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* --- Shortcode for Sending Demonstration Email --- */
function mindplex_send_demonstration_email() {
    ob_start();
    $output = '';

    if ( isset( $_POST['send_email'] ) ) {
        $to_email = sanitize_email( $_POST['recipient_email'] );
        $email_type_to_send = sanitize_text_field( $_POST['email_type'] );

        if ( ! is_email( $to_email ) ) {
            $output = '<p class="admin-error-message">Please enter a valid email address.</p>';
        } elseif ( ! mindplex_is_subscribed($to_email, $email_type_to_send) ) {
            $output = '<p class="admin-warning-message">Email not sent. User is unsubscribed from this preference.</p>';
        } else {
            $preferences_link = mindplex_get_unsubscribe_link($to_email, 'preferences');
            $one_click_unsubscribe_link = mindplex_get_unsubscribe_link($to_email, 'one_click');

            $subject = 'Your ' . esc_html( $email_type_to_send ) . ' Subscription';
            
            $headers = array();
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'List-Unsubscribe: <mailto:' . esc_url($to_email) . '>, <' . esc_url($one_click_unsubscribe_link) . '>';
            $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';

            $message = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Email Preferences</title>
    <style type="text/css">
        body { margin: 0; padding: 0; min-width: 100%; background-color: #f4f4f4; }
        .content { width: 100%; max-width: 600px; }
        .header { background-color: #0073aa; padding: 20px; color: #ffffff; text-align: center; font-family: sans-serif; }
        .body-content { padding: 20px; background-color: #ffffff; font-family: sans-serif; color: #555555; line-height: 1.5; }
        .button { background-color: #0073aa; border-radius: 5px; text-align: center; }
        .button a { color: #ffffff; text-decoration: none; display: block; padding: 10px 20px; font-weight: bold; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #999999; font-family: sans-serif; }
    </style>
</head>
<body>
    <center style="width: 100%; table-layout: fixed; background-color: #f4f4f4;">
        <table class="content" align="center" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto; background-color: #ffffff;">
            <tr>
                <td class="header">
                    <h2>Email Preferences</h2>
                </td>
            </tr>
            
            <tr>
                <td class="body-content">
                    <p>Hello,</p>
                    <p>You are receiving this email because you are subscribed to our <strong>' . esc_html($email_type_to_send) . '</strong> notifications.</p>
                    <p>We believe in giving you full control over the emails you receive. Below you will find a button that will take you to your personal preference page where you can easily update your subscription settings.</p>

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px auto;">
                        <tr>
                            <td class="button">
                                <a href="' . esc_url( $preferences_link ) . '">
                                    Go to Preferences Page
                                </a>
                            </td>
                        </tr>
                    </table>

                    <p>Thank you for being a subscriber.</p>
                    <p>Best regards,<br/>The Unsubscribe Manager Team</p>
                </td>
            </tr>

            <tr>
                <td class="footer">
                    <p>If you no longer wish to receive any emails from us, please use the unsubscribe button in the email header or click <a href="' . esc_url($one_click_unsubscribe_link) . '" style="color: #0073aa;">here to unsubscribe immediately</a>.</p>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>';

            $sent = wp_mail( $to_email, $subject, $message, $headers );
            
            if ( $sent ) {
                $output = '<p class="admin-success-message">Email sent successfully to ' . esc_html( $to_email ) . '. Check your inbox!</p>';
            } else {
                $output = '<p class="admin-error-message">Failed to send email. Please check your SMTP settings.</p>';
            }
        }
    }
    ?>
    <div class="admin-form-container">
        <h2>Unsubscribe Admin Tool</h2>
        <?php echo $output; ?>
        <form method="post" action="">
            <label for="recipient_email">Enter an email address to send the unsubscribe link to:</label>
            <input type="email" id="recipient_email" name="recipient_email" placeholder="e.g., test@example.com" required>
            
            <label for="email_type">Select Email Type:</label>
            <select id="email_type" name="email_type">
                <option value="publications">Publications</option>
                <option value="notifications">Notifications</option>
                <option value="interactions">Interactions</option>
                <option value="weekly_digest">Weekly Digest</option>
                <option value="mindplex_updates">Mindplex Updates</option>
            </select>

            <input type="submit" name="send_email" value="Send Email">
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Register both shortcodes
add_shortcode('mindplex_unsubscribe_form', 'mindplex_unsubscribe_form_shortcode');
add_shortcode('mindplex_send_demonstration_email', 'mindplex_send_demonstration_email');
