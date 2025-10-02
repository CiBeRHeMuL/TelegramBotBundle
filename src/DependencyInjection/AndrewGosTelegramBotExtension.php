<?php

namespace AndrewGos\TelegramBotBundle\DependencyInjection;

use AndrewGos\TelegramBot\Kernel\HandlerGroup;
use AndrewGos\TelegramBot\Kernel\UpdateHandlerInterface;
use AndrewGos\TelegramBot\Telegram;
use AndrewGos\TelegramBot\TelegramFactory;
use AndrewGos\TelegramBot\ValueObject\BotToken;
use AndrewGos\TelegramBotBundle\Storage\BotsStorage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class AndrewGosTelegramBotExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $bots = [];
        foreach ($config['bots'] as $botName => $botConfig) {
            $bots[$botName] = $this->createBotService($botName, $botConfig, $container);
        }

        $container
            ->register(
                'andrew_gos_telegram_bot.bots_storage',
                BotsStorage::class,
            )
            ->setArguments(['$bots' => $bots]);
    }

    private function createBotService(string $botName, array $botConfig, ContainerBuilder $container): Reference
    {
        $botFactory = $botConfig['factory']['method'];

        $botServiceId = "andrew_gos_telegram_bot.bots.$botName";

        $botService = $container->register(
            $botServiceId,
            Telegram::class,
        );

        if ($container->has($botFactory['class'])) {
            $botFactory['class'] = new Reference($botFactory['class']);
        }

        if (is_subclass_of($botFactory['class'], TelegramFactory::class)) {
            if (!array_key_exists('$eventDispatcher', $botConfig['arguments']) && $container->has('event_dispatcher')) {
                $botConfig['arguments']['$eventDispatcher'] = new Reference('event_dispatcher');
            }
            if (!array_key_exists('$throwOnErrorResponse', $botConfig['arguments'])) {
                $botConfig['arguments']['$throwOnErrorResponse'] = false;
            }
        }

        foreach ($botConfig['factory']['arguments'] as $name => &$argument) {
            if ($name === '$token' && !(is_string($argument) && str_starts_with($argument, '@'))) {
                $tokenServiceId = "$botServiceId.token.factory.token";
                $container
                    ->register(
                        $tokenServiceId,
                        BotToken::class,
                    )
                    ->setArguments([$argument]);
                $argument = new Reference($tokenServiceId);
            }
        }

        $botService
            ->setFactory([$botFactory['class'], $botFactory['method']])
            ->setArguments($botConfig['factory']['arguments'])
            ->setPublic(true);

        $botUpdateHandlerServiceId = "$botServiceId.update_handler";

        $updateHandlerService = $container
            ->register($botUpdateHandlerServiceId, UpdateHandlerInterface::class)
            ->setFactory([new Reference($botServiceId), 'getUpdateHandler']);

        $this->registerPluginsForBot($botServiceId, $botConfig, $updateHandlerService, $container);
        $this->registerHandlersForBot($botServiceId, $botConfig, $updateHandlerService, $container);

        $botAlias = $container->registerAliasForArgument($botServiceId, Telegram::class, $botName . 'Bot');
        $botAlias->setPublic(true);

        $botService->addMethodCall('setUpdateHandler', [new Reference($botUpdateHandlerServiceId)]);

        return new Reference($botServiceId);
    }

    private function registerPluginsForBot(
        string $botServiceId,
        array $botConfig,
        Definition $updateHandlerService,
        ContainerBuilder $container,
    ): void {
        foreach ($botConfig['plugins'] ?? [] as $index => $pluginConfig) {
            $updateHandlerService->addMethodCall(
                'registerPlugin',
                [$this->createSubService('plugins', $botServiceId, $index, $pluginConfig, $container)],
            );
        }
    }

    private function registerHandlersForBot(
        string $botServiceId,
        array $botConfig,
        Definition $updateHandlerService,
        ContainerBuilder $container,
    ): void {
        foreach ($botConfig['handlers'] ?? [] as $index => $handlerConfig) {
            $checkerService = $this->createSubService(
                'checkers',
                $botServiceId,
                $index,
                $handlerConfig['checker'],
                $container,
            );
            $handlerService = $this->createSubService('handlers', $botServiceId, $index, $handlerConfig['handler'], $container);

            $middlewareServices = [];
            foreach ($handlerConfig['middlewares'] ?? [] as $mi => $middlewareConfig) {
                $middlewareServices[] = $this->createSubService(
                    'middlewares',
                    $botServiceId,
                    "{$index}_$mi",
                    $middlewareConfig,
                    $container,
                );
            }

            $handlerGroupServiceId = "$botServiceId.handler_groups.$index";
            $container
                ->register($handlerGroupServiceId, HandlerGroup::class)
                ->setArguments([
                    $checkerService,
                    $handlerService,
                    $middlewareServices,
                    $handlerConfig['priority'] ?? 0,
                ]);

            $updateHandlerService->addMethodCall('addHandlerGroup', [new Reference($handlerGroupServiceId)]);
        }
    }

    private function createSubService(
        string $type,
        string $botServiceId,
        int|string $index,
        array $config,
        ContainerBuilder $container,
    ): Reference {
        $class = $config['class'];
        if (str_starts_with($class, '@')) {
            if ($container->has(substr($class, 1))) {
                return new Reference(substr($class, 1));
            } else {
                throw new InvalidArgumentException(
                    sprintf('Cannot find service "%s"', $class),
                );
            }
        }

        if ($container->has($config['class'])) {
            return new Reference($config['class']);
        }

        $serviceId = "$botServiceId.$type.$index";
        $container
            ->register($serviceId, $config['class'])
            ->setArguments($config['arguments'])
            ->setPublic(false)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        return new Reference($serviceId);
    }
}
