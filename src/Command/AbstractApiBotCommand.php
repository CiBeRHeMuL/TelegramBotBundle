<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Response\RawResponse;

abstract class AbstractApiBotCommand extends AbstractBotCommand
{
    protected function printRawResponseWithMessage(RawResponse $response, string $message): void
    {
        $this->io->error(
            sprintf(
                <<<TEXT
                    %s
                    Response:
                        Code: %s
                        Description: %s
                        Result: %s
                        Parameters: %s
                    TEXT,
                $message,
                $response->getErrorCode()?->value,
                $response->getDescription(),
                json_encode($response->getResult()),
                $response->getParameters()
                    ? [
                        'migrate_to_chat_id' => $response->getParameters()->getMigrateToChatId(),
                        'retry_after' => $response->getParameters()->getRetryAfter(),
                    ]
                    : null,
            ),
        );
    }
}
