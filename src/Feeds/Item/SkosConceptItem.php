<?php

namespace Drupal\skosmos_feeds\Feeds\Item;

use Drupal\feeds\Feeds\Item\BaseItem;

/**
 * Defines an item class for use with an OPML document.
 */
class SkosConceptItem extends BaseItem
{

    protected $prefLabel;
    protected $URI;
    protected $broader;

}
