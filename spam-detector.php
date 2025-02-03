<?php
/*
Plugin Name: Spam Detector
Description: Checks recent comments using the AI to mark spam.
Version: 1.0
Author: Gonçalo Lourenço
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Schedule WP-Cron event on activation.
function cgspd_activate() {
    if ( ! wp_next_scheduled( 'cgspd_hourly' ) ) {
        wp_schedule_event( time(), 'hourly', 'cgspd_hourly' );
    }
}
register_activation_hook( __FILE__, 'cgspd_activate' );

// Clear scheduled event on deactivation.
function cgspd_deactivate() {
    wp_clear_scheduled_hook( 'cgspd_hourly' );
}
register_deactivation_hook( __FILE__, 'cgspd_deactivate' );

// Main spam detection function.
function cgspd_detect_spam() {
    $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : '';
    if ( empty( $api_key ) ) return;

    $current_time  = current_time( 'timestamp' );
    $one_hour_ago  = $current_time - 3600;

    $args = array(
        'date_query' => array(
            array(
                'after'     => date( 'Y-m-d H:i:s', $one_hour_ago ),
                'before'    => date( 'Y-m-d H:i:s', $current_time ),
                'inclusive' => true,
            ),
        ),
        'status' => 'hold',  // Adjust as needed.
        'number' => 0,       // Retrieve all matching comments.
    );

    $comments = get_comments( $args );
    if ( empty( $comments ) ) return;

    foreach ( $comments as $comment ) {
        $text = $comment->comment_content;
        $messages = array(
            array(
                "role"    => "system",
                "content" => "You are a spam classifier. Return only 'spam' or 'not spam'."
            ),
            array(
                "role"    => "user",
                "content" => "Classify this comment: \"$text\""
            ),
        );

        $data = array(
            "model"       => "gpt-3.5-turbo", // Or use "gpt-4" if available.
            "messages"    => $messages,
            "temperature" => 0,
            "max_tokens"  => 10,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode( $data ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) continue;

        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $result['choices'][0]['message']['content'] ) ) {
            $label = trim( strtolower( $result['choices'][0]['message']['content'] ) );
            if ( $label === 'spam' ) {
                wp_spam_comment( $comment->comment_ID );
            }
        }
    }
}
add_action( 'cgspd_hourly', 'cgspd_detect_spam' );

// Add a manual trigger button to the admin dashboard
add_action('admin_menu', function() {
    add_menu_page('Spam Detector', 'Spam Detector', 'manage_options', 'spam-detector', function() {
        if (isset($_POST['run_spam_check'])) {
            cgspd_detect_spam();
            echo "<div class='updated'><p>Spam detection executed!</p></div>";
        }
        echo "<form method='post'><button name='run_spam_check' class='button button-primary'>Run Spam Detection</button></form>";
    });
});
