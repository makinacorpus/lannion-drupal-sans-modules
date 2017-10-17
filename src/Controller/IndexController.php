<?php

namespace MakinaCorpus\Lannion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends ControllerBase
{
    public function myFirstRoute(Request $request)
    {
        return [
            '#markup' => "<p>Dites bonjour à une route sans module&nbsp;!</p>"
        ];
    }

    public function contenuRecent(Request $request)
    {
        $limit = 10;
        $storage = $this->entityManager()->getStorage('node');

        $idList = $storage
            ->getQuery()
            ->condition('status', 1)
            ->sort('created', 'desc')
            ->pager($limit)
            ->addTag('node_access')
            ->execute()
        ;

        if (!$idList) {
            return ['#markup' => '<p>Ce site est désespérement vide&nbsp;!</p>'];
        }

        return $this
            ->entityManager()
            ->getViewBuilder('node')
            ->viewMultiple(
                $storage->loadMultiple($idList),
                'teaser'
            )
        ;
    }
}
