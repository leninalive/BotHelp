<?php
/*
 * People (c) 2021
 * devoted to the multiverse
 */

namespace App\Command {

    use App\Service\RedisQueue;
    use App\Service\TaskProcessor;
    use Psr\Log\LoggerInterface;
    use RedisException;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    /**
     * Class ConsumeCommand
     * @package App\Command
     */
    class ConsumeCommand extends Command
    {
        /**
         * @var string
         */
        protected static $defaultName = 'demo:consume';

        /**
         * @var RedisQueue
         */
        private RedisQueue $queue;
        /**
         * @var TaskProcessor
         */
        private TaskProcessor $processor;

        /**
         * Consume constructor.
         *
         * @param RedisQueue $queue
         * @param TaskProcessor $processor
         * @param LoggerInterface $logger
         */
        public function __construct(RedisQueue $queue, TaskProcessor $processor)
        {
            parent::__construct();

            $this->queue = $queue;
            $this->processor = $processor;
        }

        /**
         * Configures the command
         */
        protected function configure(): void
        {
            $this
                ->setDescription('Start consumer worker thread')
                ->setHelp('Start consumer worker thread');
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
            $output->writeln('Worker started.', OutputInterface::OUTPUT_RAW);

            foreach ($this->queue->consume() as $task) {
                $this->processor->handle($task);
            }

            $output->writeln('Worker terminated gracefully.');

            return Command::SUCCESS;
        }
    }
}
