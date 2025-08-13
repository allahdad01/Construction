<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate required fields
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    if (empty($subject)) {
        throw new Exception('Subject is required');
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Validate subject options
    $allowed_subjects = ['general', 'pricing', 'demo', 'support', 'partnership'];
    if (!in_array($subject, $allowed_subjects)) {
        throw new Exception('Invalid subject selected');
    }
    
    // Store contact inquiry in database (if you have a contact_inquiries table)
    // For now, we'll just log it and send email
    
    // Get system settings for admin email
    $db = new Database();
    $conn = $db->getConnection();
    
    $admin_email = getSystemSetting('contact_email', 'admin@constructionsaas.com');
    
    // Prepare email content
    $email_subject = "New Contact Inquiry: " . ucfirst($subject);
    $email_body = "
    New contact inquiry received from the website:
    
    Name: $name
    Email: $email
    Phone: " . ($phone ?: 'Not provided') . "
    Company: " . ($company ?: 'Not provided') . "
    Subject: " . ucfirst($subject) . "
    
    Message:
    $message
    
    ---
    This inquiry was submitted from the Construction SaaS website contact form.
    ";
    
    // Send email to admin
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $mail_sent = mail($admin_email, $email_subject, $email_body, $headers);
    
    if (!$mail_sent) {
        // Log the inquiry for manual follow-up
        error_log("Contact inquiry from $name ($email): $message");
    }
    
    // Store in session for success message
    $_SESSION['contact_success'] = true;
    $_SESSION['contact_name'] = $name;
    
    // Redirect back to landing page with success
    header('Location: /constract360/construction/public/?contact=success');
    exit;
    
} catch (Exception $e) {
    // Redirect back with error
    header('Location: /constract360/construction/public/?contact=error&message=' . urlencode($e->getMessage()));
    exit;
}
?>