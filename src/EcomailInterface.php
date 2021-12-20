<?php

namespace Drupal\ecomail_webform;

/**
 * Interface EcomailInterface.
 *
 * @package Drupal\ecomail_webform
 */
interface EcomailInterface {

  /**
   * Return list of collection.
   *
   * @see https://ecomailczv2.docs.apiary.io/#reference/lists/list-collections/view-all-lists
   */
  public function getListsCollection();

  /**
   * Return list detail.
   *
   * @see https://ecomailczv2.docs.apiary.io/#reference/lists/list/show-list
   */
  public function showList($list_id);

  /**
   * Add subscriber to list.
   *
   * @see https://ecomailczv2.docs.apiary.io/#reference/lists/list-subscribe/add-new-subscriber-to-list
   */
  public function addSubscriber($list_id, array $data);

}
