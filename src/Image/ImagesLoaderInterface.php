<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Image;

interface ImagesLoaderInterface
{
    /**
     * @param non-empty-string $imagesDirectoryUrl https://verstka.org/contents/uid/images/
     * @param array            $imageNames         ['fileName.jpg', 'image.gif'....]
     */
    public function load(string $imagesDirectoryUrl, array $imageNames): void;

    /**
     * @return array<string,string>
     *
     * @format ['fileName.jpg' => '/path or URL','image.gif' => '...',...]
     */
    public function getLoadedImages(): array;

    /**
     * @return array<array-key,string>
     *
     * @format ['fileName.jpg', 'image.gif'....]
     */
    public function getNotLoadedImages(): array;
}
