<?php
/*
 * People (c) 2021
 * devoted to the multiverse
 */

namespace App\Command {

    use App\DTO\TaskDTO;
    use App\Service\RedisQueue;
    use Psr\Log\LoggerInterface;
    use RedisException;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Exception\InvalidArgumentException;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;

    /**
     * Class EnqueueCommand
     * @package App\Command
     */
    class EnqueueCommand extends Command
    {
        /**
         * @var string
         */
        protected static $defaultName = 'demo:enqueue';

        /**
         * @var RedisQueue
         */
        private RedisQueue $queue;

        /**
         * @var LoggerInterface
         */
        private LoggerInterface $logger;

        /**
         * EnqueueCommand constructor.
         *
         * @param RedisQueue $queue
         * @param LoggerInterface $logger
         */
        public function __construct(RedisQueue $queue, LoggerInterface $logger)
        {
            parent::__construct();

            $this->queue = $queue;
            $this->logger = $logger;
        }

        /**
         * Configures the command
         */
        protected function configure(): void
        {
            $this
                ->addOption(
                    'clients',
                    'c',
                    InputOption::VALUE_REQUIRED,
                    'Number of clients to enqueue',
                    1000
                )
                ->addOption(
                    'messages',
                    'm',
                    InputOption::VALUE_REQUIRED,
                    'Number of messages to enqueue for each client',
                    10
                )
                ->setDescription('Enqueues tasks for demo clients')
                ->setHelp('Enqueues tasks for demo clients');
        }

        /**
         * Executes the command
         *
         * @param InputInterface $input
         * @param OutputInterface $output
         * @return int
         * @throws RedisException
         */
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $output->writeln('Started enqueueing...');

            $clientCount = (int) $input->getOption('clients');
            if ($clientCount < 1) {
                throw new InvalidArgumentException('You must specify integer value greater than zero for the client count option');
            }

            $messageCount = (int) $input->getOption('messages');
            if ($messageCount < 1) {
                throw new InvalidArgumentException('You must specify integer value greater than zero for the message count option');
            }

            $clients = range(1, $clientCount);
            shuffle($clients);

            foreach ($clients as $clientId) {
                $this->queue->enqueue($clientId, array_map(static function (int $message) use ($clientId, $messageCount) {
                    return new TaskDTO($clientId, (string) $message);
                }, range(1, $messageCount)));

                $this->logger->info(sprintf('Enqueued tasks for client %d', $clientId));
            }

            $output->writeln('Enqueueing finished.');

            return Command::SUCCESS;
        }
    }
}
