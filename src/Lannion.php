<?php

namespace MakinaCorpus\Lannion;

class Lannion
{
    /**
     * This should be in the kernel, but Drupal hey...
     */
    static public function getProjectRoot(): string
    {
        return dirname(__DIR__);
    }
}
