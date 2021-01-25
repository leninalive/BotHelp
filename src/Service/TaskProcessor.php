<?php
/*
 * People (c) 2021
 * devoted to the multiverse
 */

namespace App\Service {

    use App\DTO\TaskDTO;
    use Psr\Log\LoggerInterface;

    /**
     * Class TaskProcessor
     * @package App\Service
     */
    class TaskProcessor
    {
        /**
         * @var LoggerInterface
         */
        private LoggerInterface $logger;

        /**
         * TaskProcessor constructor.
         *
         * @param LoggerInterface $logger
         */
        public function __construct(LoggerInterface $logger)
        {
            $this->logger = $logger;
        }

        /**
         * Handles task
         *
         * @param TaskDTO $task
         */
        public function handle(TaskDTO $task): void
        {
            sleep(1);

            $this->logger->info(sprintf(
                'Processing client %d task with message "%s"',
                $task->getClientId(),
                $task->getMessage()
            ));
        }
    }
}
