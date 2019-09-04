<?php

namespace Drupal\skosmos_feeds\Feeds\Fetcher\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;
use Drupal\feeds\Utility\Feed;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form on the feed edit page for the Skosmos API Fetcher.
 */
class SkosmosAPIFetcherFeedForm extends ExternalPluginFormBase implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $form['source'] = [
      '#title' => $this->t('Concept scheme URI'),
      '#type' => 'url',
      '#default_value' => $feed->getSource(),
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];

    $form['incremental_fetch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fetch concepts in an incremental way'),
      '#description' => $this->t("For very large vocabularies, limit the number of new leaf concepts fetched from skosmos during each import."),
      '#default_value' => TRUE,
    ];

    $form['max_number_of_leaf_concepts'] = [
      '#title' => $this->t('Max number of leaf concepts'),
      '#description' => $this->t("Max number of new leaf concepts fetched from skosmos during each import if previous option is checked. New leaf concepts are determined by compaison with the cache."),
      '#type' => 'number',
      '#default_value' => $feed->get('config')->max_number_of_leaf_concepts,
      '#size' => 3,
      '#maxlength' => 5,
      '#min' => 0,
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    try {
      $url = Feed::translateSchemes($form_state->getValue('source'));
    } catch (\InvalidArgumentException $e) {
      $form_state->setError($form['source'], $this->t("The url's scheme is not supported. Supported schemes are: @supported.", [
        '@supported' => implode(', ', Feed::getSupportedSchemes()),
      ]));
      // If the source doesn't have a valid scheme the rest of the validation
      // isn't helpful. Break out early.
      return;
    }
    $form_state->setValue('source', $url);

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $feed->setSource($form_state->getValue('source'));
    $feed->get('config')->incremental_fetch = $form_state->getValue('incremental_fetch');
    $feed->get('config')->max_number_of_leaf_concepts = $form_state->getValue('max_number_of_leaf_concepts');
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }
}
