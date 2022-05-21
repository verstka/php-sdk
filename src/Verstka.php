<?php

declare(strict_types=1);

namespace Verstka;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use Verstka\Exception\ValidationException;
use Verstka\Exception\VerstkaException;

class Verstka
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $callbackUrl;

    public function __construct(string $apiKey, string $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     */
    public function setCallbackUrl(string $callbackUrl): void
    {
        $this->callbackUrl = $callbackUrl;
    }

    /**
     * @param string $name
     * @param string $articleBody
     * @param bool $isMobile
     * @param array $customFields
     * @return string - verstka edit url
     * @throws GuzzleException
     * @throws VerstkaException
     */
    public function open(string $name, string $articleBody, bool $isMobile, array $customFields = []): string
    {
        $customFields = array_merge([
            'auth_user' => 'test',        //if You have http authorization on callback url
            'auth_pw' => 'test',          //if You have http authorization on callback url
            'mobile' => $isMobile,       //if You edit mobile version of article
            'fonts.css' => '/static/vms_fonts.css', //if You use custom fonts set
            'version' => 1.0
        ], $customFields);

        $params = [
            'form_params' => [
                'user_id' => $_SERVER['PHP_AUTH_USER'] ?? 1,
                'user_ip' => $_SERVER['REMOTE_ADDR'],
                'material_id' => $name,
                'html_body' => $articleBody,
                'callback_url' => $this->getCallbackUrl(),
                'host_name' => $_SERVER['HTTP_HOST'],
                'api-key' => $this->apiKey,
                'customFields' => json_encode($customFields)
            ],
            // 'allow_redirects' => ['track_redirects' => true],
            'connect_timeout' => 3.14,
            'headers' => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
            ]
            // 'auth' => ['username', 'password']
        ];

        $params['form_params']['callback_sign'] = self::getRequestSalt($this->secretKey, $params['form_params'], 'api-key, material_id, user_id, callback_url');

        $verstka_url_open = (getenv('SSL') ? 'https://' : 'http://') . getenv('verstka_host') . '/1/open';

        $result = $this->sendRequest($verstka_url_open, $params);
        if (empty($result['data']) && empty($result['data']['edit_url'])) {
            throw new VerstkaException('Could not get url for editing');
        }

        return $result['data']['edit_url'];
    }

    /**
     * @param callable $clientCallback
     * @param array $data
     * @return string - encoded json response from verstka
     */
    public function save(callable $clientCallback, array $data): string
    {
        set_time_limit(0);
        try {
            $this->validateArticleData($data);

//          Article params:
            $article_body = $data['html_body'];
            $verstkaDownloadUrl = $data['download_url'];
            $custom_fields = json_decode($data['custom_fields'], true);
            $is_mobile = $custom_fields['mobile'] === true;
            $material_id = $data['material_id'];
            $user_id = $data['user_id'];

            //Request list of images
            $articleImages = $this->sendRequest($verstkaDownloadUrl, [
                'connect_timeout' => 3.14,
                'headers' => [
                    'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                ],
                'form_params' => [
                    'api-key' => $this->apiKey,
                    'unixtime' => time()
                ]
            ]);

            [$images_ready, $lacking_images] = $this->uploadImages($verstkaDownloadUrl, $articleImages);

            $call_back_result = call_user_func($clientCallback, [
                'article_body' => $article_body,
                'custom_fields' => $custom_fields,
                'is_mobile' => $is_mobile,
                'material_id' => $material_id,
                'user_id' => $user_id,
                'images' => $images_ready
            ]);

            $debug = [];
            if ($call_back_result === true) {
                $debug = [];
                foreach ($images_ready as $image => $image_temp_file) {    // clean temp folder if callback successfull
                    if (is_readable($image_temp_file)) {
                        unlink($image_temp_file);
                        $debug[] = $image_temp_file;
                    }
                }
            }

            $additional_data = [
//                'images_list' => $images_list,
//                'results' => $results,
//                'temp_files' => $temp_files,
//                'attempts' => $attempts,
                'debug' => $debug,
                'custom_fields' => $custom_fields,
                'lacking_images' => $lacking_images
            ];
            return static::formJSON(1, 'save sucessfull', $additional_data);
        } catch (\Throwable $e) {
            return static::formJSON($e->getCode(), $e->getMessage(), $data);
        }
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     * @throws VerstkaException
     * @throws GuzzleException
     */
    private function sendRequest(string $url, array $params): array
    {
        $guzzleClient = new Client(['timeout' => 60.0]); //Base URI is used with relative requests // 'base_uri' => 'http://httpbin.org',
        $response = $guzzleClient->post($url, $params);
        $result_json = $response->getBody()->getContents();
        $code = $response->getStatusCode();
        $result = json_decode($result_json, true);

        if ($code !== 200 || json_last_error() || empty($result['data']) || empty($result['rc']) || $result['rc'] !== 1) {
            throw new VerstkaException(sprintf("verstka api open return %d\n%s", $code, $result_json));
        }

        return $result;
    }

    private static function formJSON($res_code, $res_msg, $data = array())
    {
        return json_encode(array(
            'rc' => $res_code,
            'rm' => $res_msg,
            'data' => $data
        ), JSON_NUMERIC_CHECK);
    }

    /**
     * @param string $secret
     * @param array $data
     * @param string $fields
     * @return string
     */
    private static function getRequestSalt(string $secret, array $data, string $fields): string
    {
        $fields = array_filter(array_map('trim', explode(',', $fields)));
        $result = $secret;
        foreach ($fields as $field) {
            $result .= $data[$field];
        }
        return md5($result);
    }

    /**
     * @param array $data
     * @throws ValidationException
     */
    private function validateArticleData(array $data): void
    {
        $expectCallbackSign = static::getRequestSalt($this->secretKey, $data, 'session_id, user_id, material_id, download_url');
        if (
            empty($data['download_url'])
            || $expectCallbackSign !== $data['callback_sign']
        ) {
            throw new ValidationException('invalid callback sign');
        }
    }

    private function uploadImages(string $url, array $result): array
    {
        $images_list = $result['data'];
        $images_to_download = $images_list;

        $guzzle_client = new Client([
            'timeout' => 180.0, // see how i set a timeout
            'handler' => HandlerStack::create(new CurlMultiHandler([
                'options' => [
                    CURLMOPT_MAX_TOTAL_CONNECTIONS => 20,
                    CURLMOPT_MAX_HOST_CONNECTIONS => 20,
                ]
            ]))
        ]);

        $attempts = [];
        $images_ready = [];
        for ($i = 1; $i <= 3; $i++) {

            $requestPromises = [];
            $temp_files = [];
            foreach ($images_to_download as $image_name) {
                $image_url = sprintf('%s/%s', $url, $image_name);
                $tmp_file = tempnam(sys_get_temp_dir(), str_replace('.', '_', uniqid('vms_' . microtime(true) . '_' . $image_name)));
                $temp_files[$image_name] = $tmp_file;
                $requestPromises[$image_name] = $guzzle_client->getAsync($image_url, [
                    'sink' => $tmp_file,
                    'connect_timeout' => 3.14
                ]);
                $attempts[$image_name] = empty($attempts[$image_name]) ? 1 : $attempts[$image_name] + 1;
            }

            $images_to_download = [];
            $results = Utils::settle($requestPromises)->wait();
            foreach ($results as $image_name => $image_result) {
                if (
                    $image_result['state'] !== 'fulfilled'
                    || !file_exists($temp_files[$image_name])
                    || (filesize($temp_files[$image_name]) === 0)
                ) {
                    $images_to_download[] = $image_name;
                    unlink($temp_files[$image_name]);
                } else {
                    $images_ready[$image_name] = $temp_files[$image_name];
                }
            }
        }

        $lacking_images = [];
        foreach ($images_list as $image_name) {
            if (empty($images_ready[$image_name])) {
                $lacking_images[] = $image_name;
            }
        }

        return [$images_ready, $lacking_images];
    }
}