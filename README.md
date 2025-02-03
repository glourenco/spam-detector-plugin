# Spam Detector Plugin

## Overview
The **Spam Detector Plugin** is a simple and lightweight WordPress plugin that automatically detects and marks spam comments using AI (OpenAI's ChatGPT API). It runs every hour via WP-Cron and also provides a manual trigger in the WordPress admin panel.

## Features
- 🚀 **AI-Powered Detection** – Uses OpenAI to classify comments as spam or not spam.
- ⏰ **Automated WP-Cron Execution** – Runs every hour to check for spam.
- 🛠 **Manual Admin Trigger** – Test and run spam detection anytime with a button.
- 🔒 **Secure API Key Storage** – Uses `wp-config.php` for API credentials.

---

## Installation
### 1. Download or Clone the Plugin
```sh
git clone https://github.com/glourenco/spam-detector-plugin.git
```

### 2. Upload to Your WordPress Site
- Go to **EasyWP** (or your WordPress hosting).
- Access **SFTP** from "File & Database" in EasyWP.
- Upload `spam-detector.php` to `/wp-content/plugins/`.

### 3. Add OpenAI API Key to `wp-config.php`
Edit your `wp-config.php` file and add:
```php
define('OPENAI_API_KEY', 'your_actual_openai_api_key_here');
```

### 4. Activate the Plugin
- Log in to WordPress Admin.
- Go to **Plugins → Installed Plugins**.
- Activate **Spam Detector**.

---

## How It Works
### 🔄 Automatic Spam Detection (Runs Every Hour)
- Uses WP-Cron to execute `cgspd_detect_spam()` every hour.
- Retrieves comments from the past hour.
- Sends comment text to OpenAI.
- Marks as spam if AI returns "spam".

### 🖥️ Manual Admin Trigger
- Adds a **Spam Detector** menu in WordPress Admin.
- Clicking the button manually triggers spam detection.

---

## Code Overview
### 1. Plugin Header & Security Check
```php
/*
Plugin Name: Spam Detector
Description: AI-powered comment spam detector for WordPress.
Version: 1.0
Author: Gonçalo Lourenço
*/
if (!defined('ABSPATH')) exit;
```

### 2. WP-Cron Scheduling on Activation
```php
function cgspd_activate() {
    if (!wp_next_scheduled('cgspd_hourly')) {
        wp_schedule_event(time(), 'hourly', 'cgspd_hourly');
    }
}
register_activation_hook(__FILE__, 'cgspd_activate');
```

### 3. AI-Based Spam Detection Function
```php
function cgspd_detect_spam() {
    $api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if (!$api_key) return;
    
    $comments = get_comments(['status' => 'hold', 'number' => 0]);
    foreach ($comments as $comment) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Return only "spam" or "not spam".'],
                    ['role' => 'user', 'content' => 'Classify: "' . $comment->comment_content . '"']
                ],
                'temperature' => 0,
                'max_tokens' => 10
            ])
        ]);
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($result['choices'][0]['message']['content']) && strtolower(trim($result['choices'][0]['message']['content'])) === 'spam') {
            wp_spam_comment($comment->comment_ID);
        }
    }
}
add_action('cgspd_hourly', 'cgspd_detect_spam');
```

### 4. Manual Trigger Button in WP Admin
```php
add_menu_page('Spam Detector', 'Spam Detector', 'manage_options', 'spam-detector', function() {
    if (isset($_POST['run_spam_check'])) {
        cgspd_detect_spam();
        echo "<div class='updated'><p>Spam detection executed!</p></div>";
    }
    echo "<form method='post'><button name='run_spam_check' class='button button-primary'>Run Spam Detection</button></form>";
});
```

---

## Testing the Plugin
### ✅ Test Without Waiting an Hour
**Option 1: Force WP-Cron Execution**
Visit in your browser:
```
https://yourwebsite.com/wp-cron.php?doing_wp_cron
```

**Option 2: Manually Run the Function**
Create a file `test-spam.php` in WordPress root and run:
```php
<?php
require_once('wp-load.php');
cgspd_detect_spam();
echo "Spam detection executed.";
?>
```
Visit `https://yourwebsite.com/test-spam.php` to trigger it instantly.

**Option 3: Use the Manual Admin Button**
- Navigate to **Spam Detector** in the WordPress admin menu.
- Click the "Run Spam Detection" button.
- This instantly triggers the spam detection process.

---

## Contributing
Have suggestions or improvements? Feel free to open an issue or submit a pull request.

GitHub Repository: **[yourrepo](https://github.com/glourenco/spam-detector-plugin)**

---

## License
This project is licensed under the MIT License.

