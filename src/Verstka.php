<?php

declare(strict_types=1);


namespace Verstka;

use Verstka\EditorApi\Builder\VerstkaEnvBuilder;
use Verstka\EditorApi\Material\MaterialDataInterface;
use Verstka\EditorApi\Material\MaterialSaverInterface;
use Verstka\EditorApi\VerstkaEditorInterface;

/**
 * @deprecated  The class will be removed in the future
 *
 * Use the interface implementation {@see \Verstka\EditorApi\VerstkaBuilderInterface}
 *
 * @see         \Verstka\EditorApi\Builder\VerstkaConfigBuilder
 * @see         \Verstka\EditorApi\Builder\VerstkaEnvBuilder
 *
 * @final
 */
class Verstka implements VerstkaEditorInterface
{
    /**
     * @var VerstkaEditorInterface
     */
    private $verstkaEditor;

    public function __construct()
    {
        $this->verstkaEditor = (new VerstkaEnvBuilder())->build();
    }

    /**
     * @param string      $materialId
     * @param string|null $articleBody
     * @param bool        $isMobile
     * @param string      $clientSaveUrl
     * @param array       $customFields
     *
     * @return string
     */
    public function open(
        string $materialId,
        ?string $articleBody,
        bool $isMobile,
        string $clientSaveUrl,
        array $customFields = []
    ): string {
        return $this->verstkaEditor->open($materialId, $articleBody, $isMobile, $clientSaveUrl, $customFields);
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
        return $this->verstkaEditor->save($clientSaveHandler, $materialData);
    }
}