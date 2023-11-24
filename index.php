<?php

require_once('bootstrap.php');

use Src\Controller\HandlePaymentController;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        // Check if the "CONTENT_TYPE" header is set
        if (!isset($_SERVER["CONTENT_TYPE"])) {
            http_response_code(400); // Bad Request
            header('Content-Type: application/json');
            echo json_encode(array("resp_code" => "601", "message" => "No Content-Type header provided."));
            exit;
        }

        // Check if Content-Type header is set to json
        if (strpos($_SERVER["CONTENT_TYPE"], "application/json") === false) {
            http_response_code(415); // Unsupported Media Type
            header('Content-Type: application/json');
            echo json_encode(array("resp_code" => "602", "message" => "Only JSON-encoded requests are allowed."));
            exit;
        }

        $_POST = json_decode(file_get_contents("php://input"), true);
        $request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
        $endpoint = '/' . basename($request_uri);

        switch ($endpoint) {
            case '/pay':
                $response = (new HandlePaymentController)->pay($_POST);
                break;

            case '/confirm':
                $response = (new HandlePaymentController)->confirm($_POST);
                break;
        }

        http_response_code(201);
        header("Content-Type: application/json");
        echo json_encode($response);
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(array("response" => "Not permitted"));
        break;
}
exit();
