<?php
namespace App\AcademicWorkflows;

/** Caller owns the transaction. No commits, DDL, external effects or callbacks. */
interface Executor
{
    /** Return only processed/skipped/failed nonnegative integer counts. */
    public function execute(Operation $operation, Preview $preview): array;
}
