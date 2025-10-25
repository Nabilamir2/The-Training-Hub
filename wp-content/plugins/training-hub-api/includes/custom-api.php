<?php
/**
 * Custom API Endpoints Template
 * 
 * Add your custom endpoints here
 * Example: Program enrollment, user progress, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register custom endpoints
add_action('rest_api_init', function() {
    // Example: Get user programs
    register_rest_route('training-hub/v1', '/user/programs', array(
        'methods' => 'GET',
        'callback' => 'training_hub_get_user_programs',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Example: Enroll in program
    register_rest_route('training-hub/v1', '/user/programs/(?P<program_id>\d+)/enroll', array(
        'methods' => 'POST',
        'callback' => 'training_hub_enroll_program',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Example: Get program details
    register_rest_route('training-hub/v1', '/programs/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'training_hub_get_program_details',
        'permission_callback' => '__return_true',
    ));
});

/**
 * Example: Get user's enrolled programs
 */
function training_hub_get_user_programs($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    // Get programs user is enrolled in
    $programs = get_posts(array(
        'post_type' => 'program',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

    $user_programs = array();
    foreach ($programs as $program) {
        // Get enrollment status from user meta
        $enrollment = get_user_meta($user_id, 'program_' . $program->ID, true);

        if ($enrollment) {
            $user_programs[] = array(
                'id' => $program->ID,
                'title' => $program->post_title,
                'status' => $enrollment['status'] ?? 'pending',
                'enrolled_date' => $enrollment['date'] ?? '',
                'progress' => $enrollment['progress'] ?? 0,
            );
        }
    }

    return array(
        'success' => true,
        'programs' => $user_programs,
    );
}

/**
 * Example: Enroll user in program
 */
function training_hub_enroll_program($request) {
    $user_id = training_hub_get_current_user_from_jwt();
    $program_id = intval($request['program_id']);

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    // Check if program exists
    $program = get_post($program_id);
    if (!$program || $program->post_type !== 'program') {
        return new WP_Error('program_not_found', 'Program not found', array('status' => 404));
    }

    // Check if already enrolled
    $enrollment = get_user_meta($user_id, 'program_' . $program_id, true);
    if ($enrollment) {
        return new WP_Error('already_enrolled', 'Already enrolled in this program', array('status' => 400));
    }

    // Enroll user
    $enrollment_data = array(
        'status' => 'active',
        'date' => current_time('mysql'),
        'progress' => 0,
    );

    update_user_meta($user_id, 'program_' . $program_id, $enrollment_data);

    return array(
        'success' => true,
        'message' => 'Successfully enrolled in program',
        'program_id' => $program_id,
    );
}

/**
 * Example: Get program details with ACF fields
 */
function training_hub_get_program_details($request) {
    $program_id = intval($request['id']);

    $program = get_post($program_id);

    if (!$program || $program->post_type !== 'program') {
        return new WP_Error('program_not_found', 'Program not found', array('status' => 404));
    }

    // Get ACF fields if available
    $acf_fields = function_exists('get_fields') ? get_fields($program_id) : array();

    return array(
        'success' => true,
        'program' => array(
            'id' => $program->ID,
            'title' => $program->post_title,
            'excerpt' => $program->post_excerpt,
            'content' => $program->post_content,
            'featured_image' => get_the_post_thumbnail_url($program_id),
            'acf' => $acf_fields,
        ),
    );
}

/**
 * HOW TO ADD YOUR OWN CUSTOM ENDPOINTS:
 * 
 * 1. Create a new function that handles your endpoint logic
 * 2. Register it with register_rest_route() in the rest_api_init hook
 * 3. Use training_hub_get_current_user_from_jwt() to get authenticated user
 * 4. Use training_hub_check_jwt_auth as permission callback for protected endpoints
 * 
 * Example:
 * 
 * register_rest_route('training-hub/v1', '/my-custom-endpoint', array(
 *     'methods' => 'POST',
 *     'callback' => 'my_custom_endpoint_handler',
 *     'permission_callback' => 'training_hub_check_jwt_auth', // For protected endpoints
 * ));
 * 
 * function my_custom_endpoint_handler($request) {
 *     $user_id = training_hub_get_current_user_from_jwt();
 *     
 *     // Your logic here
 *     
 *     return array('success' => true, 'data' => $data);
 * }
 */
