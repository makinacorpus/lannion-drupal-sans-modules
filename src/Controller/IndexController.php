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
}