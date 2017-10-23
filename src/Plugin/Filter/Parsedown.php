<?php

namespace MakinaCorpus\Lannion\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Uses parsedown to generate HTML from Markdown
 *
 * @Filter(
 *   id = "parsedown",
 *   title = @Translation("Markdown to HTML"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {},
 *   weight = -20
 * )
 */
class Parsedown extends FilterBase
{
    /**
     * {@inheritdoc}
     */
    public function process($text, $langcode)
    {
        return new FilterProcessResult(
            (new \Parsedown())
                ->text($text)
        );
    }
}
