<?php

declare(strict_types=1);

namespace Switch\Router\Exception;

use Exception;

class MethodNotAllowedException extends Exception
{
    /**
     * @param array<int, string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods,
        string $message = 'Method Not Allowed'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
