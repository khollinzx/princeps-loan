<?php


namespace App\Services;


use Illuminate\Support\Facades\Log;

class HTTPRequestService
{
    /**
     * This makes a post request call to an external service
     * Example of the headers :
     * ["Content-Type: application/json", "Authorization: Bearer bearer_code_here"]
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \JsonException
     */
    public static function initializePostRequest(string $uri, array $data, array $headers = [])
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers
        ));

        //        Log::alert("Logging payload...");
        //        Log::alert($payload);

        $response = curl_exec($curl);
        Log::alert("Alert: Logging information from Post response...", [$response]);
        curl_close($curl);

        return $response ? json_decode($response, true, 512, JSON_THROW_ON_ERROR) : null;
    }


    /**
     * This makes a post request call to an external service
     * Example of the headers :
     * ["Content-Type: application/json", "Authorization: Bearer bearer_code_here"]
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \JsonException
     */
    public static function initializePostRequestV2(string $uri, array $data, array $headers = [])
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers
        ));

        //        Log::alert("Logging payload...");
        //        Log::alert($payload);

        $response = curl_exec($curl);
        curl_close($curl);
        
        $response = @json_decode($response);
        Log::alert("Alert: Logging information from Post response...", [$response]);
  
        if (
            $response === null
            && json_last_error() !== JSON_ERROR_NONE
        ) {
            return false;
        } else {
            return $response;
        }

        // return $response ? json_decode($response, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return mixed
     * @throws \JsonException
     */
    public static function initializePutRequest(string $uri, array $data, array $headers = [])
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * This makes a get request call to an external service
     * @param string $uri
     * @param array $headers
     * @return mixed
     * @throws \JsonException
     */
    public static function initializeGetRequest(string $uri, array $headers = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param $upload_url
     * @param $stream
     * @param $file_len
     * @param $headers
     * @return bool|string
     * @throws \JsonException
     */
    public static function uploadFileToServer($upload_url, $stream, $file_len, $headers)
    {
        $ch = curl_init($upload_url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_INFILESIZE, $file_len);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_INFILE, $stream);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
}
