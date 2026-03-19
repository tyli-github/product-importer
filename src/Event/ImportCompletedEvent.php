<?php

declare(strict_types=1);

namespace App\Event;

use App\DTO\ImportResult;
use App\Entity\ImportJob;
use Symfony\Contracts\EventDispatcher\Event;

class ImportCompletedEvent extends Event
{
    public function __construct(
        private readonly ImportJob $importJob,
        private readonly ImportResult $result,
    ) {
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function getResult(): ImportResult
    {
        return $this->result;
    }
}
