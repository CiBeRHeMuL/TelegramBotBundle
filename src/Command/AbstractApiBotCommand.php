<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Response\RawResponse;
use AndrewGos\TelegramBot\Serializer\SerializerFactory;

abstract class AbstractApiBotCommand extends AbstractBotCommand
{
    protected function printRawResponseWithMessage(RawResponse $response, string $message): void
    {
        $serializer = SerializerFactory::getDefaultApiSerializer();
        $this->io->error(
            sprintf(
                <<<TEXT
                    %s
                    Response: %s
                    TEXT,
                $message,
                $serializer->serialize($response, 'json'),
            ),
        );
    }
}
