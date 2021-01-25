<?php
/*
 * People (c) 2021
 * devoted to the multiverse
 */

namespace App\Service {

    use App\DTO\TaskDTO;
    use Redis;
    use RedisException;

    /**
     * Class RedisQueue
     * @package App\Service
     */
    final class RedisQueue
    {
        /**
         * @var string
         */
        private string $host;
        /**
         * @var int
         */
        private int $port;
        /**
         * @var int
         */
        private int $timeout;
        /**
         * @var Redis|null
         */
        private ?Redis $connection = null;
        /**
         * @var bool
         */
        private bool $shouldTerminate = false;

        /**
         * RedisQueue constructor.
         *
         * @param string $host Redis host
         * @param int $port Redis port
         * @param int $timeout Worker timeout after which client will return to the  waiting list
         */
        public function __construct(string $host, int $port, int $timeout = 10)
        {
            $this->host = $host;
            $this->port = $port;
            $this->timeout = $timeout;
        }

        /**
         * Enqueues the set of tasks for the specified client id
         *
         * @param int $clientId
         * @param TaskDTO[] $tasks
         * @throws RedisException
         */
        public function enqueue(int $clientId, array $tasks): void
        {
            $this->checkConnection();

            $queueKey = $this->getDefaultQueueKeyName($clientId);
            $pipe = $this->connection->pipeline();
            foreach ($tasks as $task) {
                $pipe->lPush($queueKey, serialize($task));
            }

            $pipe->sAdd('clients', $clientId);
            $pipe->exec();
        }

        /**
         * Consumes tasks for waiting clients
         *
         * @return iterable|TaskDTO[]
         * @throws RedisException
         */
        public function consume(): iterable
        {
            // Ensure that containers will shut down gracefully when required to do so
            $this->setupSignalHandlers();

            while (!$this->shouldTerminate) {
                // Ensure that Redis connection is active
                $this->checkConnection();

                // Get a random client id from the waiting list
                $clientId = $this->getRandomClientId();
                if (!$clientId) {
                    continue;
                }

                $this->populatePendingQueue($clientId);
                $this->checkProcessingQueue($clientId);

                $pendingKey = $this->getPendingQueueKeyName($clientId);
                $processingKey = $this->getProcessingQueueKeyName($clientId);

                // Process asks from 'pending' queue one by one
                while (false !== ($task = $this->connection->rpoplpush($pendingKey, $processingKey))) {
                    yield unserialize($task, [TaskDTO::class]);

                    // ACKs the task execution
                    $this->connection->lPop($processingKey);

                    if ($this->shouldTerminate) {
                        break 1;
                    }
                }

                // Release lock for current client
                $this->connection->del($this->getLockKeyName($clientId));

                // Remove client from the waiting list if necessary
                $this->shouldTerminate || $this->removeClientFromWaitingList($clientId);
            }
        }

        /**
         * Setup signal handlers though worker can shut down gracefully when needed
         */
        private function setupSignalHandlers(): void
        {
            if (function_exists('pcntl_signal')) {
                $handler = function () {
                    $this->shouldTerminate = true;
                };

                pcntl_signal(SIGTERM, $handler);
                pcntl_signal(SIGINT, $handler);
                pcntl_signal(SIGQUIT, $handler);
            }
        }

        /**
         * Checks and re-instantiates connection if has been dropped
         */
        private function checkConnection(): void
        {
            try {
                if ((null === $this->connection) || ('ECHO' !== $this->connection->ping('ECHO'))) {
                    $this->connection = new Redis();
                    $this->connection->connect($this->host, $this->port);
                }
            } catch (RedisException $e) {
                $this->connection = null;
                $this->checkConnection();
            }
        }

        /**
         * @return int
         */
        private function getRandomClientId(): int
        {
            do {
                $lock = false;

                // Get random client from waiting list
                $clientId = (int) $this->connection->sRandMember('clients');

                // If waiting list is empty, sleep for 100 milliseconds
                if (!$clientId) {
                    usleep(100000);
                    continue 1;
                }

                // Acquire lock to ensure that only single worker will handle this client
                $lock = $this->connection->setnx($this->getLockKeyName($clientId), 1);

                // If have been unable to acquire lock, sleep for 100 milliseconds
                $lock || usleep(100000);
            } while (!$lock && !$this->shouldTerminate);

            return $this->shouldTerminate ? 0 : $clientId;
        }

        /**
         * Setup timeout for lock and move all tasks from 'default' to 'pending' queue
         *
         * @param int $clientId
         */
        private function populatePendingQueue(int $clientId): void
        {
            $queueKey = $this->getDefaultQueueKeyName($clientId);
            $pendingKey = $this->getPendingQueueKeyName($clientId);

            do {
                $this->connection->watch($queueKey);
                $queueLen = $this->connection->lLen($queueKey);

                $pipe = $this->connection->multi();
                $pipe->expire($this->getLockKeyName($clientId), $queueLen * $this->timeout + 1);
                while ($queueLen--) {
                    $pipe->rpoplpush($queueKey, $pendingKey);
                }
            } while (!$pipe->exec());

            $this->connection->watch($queueKey);
        }

        /**
         * @param int $clientId
         */
        private function removeClientFromWaitingList(int $clientId): void
        {
            /** @var Redis $multi */
            $multi = $this->connection
                ->multi()
                ->sRem('clients', $clientId);

            $multi->exec();
        }

        /**
         * Check if there is any task that has been terminated during processing for some reason (e.g. power outage)
         * and not ACKed
         *
         * @param int $clientId
         */
        private function checkProcessingQueue(int $clientId): void
        {
            $processingKey = $this->getProcessingQueueKeyName($clientId);

            if ($this->connection->lLen($processingKey)) {
                $this->connection->rpoplpush($processingKey, $this->getPendingQueueKeyName($clientId));
            }
        }

        /**
         * @param int $clientId
         * @return string
         */
        private function getLockKeyName(int $clientId): string
        {
            return sprintf('client:%d:lock', $clientId);
        }

        /**
         * @param int $clientId
         * @return string
         */
        private function getDefaultQueueKeyName(int $clientId): string
        {
            return sprintf('client:%d:queue', $clientId);
        }

        /**
         * @param int $clientId
         * @return string
         */
        private function getPendingQueueKeyName(int $clientId): string
        {
            return sprintf('client:%d:pending', $clientId);
        }

        /**
         * @param int $clientId
         * @return string
         */
        private function getProcessingQueueKeyName(int $clientId): string
        {
            return sprintf('client:%d:processing', $clientId);
        }
    }
}
