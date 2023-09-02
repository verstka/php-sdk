<?php

namespace Verstka\Image;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;

class ImageLoader
{
    /**
     * @param string $url
     * @param array $images
     *
     * @return array
     */
    public function load(string $url, array $images): array
    {
        $imagesForDownload = [];
        foreach ($images as $imageName) {
            $imagesForDownload[$imageName] = [
                'download_from' => sprintf('%s/%s', $url, $imageName),
                'download_to' => $this->generateTempFileName($imageName)
            ];
        }

        $imagesForDownload = $this->downloadImages($imagesForDownload); // Attempt number one
        $imagesForDownload = $this->downloadImages($imagesForDownload); // Attempt number two
        $imagesForDownload = $this->downloadImages($imagesForDownload); // Attempt number three

        $imagesReady = [];
        $lackingImages = [];
        foreach ($imagesForDownload as $imageName => $imageData) {
            if ($imageData['is_lacking'] === true) {
                $lackingImages[] = $imageName;
            } else {
                $imagesReady[$imageName] = $imageData['download_to'];
            }
        }

        return [$imagesReady, $lackingImages];
    }

    /**
     * @param string $imageName
     *
     * @return string
     */
    private function generateTempFileName(string $imageName): string
    {
        return tempnam(sys_get_temp_dir(), str_replace('.', '_', uniqid('vms_' . microtime(true) . '_' . $imageName)));
    }

    /**
     * @param $imagesForDownload
     *
     * @return array
     */
    private function downloadImages($imagesForDownload): array
    {
        $client = new Guzzle(['connect_timeout' => 3.14, 'timeout' => 180.0]);
        $requestPromises = function ($imagesForDownload) use ($client) {
            foreach ($imagesForDownload as $imageName => $imageData) {
                if (isset($imageData['is_lacking']) && $imageData['is_lacking'] === false) {
                    continue; // Skip successful ones on retry
                }
                yield $imageName => function () use ($client, $imageName, $imageData) {
                    return $client->getAsync($imageData['download_from'], [
                        'sink' => $imageData['download_to']
                    ]);
                };
            }
        };

        $pool = new Pool($client, $requestPromises($imagesForDownload), [
            'concurrency' => 20,
            'fulfilled' => function (Response $response, $imageName) use (&$imagesForDownload) {
                $imagesForDownload[$imageName]['is_lacking'] = false;
            },
            'rejected' => function (RequestException $reason, $imageName) use (&$imagesForDownload) {
                $imagesForDownload[$imageName]['is_lacking'] = true;
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        foreach ($imagesForDownload as $imageName => $imageData) {
            if ($imageData['is_lacking'] === true) {
                if (is_readable($imageData['download_to'])) {
                    unlink($imageData['download_to']);
                }
                continue;
            }
            if (is_readable($imageData['download_to']) && filesize($imageData['download_to']) === 0) {
                $imagesForDownload[$imageName]['is_lacking'] = true;
                unlink($imageData['download_to']);
            }
        }

        return $imagesForDownload;
    }

    /**
     * @param array $images
     * @param bool $debugInfo
     *
     * @return null|array
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
}
