<?php

namespace Drupal\Tests\skosmos_feeds\Unit\Feeds\Fetcher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\State;
use Drupal\skosmos_feeds\Feeds\Fetcher\SkosmosAPIFetcher;
use Drupal\skosmos_feeds\Model\RdfGraphService;
use Drupal\Tests\skosmos_feeds\Unit\SkosmosFeedsUnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Fetcher\HttpFetcher
 * @group feeds
 */
class SkosmosAPIFetcherTest extends SkosmosFeedsUnitTestCase {

  /**
   * The feed entity.
   *
   * @var \Drupal\feeds\FeedInterface
   */
  protected $feed;

  /**
   * The Feeds fetcher plugin under test.
   *
   * @var \Drupal\feeds\Feeds\Fetcher\HttpFetcher
   */
  protected $fetcher;

  /**
   *
   * @var \Drupal\skosmos_feeds\Model\RdfGraphService
   */
  protected $rdfGraphService;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $feed_type = $this->createMock(FeedTypeInterface::class);
    $cache = $this->createMock(CacheBackendInterface::class);
    $this->rdfGraphService = $this->createMock(RdfGraphService::class);


    $file_system = $this->prophesize(FileSystemInterface::class);
    $file_system->tempnam(Argument::type('string'), Argument::type('string'))
      ->will(function ($args) {
        // We suppress any notices as since PHP 7.1, this results into a warning
        // when the temporary directory is not configured in php.ini. We are not
        // interested in that artefact for this test.
        return @tempnam($args[0], $args[1]);
      });
    $file_system->realpath(Argument::type('string'))->will(function ($args) {
      return realpath($args[0]);
    });

    $this->fetcher = new SkosmosAPIFetcher(['feed_type' => $feed_type], 'http', [], $this->rdfGraphService, $cache, $file_system->reveal());
    $this->fetcher->setStringTranslation($this->getStringTranslationStub());

    $this->feed = $this->prophesize(FeedInterface::class);
    $this->feed->id()->willReturn(1);
    $this->feed->getSource()
      ->willReturn('http://data.example.com/vocabulary/dummy');
    $this->feed->get('config')->willReturn((object) ['1' => 'foo']);
  }

  /**
   * Tests a successful fetch of a very simple skos file
   *
   * @covers ::fetch
   */
  public function testFetchSingleConcept() {
    $skosDummyContent = file_get_contents($this->resourcesPath() . '/skos/single-dummy-concept.nt');
    $this->rdfGraphService->method('fetchVocabularyFromSkosmos')
      ->willReturn(["http://data.example.com/concepts/dummy"]);
    $this->rdfGraphService->method('serialize')
      ->willReturn($skosDummyContent);
    $result = $this->fetcher->fetch($this->feed->reveal(), new State());
    $this->assertSame($skosDummyContent, $result->getRaw());
  }


}
