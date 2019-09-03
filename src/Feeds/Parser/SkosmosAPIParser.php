<?php

namespace Drupal\skosmos_feeds\Feeds\Parser;

use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Parser\ParserBase;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;
use Drupal\skosmos_feeds\Feeds\Item\SkosConceptItem;

/**
 * Defines a CSV feed parser.
 *
 * @FeedsParser(
 *   id = "skosmos_api",
 *   title = "Skos RDF",
 *   description = @Translation("Parses RDF data from Skosmos API."),
 *   form = {
 *     "configuration" =
 *   "Drupal\skosmos_feeds\Feeds\Parser\Form\SkosmosAPIParserForm",
 *     "feed" =
 *   "Drupal\skosmos_feeds\Feeds\Parser\Form\SkosmosAPIParserFeedForm",
 *   },
 *   arguments = {"@skosmos_feeds.rdf_graph_service"}
 * )
 */
class SkosmosAPIParser extends ParserBase {

  /**
   * SkosmosAPIParser constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService) {
    $this->rdfGraphService = $rdfGraphService;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    $feed_config = $feed->getConfigurationFor($this);

    if (!filesize($fetcher_result->getFilePath())) {
      throw new EmptyFeedException();
    }

    // Load and configure parser.
    $concepts = $this->rdfGraphService->findConcepts($fetcher_result->getFilePath(), $state);
    if (FALSE === $concepts) {
      throw new EmptyFeedException();
    }
    $result = new ParserResult();
    $state->total = count($concepts);
    $counter = 0;
    /**
     * @var \EasyRdf_Resource $concept
     */
    foreach ($concepts as $concept) {
      // Report progress.
      $counter += 1;
      //      $state->pointer = $counter;
      //      $state->progress($state->total, $state->pointer);
      $item = new SkosConceptItem();

      $this->registerUri($concept, $item);
      $this->registerBroader($concept, $item);

      $this->registerPredicateAsSingleLitteral('prefLabel', $concept, $item);
      $this->registerPredicateAsSingleLitteral('scopeNote', $concept, $item);
      $this->registerPredicateAsSingleLitteral('definition', $concept, $item);
      $this->registerPredicateAsMultipleLitteral('altLabel', $concept, $item);

      $result->addItem($item);
      $state->setMessage("Parsed SKOS concept : {$prefLabel}");
      $state->logMessages($feed);
    }


    //    $state->setCompleted();


    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingSources() {
    return [
      'prefLabel' => [
        'label' => $this->t('skos:prefLabel'),
        'description' => $this->t('The preferred lexical label for a resource, in a given language. '),
      ],
      'altLabel' => [
        'label' => $this->t('skos:altLabel'),
        'description' => $this->t('An alternative lexical label for a resource.'),
      ],
      'definition' => [
        'label' => $this->t('skos:definition'),
        'description' => $this->t('A statement or formal explanation of the meaning of a concept.'),
      ],
      'scopeNote' => [
        'label' => $this->t('skos:scopeNote'),
        'description' => $this->t('Note that helps to clarify the meaning and/or the use of the concept.'),
      ],
      'URI' => [
        'label' => $this->t('Resource URI'),
        'description' => $this->t('URI of the RDF resource.'),
      ],
      'broader' => [
        'label' => $this->t('skos:broader'),
        'description' => $this->t('Broader concept.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function configSourceLabel() {
    return $this->t('Skos Concept source');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultFeedConfiguration() {
    return [
      'keep_structure' => $this->configuration['keep_structure'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'keep_structure' => TRUE,
    ];
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
