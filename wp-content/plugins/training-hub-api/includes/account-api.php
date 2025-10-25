<?php
/**
 * Account API - User Profile Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register account endpoints
add_action('rest_api_init', function() {
    // Get current user profile
    register_rest_route('training-hub/v1', '/account/profile', array(
        'methods' => 'GET',
        'callback' => 'training_hub_get_profile',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Update user profile
    register_rest_route('training-hub/v1', '/account/profile', array(
        'methods' => 'POST',
        'callback' => 'training_hub_update_profile',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Change password
    register_rest_route('training-hub/v1', '/account/change-password', array(
        'methods' => 'POST',
        'callback' => 'training_hub_change_password',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Get account settings
    register_rest_route('training-hub/v1', '/account/settings', array(
        'methods' => 'GET',
        'callback' => 'training_hub_get_settings',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Update account settings
    register_rest_route('training-hub/v1', '/account/settings', array(
        'methods' => 'POST',
        'callback' => 'training_hub_update_settings',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));

    // Delete account
    register_rest_route('training-hub/v1', '/account/delete', array(
        'methods' => 'POST',
        'callback' => 'training_hub_delete_account',
        'permission_callback' => 'training_hub_check_jwt_auth',
    ));
});

/**
 * Get user profile
 */
function training_hub_get_profile($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    return array(
        'success' => true,
        'user' => array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'avatar' => get_avatar_url($user->ID),
            'bio' => get_user_meta($user->ID, 'description', true),
            'phone' => get_user_meta($user->ID, 'phone', true),
            'registered' => $user->user_registered,
        ),
    );
}

/**
 * Update user profile
 */
function training_hub_update_profile($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $params = $request->get_json_params();
    $update_data = array('ID' => $user_id);

    // Update basic info
    if (isset($params['first_name'])) {
        update_user_meta($user_id, 'first_name', sanitize_text_field($params['first_name']));
    }

    if (isset($params['last_name'])) {
        update_user_meta($user_id, 'last_name', sanitize_text_field($params['last_name']));
    }

    if (isset($params['name'])) {
        $update_data['display_name'] = sanitize_text_field($params['name']);
    }

    if (isset($params['bio'])) {
        update_user_meta($user_id, 'description', sanitize_textarea_field($params['bio']));
    }

    if (isset($params['phone'])) {
        update_user_meta($user_id, 'phone', sanitize_text_field($params['phone']));
    }

    wp_update_user($update_data);

    return training_hub_get_profile($request);
}

/**
 * Change password
 */
function training_hub_change_password($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $params = $request->get_json_params();
    $current_password = $params['current_password'] ?? '';
    $new_password = $params['new_password'] ?? '';
    $confirm_password = $params['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        return new WP_Error('missing_fields', 'All fields are required', array('status' => 400));
    }

    if ($new_password !== $confirm_password) {
        return new WP_Error('password_mismatch', 'New passwords do not match', array('status' => 400));
    }

    if (strlen($new_password) < 6) {
        return new WP_Error('weak_password', 'Password must be at least 6 characters', array('status' => 400));
    }

    // Verify current password
    $user = get_user_by('id', $user_id);
    $user_obj = new WP_User($user_id);

    if (!wp_check_password($current_password, $user_obj->user_pass, $user_id)) {
        return new WP_Error('invalid_password', 'Current password is incorrect', array('status' => 401));
    }

    // Update password
    wp_set_password($new_password, $user_id);

    return array(
        'success' => true,
        'message' => 'Password changed successfully',
    );
}

/**
 * Get account settings
 */
function training_hub_get_settings($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $settings = array(
        'email_notifications' => get_user_meta($user_id, 'email_notifications', true) ?: 'yes',
        'newsletter' => get_user_meta($user_id, 'newsletter', true) ?: 'yes',
        'privacy' => get_user_meta($user_id, 'privacy', true) ?: 'public',
        'two_factor' => get_user_meta($user_id, 'two_factor', true) ?: 'no',
    );

    return array(
        'success' => true,
        'settings' => $settings,
    );
}

/**
 * Update account settings
 */
function training_hub_update_settings($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $params = $request->get_json_params();

    if (isset($params['email_notifications'])) {
        update_user_meta($user_id, 'email_notifications', sanitize_text_field($params['email_notifications']));
    }

    if (isset($params['newsletter'])) {
        update_user_meta($user_id, 'newsletter', sanitize_text_field($params['newsletter']));
    }

    if (isset($params['privacy'])) {
        update_user_meta($user_id, 'privacy', sanitize_text_field($params['privacy']));
    }

    if (isset($params['two_factor'])) {
        update_user_meta($user_id, 'two_factor', sanitize_text_field($params['two_factor']));
    }

    return training_hub_get_settings($request);
}

/**
 * Delete account
 */
function training_hub_delete_account($request) {
    $user_id = training_hub_get_current_user_from_jwt();

    if (!$user_id) {
        return new WP_Error('unauthorized', 'Unauthorized', array('status' => 401));
    }

    $params = $request->get_json_params();
    $password = $params['password'] ?? '';

    if (empty($password)) {
        return new WP_Error('missing_password', 'Password is required', array('status' => 400));
    }

    // Verify password
    $user_obj = new WP_User($user_id);

    if (!wp_check_password($password, $user_obj->user_pass, $user_id)) {
        return new WP_Error('invalid_password', 'Password is incorrect', array('status' => 401));
    }

    // Delete user
    wp_delete_user($user_id);

    return array(
        'success' => true,
        'message' => 'Account deleted successfully',
    );
}
