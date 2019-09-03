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
   * @param $application_uri Base URI of reste API
   * @param $vocabularyUri URI of the vocabulary (conceptScheme) to load
   * @param StateInterface $state
   *
   * @return bool
   */
  public function fetchVocabularyFromSkosmos($application_uri, $vocabularyUri, $max, StateInterface $state, FeedInterface $feed, $cacheKey) {
    if (!$this->fetchVocabularyRoot($application_uri, $vocabularyUri, $state)) {
      return FALSE;
    }
    $scheme = $this->getGraph()->allOfType('skos:ConceptScheme')[0];
    $topConcepts = $scheme->all('skos:hasTopConcept');
    //$state->total = self::PROGRESS_TOTAL;
    //$state->progress(self::PROGRESS_TOTAL, 0);
    return $this->fetchConceptTreeRecursively($application_uri, $topConcepts, $max, $cacheKey, [], $state, $feed, self::PROGRESS_TOTAL);
  }


  /**
   * @param $application_uri
   * @param $vocabulary
   * @param StateInterface $state
   *
   * @return bool
   */
  private function fetchVocabularyRoot($application_uri, $vocabulary, StateInterface $state) {
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

  private function fetchConceptTreeRecursively($application_uri, array $resources, $max, $cacheKey, array $buffer, StateInterface $state, FeedInterface $feed, $maxProgress) {
    if (count($resources) == 0) {
      return TRUE;
    }
    $newConceptFound = FALSE;
    $progressShare = (float) ($maxProgress / count($resources));
    $counter = 0;
    foreach ($resources as $resource) {
      error_log($resource->getUri());
      if (in_array($resource->getUri(), $buffer)) {
        continue;
      }
      if ($this->isInCache($resource->getUri(), $cacheKey)) {
        //continue;
      }

      $buffer[] = $resource->getUri();
      if (count($buffer) >= $max) {
        //return TRUE;
      }
      $args = ['%uri' => $resource->getUri()];
      $state->setMessage($this->t('Loading concept "%uri"', $args), 'status');
      $state->logMessages($feed);
      if (!$this->fetchVocabularyRoot($application_uri, $resource->getUri(), $state)) {
        return FALSE;
      }
      $newConceptFound = TRUE;
      //$state->progress(self::PROGRESS_TOTAL, $counter * $progressShare);
      $counter++;
      $this->setInCache($resource->getUri(), $cacheKey);
    }
    if (TRUE === $newConceptFound) {
      $children = $resource->all('skos:narrower');

      if (!$this->fetchConceptTreeRecursively($application_uri, $children, $max, $cacheKey, $buffer, $state, $feed, $counter * $progressShare)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function isInCache($uri, $cacheKey) {
    $cachedUris = $this->getCachedUris($cacheKey);
    return is_array($cachedUris) && in_array($uri, $cachedUris);
  }


  private function setInCache($uri, $cacheKey) {
    $cachedUris = $this->getCachedUris($cacheKey);
    if (!is_array($cachedUris)) {
      $cachedUris = [$uri];
    }
    else {
      $cachedUris[] = $uri;
    }
    $urisCache = $this->cache->set($cacheKey . '_loaded_uris', $cachedUris);
  }


  /**
   * @param $cacheKey
   *
   * @return bool
   */
  private function getCachedUris($cacheKey) {
    return $cachedUris = $this->cache->get($cacheKey . '_loaded_uris')->data;
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
