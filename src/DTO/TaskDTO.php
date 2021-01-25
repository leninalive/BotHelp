<?php
/*
 * People (c) 2021
 * devoted to the multiverse
 */

namespace App\DTO {
    class TaskDTO
    {
        private int $clientId;
        private string $message;

        /**
         * TaskDTO constructor.
         * @param int $clientId
         * @param string $message
         */
        public function __construct(int $clientId, string $message)
        {
            $this->clientId = $clientId;
            $this->message = $message;
        }

        /**
         * @return int
         */
        public function getClientId(): int
        {
            return $this->clientId;
        }

        /**
         * @return string
         */
        public function getMessage(): string
        {
            return $this->message;
        }
    }
}
