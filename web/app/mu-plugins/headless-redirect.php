<?php
/**
 * Plugin Name: Headless Frontend Redirect
 * Description: Redirects non-logged-in users to the frontend for all non-admin pages.
 */

add_action('template_redirect', function () {
    // Don't redirect if no frontend URL is set
    $frontend_url = env('FRONTEND_URL');
    if (empty($frontend_url)) {
        return;
    }

    // Don't redirect logged-in users
    if (is_user_logged_in()) {
        return;
    }

    // Don't redirect admin, login, AJAX, REST API, GraphQL, or cron requests
    if (
        is_admin() ||
        wp_doing_ajax() ||
        wp_doing_cron() ||
        (defined('REST_REQUEST') && REST_REQUEST) ||
        (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST) ||
        strpos($_SERVER['REQUEST_URI'], '/wp-login.php') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/graphql') !== false
    ) {
        return;
    }

    // Build the frontend URL with the current path
    $path = $_SERVER['REQUEST_URI'];
    $redirect_url = rtrim($frontend_url, '/') . $path;

    wp_redirect($redirect_url, 301);
    exit;
});
