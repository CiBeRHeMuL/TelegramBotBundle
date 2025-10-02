<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Telegram;
use AndrewGos\TelegramBotBundle\Command\AbstractBotCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsCommand('andrew-gos:telegram-bot:listen')]
class ListenBotCommand extends AbstractBotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Start listening for incoming updates from telegram bot')
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Timeout for listening to the incoming updates',
                1,
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

        return $this->listen($bot, $input);
    }

    private function listen(Telegram $bot, InputInterface $input): int
    {
        $this->io->info('Start listening...');

        $bot->getUpdateHandler()->listen((int) $input->getOption('timeout'));

        $this->io->info('Stop listening...');
        return self::SUCCESS;
    }

    #[AsEventListener(ConsoleSignalEvent::class)]
    public function handleSystemSignal(ConsoleSignalEvent $event): void
    {
        if (in_array($event->getHandlingSignal(), [SIGINT, SIGTERM, SIGKILL])) {
            $this->io->info('Stop listening...');
        }
    }
}
