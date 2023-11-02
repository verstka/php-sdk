<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Material;

use Verstka\EditorApi\Exception\VerstkaException;
use Verstka\EditorApi\Image\ImagesLoaderInterface;

interface MaterialSaverInterface
{
    /**
     * @return ImagesLoaderInterface
     */
    public function getImagesLoader(): ImagesLoaderInterface;

    /**
     * @param MaterialDataInterface $materialData
     *
     * @throws VerstkaException
     */
    public function save(MaterialDataInterface $materialData): void;
}
