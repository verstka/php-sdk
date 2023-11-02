<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Builder;

use Verstka\EditorApi\Exception\ValidationException;
use Verstka\EditorApi\VerstkaEditor;
use Verstka\EditorApi\VerstkaEditorInterface;

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
     * @var null|string
     */
    private $imagesHost;

    /**
     * @param non-empty-string      $apiKey
     * @param non-empty-string      $secretKey
     * @param null|non-empty-string $verstkaHost
     * @param bool                  $debug
     * @param null|non-empty-string $imagesHost
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
