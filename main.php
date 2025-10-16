<?php
error_reporting(0); 

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
// For Docker containers, logging to stderr (/proc/self/fd/2) is the best practice
// as it appears in 'docker logs'.
ini_set('error_log', '/proc/self/fd/2');

// Database Connection Settings
// NOTE: We use 'db' as the host, which is the service name defined in compose.yaml.
// This is required for inter-service communication within the Docker network.
$host = 'db'; // CORRECTED: Use service name 'db' instead of container name 'busticket-db-1'
$username = 'root';
$password = 'dbPassword';
$database = 'bus_ticket';

// OR, even better practice: use the environment variables already set in compose.yaml
/*
$host = getenv('DB_HOST') ?: 'db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: 'abcd';
$database = getenv('DB_NAME') ?: 'bus_ticket';
*/


$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    // If the connection fails, this JSON error response should be returned (not a 500)
    // If you are still seeing a 500, it means the script is crashing before this line.
    error_log("DB Connection Failed: " . $conn->connect_error);
    http_response_code(503); // Service Unavailable status for DB error
    echo json_encode(["error" => "Service currently unavailable (Database connection failed)."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents("php://input"), true);

    // Validation and sanitization
    // Use proper variable assignment for potentially missing array keys
    $name = $conn->real_escape_string(trim($input['name'] ?? ''));
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $number = trim($input['number'] ?? '');
    $journey_date = trim($input['journey_date'] ?? '');
    $totalPrice = trim($input['totalPrice'] ?? '');
    $selectSitArr = $conn->real_escape_string($input['selectSitArr'] ?? '');

    // Basic required field check
    if (empty($name) || empty($email) || empty($number) || empty($selectSitArr)) {
         http_response_code(400); // Bad Request
         echo json_encode(["error" => "Missing required fields."]);
         exit;
    }

    // Validate phone number format
    if (!preg_match('/^\d{10,15}$/', $number)) {
        http_response_code(400); 
        echo json_encode(["error" => "Invalid phone number format"]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); 
        echo json_encode(["error" => "Invalid email format"]);
        exit;
    }

    // Use prepared statements to insert data safely
    $stmt = $conn->prepare("INSERT INTO passenger (name, email, number, selectSitArr, journey_date, totalPrice) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $number, $selectSitArr, $journey_date, $totalPrice);

    if ($stmt->execute()) {
        // Generate HTML content for the ticket
        $seats_display = implode(", ", explode(",", $selectSitArr));
        $htmlContent = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link rel='icon' type='image/png' href='https://i.ibb.co/7VN1b9n/th.jpg' />
            <title>Bus Ticket</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin: auto; padding: 20px; background-color: #f4f4f4; }
                .ticket { background: white; border: 1px solid #ccc; border-radius: 8px; max-width: 600px; margin: 20px auto; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                h1 { color: #1DD100; border-bottom: 2px solid #1DD100; padding-bottom: 10px; }
                p { font-size: 16px; text-align: left; margin: 5px 0; }
                strong { display: inline-block; width: 150px; }
            </style>
        </head>
        <body>
            <div class='ticket'>
                <h1>Your Bus Ticket</h1>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Number:</strong> $number</p>
                <p><strong>Selected Seats:</strong> $seats_display</p>
                <p><strong>Journey Date:</strong> $journey_date</p>
                <p><strong>Total Price:</strong> BDT $totalPrice</p>
            </div>
            <p style='text-align: center; color: #666;'>Thank you for choosing our service!</p>
        </body>
        </html>";

        
        // Email content (plain message for email body)
        $message = "
            <p>Dear $name,</p>
            <p>Please find your bus ticket information below:</p>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Number:</strong> $number</p>
            <p><strong>Selected Seats:</strong> $seats_display</p>
            <p><strong>Journey Date:</strong> $journey_date</p>
            <p><strong>Total Price:</strong> BDT $totalPrice</p>
            <p>Thank you for choosing our service!</p>
        ";

        // --- START PHPMailer Configuration ---
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            // Set to 0 for production, but using 4 here to debug connection issues.
            $mail->SMTPDebug = 0; // Changed to 0 for less noise on successful run
            $mail->Debugoutput = function($str, $level) {
                // Output debug to the error log, NOT to the HTTP response body
                error_log(trim($str), 0);
            };
            $mail->isSMTP();

            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true; 
            // NOTE: Using environment variables for sensitive data is highly recommended
            // $mail->Username = getenv('EMAIL_USER');
            // $mail->Password = getenv('EMAIL_PASS');
            $mail->Username   = 'tauhidt994@gmail.com'; 
            $mail->Password   = 'miae eumh evna ntgh'; // *REQUIRED* - Use an App Password here
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('tauhidt994@gmail.com', 'Bus Ticket Service');
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Bus Ticket Confirmation for " . $name;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message); 

            $mail->send();
            $email_status = "Email sent successfully via SMTP.";
            
        } catch (Exception $e) {
            // Log the PHPMailer error instead of crashing silently
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            $email_status = "Email failed to send. Mailer Error: {$mail->ErrorInfo}";
        }
        // --- END PHPMailer Configuration ---

        // Create 'tickets' directory if it doesn't exist
        $ticketsDir = 'tickets';
        if (!file_exists($ticketsDir)) {
            // Use 0755 for permissions in a web context
            if (!mkdir($ticketsDir, 0755, true)) {
                 error_log("Failed to create ticket directory: " . $ticketsDir);
                 $fileName = null; // Prevent use of invalid file path
            }
        }
        
        $fileName = $ticketsDir . "/ticket_" . time() . ".html";
        file_put_contents($fileName, $htmlContent);

        echo json_encode([
            "message" => "New record created successfully. {$email_status}",
            "downloadLink" => $fileName,
        ]);
    } else {
        http_response_code(500); // Internal Server Error for DB execution failure
        error_log("SQL Execution Error: " . $stmt->error);
        echo json_encode(["error" => "Database Error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Invalid request method"]);
}