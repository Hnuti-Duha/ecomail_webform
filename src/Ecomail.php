<?php

namespace Drupal\ecomail_webform;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class Ecomail.
 */
class Ecomail implements EcomailInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Ecomail php wrapper.
   *
   * @var \Ecomail
   *
   * @see https://github.com/Ecomailcz/ecomail-php
   */
  protected $ecomail;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Ecomail constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger.
   */
  public function __construct(ConfigFactoryInterface $config, LoggerChannelFactoryInterface $logger_factory) {
    $this->config = $config->get('ecomail_webform.settings');
    $this->logger = $logger_factory;
    $this->ecomail = new \Ecomail($this->config->get('api_key'));
  }

  /**
   * {@inheritdoc}
   */
  public function getListsCollection() {
    return $this->processResponse($this->ecomail->getListsCollection());
  }

  /**
   * {@inheritdoc}
   */
  public function showList($list_id) {
    return $this->processResponse($this->ecomail->showList($list_id));
  }

  /**
   * {@inheritdoc}
   */
  public function addSubscriber($list_id, array $data) {
    return $this->processResponse($this->ecomail->addSubscriber($list_id, $data));
  }

  /**
   * Helper method to process response from API.
   *
   * @param mixed $response
   *   Response from API call.
   *
   * @return mixed|null
   *   Return decoded data or NULL.
   */
  protected function processResponse($response) {
    if (is_array($response) || is_object($response)) {
      return $response;
    }
    if (is_string($response)) {
      $decoded_response = Json::decode($response);

      if ($decoded_response) {
        return $decoded_response;
      }
    }

    $this->logger->get('ecomail_webform')->error('Response from API: ' . Json::encode($response));
    return NULL;
  }

}
