<?php

namespace AndrewGos\TelegramBotBundle\DependencyInjection;

use AndrewGos\TelegramBot\Kernel\Checker\AnyChecker;
use AndrewGos\TelegramBot\TelegramFactory;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('andrew_gos_telegram_bot');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('bots')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('factory')
                                ->isRequired()
                                ->beforeNormalization()
                                    ->always($this->normalizeFactory(...))
                                ->end()
                                ->children()
                                    ->arrayNode('method')
                                        ->beforeNormalization()
                                            ->always($this->normalizeFactoryMethod(...))
                                        ->end()
                                        ->children()
                                            ->scalarNode('class')->isRequired()->end()
                                            ->scalarNode('method')->defaultValue('__invoke')->isRequired()->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('arguments')
                                        ->useAttributeAsKey('name')
                                        ->variablePrototype()->end()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(fn($v) => $v['method']['class'] === TelegramFactory::class && empty($v['arguments']['$token']))
                                    ->thenInvalid('Argument "$token" must be set for default telegram factory')
                                ->end()
                            ->end()
                            ->append($this->getServicesNode('plugins'))
                            ->arrayNode('handlers')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->beforeNormalization()
                                        ->always(
                                            fn($v) => is_string($v)
                                                ? ['handler' => $v, 'checker' => AnyChecker::class]
                                                : ['checker' => AnyChecker::class, ...$v],
                                        )
                                    ->end()
                                    ->children()
                                        ->append($this->getServiceNode('checker')->isRequired())
                                        ->append($this->getServiceNode('handler')->isRequired())
                                        ->append($this->getServicesNode('middlewares'))
                                        ->scalarNode('priority')->defaultValue(0)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function normalizeFactory(mixed $value): mixed
    {
        if (is_string($value)) {
            return ['method' => $value, 'arguments' => []];
        } elseif (is_array($value) && array_is_list($value)) {
            return ['method' => $value, 'arguments' => []];
        } elseif (is_array($value)) {
            $method = $value['method'] ?? null;
            $arguments = $value;
            unset($arguments['method']);
            unset($arguments['arguments']);
            $arguments = array_merge($arguments, $value['arguments'] ?? []);
            return compact('method', 'arguments');
        }

        return $value;
    }

    private function normalizeFactoryMethod(mixed $value): mixed
    {
        if (mb_strtolower($value) === 'default') {
            return ['class' => TelegramFactory::class, 'method' => 'getDefaultTelegram'];
        } elseif (mb_strtolower($value) === mb_strtolower('getUpdates')) {
            return ['class' => TelegramFactory::class, 'method' => 'getGetUpdatesTelegram'];
        } elseif (is_string($value)) {
            return ['class' => $value, 'method' =>  '__invoke'];
        } else {
            if (array_is_list($value)) {
                return ['class' => $value[0], 'method' =>  $value[1] ?? '__invoke'];
            }
        }
        return $value;
    }

    private function normalizeService(mixed $value): mixed
    {
        if (is_string($value)) {
            return ['class' => $value, 'arguments' => []];
        } else {
            if (array_is_list($value)) {
                return [
                    'class' => $value[0],
                    'arguments' => $value[1] ?? [],
                ];
            }
        }
        return $value;
    }

    private function getServiceNode(?string $name = null): ArrayNodeDefinition
    {
        $builder = new NodeBuilder();
        $node = $builder->node($name, 'array');
        assert($node instanceof ArrayNodeDefinition);

        $node
            ->beforeNormalization()
                ->always($this->normalizeService(...))
            ->end()
            ->children()
                ->stringNode('class')->isRequired()->end()
                ->arrayNode('arguments')
                    ->useAttributeAsKey('name')
                    ->defaultValue([])
                    ->variablePrototype()->end()
                ->end()
            ->end()
            ->validate()
                ->always(static function (array $value): array {
                    $classOrId = $value['class'];
                    if (str_starts_with($classOrId, '@')) {
                        if (!empty($value['arguments'])) {
                            throw new InvalidArgumentException('Cannot pass "arguments" to a service reference.');
                        }
                        return ['class' => $classOrId, 'arguments' => []];
                    }

                    return [
                        'class' => $classOrId,
                        'arguments' => $value['arguments'],
                    ];
                })
            ->end();

        return $node;
    }

    private function getServicesNode(string $name): ArrayNodeDefinition
    {
        $builder = new TreeBuilder($name, 'array');
        $node = $builder->getRootNode();
        assert($node instanceof ArrayNodeDefinition);

        $node
            ->defaultValue([])
            ->arrayPrototype()
                ->beforeNormalization()
                    ->always($this->normalizeService(...))
                ->end()
                ->children()
                    ->stringNode('class')->isRequired()->end()
                    ->arrayNode('arguments')
                        ->useAttributeAsKey('name')
                        ->defaultValue([])
                        ->variablePrototype()->end()
                    ->end()
                ->end()
                ->validate()
                    ->always(static function (array $value): array {
                        $classOrId = $value['class'];
                        if (str_starts_with($classOrId, '@')) {
                            if (!empty($value['arguments'])) {
                                throw new InvalidArgumentException('Cannot pass "arguments" to a service reference.');
                            }
                            return ['class' => $classOrId, 'arguments' => []];
                        }

                        return [
                            'class' => $classOrId,
                            'arguments' => $value['arguments'],
                        ];
                    })
                ->end()
            ->end();

        return $node;
    }
}
