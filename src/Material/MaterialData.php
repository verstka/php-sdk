<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Material;

final class MaterialData implements MaterialDataInterface
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $customFields = [];

    /**
     * @psalm-param  array{
     *    html_body: string,
     *    download_url: string,
     *    custom_fields: array<string,mixed>,
     *    material_id: string,
     *    user_id: string,
     * } $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->customFields = isset($data['custom_fields']) ? json_decode($this->data['custom_fields'], true) : [];
    }

    /**
     * @return non-empty-string
     */
    public function getBody(): string
    {
        return $this->data['html_body'];
    }

    /**
     * @return non-empty-string
     */
    public function getMaterialId(): string
    {
        return (string)$this->data['material_id'];
    }

    /**
     * @return non-empty-string
     */
    public function getImagesDownloadDirectory(): string
    {
        return $this->data['download_url'];
    }

    /**
     * @return array
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @return bool
     */
    public function isMobile(): bool
    {
        return isset($this->customFields['mobile']) && $this->customFields['mobile'] === true;
    }

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return (string)$this->data['user_id'];
    }

    /**
     * @return string
     */
    public function getSessionId(): string
    {
        return (string)$this->data['session_id'];
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
