<?php
namespace App\AcademicWorkflows;

final readonly class Preview
{
    public function __construct(public int $version, public string $hash, public string $validation, public array $items, public array $snapshot) {}
}
