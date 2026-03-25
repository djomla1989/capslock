<?php

declare(strict_types=1);

namespace App\Lock;

use App\Lock\Contract\LockManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class RedisLockManager implements LockManagerInterface
{
    private const COOLDOWN_PREFIX = 'event_loader:cooldown:';
    private const STOP_SIGNAL_KEY = 'event_loader:stop_signal';

    /** @var array<string, LockInterface> */
    private array $locks = [];

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly \Redis $redis,
        private readonly int $cooldownMs = 200,
    ) {}

    public function acquire(string $sourceName): bool
    {
        $cooldownKey = self::COOLDOWN_PREFIX . $sourceName;

        if ($this->redis->exists($cooldownKey)) {
            return false;
        }

        $lock = $this->lockFactory->createLock($sourceName, 30);

        if (!$lock->acquire()) {
            return false;
        }

        $this->locks[$sourceName] = $lock;
        $this->redis->set($cooldownKey, '1', ['PX' => $this->cooldownMs]);

        return true;
    }

    public function release(string $sourceName): void
    {
        if (!isset($this->locks[$sourceName])) {
            return;
        }

        $this->locks[$sourceName]->release();
        unset($this->locks[$sourceName]);
    }

    public function shouldStop(): bool
    {
        return (bool) $this->redis->get(self::STOP_SIGNAL_KEY);
    }

    public function sendStopSignal(): void
    {
        $this->redis->set(self::STOP_SIGNAL_KEY, '1', 60);
    }

    public function clearStopSignal(): void
    {
        $this->redis->del(self::STOP_SIGNAL_KEY);
    }
}
