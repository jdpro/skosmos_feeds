<?php

namespace Drupal\skosmos_feeds\Feeds\Fetcher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\ClearableInterface;
use Drupal\feeds\Plugin\Type\Fetcher\FetcherInterface;
use Drupal\feeds\Plugin\Type\PluginBase;
use Drupal\feeds\Result\FetcherResult;
use Drupal\feeds\Result\HttpFetcherResult;
use Drupal\feeds\StateInterface;
use Drupal\feeds\Utility\Feed;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines an HTTP fetcher.
 *
 * @FeedsFetcher(
 *   id = "skosmos_api",
 *   title = @Translation("Skosmos API"),
 *   description = @Translation("xxx."),
 *   form = {
 *     "configuration" =
 *   "Drupal\skosmos_feeds\Feeds\Fetcher\Form\SkosmosAPIFetcherForm",
 *     "feed" =
 *   "Drupal\skosmos_feeds\Feeds\Fetcher\Form\SkosmosAPIFetcherFeedForm",
 *   },
 *   arguments = {"@skosmos_feeds.rdf_graph_service", "@cache.feeds_download",
 *   "@file_system"}
 * )
 */
class SkosmosAPIFetcher extends PluginBase implements ClearableInterface, FetcherInterface {

  /**
   * Provider for Easy RDF Graph
   *
   * @var \Drupal\skosmos_feeds\Model\RdfGraphService
   */
  protected $rdfGraphService;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Drupal file system helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a SkosmosAPIFetcher object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService
   *   The RDF gaph service
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The Drupal file system helper.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, \Drupal\skosmos_feeds\Model\RdfGraphService $rdfGraphService, CacheBackendInterface $cache, FileSystemInterface $file_system) {
    $this->rdfGraphService = $rdfGraphService;
    $this->cache = $cache;
    $this->fileSystem = $file_system;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(FeedInterface $feed, StateInterface $state) {
    $maxNumberOfLeafConcepts = 0;
    $incrementalFetch = FALSE;
    // Get value of "incremental fetch" option
    if (isset($feed->get('config')->incremental_fetch)) {
      $incrementalFetch = $feed->get('config')->incremental_fetch;
    }
    // Get value of "max number of leaf concepts" option
    if ($incrementalFetch) {
      if (isset($feed->get('config')->max_number_of_leaf_concepts)) {
        $maxNumberOfLeafConcepts = intval($feed->get('config')->max_number_of_leaf_concepts);
      }
    }

    $cacheKey = $this->getCacheKey($feed);
    $fetchedConcepts = $this->rdfGraphService->fetchVocabularyFromSkosmos($this->getConfiguration('application_uri'), $feed->getSource(), $incrementalFetch, $maxNumberOfLeafConcepts, $state, $feed, $cacheKey);
    //TODO handle fetch failure

    if ($incrementalFetch) {
      //Store list of newly fetched concepts in feed configuration
      // as the parser should not process everything in the generated skos file
      $feed->get('config')->uris_to_parse = $fetchedConcepts;
    }
    $tempFile = $this->getTempFile();
    $data = $this->rdfGraphService->serialize($state);
    //TODO handle serialization failure
    file_put_contents($tempFile, $data);

    $state->setMessage($this->t("Fetched " . count($fetchedConcepts) . " new concept(s) from Skosmos."));
    //TODO handle write failure

    return new FetcherResult($tempFile);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'application_uri' => '',
    ];
  }

  /**
   * @return bool|false|string
   */
  private function getTempFile() {
    $tempFile = $this->fileSystem->tempnam('temporary://', 'feeds_skosmos_api_fetcher');
    return $this->fileSystem->realpath($tempFile);
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
    return $feed->id() . ':fetcher:' . hash('sha256', $feed->getSource());
  }

  /**
   * {@inheritdoc}
   */
  public function clear(FeedInterface $feed, StateInterface $state) {
    // TODO: Implement clear() method.
  }
}
