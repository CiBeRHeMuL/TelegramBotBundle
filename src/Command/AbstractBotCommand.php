<?php

namespace AndrewGos\TelegramBotBundle\Command;

use AndrewGos\TelegramBot\Telegram;
use AndrewGos\TelegramBotBundle\Storage\BotsStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class AbstractBotCommand extends Command
{
    protected SymfonyStyle $io;

    public function __construct(
        #[Autowire('@andrew_gos_telegram_bot.bots_storage')]
        protected BotsStorage $bots,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(
                'bot',
                InputArgument::REQUIRED,
                'Bot name',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
    }

    protected function findBot(string $name): ?Telegram
    {
        if ($this->bots->has($name)) {
            return $this->bots->get($name);
        } else {
            $closestBotName = $this->bots->findBestName($name);
            if ($closestBotName === null) {
                return null;
            }

            $useClosest = $this->io->confirm(
                sprintf(
                    'Bot with name "%s" not found. Did you mean "%s"?',
                    $name,
                    $closestBotName,
                ),
            );

            if ($useClosest) {
                return $this->bots->get($closestBotName);
            } else {
                return null;
            }
        }
    }
}
