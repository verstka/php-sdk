<?php

declare(strict_types=1);

namespace Verstka\EditorApi\Builder;

use Verstka\EditorApi\VerstkaEditorInterface;

interface VerstkaBuilderInterface
{
    /**
     * @return VerstkaEditorInterface
     */
    public function build(): VerstkaEditorInterface;
}
