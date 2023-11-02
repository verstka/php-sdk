<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Image;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;

/**
 * The class loads images into a local temp folder
 * and gives the names of files and their temporary paths
 */
final class ImagesLoaderToTemp implements ImagesLoaderInterface
{
    /**
     * Loaded images to temp dir
     *
     * @var array<string,string>
     */
    private $loadedImages;

    /**
     * Loaded images to temp dir
     *
     * @var array<array-key,string>
     */
    private $lackingImages;

    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getLoadedImages(): array
    {
        return $this->loadedImages;
    }

    /***
     * @inheritDoc
     * @return array<array-key,string>
     */
    public function getNotLoadedImages(): array
    {
        return $this->lackingImages;
    }

    /**
     * @param non-empty-string $imagesDirectoryUrl
     * @param array<string>    $imageNames
     *
     * @return array
     */
    public function load(string $imagesDirectoryUrl, array $imageNames): array
    {
        $imagesForDownload = [];
        foreach ($imageNames as $imageName) {
            $imagesForDownload[$imageName] = [
                'download_from' => sprintf('%s/%s', $imagesDirectoryUrl, $imageName),
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
        $this->loadedImages = $imagesReady;
        $this->lackingImages = $lackingImages;
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
     * @param array $imagesForDownload
     *
     * @return array
     */
    private function downloadImages(array $imagesForDownload): array
    {
        $client = new Guzzle(['connect_timeout' => 3.14, 'timeout' => 180.0]);
        $requestPromises = function (array $imagesForDownload) use ($client) {
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
     * Remove temp loaded images
     */
    public function __destruct()
    {
        foreach ($this->loadedImages as $imageTempFile) {
            if (is_readable($imageTempFile)) {
                unlink($imageTempFile);
            }
        }
    }
}
