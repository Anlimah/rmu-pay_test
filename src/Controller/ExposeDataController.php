<?php

namespace Src\Controller;

use Src\System\DatabaseMethods;
use Src\Gateway\CurlGatewayAccess;

class ExposeDataController
{
    private $dm;

    public function __construct()
    {
        $this->dm = new DatabaseMethods();
    }

    public function genCode($length = 6)
    {
        $digits = $length;
        $first = pow(10, $digits - 1);
        $second = pow(10, $digits) - 1;
        return rand($first, $second);
    }

    public function validateTransactionID($input)
    {
        if (empty($input)) return array("success" => false, "message" => "Transaction ID missing in request!");
        $user_input = htmlentities(htmlspecialchars($input));
        $validated_input = (bool) preg_match('/^[0-9]/', $user_input);
        if ($validated_input) return array("success" => true, "message" => $user_input);
        return array("success" => false, "message" => "Invalid input transaction ID {$input}!");
    }

    public function getAdminYearCode()
    {
        $sql = "SELECT EXTRACT(YEAR FROM (SELECT `start_date` FROM admission_period WHERE active = 1)) AS 'year'";
        $year = (string) $this->dm->getData($sql)[0]['year'];
        return (int) substr($year, 2, 2);
    }

    public function sendHubtelSMS($url, $payload)
    {
        $client = getenv('HUBTEL_CLIENT');
        $secret = getenv('HUBTEL_SECRET');
        $secret_key = base64_encode($client . ":" . $secret);
        $httpHeader = array("Authorization: Basic " . $secret_key, "Content-Type: application/json");
        return (new CurlGatewayAccess($url, $httpHeader, $payload))->initiateProcess();
    }

    public function sendSMS($to, $message)
    {
        $url = "https://sms.hubtel.com/v1/messages/send";
        $payload = json_encode(array("From" => "RMU", "To" => $to, "Content" => $message));
        return $this->sendHubtelSMS($url, $payload);
    }

    public function sendOTP($to)
    {
        $otp_code = $this->genCode(4);
        $message = 'Your OTP verification code: ' . $otp_code;
        $response = json_decode($this->sendSMS($to, $message), true);
        if (!$response["status"]) $response["otp_code"] = $otp_code;
        return $response;
    }

    public function verifyAPIAccess($username, $password): int
    {
        $sql = "SELECT * FROM `api_users` WHERE `username`=:u";
        $data = $this->dm->getData($sql, array(':u' => $username));
        if (!empty($data)) if (password_verify($password, $data[0]["password"])) return (int) $data[0]["id"];
        return 0;
    }

    public function confirmPaymentsLog(int $transaction_id, string $message): void
    {
        $logMessage = date("Y-m-d H:i:s") . "\tTransaction ID: $transaction_id\t$message" . PHP_EOL;
        $file = fopen("confirmPayments.log", "a"); // Open the file in append mode
        fwrite($file, $logMessage);
        fclose($file);
    }

    public function logSuccessPaymentProcesses($data): void
    {
        $query = "INSERT INTO `success_payment_logs` (`category`, `log_data`) VALUES (:c, :l)";
        $this->dm->inputData($query, array(":c" => $data["pay_category"], ":l" => json_encode($data["data"])));
    }

    public function logFailedPaymentProcesses($data, $error_message): void
    {
        $query = "INSERT INTO `fail_payment_logs` (`log_data`, `error_message`) VALUES (:l, :m)";
        $this->dm->inputData($query, array(":l" => json_encode($data), ":m" => $error_message));
    }
}
