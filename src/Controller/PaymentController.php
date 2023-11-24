<?php

namespace Src\Controller;

use Src\Gateway\CurlGatewayAccess;
use Src\Controller\VoucherPurchase;

class PaymentController
{
    private $voucher;

    public function __construct()
    {
        $this->voucher = new VoucherPurchase();
    }

    public function setOrchardPaymentGatewayParams($payload, $endpointUrl)
    {
        $client_id = getenv('ORCHARD_CLIENT');
        $client_secret = getenv('ORCHARD_SECRET');
        $signature = hash_hmac("sha256", $payload, $client_secret);
        $secretKey = $client_id . ":" . $signature;
        $httpHeader = array(
            "Authorization: " . $secretKey,
            "Content-Type: application/json"
        );

        try {
            $gateAccess = new CurlGatewayAccess($endpointUrl, $httpHeader, $payload);
            return $gateAccess->initiateProcess();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param int transaction_id //transaction_id
     */
    private function getTransactionStatusFromOrchard(int $transaction_id): mixed
    {
        $payload = json_encode(array(
            "exttrid" => $transaction_id,
            "trans_type" => "TSC",
            "service_id" => getenv('ORCHARD_SERVID')
        ));
        $endpointUrl = "https://orchard-api.anmgw.com/checkTransaction";
        return $this->setOrchardPaymentGatewayParams($payload, $endpointUrl);
    }

    public function processTransaction(int $transaction_id): mixed
    {
        $data = $this->voucher->getTransactionStatusFromDB($transaction_id);
        if (empty($data)) return array("success" => false, "message" => "No transaction match found in database for transaction ID {$transaction_id}");
        if (strtoupper($data[0]["status"]) != "PENDING")
            return array("success" => false, "message" => "Transaction already performed! Check mail and/or SMS inbox for login details.");

        $response = json_decode($this->getTransactionStatusFromOrchard($transaction_id));
        if (empty($response)) return array("success" => false, "message" => "Invalid transaction parameters!");

        if (isset($response->trans_status)) {
            $status_code = substr($response->trans_status, 0, 3);
            if ($status_code == '000') return $this->voucher->genLoginsAndSend($transaction_id);
            $this->voucher->updateTransactionStatusInDB('FAILED', $transaction_id);
            return array("success" => false, "message" => "Payment failed! Code: " . $status_code);
        } elseif (isset($response->resp_code)) {
            if ($response->resp_code == '084') return array(
                "success" => false,
                "message" => "Payment pending! This might be due to insufficient fund in your mobile wallet or your payment session expired. Code: " . $response->resp_code
            );
            return array("success" => false, "message" => "Payment process failed! Code: " . $response->resp_code);
        }
        return array("success" => false, "message" => "Bad request: Payment process failed!");
    }

    public function orchardPaymentController($data): mixed
    {
        $trans_id = time();
        $callback_url = "https://test.pay.rmuictonline.com/confirm";
        $payload = json_encode(array(
            "amount" => $data["amount"],
            "callback_url" => $callback_url,
            "customer_number" => $data["phone_number"],
            "exttrid" => $trans_id,
            "nw" => $data["network"],
            "reference" => "RMU Forms Online",
            "service_id" => getenv('ORCHARD_SERVID'),
            "trans_type" => "CTM",
            "ts" => date("Y-m-d H:i:s")
        ));

        $endpointUrl = "https://orchard-api.anmgw.com/sendRequest";
        $response = json_decode($this->setOrchardPaymentGatewayParams($payload, $endpointUrl));

        if (isset($response->resp_code) && isset($response->resp_desc))
            if ($response->resp_code == "000" || $response->resp_code == "015") {
                $saved = $this->voucher->SaveFormPurchaseData($data, $trans_id);
                return $saved;
            }
        return array("success" => false, "status" => $response->resp_code, "message" => $response->resp_desc);
    }
}
