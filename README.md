### Unsubscribe Manager Plugin

A simple and secure WordPress plugin to manage user email subscriptions and preferences via a custom database and on-site forms.

***

### Initial Project Setup (Local Development)

This project uses Docker Compose to create a self-contained WordPress development environment.

1.  **Prerequisites:** Ensure you have Docker and Docker Compose installed and running on your system.
2.  **Start the Stack:** In your project's root directory (where `docker-compose.yml` is located), run the following command to start the web server and database containers:

    ```bash
    docker-compose up -d
    ```

3.  **Configure `wp-config.php`:** Copy `wp-config-sample.php` to a new file named `wp-config.php`. Edit the new file with the database connection details from your `docker-compose.yml` file.

    ```php
    // ** MySQL settings ** //
    define('DB_NAME', 'wordpress');
    define('DB_USER', 'your_db_username');
    define('DB_PASSWORD', 'your_db_password');
    define('DB_HOST', 'db'); // 'db' is the name of the database service in docker-compose.yml
    ```
4.  **Complete WordPress Installation:** Open your web browser and navigate to the address specified in your `docker-compose.yml` file (e.g., `http://localhost`). Follow the on-screen instructions to complete the WordPress "5-minute install."

***

### Features
* **User Preferences:** Provides a secure form for users to update their subscription settings.
* **One-Click Unsubscribe:** Supports the `List-Unsubscribe` header for instant opt-outs in email clients.
* **Admin Tools:** Includes a shortcode for administrators to test email functionality.

### Installation & Usage
1.  Upload the `unsubscribe-manager` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin in your WordPress dashboard.
3.  Create an "Email Preferences" page with the shortcode `[mindplex_unsubscribe_form]`.
4.  Create an "Admin Demo" page with the shortcode `[mindplex_send_demonstration_email]`.
5.  **Important:** Install and configure an SMTP plugin (like **WP Mail SMTP**) for reliable email delivery and to ensure the one-click unsubscribe feature functions correctly.

### Customization
* **Styling:** Modify `mindplex-styles.css` to customize the appearance of the forms.
