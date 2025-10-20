<?php
error_reporting(E_ALL);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/proc/self/fd/2'); // For Docker logs

// Database Connection Settings
$host = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$database = getenv('DB_NAME');

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {    
    error_log("DB Connection Failed: " . $conn->connect_error);
    http_response_code(503);
    echo json_encode(["error" => "Service currently unavailable (Database connection failed)."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or empty JSON input"]);
        exit;
    }

    // Validation and sanitization
    $name = $conn->real_escape_string(trim($input['name'] ?? ''));
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $number = trim($input['number'] ?? '');
    $journey_date = trim($input['journey_date'] ?? '');
    $totalPrice = trim($input['totalPrice'] ?? '');
    $selectSitArr = $input['selectSitArr'] ?? '';

    // Convert array to string if needed
    if (is_array($selectSitArr)) {
        $selectSitArr = implode(",", $selectSitArr);
    }
    $selectSitArr = $conn->real_escape_string($selectSitArr);

    // Basic required field check
    if (empty($name) || empty($email) || empty($number) || empty($selectSitArr)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields."]);
        exit;
    }

    // Validate phone number
    if (!preg_match('/^\d{10,15}$/', $number)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid phone number format"]);
        exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid email format"]);
        exit;
    }

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO passenger (name, email, number, selectSitArr, journey_date, totalPrice) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $number, $selectSitArr, $journey_date, $totalPrice);

    if ($stmt->execute()) {
        $seats_display = implode(", ", explode(",", $selectSitArr));

        $htmlContent = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Bus Ticket</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding: 20px; }
                .ticket { background: #fff; border-radius: 8px; max-width: 600px; margin: 20px auto; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
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
            <p style='color: #666;'>Thank you for choosing our service!</p>
        </body>
        </html>";

        // PHPMailer setup
        $mail = new PHPMailer(true);
        $email_status = '';

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('APP_EMAIL');
            $mail->Password   = getenv('APP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom(getenv('APP_EMAIL'), 'Bus Ticket Service');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = "Bus Ticket Confirmation for " . $name;
            $mail->Body    = $htmlContent;
            $mail->AltBody = strip_tags($htmlContent);

            $mail->send();
            $email_status = "Email sent successfully.";
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            $email_status = "Email failed to send.";
        }

        // Save HTML file
        $ticketsDir = 'tickets';
        if (!file_exists($ticketsDir)) {
            mkdir($ticketsDir, 0755, true);
        }

        $fileName = $ticketsDir . "/ticket_" . time() . ".html";
        file_put_contents($fileName, $htmlContent);

        echo json_encode([
            "success" => true,
            "message" => "New record created successfully.",
            "email_status" => $email_status,
            "downloadLink" => $fileName,
        ]);
    } else {
        http_response_code(500);
        error_log("SQL Error: " . $stmt->error);
        echo json_encode(["error" => "Database error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(["error" => "Invalid request method"]);
}
