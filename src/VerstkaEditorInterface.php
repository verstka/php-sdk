<?php

declare(strict_types=1);

namespace Verstka\EditorApi;

use Verstka\EditorApi\Material\MaterialDataInterface;
use Verstka\EditorApi\Material\MaterialSaverInterface;

interface VerstkaEditorInterface
{

    const API_HOST = 'https://verstka.org';

    /**
     * @param string      $materialId
     * @param string|null $articleBody
     * @param bool        $isMobile
     * @param string      $clientSaveUrl
     * @param array       $customFields
     *
     * @return string  Verstka edit url
     */
    public function open(
        string $materialId,
        ?string $articleBody,
        bool $isMobile,
        string $clientSaveUrl,
        array $customFields = []
    ): string;

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
     * @return string Encoded json response from verstka
     */
    public function save($clientSaveHandler, $materialData): string;
}