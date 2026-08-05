<?php
/**
 * Child Theme Functions
 *
 * Copy this file into your active child theme folder (same folder as
 * page-toastmasters-portal.php). If a functions.php already exists there,
 * paste the code below into it instead of overwriting.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Strip WordPress junk from <head> ──────────────────────────────────────────

add_action('init', function () {
    // WordPress version meta tag  (<meta name="generator" content="WordPress X.X">)
    remove_action('wp_head', 'wp_generator');

    // EditURI / RSD link  (<link rel="EditURI" ...>)
    remove_action('wp_head', 'rsd_link');

    // Windows Live Writer manifest  (<link rel="wlwmanifest" ...>)
    remove_action('wp_head', 'wlwmanifest_link');

    // Shortlink  (<link rel="shortlink" ...>)
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);

    // REST API discovery link  (<link rel="https://api.w.org/" ...>)
    remove_action('wp_head', 'rest_output_link_wp_head', 10);

    // oEmbed discovery links and JS
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');

    // RSS feed links
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);

    // Prev/next post rel links (irrelevant on a single page site)
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);

    // Emoji detection script and styles
    remove_action('wp_head',         'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail',          'wp_staticize_emoji_for_email');
});

// ── Strip REST API link from HTTP response headers ────────────────────────────
remove_action('template_redirect', 'rest_output_link_header', 11, 0);

// ── Hide the admin toolbar on the front end ───────────────────────────────────
// Editors/admins still see it inside wp-admin; it just won't float over the site.
add_filter('show_admin_bar', '__return_false');

// ── Remove WP's auto-injected block / classic-editor styles ──────────────────
// These are only needed if you use the Gutenberg editor on this page; you don't.
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
}, 100);
