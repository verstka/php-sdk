<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Material;

use Closure;
use Verstka\EditorApi\Image\ImagesLoaderInterface;
use Verstka\EditorApi\Image\ImagesLoaderToTemp;


final class MaterialSaverCallback implements MaterialSaverInterface
{
    /**
     * @var callable|Closure(array|\ArrayAccess $data):int
     */
    private $saveHandlerCallback;

    /**
     * @var ImagesLoaderInterface
     */
    private $imagesLoader;

    /**
     * @param callable|Closure(array|\ArrayAccess $data):int $saveHandlerCallback
     */
    public function __construct(callable $saveHandlerCallback)
    {
        $this->saveHandlerCallback = $saveHandlerCallback;
        $this->imagesLoader = new ImagesLoaderToTemp();
    }

    /**
     * @return ImagesLoaderInterface
     */
    public function getImagesLoader(): ImagesLoaderInterface
    {
        return $this->imagesLoader;
    }

    /**
     * @param MaterialDataInterface $materialData
     *
     * @throws
     */
    public function save(MaterialDataInterface $materialData): void
    {
        call_user_func($this->saveHandlerCallback, [
            'article_body' => $materialData->getBody(),
            'custom_fields' => $materialData->getCustomFields(),
            'is_mobile' => $materialData->isMobile(),
            'material_id' => $materialData->getMaterialId(),
            'user_id' => $materialData->getUserId(),
            'images' => $this->imagesLoader->getLoadedImages()
        ]);
    }
}