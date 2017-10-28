<?php

namespace MakinaCorpus\Lannion\Tests\Controller;

use Drupal\Tests\BrowserTestBase;

class IndexControllerTest extends BrowserTestBase
{
    public function testMaRouteCustom()
    {
        $this->drupalGet('my-first-route');
        $this->assertSession()->titleEquals("Ma première route");
        $this->assertSession()->responseContains("Ce site est désespérement vide");
    }
}
