<?php

namespace Drupal\skosmos_feeds\Feeds\Item;

use Drupal\feeds\Feeds\Item\BaseItem;

/**
 * Defines an item class to gather information from SKOS concepts.
 */
class SkosConceptItemFactory {

  /**
   * @param \EasyRdf_Resource $concept
   *
   * @return \Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem
   */
  public function buildItem(\EasyRdf_Resource $concept) {
    $item = new SkosConceptItem();

    $this->registerUri($concept, $item);
    $this->registerBroader($concept, $item);
    // TODO handle missing preflabel. Should not happen with regular skos.
    $this->registerPredicateAsSingleLitteral('prefLabel', $concept, $item);
    $this->registerPredicateAsSingleLitteral('scopeNote', $concept, $item);
    $this->registerPredicateAsSingleLitteral('definition', $concept, $item);
    $this->registerPredicateAsMultipleLitteral('altLabel', $concept, $item);
    return $item;
  }


  /**
   * @param \EasyRdf_Resource $concept
   * @param string $predicate
   * @param \Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem $item
   */
  private function registerPredicateAsMultipleLitteral($predicate, \EasyRdf_Resource $concept, SkosConceptItem $item) {
    $objects = $concept->allLiterals('skos:' . $predicate);
    $item->set($predicate, array_map(function (\EasyRdf_Literal $literal) {
      return $literal->getValue();
    }, $objects));
  }

  /**
   * @param \EasyRdf_Resource $concept
   * @param string $predicate
   * @param \Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem $item
   */
  private function registerPredicateAsSingleLitteral($predicate, \EasyRdf_Resource $concept, SkosConceptItem $item) {
    $object = $concept->getLiteral('skos:' . $predicate);
    if ($object instanceof \EasyRdf_Literal) {
      $item->set($predicate, $object->getValue());
    }
  }

  /**
   * @param \Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem $item
   * @param \EasyRdf_Resource $concept
   */
  private function registerUri(\EasyRdf_Resource $concept, SkosConceptItem $item) {
    $item->set('URI', $concept->getUri());
  }

  /**
   * @param \EasyRdf_Resource $concept
   * @param \Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem $item
   */
  private function registerBroader(\EasyRdf_Resource $concept, SkosConceptItem $item) {
    $broader = $concept->getResource('skos:broader');
    if ($broader instanceof \EasyRdf_Resource) {
      $item->set('broader', $broader->getUri());
    }
  }


}
