<?php

namespace Drupal\skosmos_feeds\Feeds\Processor\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;
use Drupal\feeds\Utility\Feed;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form on the feed edit page for the HttpFetcher.
 */
class CustomTaxonomyProcessorFeedForm extends ExternalPluginFormBase implements ContainerInjectionInterface
{

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state, FeedInterface $feed = NULL)
    {
        $form['target_vocabulary'] = [
            '#type' => 'select',
            '#options' => $this->targetVocabularyOptions(),
            '#title' => $this->t('Target vocabulary'),
            '#required' => FALSE,
            '#default_value' => $feed->get('config')->target_vocabulary,
        ];

        return $form;
    }

    protected function targetVocabularyOptions()
    {
        // TODO USE CONTAINER INTERFACE
        $vocabularies = \Drupal::service('entity_type.bundle.info')->getBundleInfo('taxonomy_term');
        $options = [];
        foreach ($vocabularies as $key => $value) {
            $options[$key] = $value['label'];
        }
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL)
    {
        // todo VALIDATE
        /*try {
            $url = Feed::translateSchemes($form_state->getValue('source'));
        } catch (\InvalidArgumentException $e) {
            $form_state->setError($form['source'], $this->t("The url's scheme is not supported. Supported schemes are: @supported.", [
                '@supported' => implode(', ', Feed::getSupportedSchemes()),
            ]));
            // If the source doesn't have a valid scheme the rest of the validation
            // isn't helpful. Break out early.
            return;
        }*/
        $form_state->setValue('target_vocabulary', $form_state->getValue('target_vocabulary'));

    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL)
    {
        $feed->get('config')->target_vocabulary = $form_state->getValue('target_vocabulary');
    }

    /**
     * @inheritdoc
     */
    public static function create(ContainerInterface $container)
    {
        return new static();
    }
}
