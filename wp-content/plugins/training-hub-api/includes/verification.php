<?php
/**
 * Verification functionality for Training Hub API
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register verification endpoints
add_action('rest_api_init', function() {
    // Verify email endpoint
    register_rest_route('training-hub/v1', '/auth/verify-email', array(
        'methods' => 'POST',
        'callback' => 'training_hub_verify_email',
        'permission_callback' => '__return_true',
    ));

    // Resend verification code endpoint
    register_rest_route('training-hub/v1', '/auth/resend-verification', array(
        'methods' => 'POST',
        'callback' => 'training_hub_resend_verification',
        'permission_callback' => '__return_true',
    ));
});

/**
 * Generate a 6-digit verification code
 */
function training_hub_generate_verification_code() {
    return str_pad(wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send verification email
 */
function training_hub_send_verification_email($email, $code) {
    $subject = 'Verify Your Email Address';
    $message = sprintf(
        'Your verification code is: %s\n\nThis code will expire in 1 hour.',
        $code
    );
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    return wp_mail($email, $subject, $message, $headers);
}

/**
 * Store verification code in user meta
 */
function training_hub_store_verification_code($user_id, $code) {
    $expires = time() + HOUR_IN_SECONDS; // 1 hour expiration
    update_user_meta($user_id, '_verification_code', wp_hash($code));
    update_user_meta($user_id, '_verification_code_expires', $expires);
}

/**
 * Verify email with code
 */
function training_hub_verify_email($request) {
    $email = sanitize_email($request->get_param('email'));
    $code = sanitize_text_field($request->get_param('code'));
    
    if (empty($email) || empty($code)) {
        return new WP_Error('missing_fields', 'Email and verification code are required', array('status' => 400));
    }
    
    $user = get_user_by('email', $email);
    
    if (!$user) {
        return new WP_Error('invalid_email', 'No account found with this email', array('status' => 404));
    }
    
    $stored_code = get_user_meta($user->ID, '_verification_code', true);
    $expires = (int) get_user_meta($user->ID, '_verification_code_expires', true);
    
    if (empty($stored_code) || !wp_check_password($code, $stored_code) || $expires < time()) {
        return new WP_Error('invalid_code', 'Invalid or expired verification code', array('status' => 400));
    }
    
    // Mark user as verified
    update_user_meta($user->ID, '_email_verified', true);
    
    // Clean up
    delete_user_meta($user->ID, '_verification_code');
    delete_user_meta($user->ID, '_verification_code_expires');
    
    return array(
        'success' => true,
        'message' => 'Email verified successfully',
        'user_id' => $user->ID
    );
}

/**
 * Resend verification code
 */
function training_hub_resend_verification($request) {
    $email = sanitize_email($request->get_param('email'));
    
    if (empty($email)) {
        return new WP_Error('missing_email', 'Email is required', array('status' => 400));
    }
    
    $user = get_user_by('email', $email);
    
    if (!$user) {
        return new WP_Error('invalid_email', 'No account found with this email', array('status' => 404));
    }
    
    // Generate new verification code
    $code = training_hub_generate_verification_code();
    training_hub_store_verification_code($user->ID, $code);
    
    // Send verification email
    $sent = training_hub_send_verification_email($email, $code);
    
    if (!$sent) {
        return new WP_Error('email_failed', 'Failed to send verification email', array('status' => 500));
    }
    
    return array(
        'success' => true,
        'message' => 'Verification code resent successfully'
    );
}

/**
 * Check if user's email is verified
 */
function training_hub_is_email_verified($user_id) {
    return (bool) get_user_meta($user_id, '_email_verified', true);
}

/**
 * Check if user needs to verify email before login
 */
function training_hub_needs_verification($user) {
    return !training_hub_is_email_verified($user->ID);
}
