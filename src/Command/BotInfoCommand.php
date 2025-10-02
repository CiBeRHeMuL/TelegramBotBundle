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

#[AsCommand('andrew-gos:telegram-bot:bot-info')]
class BotInfoCommand extends AbstractApiBotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Show actual bot info for telegram bot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botName = $input->getArgument('bot');
        $bot = $this->findBot($botName);
        if ($bot === null) {
            $this->io->error(sprintf('Bot "%s" not found', $botName));
            return self::INVALID;
        }

        return $this->getBotInfo($bot);
    }

    private function getBotInfo(Telegram $telegram): int
    {
        try {
            $response = $telegram->getApi()->getMe();
        } catch (ErrorResponseException $e) {
            $response = $e->getResponse();
        } catch (Throwable $e) {
            $this->io->error(sprintf('Failed to get bot info due to error: "%s"', $e->getMessage()));
            return self::FAILURE;
        }

        if ($response->isOk()) {
            $user = $response->getUser();
            $this->io->success(
                sprintf(
                    <<<TEXT
                        id: %s
                        is_bot: %s
                        first_name: %s
                        last_name: %s
                        username: %s
                        language_code: %s
                        is_premium: %s
                        added_to_attachment_menu: %s
                        can_join_groups: %s
                        can_read_all_group_messages: %s
                        supports_inline_queries: %s
                        can_connect_to_business: %s
                        has_main_web_app: %s
                        TEXT,
                    $user->getId(),
                    $user->getIsBot() ? 'yes' : 'no',
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getUsername(),
                    $user->getLanguageCode()?->getLanguage(),
                    $user->getIsPremium() ? 'yes' : 'no',
                    $user->getAddedToAttachmentMenu() ? 'yes' : 'no',
                    $user->getCanJoinGroups() ? 'yes' : 'no',
                    $user->getCanReadAllGroupMessages() ? 'yes' : 'no',
                    $user->getSupportsInlineQueries() ? 'yes' : 'no',
                    $user->getCanConnectToBusiness() ? 'yes' : 'no',
                    $user->getHasMainWebApp() ? 'yes' : 'no',
                ),
            );
            return self::SUCCESS;
        } else {
            $this->printRawResponseWithMessage($response, 'Failed to get webhook info!');
            return self::FAILURE;
        }
    }
}
