<?php

declare(strict_types=1);

namespace Verstka\EditorApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use Verstka\EditorApi\Exception\ValidationException;
use Verstka\EditorApi\Exception\VerstkaException;
use Verstka\EditorApi\Image\ImagesLoaderToTemp;
use Verstka\EditorApi\Material\MaterialData;
use Verstka\EditorApi\Material\MaterialDataInterface;
use Verstka\EditorApi\Material\MaterialSaverCallback;
use Verstka\EditorApi\Material\MaterialSaverInterface;

class VerstkaEditor implements VerstkaEditorInterface
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
     * @var bool
     */
    private $debug;

    /**
     * @var string
     */
    private $imagesHost;

    /**
     * @param non-empty-string      $apiKey
     * @param non-empty-string      $secretKey
     * @param null|non-empty-string $imagesHost
     * @param null|non-empty-string $verstkaHost
     * @param bool                  $verstkaDebug
     */
    public function __construct(
        string $apiKey,
        string $secretKey,
        string $imagesHost = null,
        string $verstkaHost = null,
        bool $verstkaDebug = false
    ) {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->imagesHost = $imagesHost;
        $this->verstkaHost = !empty($verstkaHost) ? $verstkaHost : VerstkaEditorInterface::API_HOST;
        $this->debug = $verstkaDebug;
    }

    /**
     * @param string      $materialId
     * @param null|string $articleBody
     * @param bool        $isMobile
     * @param string      $clientSaveUrl
     * @param array       $customFields
     *
     * @throws GuzzleException
     * @throws VerstkaException
     *
     * @return string - verstka edit url
     */
    public function open(
        string $materialId,
        ?string $articleBody,
        bool $isMobile,
        string $clientSaveUrl,
        array $customFields = []
    ): string {
        $customFields = array_merge(
            [
                'auth_user' => 'test',
                //if You have http authorization on callback url
                'auth_pw' => 'test',
                //if You have http authorization on callback url
                'mobile' => $isMobile,
                //if You edit mobile version of article
                'fonts.css' => '/static/vms_fonts.css',
                //if You use custom fonts set
                'version' => 1.0
            ],
            $customFields
        );

        $params = [
            'user_id' => $customFields['user_id'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 1),
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'material_id' => $materialId,
            'html_body' => $articleBody,
            'callback_url' => $clientSaveUrl,
            'host_name' => $this->imagesHost,
            'api-key' => $this->apiKey,
            'custom_fields' => json_encode($customFields)
        ];
        $params['callback_sign'] = self::getRequestSalt(
            $this->secretKey,
            $params,
            'api-key, material_id, user_id, callback_url'
        );

        $result = $this->sendRequest($this->getVerstkaUrl('/1/open'), $params);
        if (empty($result['data']) && empty($result['data']['edit_url'])) {
            throw new VerstkaException('Could not get url for editing');
        }

        return $result['data']['edit_url'];
    }

    /**
     * @param callable|MaterialSaverInterface $clientSaveHandler
     * @param array{
     *    html_body: string,
     *    download_url: string,
     *    session_id: string,
     *    custom_fields: string,
     *    material_id: string,
     *    user_id: string,
     * }|MaterialDataInterface                $materialData
     *
     * @return string - encoded json response from verstka
     */
    public function save($clientSaveHandler, $materialData): string
    {
        set_time_limit(0);
        try {
            $this->validateArticleData($materialData);
            if (!$materialData instanceof MaterialDataInterface) {
                $materialData = new MaterialData($materialData);
            }

            if (!$clientSaveHandler instanceof MaterialSaverInterface) {
                // For Closure callbacks or classes
                $clientSaveHandler = new MaterialSaverCallback($clientSaveHandler);
            }

            $verstkaDownloadUrl = $materialData->getImagesDownloadDirectory();
            //Request list of images
            $verstkaImagesListResponse = $this->sendRequest($materialData->getImagesDownloadDirectory(), [
                'api-key' => $this->apiKey,
                'unixtime' => time()
            ]);

            $imagesLoader = $clientSaveHandler->getImagesLoader();
            $imagesLoader->load($verstkaDownloadUrl, $verstkaImagesListResponse['data']);

            $clientSaveHandler->save($materialData);

            $debug = [];

            /**  @todo delete, please check
             * @see ImagesLoaderToTemp::__destruct()
             * if ($callbackResult === true) {
             * $debug = $imagesLoader->cleanTempFiles($imagesReady, $this->debug);
             * }
             */
            return static::formJSON(1, 'save sucessfull', [
                'debug' => $debug,
                'custom_fields' => $materialData->getCustomFields(),
                'lacking_images' => $imagesLoader->getNotLoadedImages()
            ]);
        } catch (Throwable $e) {
            return static::formJSON($e->getCode(), $e->getMessage(), $materialData);
        }
    }

    /**
     * @param string $secret
     * @param array  $data
     * @param string $fields
     *
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
     * @param string $url
     * @param array  $params
     *
     * @throws VerstkaException
     * @throws GuzzleException
     *
     * @return array
     */
    private function sendRequest(string $url, array $params): array
    {
        $guzzleClient = new Client(
            ['timeout' => 60.0]
        ); //Base URI is used with relative requests // 'base_uri' => 'http://httpbin.org',
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

        if ($code !== 200 || json_last_error(
        ) || !isset($result['data']) || empty($result['rc']) || $result['rc'] !== 1) {
            throw new VerstkaException(sprintf("verstka api open return %d\n%s", $code, $result_json));
        }

        return $result;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getVerstkaUrl(string $path): string
    {
        return $this->verstkaHost . $path;
    }

    /**
     * @param array $data
     *
     * @throws ValidationException
     */
    private function validateArticleData(array $data): void
    {
        $expectCallbackSign = static::getRequestSalt(
            $this->secretKey,
            $data,
            'session_id, user_id, material_id, download_url'
        );
        if (
            empty($data['download_url'])
            || $expectCallbackSign !== $data['callback_sign']
        ) {
            throw new ValidationException('invalid callback sign');
        }
    }

    private static function formJSON($res_code, $res_msg, $data = [])
    {
        return json_encode(
            [
                'rc' => $res_code,
                'rm' => $res_msg,
                'data' => $data
            ],
            JSON_NUMERIC_CHECK
        );
    }
}
