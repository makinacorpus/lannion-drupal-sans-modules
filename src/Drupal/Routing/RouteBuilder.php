<?php

namespace MakinaCorpus\Lannion\Drupal\Routing;

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Routing\RouteBuilder as BaseRouteBuilder;
use MakinaCorpus\Lannion\Lannion;

/**
 * Also finds app/config/app.routing.yml
 *
 * We have no other choices than overriding it, decorating it would have been
 * better but we can't alter its internals otherwise.
 */
class RouteBuilder extends BaseRouteBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function getRouteDefinitions()
    {
        // Always instantiate a new YamlDiscovery object so that we always search on
        // the up-to-date list of modules.
        return (new YamlDiscovery('routing', $this->moduleHandler->getModuleDirectories() + ['app' => Lannion::getProjectRoot().'/app/config']))->findAll();
    }
}
