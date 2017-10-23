<?php

namespace MakinaCorpus\Lannion\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "latest_article",
 *   admin_label = @Translation("Latest article block"),
 *   category = @Translation("Articles"),
 * )
 */
class LatestArticleBlock extends BlockBase implements ContainerFactoryPluginInterface
{
    private $entityManager;

    /**
     * Constructs a new SystemMenuBlock.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity.manager'));
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $storage = $this->entityManager->getStorage('node');

        $idList = $storage
            ->getQuery()
            ->condition('status', 1)
            ->sort('created', 'desc')
            ->range(0, 1)
            ->addTag('node_access')
            ->execute()
        ;

        return [
            '#type'     => 'twig_template',
            '#template' => '@lannion/block/latest-article.html.twig',
            '#context'  => [
                'nodes' => $this
                    ->entityManager
                    ->getViewBuilder('node')
                    ->viewMultiple(
                        $storage->loadMultiple($idList),
                        'sidebar_block'
                    )
                ]
            ]
        ;
    }
}
