<?php

namespace Src\Controller;

use Src\Controller\PaymentController;
use Src\Controller\ExposeDataController;

class HandlePaymentController
{
    private $expose = null;
    private $pay = null;

    public function __construct()
    {
        $this->expose = new ExposeDataController();
        $this->pay = new PaymentController();
    }

    public function pay($data): mixed
    {
        if (!in_array($data["pay_category"], ["forms", "fees", "resit", "accom"])) {
            $this->expose->logFailedPaymentProcesses($data, "Request payload doesn't match available categories.");
            return array("success" => false, "message" => "Request payload doesn't match available categories.");
        } else {
            $result = $this->pay->orchardPaymentController($data["data"]);
            if (!empty($result) && isset($result["success"]) && $result["success"] === true)
                $this->expose->logSuccessPaymentProcesses($data);
            else $this->expose->logFailedPaymentProcesses($data, $result["message"]);
            return $result;
        }
    }

    public function confirm($data): mixed
    {
        $this->expose->confirmPaymentsLog($data["trans_ref"], json_encode($data));
        $result = $this->expose->validateTransactionID($data["trans_ref"]);
        if (!empty($result) && isset($result["success"]) && $result["success"] == true) {
            $transaction_id = $result["message"];
            $data = (new PaymentController())->processTransaction($transaction_id);
            $this->expose->confirmPaymentsLog($result["message"], $data["message"]);
            return $data;
        } else {
            $this->expose->confirmPaymentsLog($data["trans_ref"], $result["message"]);
            return $result;
        }
    }

    public function authenticateAccess($username, $password)
    {
        return $this->expose->verifyAPIAccess($username, $password);
    }
}
