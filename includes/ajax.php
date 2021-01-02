<?php
if (!defined('ABSPATH')) die;

if (!empty($_POST['action'])) {
    if (function_exists("{$_POST['action']}_callback")) {
        add_action("wp_ajax_{$_POST['action']}", "{$_POST['action']}_callback") ;
    }
}

/**
 * Validation: Provider Post Title
 */
function sanitize_ga_provider_title_callback()
{
    switch ($_POST) {
        case (empty($_POST)):
            echo -2;
            break;
        // Check if title is empty
        case ($_POST['title'] === ''):
            echo -1;
            break;
        // Check if post title already exists
        default:
            echo post_exists(sanitize_text_field($_POST['title']), '', '', 'ga_providers');
            break;
    }

    die();
}
