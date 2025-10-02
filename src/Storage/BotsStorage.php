<?php

namespace AndrewGos\TelegramBotBundle\Storage;

use AndrewGos\TelegramBot\Telegram;
use Iterator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final readonly class BotsStorage implements ContainerInterface
{
    /**
     * @param array<string, Telegram> $bots
     */
    public function __construct(
        private array $bots,
    ) {}

    public function findBestName(string $name): ?string
    {
        $best = -1;
        $closest = null;
        foreach (array_keys($this->bots) as $botName) {
            $lev = levenshtein($name, $botName);
            if ($lev === 0) {
                return $botName;
            }

            if ($lev <= $best || $best < 0) {
                // Replace the closest alternative only if similarity is greater than 75% (means that given name have some mistakes)
                if (1 - $lev / max(strlen($name), strlen($botName)) > 0.75 || str_contains($botName, $name)) {
                    $closest = $botName;
                    $best = $lev;
                }
            }
        }
        return $closest;
    }

    public function get(string $id): Telegram
    {
        return $this->bots[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->bots[$id]);
    }
}
