<?php

namespace Drupal\skosmos_feeds\Feeds\Parser;

use Drupal\Core\Cache\CacheBackendInterface;
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
 *   arguments = {"@skosmos_feeds.rdf_graph_service", "@cache.feeds_download"}
 * )
 */
class SkosmosAPIParser extends ParserBase {

  use Drupal\skosmos_feeds\Utils\Cache\UriCachingAbilityTrait;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * SkosmosAPIParser constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService, CacheBackendInterface $cache) {
    $this->rdfGraphService = $rdfGraphService;
    $this->cache = $cache;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    $feed_config = $feed->getConfigurationFor($this);
    $incrementalFetch = FALSE;
    // Get value of "incremental fetch" option
    if (isset($feed->get('config')->incremental_fetch)) {
      $incrementalFetch = $feed->get('config')->incremental_fetch;
    }
    // In case of "incremental fetch", a list of uris to parse is available in feed configuration
    // only newly fetched uris should be parsed
    $conceptsToFetchUris = [];
    if ($incrementalFetch) {
      $conceptsToFetchUris = $feed->get('config')->uris_to_parse;
    }
    // Rdf file could be empty or missing
    if (!filesize($fetcher_result->getFilePath())) {
      throw new EmptyFeedException();
    }
    // Extract skos concepts from Rdf file.
    $concepts = $this->rdfGraphService->findConcepts($fetcher_result->getFilePath(), $state);
    if (FALSE === $concepts) {
      throw new EmptyFeedException();
    }
    $result = new ParserResult();
    $state->total = count($concepts);
    $counter = 0;
    if ($incrementalFetch) {
      // Keep only newly fetched concepts
      $concepts = array_filter($concepts, function (\EasyRdf_Resource $concept) use ($conceptsToFetchUris) {
        return in_array($concept->getUri(), $conceptsToFetchUris) ? TRUE : FALSE;
      });
    }
    $cacheKey = $this->getCacheKey($feed);

    $listOfPrefLabelsForLogs = [];

    /**
     * @var \EasyRdf_Resource $concept
     */
    foreach ($concepts as $concept) {
      if ($incrementalFetch) {
        if ($this->isInCache($concept->getUri(), $cacheKey)) {
          continue;
        }
        else {
          $this->setInCache($concept->getUri(), $cacheKey);
        }
      }
      $counter += 1;
      //      $state->pointer = $counter;
      //      $state->progress($state->total, $state->pointer);
      $item = new SkosConceptItem();

      $this->registerUri($concept, $item);
      $this->registerBroader($concept, $item);
      // TODO handle missing preflabel. Should not happen with regular skos.
      $this->registerPredicateAsSingleLitteral('prefLabel', $concept, $item);
      $this->registerPredicateAsSingleLitteral('scopeNote', $concept, $item);
      $this->registerPredicateAsSingleLitteral('definition', $concept, $item);
      $this->registerPredicateAsMultipleLitteral('altLabel', $concept, $item);

      $result->addItem($item);
      $message = "Parsed SKOS concept : {$item->get('prefLabel')} ({$item->get('URI')})";
      \Drupal::logger("skosmos_feeds")->debug($message);

      $listOfPrefLabelsForLogs[] = $item->get('prefLabel');
    }

    $message = "Parsed {$counter} concepts : " . implode(",", $listOfPrefLabelsForLogs);

    $state->setMessage($message);
    $state->logMessages($feed);

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

  /**
   * Returns the fetcher cache key for a given feed.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed to find the cache key for.
   *
   * @return string
   *   The cache key for the feed.
   */
  protected function getCacheKey(FeedInterface $feed) {
    return $feed->id() . ':parser:' . hash('sha256', $feed->getSource());
  }

}
