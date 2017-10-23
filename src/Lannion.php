<?php

namespace MakinaCorpus\Lannion;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class Lannion extends ServiceProviderBase
{
    /**
     * This should be in the kernel, but Drupal hey...
     */
    static public function getProjectRoot(): string
    {
        return dirname(__DIR__);
    }

    /**
     * {@inheritdoc}
     */
    public function register(ContainerBuilder $container)
    {
        $this->registerProjectNamespace($container);
        $this->registerTwigNamespaces($container);
    }

    /**
     * Utilisation des plugins et autres joyeusetés sans module!
     */
    private function registerProjectNamespace(ContainerBuilder $container)
    {
        $namespaces = $container->getParameter('container.namespaces');
        $namespaces['MakinaCorpus\\Lannion'] = self::getProjectRoot().'/src';
        $container->setParameter('container.namespaces', $namespaces);
    }

    /**
     * Enregistre des namespace twig pour notre project.
     */
    private function registerTwigNamespaces(ContainerBuilder $container)
    {
        $id = 'twig.loader.filesystem';
        if ($container->hasAlias($id)) {
            $id = (string)$container->getAlias($id);
        }
        if ($container->hasDefinition($id)) {
            $definition = $container->getDefinition($id);
            $definition->addMethodCall('addPath', [dirname(__DIR__) . '/app/templates', 'lannion']);
        }
    }
}
