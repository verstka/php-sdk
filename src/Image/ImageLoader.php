<?php

namespace Verstka\Image;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

class ImageLoader
{
    /**
     * @param string $url
     * @param array $images
     * @return array
     */
    public function load(string $url, array $images): array
    {
        $guzzle_client = new Client([
            'timeout' => 180.0, // see how i set a timeout
            'handler' => HandlerStack::create(new CurlMultiHandler([
                'options' => [
                    CURLMOPT_MAX_TOTAL_CONNECTIONS => 20,
                    CURLMOPT_MAX_HOST_CONNECTIONS => 20,
                ]
            ]))
        ]);

        $tempFiles = [];
        $requestPromises = [];
        foreach ($images as $imageName) {
            $imageUrl = sprintf('%s/%s', $url, $imageName);
            $tmpFile = $this->generateTempFileName($imageName);
            $tempFiles[$imageName] = $tmpFile;
            $requestPromises[$imageName] = $guzzle_client->getAsync($imageUrl, [
                'sink' => $tmpFile,
                'connect_timeout' => 3.14
            ]);
        }

        $imagesReady = [];
        $lackingImages = [];
        foreach (array_chunk($requestPromises, 20) as $promises) {
            $results = Utils::settle($promises)->wait();
            foreach ($results as $image_name => $image_result) {
                if (
                    $image_result['state'] !== PromiseInterface::FULFILLED
                    || !file_exists($tempFiles[$image_name])
                    || (filesize($tempFiles[$image_name]) === 0)
                ) {
                    $lackingImages[] = $image_name;
                    unlink($tempFiles[$image_name]);
                } else {
                    $imagesReady[$image_name] = $tempFiles[$image_name];
                }
            }
        }

        return [$imagesReady, $lackingImages];
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