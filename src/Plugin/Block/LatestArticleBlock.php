<?php

namespace MakinaCorpus\Lannion\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 *   id = "latest_article",
 *   admin_label = @Translation("Latest article block"),
 *   category = @Translation("Articles"),
 * )
 */
class LatestArticleBlock extends BlockBase
{
    /**
     * {@inheritdoc}
     */
    public function build()
    {
        return [
            '#markup' => $this->t('Hello, World!'),
        ];
    }
}
