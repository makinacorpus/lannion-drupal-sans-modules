<?php

namespace MakinaCorpus\Lannion\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides an example element.
 *
 * @todo
 *   document me
 *   add twig namespace for the project root (app/templates)
 *   add twig namespaces for controllers maybe ?
 *
 * @RenderElement("twig_template")
 */
class TwigTemplate extends RenderElement
{
    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        return [
            '#pre_render' => [[TwigTemplate::class, 'doPostRender']],
            '#template'   => null,
            '#context'    => [],
        ];
    }

    /**
     * Prepare the render array for the template.
     */
    static public function doPostRender($element)
    {
        /** @var \Twig_Environment $twig */
        $twig = \Drupal::service('twig');

        if (empty($element['#template'])) {
            $element['#template'] = '';

            return $element;
        }

        $element['#markup'] = $twig->render($element['#template'], $element['#context'] ?? []);

        return $element;
    }
}
