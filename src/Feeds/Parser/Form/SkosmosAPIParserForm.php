<?php

namespace Drupal\skosmos_feeds\Feeds\Parser\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;

/**
 * The configuration form for the CSV parser.
 */
class SkosmosAPIParserForm extends ExternalPluginFormBase
{

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['keep_structure'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Keep vocabulary structure'),
            '#description' => $this->t("Translate broder relations into drupal taxonomy reference to 'parent term'"),
            '#default_value' => true,];

        return $form;
    }

}
