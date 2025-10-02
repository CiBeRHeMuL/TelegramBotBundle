<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Enum\UpdateTypeEnum;
use AndrewGos\TelegramBot\Exception\ErrorResponseException;
use AndrewGos\TelegramBot\Request\SetWebhookRequest;
use AndrewGos\TelegramBot\Telegram;
use AndrewGos\TelegramBot\ValueObject\IpV4;
use AndrewGos\TelegramBot\ValueObject\IpV6;
use AndrewGos\TelegramBot\ValueObject\Url;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('andrew-gos:telegram-bot:set-webhook')]
class SetWebhookCommand extends AbstractApiBotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Set webhook for telegram bot')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'HTTPS URL to send updates to',
            )
            ->addOption(
                'allowed-updates',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                <<<TEXT
                    List of the update types you want your bot to receive.
                    For example, specify ["message", "edited_channel_post", "callback_query"] to only receive updates of these types.
                    See Update for a complete list of available update types.
                    Specify an empty list to receive all update types except chat_member, message_reaction, and message_reaction_count (default).
                    If not specified, the previous setting will be used.
                    Please note that this parameter doesn't affect updates created before the call to the setWebhook, so unwanted updates may be received for a short period of time.
                    TEXT,
            )
            ->addOption(
                'certificate-file',
                'cf',
                InputOption::VALUE_OPTIONAL,
                <<<TEXT
                    Filename of certificate file.
                    TEXT,
            )
            ->addOption(
                'certificate-url',
                'cu',
                InputOption::VALUE_OPTIONAL,
                <<<TEXT
                    Url of certificate file.
                    TEXT,
            )
            ->addOption(
                'drop-pending-updates',
                'd',
                InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE,
                'Drop all pending updates',
            )
            ->addOption(
                'ip-address',
                'i',
                InputOption::VALUE_OPTIONAL,
                'The fixed IP address which will be used to send webhook requests instead of the IP address resolved through DNS',
            )
            ->addOption(
                'max-connections',
                'm',
                InputOption::VALUE_OPTIONAL,
                <<<TEXT
                    The maximum allowed number of simultaneous HTTPS connections to the webhook for update delivery, 1-100.
                    Defaults to 40.
                    Use lower values to limit the load on your bot's server, and higher values to increase your bot's throughput.
                    TEXT,
            )
            ->addOption(
                'secret-token',
                's',
                InputOption::VALUE_OPTIONAL,
                <<<TEXT
                    A secret token to be sent in a header “X-Telegram-Bot-Api-Secret-Token” in every webhook request, 1-256 characters.
                    Only characters A-Z, a-z, 0-9, _ and - are allowed.
                    The header is useful to ensure that the request comes from a webhook set by you.
                    TEXT,
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

        return $this->setWebhook($bot, $input);
    }

    private function setWebhook(Telegram $telegram, InputInterface $input): int
    {
        $url = $input->getArgument('url');
        $allowedUpdates = $input->getOption('allowed-updates');
        $certificateFile = $input->getOption('certificate-file');
        $certificateUrl = $input->getOption('certificate-url');
        $dropPendingUpdates = $input->getOption('drop-pending-updates');
        $ipAddress = $input->getOption('ip-address');
        $maxConnections = $input->getOption('max-connections');
        $secretToken = $input->getOption('secret-token');

        // Validate url
        try {
            $url = new Url($url);
        } catch (Throwable $e) {
            $this->io->error('You must provide a valid URL for webhook');
            return self::INVALID;
        }

        // Validate allowed updates
        $allowedUpdates = (array) $allowedUpdates;
        foreach ($allowedUpdates as &$allowedUpdate) {
            $allowedUpdate = UpdateTypeEnum::tryFrom((string) $allowedUpdate);
            if ($allowedUpdate === null) {
                $this->io->error(
                    'Allowed updates must be a valid update type. Check out the AndrewGos\\TelegramBot\\UpdateTypeEnum for more information',
                );
                return self::INVALID;
            }
        }

        // Validate certificate
        if ($certificateFile !== null && $certificateUrl !== null) {
            $this->io->error('You can specify only filename or url for certificate');
            return self::INVALID;
        }

        if ($certificateFile !== null) {
            if (!file_exists($certificateFile)) {
                $this->io->error(sprintf('Certificate file "%s" not found', $certificateFile));
                return self::INVALID;
            }
        }

        if ($certificateUrl !== null) {
            try {
                $certificateUrl = new Url($certificateUrl);
            } catch (Throwable $e) {
                $this->io->error('You must provide a valid URL for certificate');
                return self::INVALID;
            }
        }

        // Validate drop pending updates
        $dropPendingUpdates = $dropPendingUpdates !== null ? (bool) $dropPendingUpdates : null;

        // Validate ip address
        if ($ipAddress !== null) {
            try {
                $ipAddress = str_contains((string) $ipAddress, ':')
                    ? new IpV6($ipAddress)
                    : new IpV4($ipAddress);
            } catch (Throwable $e) {
                $this->io->error('You must provide a valid IPv4 or IPv6 address');
                return self::INVALID;
            }
        }

        // Validate max connections
        $maxConnections = $maxConnections !== null ? min(max(0, (int) $maxConnections), 100) : null;

        // Validate secret token
        if ($secretToken !== null) {
            $secretToken = (string) $secretToken;
            if (!preg_match('/^[a-zA-Z0-9_\-]$/iu', $secretToken)) {
                $this->io->error('You must provide a valid secret token that contains only alphanumeric characters, underscores, and dashes');
                return self::INVALID;
            }
        }

        try {
            $response = $telegram->getApi()->setWebhook(
                new SetWebhookRequest(
                    $url,
                    $allowedUpdates,
                    $certificateUrl ?? $certificateFile ?? null,
                    $dropPendingUpdates,
                    $ipAddress,
                    $maxConnections,
                    $secretToken,
                ),
            );
        } catch (ErrorResponseException $e) {
            $response = $e->getResponse();
        } catch (Throwable $e) {
            $this->io->error(sprintf('Failed to set webhook due to error: "%s"', $e->getMessage()));
            return self::FAILURE;
        }

        if ($response->isOk()) {
            $this->io->success('Webhook set successfully');
            return self::SUCCESS;
        } else {
            $this->printRawResponseWithMessage($response, 'Failed to set webhook!');
            return self::FAILURE;
        }
    }
}
