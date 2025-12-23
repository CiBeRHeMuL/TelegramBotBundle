<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Enum\UpdateTypeEnum;
use AndrewGos\TelegramBot\Exception\ErrorResponseException;
use AndrewGos\TelegramBot\Telegram;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('andrew-gos:telegram-bot:webhook-info')]
class WebhookInfoCommand extends AbstractApiBotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Show actual webhook info for telegram bot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botName = $input->getArgument('bot');
        $bot = $this->findBot($botName);
        if ($bot === null) {
            $this->io->error(sprintf('Bot "%s" not found', $botName));
            return self::INVALID;
        }

        return $this->getWebhookInfo($bot);
    }

    private function getWebhookInfo(Telegram $telegram): int
    {
        try {
            $response = $telegram->getApi()->getWebhookInfo();
        } catch (ErrorResponseException $e) {
            $response = $e->getResponse();
        } catch (Throwable $e) {
            $this->io->error(sprintf('Failed to get webhook info due to error: "%s"', $e->getMessage()));
            return self::FAILURE;
        }

        if ($response->isOk()) {
            $info = $response->getWebhookInfo();
            $this->io->success(
                sprintf(
                    <<<TEXT
                        url: %s
                        has_custom_certificate: %s
                        pending_update_count: %s
                        allowed_updates: %s
                        ip_address: %s
                        last_error_date: %s
                        last_error_message: %s
                        last_synchronization_error_date: %s
                        max_connections: %s
                        TEXT,
                    $info->getUrl()?->getUrl() ?? 'null',
                    $info->getHasCustomCertificate() ? 'yes' : 'no',
                    $info->getPendingUpdateCount(),
                    $info->getAllowedUpdates() !== null
                        ? '[' . implode(', ', array_map(fn(UpdateTypeEnum $e) => $e->value, $info->getAllowedUpdates())) . ']'
                        : 'default allowed updates',
                    $info->getIpAddress()?->getAddress() ?? 'null',
                    $info->getLastErrorDate()
                        ? (new DateTimeImmutable("@{$info->getLastErrorDate()}"))->format(DATE_RFC3339)
                        : 'null',
                    $info->getLastErrorMessage() ?? 'null',
                    $info->getLastSynchronizationErrorDate()
                        ? (new DateTimeImmutable("@{$info->getLastSynchronizationErrorDate()}"))->format(DATE_RFC3339)
                        : 'null',
                    $info->getMaxConnections(),
                ),
            );
            return self::SUCCESS;
        } else {
            $this->printRawResponseWithMessage($response, 'Failed to get webhook info!');
            return self::FAILURE;
        }
    }
}
