<?php

declare(strict_types=1);

namespace App\Loader;

use App\Lock\Contract\LockManagerInterface;
use App\Source\Contract\EventSourceInterface;
use App\Source\EventSourceCollection;
use App\Source\Exception\SourceUnavailableException;
use App\Store\Contract\EventStoreInterface;
use Psr\Log\LoggerInterface;

final class EventLoader
{
    public function __construct(
        private readonly EventSourceCollection $sources,
        private readonly EventStoreInterface $store,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(): void
    {
        if ($this->sources->count() === 0) {
            $this->logger->warning('No event sources configured.');
            return;
        }

        $this->logger->info('Event loader started. Use /stop endpoint to gracefully stop.');
        $this->lockManager->clearStopSignal();

        while (true) {
            if ($this->checkStopSignal()) {
                return;
            }

            foreach ($this->sources as $source) {
                $this->logger->debug('Attempting to load events from source {source}.', [
                    'source' => $source->getName(),
                ]);

                $this->processSource($source);

                if ($this->checkStopSignal()) {
                    return;
                }
            }

            usleep(10000);
        }
    }

    private function checkStopSignal(): bool
    {
        if ($this->lockManager->shouldStop()) {
            $this->logger->info('Stop signal received. Shutting down gracefully.');
            $this->lockManager->clearStopSignal();
            return true;
        }

        return false;
    }


    private function processSource(EventSourceInterface $source): void
    {
        $sourceName = $source->getName();

        if (!$this->lockManager->acquire($sourceName)) {
            $this->logger->debug('Skipping source {source}: locked or in cooldown.', [
                'source' => $sourceName,
            ]);
            return;
        }

        try {
            $lastEventId = $this->store->getLastEventId($sourceName);
            $events = $source->fetchEvents($lastEventId);

            if (count($events) > 0) {
                $this->store->store($events);
                $this->logger->info('Stored {count} events from source {source}.', [
                    'count' => count($events),
                    'source' => $sourceName,
                ]);
            } else {
                $this->logger->debug('No new events from source {source}.', [
                    'source' => $sourceName,
                ]);
            }
        } catch (SourceUnavailableException $e) {
            $this->logger->error('Source {source} unavailable: {message}', [
                'source' => $sourceName,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in source {source}: {message}', [
                'source' => $sourceName,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        } finally {
            $this->lockManager->release($sourceName);
        }
    }
}
