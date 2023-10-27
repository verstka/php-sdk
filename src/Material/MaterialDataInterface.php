<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Material;

use ArrayAccess;
use JsonSerializable;

interface MaterialDataInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Material html content
     *
     * @return non-empty-string
     */
    public function getBody(): string;

    /**
     * Your material ID
     *
     * @return non-empty-string
     */
    public function getMaterialId(): string;

    /**
     * Images download directory
     *
     * @return non-empty-string
     */
    public function getImagesDownloadDirectory(): string;

    /**
     * Advanced data
     *
     * @return array
     */
    public function getCustomFields(): array;

    /**
     * Is mobile content version
     *
     * @return bool
     */
    public function isMobile(): bool;

    /**
     * Your editor User ID
     *
     * @return string
     */
    public function getUserId(): string;

    /**
     * @return string
     */
    public function getSessionId(): string;
}