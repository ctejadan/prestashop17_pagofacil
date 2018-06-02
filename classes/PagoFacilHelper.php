<?php

/**
 * author: Cristian Tejada - https://github.com/ctejadan
 */

class PagoFacilHelper
{
    var $dev_server = "https://t.pagofacil.xyz/v1";
    var $prod_server = "https://t.pgf.cl/v1";

    public function generateSignature($payload, $tokenSecret)
    {
        $signatureString = "";
        ksort($payload);
        foreach ($payload as $key => $value) {
            $signatureString .= $key . $value;
        }
        $signature = hash_hmac('sha256', $signatureString, $tokenSecret);
        return $signature;
    }

    public function createTransaction($postVars, $request, $showAllPlatformsInPagoFacil, $environment)
    {
        $ch = curl_init();

        if ($environment == 'PRODUCTION') {
            curl_setopt($ch, CURLOPT_URL, $this->prod_server);
        } else {
            curl_setopt($ch, CURLOPT_URL, $this->dev_server);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        $result = json_decode($server_output, true);

        curl_close($ch);

        if ($showAllPlatformsInPagoFacil === 'YES') {
            //show all platforms
            return $server_output;

        } else {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $request['endpoint']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"transaction\":\"" . $result['transactionId'] . "\"}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output_response = curl_exec($ch);

            curl_close($ch);

            return $server_output_response;
        }

    }

    public function httpResponseCode($response_code)
    {
        if ($response_code !== NULL) {

            switch ($response_code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Prohibido';
                    break;
                case 404:
                    $text = 'No encontrado';
                    break;
                case 405:
                    $text = 'Método no permitido';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($response_code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            $header = $protocol . ' ' . $response_code . ' ' . $text;
            header($header);
            die($text);
        }
    }

    public function getServices($environment, $iso_code, $token_service)
    {
        $ch = curl_init();

        if ($environment == 'PRODUCTION') {
            curl_setopt($ch, CURLOPT_URL, "https://t.pgf.cl/v1/services");
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://t.pagofacil.xyz/v1/services");
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('x-currency: ' . $iso_code, 'x-service: ' . $token_service));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        $result = json_decode($server_output, true);

        curl_close($ch);

        return $result;
    }

    public function generatePostVarsString($signaturePayload)
    {
        $postVars = '';
        $ix = 0;
        $len = count($signaturePayload);

        foreach ($signaturePayload as $key => $value) {
            if ($ix !== $len - 1) {
                if ($key == "pf_url_complete" || $key == "pf_url_callback") {
                    $postVars .= $key . "=" . urlencode($value) . "&";

                } else {
                    $postVars .= $key . "=" . $value . "&";
                }

            } else {
                if ($key == "pf_url_complete" || $key == "pf_url_callback") {
                    $postVars .= $key . "=" . urlencode($value);
                } else {
                    $postVars .= $key . "=" . $value;
                }
            }
            $ix++;
        }

        return $postVars;
    }

}