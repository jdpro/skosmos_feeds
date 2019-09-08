<?php

namespace Drupal\Tests\skosmos_feeds\Unit {


  use Drupal\Tests\UnitTestCase;

  /**
   * Base class for Feeds unit tests.
   */
  abstract class SkosmosFeedsUnitTestCase extends UnitTestCase {


    /**
     * Returns the absolute directory path of the Feeds module.
     *
     * @return string
     *   The absolute path to the Feeds module.
     */
    protected function absolutePath() {
      return dirname(dirname(dirname(__DIR__)));
    }

    /**
     * Returns the absolute directory path of the resources folder.
     *
     * @return string
     *   The absolute path to the resources folder.
     */
    protected function resourcesPath() {
      return $this->absolutePath() . '/tests/resources';
    }
  }
}