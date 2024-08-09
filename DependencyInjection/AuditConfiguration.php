<?php declare(strict_types = 1);

/*
 * This file is part of the Vairogs package.
 *
 * (c) Dāvis Zālītis (k0d3r1s) <davis@vairogs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
