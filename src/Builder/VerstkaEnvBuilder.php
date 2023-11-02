<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Builder;

use Verstka\EditorApi\Exception\ValidationException;
use Verstka\EditorApi\VerstkaEditor;
use Verstka\EditorApi\VerstkaEditorInterface;

class VerstkaEnvBuilder implements VerstkaBuilderInterface
{
    /**
     * @throws ValidationException
     *
     * @return VerstkaEditorInterface
     */
    public function build(): VerstkaEditorInterface
    {
        assert(
            !empty(getenv('verstka_apikey'))
            && !empty(getenv('verstka_secret'))
            && !empty(getenv('verstka_host')),
            'Invalid Verstka configuration from ENV!'
        );

        $verstkaHost = getenv('verstka_host');
        if (!is_string($verstkaHost) || empty($verstkaHost)) {
            $verstkaHost = VerstkaEditorInterface::API_HOST;
        }

        $imagesHost = getenv('images_host');
        if (empty($imagesHost) || !is_string($imagesHost)) {
            if (!isset($_SERVER['HTTP_HOST'])) {
                throw new ValidationException('Invalid images host!');
            }
            $imagesHost = $_SERVER['HTTP_HOST'];
        }

        return new VerstkaEditor(
            getenv('verstka_apikey'),
            getenv('verstka_secret'),
            $imagesHost,
            $verstkaHost,
            getenv('verstka_debug') ?? false
        );
    }
}
