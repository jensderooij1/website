<?php
/**
 * Webhook Middleware for Contact Form to Freshworks CRM
 * Save this as webhook-middleware.php on your server
 * Point your form submission to: https://yoursite.com/webhook-middleware.php
 */

// Enable CORS for testing (remove in production if not needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get the webhook data from the form
$webhook_data = array();

// Check if data is coming as form data or JSON
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    // JSON data
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
} else {
    // Form data (FormData or URL-encoded)
    $webhook_data = $_POST;
}

// Log received data for debugging
error_log('Received webhook data: ' . print_r($webhook_data, true));

// Map flat webhook data to nested Freshworks structure
$contact_data = array(
    'contact' => array(
        'first_name' => isset($webhook_data['first_name']) ? $webhook_data['first_name'] : '',
        'last_name' => isset($webhook_data['last_name']) ? $webhook_data['last_name'] : '',
        'email' => isset($webhook_data['email']) ? $webhook_data['email'] : '',
        'work_number' => isset($webhook_data['work_number']) ? $webhook_data['work_number'] : '',
        'address' => isset($webhook_data['address']) ? $webhook_data['address'] : '',
        'zipcode' => isset($webhook_data['zipcode']) ? $webhook_data['zipcode'] : '',
        'city' => isset($webhook_data['city']) ? $webhook_data['city'] : '',
        'company_name' => isset($webhook_data['company_name']) ? $webhook_data['company_name'] : '',
        'custom_field' => array(
            'cf_comment' => isset($webhook_data['cf_comment']) ? $webhook_data['cf_comment'] : '',
            'cf_send_me_more_information' => isset($webhook_data['cf_send_me_more_information']) ? filter_var($webhook_data['cf_send_me_more_information'], FILTER_VALIDATE_BOOLEAN) : false,
            'cf_i_have_further_questions_please_contact_me' => isset($webhook_data['cf_i_have_further_questions_please_contact_me']) ? filter_var($webhook_data['cf_i_have_further_questions_please_contact_me'], FILTER_VALIDATE_BOOLEAN) : false,
            'cf_i_would_like_an_online_demo' => isset($webhook_data['cf_i_would_like_an_online_demo']) ? filter_var($webhook_data['cf_i_would_like_an_online_demo'], FILTER_VALIDATE_BOOLEAN) : false,
            'cf_send_me_a_quote' => isset($webhook_data['cf_send_me_a_quote']) ? filter_var($webhook_data['cf_send_me_a_quote'], FILTER_VALIDATE_BOOLEAN) : false
        )
    )
);

// Remove empty fields
$contact_data['contact'] = array_filter($contact_data['contact'], function($value) {
    if (is_array($value)) {
        return !empty(array_filter($value));
    }
    return !empty($value) || is_bool($value);
});

// Freshworks API configuration
$api_url = 'https://fresh-agrovision.myfreshworks.com/crm/sales/api/contacts';
$api_token = 'XGOvUHqLSwS9z2ugWD0ekQ';

// Prepare cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contact_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Token token=' . $api_token,
    'Content-Type: application/json',
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Log the response (optional - remove in production)
error_log('Webhook Data: ' . print_r($webhook_data, true));
error_log('Contact Data: ' . json_encode($contact_data));
error_log('Freshworks Response Code: ' . $http_code);
error_log('Freshworks Response: ' . $response);

// Return response to Gravity Forms
if ($curl_error) {
    http_response_code(500);
    echo 'cURL Error: ' . $curl_error;
} elseif ($http_code == 201) {
    http_response_code(200);
    echo 'Contact created successfully';
} else {
    http_response_code($http_code);
    echo 'Freshworks API Error: ' . $response;
}
?>
