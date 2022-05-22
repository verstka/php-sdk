<?php

namespace Verstka\Image;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

class ImageLoader
{
    public function load(string $url, array $images): array
    {
        $images_to_download = $images;

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
                $imageUrl = sprintf('%s/%s', $url, $image_name);
                $tmpFile = $this->generateTempFileName($image_name);
                $temp_files[$image_name] = $tmpFile;
                $requestPromises[$image_name] = $guzzle_client->getAsync($imageUrl, [
                    'sink' => $tmpFile,
                    'connect_timeout' => 3.14
                ]);
                $attempts[$image_name] = empty($attempts[$image_name]) ? 1 : $attempts[$image_name] + 1;
            }

            $images_to_download = [];
            $results = Utils::settle($requestPromises)->wait();
            foreach ($results as $image_name => $image_result) {
                if (
                    $image_result['state'] !== PromiseInterface::FULFILLED
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
        foreach ($images as $image_name) {
            if (empty($images_ready[$image_name])) {
                $lacking_images[] = $image_name;
            }
        }

        return [$images_ready, $lacking_images];
    }

    /**
     * @param array $images
     * @param bool $debugInfo
     * @return array|null
     */
    public function cleanTempFiles(array $images, bool $debugInfo = false): ?array
    {
        $debug = [];
        foreach ($images as $image => $imageTempFile) {
            // clean temp folder if callback successfull
            if (is_readable($imageTempFile)) {
                unlink($imageTempFile);
                $debug[] = $imageTempFile;
            }
        }

        return $debugInfo ? $debug : null;
    }

    /**
     * @param string $imageName
     * @return string
     */
    private function generateTempFileName(string $imageName): string
    {
        return tempnam(sys_get_temp_dir(), str_replace('.', '_', uniqid('vms_' . microtime(true) . '_' . $imageName)));
    }
}