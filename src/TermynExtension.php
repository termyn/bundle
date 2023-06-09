<?php

declare(strict_types=1);

namespace Termyn\Symfony\Bundle;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Termyn\Cqrs\CommandHandler;
use Termyn\Cqrs\Messaging\Messenger\Middleware\AckHandledCommandMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\AckSentCommandMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\ResolveHandledQueryResultMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\ValidateCommandMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\ValidateQueryMiddleware;
use Termyn\Cqrs\QueryHandler;
use Termyn\Ddd\DomainEventHandler;
use Termyn\Mesh\IntegrationEventHandler;

final class TermynExtension extends Extension implements ExtensionInterface, PrependExtensionInterface
{
    private FileLocator $fileLocator;

    private array $buses = [
        'termyn.cqrs.command_bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => false,
                'allow_no_senders' => true,
            ],
            'middleware' => [
                ValidateCommandMiddleware::class,
                AckHandledCommandMiddleware::class,
                AckSentCommandMiddleware::class,
            ],
        ],
        'termyn.cqrs.query_bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => false,
                'allow_no_senders' => true,
            ],
            'middleware' => [
                ValidateQueryMiddleware::class,
                ResolveHandledQueryResultMiddleware::class,
            ],
        ],
        'termyn.ddd.domain_event_bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => true,
                'allow_no_senders' => true,
            ],
        ],
        'termyn.mesh.integration_event_bus' => [
            'default_middleware' => [
                'enabled' => true,
                'allow_no_handlers' => true,
                'allow_no_senders' => true,
            ],
        ],
    ];

    public function __construct()
    {
        $this->fileLocator = new FileLocator(__DIR__ . '/../config');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->setUpMessengerBuses($container);
        $this->registerHandlersForAutoconfiguration($container);
    }

    public function load(
        array $configs,
        ContainerBuilder $container,
    ): void {
        $xmlLoader = new XmlFileLoader($container, $this->fileLocator);
        $xmlLoader->load('services.xml');
    }

    private function setUpMessengerBuses(ContainerBuilder $container): void
    {
        $configs = $this->processConfiguration(
            configuration: new Configuration(false),
            configs: $container->getExtensionConfig('framework')
        )['messenger'] ?? [];

        foreach ($this->buses as $name => $config) {
            $configs['buses'][$name] = [
                'default_middleware' => $config['default_middleware'],
                'middleware' => array_merge(
                    $configs['buses'][$name]['middleware'] ?? [],
                    $config['middleware'] ?? [],
                ),
            ];
        }

        foreach ($configs['buses'] as $name => $bus) {
            $configs['default_bus'] = $configs['default_bus'] ?? $name;
            $configs['buses'][$name] = array_filter($configs['buses'][$name], fn (array $config): bool => count($config) > 0);
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => $configs,
        ]);
    }

    private function registerHandlersForAutoconfiguration(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('termyn.cqrs.command_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.cqrs.command_bus',
            ]);

        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('termyn.cqrs.query_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.cqrs.query_bus',
            ]);

        $container->registerForAutoconfiguration(DomainEventHandler::class)
            ->addTag('termyn.ddd.domain_event_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.ddd.domain_event_bus',
            ]);

        $container->registerForAutoconfiguration(IntegrationEventHandler::class)
            ->addTag('termyn.mesh.integration_event_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.mesh.integration_event_bus',
            ]);
    }
}
