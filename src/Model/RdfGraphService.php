<?php

namespace Drupal\skosmos_feeds\Model;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\StateInterface;
use Drupal\Tests\Core\Plugin\ObjectDefinition;
use EasyRdf_Serialiser_Arc;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;


class RdfGraphService {

  use StringTranslationTrait;
  use Drupal\skosmos_feeds\Utils\Cache\UriCachingAbilityTrait;

  const PROGRESS_TOTAL = 1000;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * @var \EasyRdf_Graph $graph
   */
  protected $graph;

  /**
   * RedirectService constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param CacheBackendInterface $cache
   */
  public function __construct(TranslationInterface $stringTranslation, CacheBackendInterface $cache) {
    $this->stringTranslation = $stringTranslation;
    $this->cache = $cache;
    $this->initializeGraph();
  }

  protected function initializeGraph() {
    if (!isset($this->graph)) {
      $this->graph = new \EasyRdf_Graph();
    }
  }

  /**
   * @return \EasyRdf_Graph
   */
  public function getGraph() {
    return $this->graph;
  }

  /**
   * @param string $application_uri Base URI of reste API
   * @param string $vocabularyUri URI of the vocabulary (conceptScheme) to load
   * @param boolean $incrementalFetch
   * @param integer $maxNumberOfLeafConcepts
   * @param \Drupal\feeds\StateInterface $state
   * @param \Drupal\feeds\FeedInterface $feed
   * @param $cacheKey
   *
   * @return bool
   */
  public function fetchVocabularyFromSkosmos($application_uri, $vocabularyUri, $incrementalFetch, $maxNumberOfLeafConcepts, StateInterface $state, FeedInterface $feed, $cacheKey) {
    if (!$this->fetchResourceURIFromSkosmos($application_uri, $vocabularyUri, $state)) {
      return FALSE;
    }
    $scheme = $this->getGraph()->allOfType('skos:ConceptScheme')[0];
    $topConcepts = $scheme->all('skos:hasTopConcept');
    //$state->total = self::PROGRESS_TOTAL;
    //$state->progress(self::PROGRESS_TOTAL, 0);
    $fetchedLeafConceptUriBuffer = [];
    $fetchedUribuffer = [];
    $success = $this->fetchConceptTreeRecursively($application_uri, $topConcepts, $incrementalFetch, $fetchedLeafConceptUriBuffer, $maxNumberOfLeafConcepts, $cacheKey, $fetchedUribuffer, $state, $feed, self::PROGRESS_TOTAL);
    if ($success) {
      return $fetchedUribuffer;
    }
    else {
      return FALSE;
    }
  }


  /**
   * @param $application_uri
   * @param $vocabulary
   * @param StateInterface $state
   *
   * @return bool
   */
  private function fetchResourceURIFromSkosmos($application_uri, $vocabulary, StateInterface $state) {
    $completeUri = "$application_uri?uri=$vocabulary&format=text/turtle";
    try {
      $this->graph->load($completeUri, 'text/turtle');
    } catch (\EasyRdf_Http_Exception $e) {
      $args = [
        '%uri' => $application_uri,
        '%resource' => $vocabulary,
        '%error' => $e->getMessage(),
      ];
      $state->setMessage($this->t('Http error while loading data from "%uri" for resource : "%resource" with message: "%error"', $args), 'error');
      return FALSE;
    } catch (\EasyRdf_Exception $e) {
      $args = [
        '%uri' => $application_uri,
        '%resource' => $vocabulary,
        '%error' => $e->getMessage(),
      ];
      $state->setMessage($this->t('Unknown error while loading data from "%uri" for resource : "%resource" with message: "%error"', $args), 'error');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param StateInterface $state
   *
   * @return bool|string
   */
  public function serialize(StateInterface $state) {
    $path = __DIR__ . '/../..';
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);
    \EasyRdf_Format::registerSerialiser('ntriples', 'EasyRdf_Serialiser_Arc');
    try {
      $data = $this->graph->serialise('ntriples');
    } catch (\Exception $e) {
      $args = ['%error' => $e->getMessage()];
      $state->setMessage($this->t('Error while serialising data to turtle with message: "%error"', $args), 'error');
      return FALSE;
    }
    return $data;
  }

  public function findConcepts($filepath, StateInterface $state) {
    if (!is_file($filepath) || !is_readable($filepath)) {
      throw new \InvalidArgumentException("\$filepath must exist and be readable.");
    }
    $data = file_get_contents($filepath);
    $this->graph->parse($data, 'application/n-triples');
    $conceptSchemes = $this->graph->allOfType('skos:ConceptScheme');
    if (count($conceptSchemes) < 1) {
      $args = ['%file' => $filepath];
      $state->setMessage($this->t('Unable to find ConceptScheme in generated skos file : "%file"', $args), 'error');
      return FALSE;
    }
    else {
      $conceptScheme = $conceptSchemes[0];
    }
    $tops = $this->graph->allResources($conceptScheme->getUri(), '^skos:topConceptOf');
    $children = [];
    foreach ($tops as $top) {
      $children = array_merge($children, $this->findNarrowerRecursively($top));
    }
    return array_merge($tops, $children);
  }

  /**
   * @param string $application_uri
   * @param array $resources
   * @param boolean $incrementalFetch
   * @param array $fetchedLeafConceptUriBuffer
   * @param integer $maxNumberOfLeafConcepts
   * @param string $cacheKey
   * @param array $fetchedUribuffer
   * @param \Drupal\feeds\StateInterface $state
   * @param \Drupal\feeds\FeedInterface $feed
   * @param $maxProgress
   *
   * @return bool
   */
  private function fetchConceptTreeRecursively($application_uri, array $resources, $incrementalFetch, array &$fetchedLeafConceptUriBuffer, $maxNumberOfLeafConcepts, $cacheKey, array &$fetchedUribuffer, StateInterface $state, FeedInterface $feed, $maxProgress) {
    if (count($resources) == 0) {
      return TRUE;
    }
    if (count($fetchedLeafConceptUriBuffer) >= $maxNumberOfLeafConcepts) {
      \Drupal::logger("skosmos_feeds")
        ->debug("Max number of leaf concepts reached !");
      // When the max number of leaf concepts is reached, we don't load concepts any more
      return TRUE;
    }
    $newConceptLoaded = FALSE;
    $progressShare = (float) ($maxProgress / count($resources));
    $counter = 0;
    foreach ($resources as $resource) {
      \Drupal::logger("skosmos_feeds")
        ->debug("Resource to fetch : {$resource->getUri()}");
      if (count($fetchedLeafConceptUriBuffer) >= $maxNumberOfLeafConcepts) {
        \Drupal::logger("skosmos_feeds")
          ->debug("Max number of leaf concepts reached : " . count($fetchedLeafConceptUriBuffer));
        // When the max number of leaf concepts is reached, we don't load concepts any more
        return TRUE;
      }
      // don't fetch the same URI twice
      if (in_array($resource->getUri(), $fetchedUribuffer)) {
        \Drupal::logger("skosmos_feeds")
          ->debug("Resource already fetched : {$resource->getUri()}");
        continue;
      }
      // If it's in cache, it's a leaf concept. In incremental mode, we dont take it
      if ($incrementalFetch && $this->isInCache($resource->getUri(), $cacheKey)) {
        \Drupal::logger("skosmos_feeds")
          ->debug("Resource is in cache : {$resource->getUri()}");
        continue;
      }

      $args = ['%uri' => $resource->getUri()];
      $state->setMessage($this->t('Loading concept "%uri"', $args), 'status');
      $state->logMessages($feed);
      if (!$this->fetchResourceURIFromSkosmos($application_uri, $resource->getUri(), $state)) {
        return FALSE;
      }
      \Drupal::logger("skosmos_feeds")
        ->debug("* New resource loaded : {$resource->getUri()}");
      // add URI to the buffer
      $fetchedUribuffer[] = $resource->getUri();
      \Drupal::logger("skosmos_feeds")
        ->debug("Number of newly fetched concepts : " . count($fetchedUribuffer));
      //$state->progress(self::PROGRESS_TOTAL, $counter * $progressShare);
      // $counter++;
      $children = $resource->all('skos:narrower');
      if (count($children) == 0) {
        // It's a leaf concept
        // Put it in cache for next execution
        \Drupal::logger("skosmos_feeds")
          ->debug($resource->getUri() . " is a leaf concept.");
        $this->setInCache($resource->getUri(), $cacheKey);
        // Put it in buffer to count number of leaf concepts fetched
        $fetchedLeafConceptUriBuffer[] = $resource->getUri();
        \Drupal::logger("skosmos_feeds")
          ->debug("Number of leaf concepts : " . count($fetchedLeafConceptUriBuffer));
      }
      else {
        $success = $this->fetchConceptTreeRecursively($application_uri, $children, $incrementalFetch, $fetchedLeafConceptUriBuffer, $maxNumberOfLeafConcepts, $cacheKey, $fetchedUribuffer, $state, $feed, $counter * $progressShare);
        if (!$success) {
          return FALSE;
        }
        // It's not a leaf concept, but the branch has been fully explored
        $branchFullyExplored = count($fetchedLeafConceptUriBuffer) < $maxNumberOfLeafConcepts;
        \Drupal::logger("skosmos_feeds")
          ->debug("Traversal of branch " . $resource->getUri() . " completed.");
        // Don't traverse it next time
        if ($incrementalFetch && $branchFullyExplored) {
          $this->setInCache($resource->getUri(), $cacheKey);
        }
      }

    }
    return TRUE;
  }

  /**
   * @param \EasyRdf_Resource $concept
   *
   * @return array
   */
  private function findNarrowerRecursively(\EasyRdf_Resource $concept) {
    $transitiveNarrowerConcept = $narrowerConcepts = $this->graph->allResources($concept->getUri(), 'skos:narrower');
    foreach ($narrowerConcepts as $child) {
      $transitiveNarrowerConcept = array_merge($transitiveNarrowerConcept, $this->findNarrowerRecursively($child));
    }
    return $transitiveNarrowerConcept;
  }

}
