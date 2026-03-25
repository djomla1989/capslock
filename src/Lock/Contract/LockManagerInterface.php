<?php

declare(strict_types=1);

namespace App\Lock\Contract;

interface LockManagerInterface
{
    /**
     * Attempt to acquire an exclusive lock for the given source.
     * Must enforce a minimum cooldown (e.g. 200ms) between consecutive
     * requests to the same source across all loader instances.
     *
     * Returns true if lock was acquired, false otherwise.
     */
    public function acquire(string $sourceName): bool;

    /**
     * Release the lock for the given source.
     */
    public function release(string $sourceName): void;

    /**
     * Check if a stop signal has been sent to all loader instances.
     */
    public function shouldStop(): bool;

    /**
     * Send a stop signal to all running loader instances.
     */
    public function sendStopSignal(): void;

    /**
     * Clear the stop signal.
     */
    public function clearStopSignal(): void;
}
