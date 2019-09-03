<?php

namespace Drupal\skosmos_feeds\Feeds\Item;

use Drupal\feeds\Feeds\Item\BaseItem;

/**
 * Defines an item class to gather information from SKOS concepts.
 */
class SkosConceptItem extends BaseItem {

  /**
   * @var string
   */
  protected $prefLabel;

  /**
   * @var string
   */
  protected $scopeNote;

  /**
   * @var string
   */
  protected $definition;

  /**
   * @var string[]
   */
  protected $altLabel;

  /**
   * URI of the RDF resource
   *
   * @var string
   */
  protected $URI;

  /**
   * URI of the broader term
   *
   * @var string
   */
  protected $broader;

}
