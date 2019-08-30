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
 *     "configuration" = "Drupal\feeds\Feeds\Processor\Form\DefaultEntityProcessorForm",
 *     "option" = "Drupal\feeds\Feeds\Processor\Form\EntityProcessorOptionForm",
 *     "feed" = "Drupal\skosmos_feeds\Feeds\Processor\Form\CustomTaxonomyProcessorFeedForm",
 *   },
 * )
 */
class CustomTaxonomyTermProcessor extends EntityProcessorBase
{

    public function process(FeedInterface $feed, ItemInterface $item, StateInterface $state)
    {
        // Substitute default target vocabulary (defined on feed type level)
        // by specific one (defined on feed level)
        if (isset($feed->get('config')->target_vocabulary)) {
            $this->configuration['values']['vid'] = $feed->get('config')->target_vocabulary;
        }
        // TODO we sould delete this field if not used any more
        $this->prepareFeedsItemField();
        $this->prepareFeedsURIField();
        $this->prepareFeedsMappings($feed);
        parent::process($feed, $item, $state);
    }

    protected function prepareFeedsURIField()
    {
        // Do not create field when syncing configuration.
        if (\Drupal::isConfigSyncing()) {
            return FALSE;
        }
        // Create field if it doesn't exist.
        if (!FieldStorageConfig::loadByName($this->entityType(), 'concept_uri')) {
            FieldStorageConfig::create([
                'field_name' => 'concept_uri',
                'entity_type' => 'taxonomy_term',
                'type' => 'link',
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

    /**
     * {@inheritdoc}
     */
    public function entityLabel()
    {
        return $this->t('Custom taxonomy term');
    }

    /**
     * {@inheritdoc}
     */
    public function entityLabelPlural()
    {
        return $this->t('Custom vocabulary terms');
    }

    public function bundleLabel()
    {
        return $this->t('Default vocabulary (if not specified in feed options)');
    }

    private function prepareFeedsMappings(FeedInterface $feed)
    {
        $currentMappings = $feed->getType()->getMappingTargets();
        if(!$this->mappingsContainsTarget('parent', $feed)){
            $feed->getType()->addMapping(array(
                'target' => 'parent',
                'map' =>
                    array(
                        'target_id' => 'broader',
                    ),
                'settings' =>
                    array(
                        'reference_by' => 'concept_uri',
                        'feeds_item' => 'guid',
                        'autocreate' => 0,
                    ),
            ));
        }
        if(!$this->mappingsContainsTarget('concept_uri', $feed)){
            $feed->getType()->addMapping(array(
                'target' => 'concept_uri',
                'map' =>
                    array(
                        'value' => 'URI',
                    ),
                'unique' =>
                    array(
                        'value' => '1',
                    ),
            ));
        }

    }

    private function mappingsContainsTarget($target, FeedInterface $feed)
    {
        $currentMappings = $feed->getType()->getMappings();
        foreach ($currentMappings as $currentMapping) {
            if (isset($currentMapping['target']) && $currentMapping['target'] == $target) {
                return true;
            }
        }
        return false;
    }
}