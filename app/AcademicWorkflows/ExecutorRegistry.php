<?php
namespace App\AcademicWorkflows;

final class ExecutorRegistry
{
    private array $executors = [];
    public function register(string $kind, Executor $executor): void
    {
        if (!in_array($kind, WorkflowService::KINDS, true) || isset($this->executors[$kind])) throw new \DomainException('workflow_executor_registration_invalid');
        $this->executors[$kind] = $executor;
    }
    public function get(string $kind): Executor
    {
        return $this->executors[$kind] ?? throw new \DomainException('workflow_executor_unavailable');
    }
}
