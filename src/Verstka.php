<?php

declare(strict_types=1);

namespace Verstka;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Verstka\Exception\ValidationException;
use Verstka\Exception\VerstkaException;
use Verstka\Image\ImageLoader;

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
    private $verstkaHost;

    /**
     * @var ImageLoader
     */
    private $loader;

    public function __construct()
    {
        $this->apiKey = getenv('verstka_apikey');
        $this->secretKey = getenv('verstka_secret');
        $this->verstkaHost = getenv('verstka_host');
        $this->loader = new ImageLoader();
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
    public function open(string $name, string $articleBody, bool $isMobile, string $clientSaveUrl, array $customFields = []): string
    {
        $customFields = array_merge([
            'auth_user' => 'test',        //if You have http authorization on callback url
            'auth_pw' => 'test',          //if You have http authorization on callback url
            'mobile' => $isMobile,       //if You edit mobile version of article
            'fonts.css' => '/static/vms_fonts.css', //if You use custom fonts set
            'version' => 1.0
        ], $customFields);

        $params = [
            'user_id' => $_SERVER['PHP_AUTH_USER'] ?? 1,
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'material_id' => $name,
            'html_body' => $articleBody,
            'callback_url' => $clientSaveUrl,
            'host_name' => $_SERVER['HTTP_HOST'],
            'api-key' => $this->apiKey,
            'custom_fields' => json_encode($customFields)
        ];
        $params['callback_sign'] = self::getRequestSalt($this->secretKey, $params, 'api-key, material_id, user_id, callback_url');

        $result = $this->sendRequest($this->getVerstkaUrl('/1/open'), $params);
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
            $customFields = json_decode($data['custom_fields'], true);
            $isMobile = isset($customFields['mobile']) && $customFields['mobile'] === true;
            $material_id = $data['material_id'];
            $user_id = $data['user_id'];

            //Request list of images
            $verstkaResponse = $this->sendRequest($verstkaDownloadUrl, [
                'api-key' => $this->apiKey,
                'unixtime' => time()
            ]);

            [$imagesReady, $lackingImages] = $this->loader->load($verstkaDownloadUrl, $verstkaResponse['data']);

            $callbackResult = call_user_func($clientCallback, [
                'article_body' => $article_body,
                'custom_fields' => $customFields,
                'is_mobile' => $isMobile,
                'material_id' => $material_id,
                'user_id' => $user_id,
                'images' => $imagesReady
            ]);

            $debug = [];
            if ($callbackResult === true) {
                $debug = $this->loader->cleanTempFiles($imagesReady, true);
            }

            $additional_data = [
//                'images_list' => $images_list,
//                'results' => $results,
//                'temp_files' => $temp_files,
//                'attempts' => $attempts,
                'debug' => $debug,
                'custom_fields' => $customFields,
                'lacking_images' => $lackingImages
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
        $response = $guzzleClient->post($url, [
            'connect_timeout' => 3.14,
            'headers' => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
            ],
            'form_params' => $params
        ]);
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

    private function getVerstkaUrl(string $path): string
    {
        return $this->verstkaHost . $path;
    }
}