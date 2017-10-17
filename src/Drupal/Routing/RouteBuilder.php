<?php

namespace MakinaCorpus\Lannion\Drupal\Routing;

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Routing\RouteBuilder as BaseRouteBuilder;
use MakinaCorpus\Lannion\Lannion;

/**
 * We have no other choices than overriding it, decorating it would have been
 * better but we can't alter its internals otherwise.
 */
class RouteBuilder extends BaseRouteBuilder
{
    /**
     * Retrieves all defined routes from .routing.yml files.
     *
     * @return array
     *   The defined routes, keyed by provider.
     */
    protected function getRouteDefinitions()
    {
        // Always instantiate a new YamlDiscovery object so that we always search on
        // the up-to-date list of modules.
        $discovery = new YamlDiscovery('routing', $this->moduleHandler->getModuleDirectories() + ['lannion' => Lannion::getProjectRoot().'/app/config']);
        return $discovery->findAll();
    }
}
