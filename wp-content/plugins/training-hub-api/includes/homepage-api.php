<?php
/**
 * Homepage API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register homepage endpoints
add_action('rest_api_init', function() {
    // Get homepage data
    register_rest_route('training-hub/v1', '/homepage', array(
        'methods' => 'GET',
        'callback' => 'training_hub_get_homepage',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Get homepage data with ACF fields
 */
function training_hub_get_homepage() {
    // Check if ACF is active
    if (!function_exists('get_field')) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'ACF is not active',
            'data' => array()
        ), 500);
    }

    // Get the homepage fields group
    $homepage = get_field('homepage_fields', 69);
    
    if (!$homepage) {
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array()
        ));
    }

    // Format the response
    $response = array(
        'hero' => array(
            'label' => $homepage['label'] ?? '',
            'title' => $homepage['title'] ?? '',
            'subtitle' => $homepage['subtitle'] ?? '',
            'image' => $homepage['image'] ?? '',
            'video' => $homepage['video'] ?? ''
        ),
        'explore' => array(
            'label' => $homepage['explore_label'] ?? '',
            'title' => $homepage['explore_title'] ?? '',
            'subtitle' => $homepage['explore_subtitle'] ?? '',
            'programs' => !empty($homepage['programs']) ? 
                array_map(function($program) {
                    return array(
                        'id' => $program->ID,
                        'title' => get_the_title($program->ID),
                        'permalink' => get_permalink($program->ID)
                    );
                }, $homepage['programs']) : []
        ),
        'overview' => array(
            'title' => $homepage['overview_title'] ?? '',
            'description' => $homepage['overview_description'] ?? '',
            'red_line_text' => $homepage['red_line_text'] ?? '',
            'label' => $homepage['overview_label'] ?? '',
            'second_title' => $homepage['overview_second_title'] ?? '',
            'second_description' => $homepage['overview_second_description'] ?? '',
            'image' => $homepage['overview_image'] ?? ''
        ),
        'facilities' => array(
            'label' => $homepage['facilities_label'] ?? '',
            'title' => $homepage['facilities_title'] ?? '',
            'facilities' => array(
                array(
                    'title' => $homepage['facility_title_1'] ?? '',
                    'description' => $homepage['facility_description_1'] ?? ''
                ),
                array(
                    'title' => $homepage['facility_title_2'] ?? '',
                    'description' => $homepage['facility_description_2'] ?? ''
                ),
                array(
                    'title' => $homepage['facility_title_3'] ?? '',
                    'description' => $homepage['facility_description_3'] ?? ''
                )
            ),
            'small_image' => $homepage['facilities_small_image'] ?? '',
            'large_image' => $homepage['facilities_large_image'] ?? ''
        ),
        'news' => array(
            'label' => $homepage['news_label'] ?? '',
            'title' => $homepage['news_title'] ?? '',
            'posts' => !empty($homepage['news']) ? 
                array_map(function($post) {
                    return array(
                        'id' => $post->ID,
                        'title' => get_the_title($post->ID),
                        'excerpt' => get_the_excerpt($post->ID),
                        'permalink' => get_permalink($post->ID),
                        'date' => get_the_date('', $post->ID),
                        'image' => get_the_post_thumbnail_url($post->ID, 'large')
                    );
                }, $homepage['news']) : []
        ),
        'partners' => array(
            'label' => $homepage['partners_label'] ?? '',
            'logos' => !empty($homepage['partners']) ? 
                array_map(function($partner) {
                    return array(
                        'image' => $partner['image'] ?? ''
                    );
                }, $homepage['partners']) : []
        )
    );

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $response
    ));
}
