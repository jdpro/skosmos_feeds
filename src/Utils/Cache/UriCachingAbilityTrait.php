<?php

namespace Drupal\skosmos_feeds\Utils\Cache;


trait UriCachingAbilityTrait {

  /**
   * @param $uri URI tto look for
   * @param $cacheKey
   *
   * @return bool URI present in cache
   */
  protected function isInCache($uri, $cacheKey) {
    $cachedUris = $this->getCachedUris($cacheKey);
    return is_array($cachedUris) && in_array($uri, $cachedUris);
  }

  /**
   * @param $uri URI to register
   * @param $cacheKey
   */
  protected function setInCache($uri, $cacheKey) {
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
   * @return array Cached URIs
   */
  protected function getCachedUris($cacheKey) {
    return $cachedUris = $this->cache->get($cacheKey . '_loaded_uris')->data;
  }
}
