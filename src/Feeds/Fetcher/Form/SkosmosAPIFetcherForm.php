<?php

namespace Drupal\skosmos_feeds\Feeds\Fetcher\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;

/**
 * The configuration form for http fetchers.
 */
class SkosmosAPIFetcherForm extends ExternalPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['application_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application URI'),
      '#description' => $this->t('Complete URI of the target Skosmos instance API, e.g., https://www.reseau-canope.fr/scolomfr/data/rest/v1/data'),
      '#default_value' => $this->plugin->getConfiguration('application_uri'),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Ensure that the application URI is a valid URI
    if (!empty($form['application_uri'])) {
      if (empty($values['application_uri'])) {
        $form_state->setError($form['application_uri'], $this->t('Application URI in fetcher parameters is mandatory.'));
      }
      elseif (!filter_var($values['application_uri'], FILTER_VALIDATE_URL)) {
        $form_state->setError($form['application_uri'], $this->t('The provided application URI in fetcher parameters is not an URI.'));
      }
    }

  }

}
