<?php

declare(strict_types=1);

namespace Termyn\Symfony\Bundle\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Termyn\Cqrs\CommandHandler;
use Termyn\Cqrs\Messaging\CommandBus;
use Termyn\Cqrs\Messaging\QueryBus;
use Termyn\Cqrs\QueryHandler;
use Termyn\Ddd\DomainEventHandler;
use Termyn\EventHandler;
use Termyn\Messaging\EventBus;
use Termyn\Symfony\Bundle\TermynExtension;

final class TermynExtensionTest extends TestCase
{
    private TermynExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new TermynExtension();
        $this->container = new ContainerBuilder();

        parent::setUp();
    }

    #[Test]
    public function shouldExistBusConfigs(): void
    {
        $this->extension->prepend($this->container);

        $messengerConfigs = $this->resolveMessengerConfig();

        $this->assertArrayHasKey('default_bus', $messengerConfigs);
        $this->assertArrayHasKey('buses', $messengerConfigs);
    }

    #[Test]
    #[DataProvider('provideBusIds')]
    public function shouldBeRegisterExpectedBuses(string $busId): void
    {
        $this->extension->prepend($this->container);

        $this->assertArrayHasKey($busId, $this->resolveMessengerConfig()['buses']);
    }

    public static function provideBusIds(): array
    {
        return [
            'command-bus' => [
                'busId' => 'termyn.command_bus',
            ],
            'query-bus' => [
                'busId' => 'termyn.query_bus',
            ],
            'event-bus' => [
                'busId' => 'termyn.event_bus',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideHandlerSettings')]
    public function shouldBeAutoconfigureHandlers(string $id): void
    {
        $this->extension->prepend($this->container);

        $this->assertArrayHasKey($id, $this->container->getAutoconfiguredInstanceof());
    }

    #[Test]
    #[DataProvider('provideHandlerSettings')]
    public function shouldHasTags(string $id, array $tags): void
    {
        $this->extension->prepend($this->container);

        $autoconfiguration = $this->container->getAutoconfiguredInstanceof()[$id];

        foreach ($tags as $tag => $configs) {
            $this->assertArrayHasKey($tag, $autoconfiguration->getTags());
            $this->assertContainsEquals($configs, $autoconfiguration->getTags()[$tag]);
        }
    }

    public static function provideHandlerSettings(): array
    {
        return [
            'command-handler' => [
                'id' => CommandHandler::class,
                'tags' => [
                    'termyn.command_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.command_bus',
                    ],
                ],
            ],
            'query-handler' => [
                'id' => QueryHandler::class,
                'tags' => [
                    'termyn.query_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.query_bus',
                    ],
                ],
            ],
            'event-handler' => [
                'id' => EventHandler::class,
                'tags' => [
                    'termyn.event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.event_bus',
                    ],
                ],
            ],
            'domain-event-handler' => [
                'id' => DomainEventHandler::class,
                'tags' => [
                    'termyn.domain_event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.event_bus',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideServiceIds
     */
    #[Test]
    #[DataProvider('provideServiceIds')]
    public function shouldBeRegisterExpectedServices(string $serviceId): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition($serviceId));
    }

    public static function provideServiceIds(): array
    {
        return [
            'command-bus' => [
                'serviceId' => CommandBus::class,
            ],
            'query-bus' => [
                'serviceId' => QueryBus::class,
            ],
            'event-bus' => [
                'serviceId' => EventBus::class,
            ],
        ];
    }

    private function resolveMessengerConfig(): array
    {
        return $this->resolveFrameworkConfig()['messenger'];
    }

    private function resolveFrameworkConfig(): array
    {
        return $this->container->getExtensionConfig('framework')[0];
    }
}
