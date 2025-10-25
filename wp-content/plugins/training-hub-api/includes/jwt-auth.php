<?php
/**
 * JWT Authentication for Training Hub API
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register JWT endpoints
add_action('rest_api_init', function() {
    // Login endpoint
    register_rest_route('training-hub/v1', '/auth/login', array(
        'methods' => 'POST',
        'callback' => 'training_hub_login',
        'permission_callback' => '__return_true',
    ));

    // Register endpoint
    register_rest_route('training-hub/v1', '/auth/register', array(
        'methods' => 'POST',
        'callback' => 'training_hub_register',
        'permission_callback' => '__return_true',
    ));

    // Verify token endpoint
    register_rest_route('training-hub/v1', '/auth/verify', array(
        'methods' => 'POST',
        'callback' => 'training_hub_verify_token',
        'permission_callback' => '__return_true',
    ));

    // Refresh token endpoint
    register_rest_route('training-hub/v1', '/auth/refresh', array(
        'methods' => 'POST',
        'callback' => 'training_hub_refresh_token',
        'permission_callback' => '__return_true',
    ));
});

/**
 * Generate JWT token
 */
function training_hub_generate_jwt($user_id) {
    $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key-change-this';
    
    $issued_at = time();
    $expire = $issued_at + (30 * DAY_IN_SECONDS); // 30 days
    
    $payload = array(
        'iss' => get_bloginfo('url'),
        'iat' => $issued_at,
        'exp' => $expire,
        'user_id' => $user_id,
    );

    return training_hub_encode_jwt($payload, $secret_key);
}

/**
 * Encode JWT token
 */
function training_hub_encode_jwt($payload, $secret) {
    $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
    $payload_encoded = json_encode($payload);

    $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $payload_encoded = rtrim(strtr(base64_encode($payload_encoded), '+/', '-_'), '=');

    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return "$header_encoded.$payload_encoded.$signature_encoded";
}

/**
 * Decode JWT token
 */
function training_hub_decode_jwt($token, $secret) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }

    list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_expected = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    if ($signature_encoded !== $signature_expected) {
        return false;
    }

    $payload = json_decode(base64_decode(strtr($payload_encoded, '-_', '+/')), true);

    if ($payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

/**
 * Login user
 */
function training_hub_login($request) {
    $email = $request->get_param('email');
    $password = $request->get_param('password');

    if (empty($email) || empty($password)) {
        return new WP_Error('missing_credentials', 'Email and password are required', array('status' => 400));
    }

    // Get user by email
    $user = get_user_by('email', $email);
    
    if (!$user) {
        return new WP_Error('invalid_credentials', 'Invalid email or password', array('status' => 401));
    }

    // Check password
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        return new WP_Error('invalid_credentials', 'Invalid email or password', array('status' => 401));
    }
    
    // Check if email is verified
    require_once plugin_dir_path(__FILE__) . 'verification.php';
    if (training_hub_needs_verification($user)) {
        return new WP_Error('email_not_verified', 'Please verify your email address before logging in', array(
            'status' => 403,
            'needs_verification' => true,
            'email' => $user->user_email
        ));
    }

    $token = training_hub_generate_jwt($user->ID);

    return array(
        'success' => true,
        'token' => $token,
        'user' => array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'isVerified' => true,
            'avatar' => get_avatar_url($user->ID),
        ),
    );
}

/**
 * Check password strength
 */
function training_hub_validate_password($password) {
    $errors = array();
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

/**
 * Generate a unique username from email
 */
function training_hub_generate_username($email) {
    $username = sanitize_user(current(explode('@', $email)), true);
    $original_username = $username;
    $i = 1;
    
    while (username_exists($username)) {
        $username = $original_username . $i;
        $i++;
    }
    
    return $username;
}

/**
 * Register user
 */
function training_hub_register($request) {
    $email = sanitize_email($request->get_param('email'));
    $password = $request->get_param('password');
    $firstName = sanitize_text_field($request->get_param('first_name'));
    $lastName = sanitize_text_field($request->get_param('last_name'));
    $phone_number = sanitize_text_field($request->get_param('phone_number'));
    $company = sanitize_text_field($request->get_param('company'));
    $position = sanitize_text_field($request->get_param('position'));
    $government = sanitize_text_field($request->get_param('government'));

    // Basic validation
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($phone_number)) {
        return new WP_Error('missing_fields', 'Fields are required', array('status' => 400));
    }

    // Validate email format
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Please provide a valid email address', array('status' => 400));
    }

    // Check if email already exists
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'An account with this email already exists', array('status' => 400));
    }

    // Validate password strength
    $password_errors = training_hub_validate_password($password);
    if (!empty($password_errors)) {
        return new WP_Error('weak_password', implode(', ', $password_errors), array('status' => 400));
    }

    // Generate username from email
    $username = training_hub_generate_username($email);
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_Error('registration_failed', $user_id->get_error_message(), array('status' => 400));
    }

    // Set user meta
    wp_update_user(array(
        'ID' => $user_id,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'display_name' => $firstName . ' ' . $lastName,
        'role' => 'subscriber'
    ));
    
    // Set user meta
    update_user_meta($user_id, 'phone_number', $phone_number);
    update_user_meta($user_id, 'company', $company);
    update_user_meta($user_id, 'position', $position);
    update_user_meta($user_id, 'government', $government);

    // Generate and store verification code
    require_once plugin_dir_path(__FILE__) . 'verification.php';
    $code = training_hub_generate_verification_code();
    training_hub_store_verification_code($user_id, $code);
    
    // Send verification email
    $email_sent = training_hub_send_verification_email($email, $code);
    
    if (!$email_sent) {
        // Log error but don't fail the registration
        error_log('Failed to send verification email to: ' . $email);
    }

    // Get user data
    $user = get_user_by('id', $user_id);

    return array(
        'success' => true,
        'message' => 'Registration successful. Please check your email to verify your account.',
        'needs_verification' => true,
        'user' => array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'isVerified' => false
        )
    );
}

/**
 * Extract JWT token from Authorization header
 */
function training_hub_get_auth_token() {
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    
    // Check for Bearer token in Authorization header
    if (!empty($auth_header) && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        return $matches[1];
    }
    
    // Fallback to token parameter for backward compatibility
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    
    return $token;
}

/**
 * Verify JWT token
 */
function training_hub_verify_token($request) {
    $token = training_hub_get_auth_token();
    
    // For the verify endpoint, still allow token as a parameter
    if (empty($token)) {
        $token = $request->get_param('token');
    }

    if (empty($token)) {
        return new WP_Error('missing_token', 'Authorization token is required', array('status' => 401));
    }

    $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key-change-this';
    $payload = training_hub_decode_jwt($token, $secret_key);

    if (!$payload) {
        return new WP_Error('invalid_token', 'Invalid or expired token', array('status' => 401));
    }

    $user = get_user_by('id', $payload['user_id']);

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
            'avatar' => get_avatar_url($user->ID),
        ),
    );
}

/**
 * Refresh JWT token
 */
function training_hub_refresh_token($request) {
    $token = $request->get_param('token');

    if (empty($token)) {
        return new WP_Error('missing_token', 'Token is required', array('status' => 400));
    }

    $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key-change-this';
    $payload = training_hub_decode_jwt($token, $secret_key);

    if (!$payload) {
        return new WP_Error('invalid_token', 'Invalid or expired token', array('status' => 401));
    }

    $new_token = training_hub_generate_jwt($payload['user_id']);

    return array(
        'success' => true,
        'token' => $new_token,
    );
}

/**
 * Get current user from JWT token
 */
function training_hub_get_current_user_from_jwt() {
    $token = training_hub_get_auth_token();

    if (empty($token)) {
        return null;
    }

    $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key-change-this';
    $payload = training_hub_decode_jwt($token, $secret_key);

    if ($payload) {
        return $payload['user_id'];
    }

    return null;
}

/**
 * Check JWT authentication
 */
function training_hub_check_jwt_auth() {
    $user_id = training_hub_get_current_user_from_jwt();
    return !empty($user_id);
}
