<?php

namespace MakinaCorpus\Lannion\Drupal\Menu;

use Drupal\Core\Menu\MenuLinkManager as BaseMenuLinkManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use MakinaCorpus\Lannion\Lannion;

/**
* Also finds app/config/app.links.menu.yml
 */
class MenuLinkManager extends BaseMenuLinkManager
{
    /**
     * {@inheritdoc}
     */
    protected function getDiscovery()
    {
        if (!isset($this->discovery)) {
            $yaml_discovery = new YamlDiscovery('links.menu', $this->moduleHandler->getModuleDirectories() + ['app' => Lannion::getProjectRoot().'/app/config']);
            $yaml_discovery->addTranslatableProperty('title', 'title_context');
            $yaml_discovery->addTranslatableProperty('description', 'description_context');
            $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
        }

        return $this->discovery;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinitions()
    {
        $definitions = $this->getDiscovery()->getDefinitions();

        $this->moduleHandler->alter('menu_links_discovered', $definitions);

        foreach ($definitions as $plugin_id => &$definition) {
            $definition['id'] = $plugin_id;
            $this->processDefinition($definition, $plugin_id);
        }

        // If this plugin was provided by a module that does not exist, remove the
        // plugin definition.
        // @todo Address what to do with an invalid plugin.
        //   https://www.drupal.org/node/2302623
        foreach ($definitions as $plugin_id => $plugin_definition) {
            if (!empty($plugin_definition['provider']) && 'app' !== $plugin_definition['provider'] && !$this->moduleHandler->moduleExists($plugin_definition['provider'])) {
                unset($definitions[$plugin_id]);
            }
        }

        return $definitions;
    }
}
