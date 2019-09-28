<?php

namespace Drupal\skosmos_feeds\Feeds\Processor;

use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Item\ItemInterface;
use Drupal\feeds\Feeds\Processor\EntityProcessorBase;
use Drupal\feeds\StateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Defines a node processor.
 *
 * Creates nodes from feed items.
 *
 * @FeedsProcessor(
 *   id = "entity:custom_vocabulary",
 *   title = @Translation("Custom vocabulary"),
 *   description = @Translation("Creates item for any vocabulary"),
 *   entity_type = "taxonomy_term",
 *   arguments = {
 *     "@entity_type.manager",
 *     "@entity.query",
 *     "@entity_type.bundle.info",
 *   },
 *   form = {
 *     "configuration" =
 *   "Drupal\feeds\Feeds\Processor\Form\DefaultEntityProcessorForm",
 *     "option" =
 *   "Drupal\feeds\Feeds\Processor\Form\EntityProcessorOptionForm",
 *     "feed" =
 *   "Drupal\skosmos_feeds\Feeds\Processor\Form\CustomTaxonomyProcessorFeedForm",
 *   },
 * )
 */
class CustomTaxonomyTermProcessor extends EntityProcessorBase {

  public function process(FeedInterface $feed, ItemInterface $item, StateInterface $state) {

    if (empty($this->getCustomVocabularyId($feed))) {
      throw new \Exception($this->t("No target vocabulary defined"));
    }
    $this->overrideDefaultVocabularyId($this->getCustomVocabularyId($feed));
    $this->prepareFeedsItemField();
    $this->prepareFeedsURIField();
    $this->prepareFeedsMappings($feed);
    $targetIsReady = $this->checkVocabularyFieldCompleteness($feed, $state);
    if (TRUE === $targetIsReady) {
      parent::process($feed, $item, $state);
    }
    else {
      throw new \RuntimeException($this->t('Target vocabulary %target_vid is missing some of the fields used in the mapping.', ['%target_vid' => $this->getCustomVocabularyId($feed)]));
    }
  }

  public function postProcess(FeedInterface $feed, StateInterface $state) {
    // FIXME something erases value in configuration
    // and makes the default taxonomy label appear in log messages instead of the specific one
    $this->overrideDefaultVocabularyId($this->getCustomVocabularyId($feed));
    parent::postProcess($feed, $state);
  }

  protected function prepareFeedsURIField() {
    // Do not create field when syncing configuration.
    if (\Drupal::isConfigSyncing()) {
      return FALSE;
    }
    // Create field if it doesn't exist.
    if (!FieldStorageConfig::loadByName($this->entityType(), 'concept_uri')) {
      FieldStorageConfig::create([
        'field_name' => 'concept_uri',
        'entity_type' => 'taxonomy_term',
        'type' => 'string',
        'translatable' => FALSE,
        'cardinality' => 1,
      ])->save();
    }
    // Create field instance if it doesn't exist.
    if (!FieldConfig::loadByName($this->entityType(), $this->bundle(), 'concept_uri')) {
      FieldConfig::create([
        'label' => $this->t('Concept URI'),
        'description' => $this->t('URI of the SKOS concept'),
        'field_name' => 'concept_uri',
        'field_type' => 'string',
        'entity_type' => $this->entityType(),
        'bundle' => $this->bundle(),
      ])->save();
    }
  }

  public function bundleLabel() {
    return $this->t('Model vocabulary for mapping (used as default if not specified in feed options)');
  }

  private function prepareFeedsMappings(FeedInterface $feed) {
    $currentMappings = $feed->getType()->getMappingTargets();
    if (!$this->mappingsContainsTarget('parent', $feed)) {
      $feed->getType()->addMapping([
        'target' => 'parent',
        'map' =>
          [
            'target_id' => 'broader',
          ],
        'settings' =>
          [
            'reference_by' => 'concept_uri',
            'feeds_item' => 'guid',
            'autocreate' => 0,
          ],
      ]);
    }
    if (!$this->mappingsContainsTarget('concept_uri', $feed)) {
      $feed->getType()->addMapping([
        'target' => 'concept_uri',
        'map' =>
          [
            'value' => 'URI',
          ],
        'unique' =>
          [
            'value' => '1',
          ],
      ]);
    }

  }

  private function mappingsContainsTarget($target, FeedInterface $feed) {
    $currentMappings = $feed->getType()->getMappings();
    foreach ($currentMappings as $currentMapping) {
      if (isset($currentMapping['target']) && $currentMapping['target'] == $target) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function checkVocabularyFieldCompleteness(FeedInterface $feed, StateInterface $state) {
    $currentMappings = $feed->getType()->getMappings();
    $complete = TRUE;
    //Loop on mappings for current Feed type
    foreach ($currentMappings as $currentMapping) {
      $target_field_from_mapping = $currentMapping['target'];
      if (in_array($target_field_from_mapping, ['name', 'parent'])) {
        continue;
      }
      //Check if the target taxonomy owns the field
      if (!FieldConfig::loadByName($this->entityType(), $this->getCustomVocabularyId($feed), $target_field_from_mapping)) {
        $state->setMessage($this->t('The target field @target_field is missing in the target taxonomy @target_taxonomy.', [
          '@target_field' => $target_field_from_mapping,
          '@target_taxonomy' => $this->getCustomVocabularyId($feed),
        ]), 'error');
        $complete = FALSE;
      }
    }
    return $complete;
  }

  /**
   * @param string $vid Vocabulary id
   */
  private function overrideDefaultVocabularyId($vid) {
    // Substitute default target vocabulary (defined on feed type level)
    // by specific one (defined on feed level)
    $this->configuration['values'][$this->entityType->getKey('bundle')] = $vid;
  }

  /**
   * @param \Drupal\feeds\FeedInterface $feed
   *
   * @return mixed
   */
  private function getCustomVocabularyId(FeedInterface $feed) {
    return $vid = isset($feed->get('config')->target_vocabulary) ? $feed->get('config')->target_vocabulary : $this->configuration['values']['vid'];
  }
}