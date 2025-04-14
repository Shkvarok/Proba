<?php

namespace App\Exceptions;

use Exception;

class ResourceNotFoundException extends Exception
{
    /**
     * @var string
     */
    protected $resourceName;

    /**
     * @param string $resourceName
     * @param string $message
     */
    public function __construct(string $resourceName = '', string $message = '')
    {
        $this->resourceName = $resourceName;
        $message = $message ?: "Ресурс {$resourceName} не знайдено";
        
        parent::__construct($message, 404);
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }
}