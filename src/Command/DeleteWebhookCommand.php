<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Exception\ErrorResponseException;
use AndrewGos\TelegramBot\Request\DeleteWebhookRequest;
use AndrewGos\TelegramBot\Telegram;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('andrew-gos:telegram-bot:delete-webhook')]
class DeleteWebhookCommand extends AbstractApiBotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Delete webhook for telegram bot')
            ->addOption(
                'drop-pending-updates',
                'd',
                InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE,
                'Drop all pending updates',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botName = $input->getArgument('bot');
        $bot = $this->findBot($botName);
        if ($bot === null) {
            $this->io->error(sprintf('Bot "%s" not found', $botName));
            return self::INVALID;
        }

        return $this->deleteWebhook($bot, $input);
    }

    private function deleteWebhook(Telegram $telegram, InputInterface $input): int
    {
        $dropPendingUpdates = $input->getOption('drop-pending-updates');

        // Validate drop pending updates
        $dropPendingUpdates = $dropPendingUpdates !== null ? (bool) $dropPendingUpdates : null;

        try {
            $response = $telegram->getApi()->deleteWebhook(
                new DeleteWebhookRequest($dropPendingUpdates),
            );
        } catch (ErrorResponseException $e) {
            $response = $e->getResponse();
        } catch (Throwable $e) {
            $this->io->error(sprintf('Failed to delete webhook due to error: "%s"', $e->getMessage()));
            return self::FAILURE;
        }

        if ($response->isOk()) {
            $this->io->success('Webhook deleted successfully');
            return self::SUCCESS;
        } else {
            $this->printRawResponseWithMessage($response, 'Failed to delete webhook!');
            return self::FAILURE;
        }
    }
}
