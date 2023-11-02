<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Builder;

use Verstka\EditorApi\VerstkaEditor;
use Verstka\EditorApi\VerstkaEditorInterface;
use Verstka\EditorApi\Exception\ValidationException;


class VerstkaConfigBuilder implements VerstkaBuilderInterface
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $verstkaHost;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var string|null
     */
    private $imagesHost;

    /**
     * @param non-empty-string       $apiKey
     * @param non-empty-string       $secretKey
     * @param non-empty-string |null $verstkaHost
     * @param bool                   $debug
     * @param non-empty-string|null  $imagesHost
     *
     * @throws ValidationException
     */
    public function __construct(
        string $apiKey,
        string $secretKey,
        string $imagesHost = null,
        string $verstkaHost = null,
        bool $debug = false

    ) {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->imagesHost = $imagesHost;
        $this->verstkaHost = !empty($verstkaHost) ? $verstkaHost : VerstkaEditorInterface::API_HOST;
        $this->debug = $debug;

        if ($this->apiKey === '') {
            throw new ValidationException('Empty api key!');
        }
        if ($this->secretKey === '') {
            throw new ValidationException('Empty secret key!');
        }
    }

    /**
     * @return VerstkaEditorInterface
     */
    public function build(): VerstkaEditorInterface
    {
        return new VerstkaEditor(
            $this->apiKey,
            $this->secretKey,
            $this->imagesHost ?? $_SERVER['HTTP_HOST'],
            $this->verstkaHost,
            $this->debug
        );
    }
}