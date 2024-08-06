<?php declare(strict_types = 1);

namespace Vairogs\Component\Audit\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Vairogs\Bundle\DependencyInjection\AbstractDependencyConfiguration;

final class AuditConfiguration extends AbstractDependencyConfiguration
{
    public function usesDoctrine(): bool
    {
        return true;
    }

    public function registerPreConfiguration(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
        string $component,
    ): void {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', [
                'dbal' => [
                    'schema_filter' => '~^(?!vairogs\.audit_)~',
                ],
            ]);
        }
    }
}
