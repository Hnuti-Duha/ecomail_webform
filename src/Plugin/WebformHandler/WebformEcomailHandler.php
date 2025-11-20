<?php

namespace Drupal\ecomail_webform\Plugin\WebformHandler;

use Countable;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\ecomail_webform\EcomailInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission to Ecomail handler.
 *
 * @WebformHandler(
 *   id = "ecomail_webform",
 *   label = @Translation("Ecomail"),
 *   category = @Translation("Ecomail"),
 *   description = @Translation("Sends a form submission to a Ecomail list."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class WebformEcomailHandler extends WebformHandlerBase {

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTranslationManagerInterface
   */
  protected $tokenManager;

  /**
   * Ecomail.
   *
   * @var \Drupal\ecomail\EcomailInterface
   */
  protected $ecomail;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->ecomail = $container->get('ecomail_webform.ecomail');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $list = NULL;

    if ($this->configuration['list']) {
      $list = $this->ecomail->showList($this->configuration['list']);
    }

    return [
      '#theme' => 'markup',
      '#markup' => $this->t('<strong>List: </strong>@name', ['@name' => (isset($list['list']['name']) ? $list['list']['name'] : '')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'list' => [],
      'email' => '',
      'subscriber_data_standard_properties' => '',
      'subscriber_data_custom_properties' => '',
      'tags' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $lists = $this->ecomail->getListsCollection();

    $options = [];
    foreach ($lists as $list) {
      $options[$list['id']] = $list['name'];
    }

    $form['ecomail'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Ecomail settings'),
      '#attributes' => ['id' => 'webform-ecomail-handler-settings'],
    ];

    $form['ecomail']['list'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('List'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select an option -'),
      '#default_value' => $this->configuration['list'],
      '#options' => $options,
      '#ajax' => [
        'callback' => [$this, 'ajaxEcomailListHandler'],
        'wrapper' => 'webform-ecomail-handler-settings',
      ],
      '#description' => $this->t('Select the list you want to send this submission to. Alternatively, you can also use the Other field for token replacement.'),
    ];

    $fields = $this->getWebform()->getElementsInitializedAndFlattened();
    $options = [];
    foreach ($fields as $field_name => $field) {
      if ($field['#type'] == 'email') {
        $options[$field_name] = $field['#title'];
      }
    }

    $default_value = $this->configuration['email'];
    if (empty($this->configuration['email']) && count($options) == 1) {
      $default_value = reset(array_keys($options));
    }
    $form['ecomail']['email'] = [
      '#type' => 'select',
      '#title' => $this->t('Email field'),
      '#required' => TRUE,
      '#default_value' => $default_value,
      '#options' => $options,
      '#empty_option' => $this->t('- Select an option -'),
      '#empty_value' => '',
    ];

    $form['ecomail']['subscriber_data_standard_properties'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Subscriber data - standard properties'),
      '#default_value' => $this->configuration['subscriber_data_standard_properties'],
      '#description' => $this->t('Enter the subscriber data that will be sent to ecomail, each line a <br><em>ecomail_field1: webform_field1</em><br><em>ecomail_field2: webform_field2</em>. <br>You may use tokens.'),
    ];

    $form['ecomail']['subscriber_data_standard_properties_examples'] = [
      '#theme' => 'subscriber_data_examples',
    ];

    $form['ecomail']['subscriber_data_custom_properties'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Subscriber data - custom propeties'),
      '#default_value' => $this->configuration['subscriber_data_custom_properties'],
      '#description' => $this->t('Enter the custom subscriber data that will be sent to ecomail, each line a <br><em>ecomail_field1: webform_field1</em><br><em>ecomail_field2: webform_field2</em>. <br>You may use tokens.'),
    ];

    $form['ecomail']['tags'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Tags'),
      '#default_value' => $this->configuration['tags'],
      '#description' => $this->t('Enter the tags that will be sent to ecomail, each line a <br><em> - tag_name1</em><br><em> - tag_name2</em>.'),
    ];

    return $form;
  }

  /**
   * Ajax callback to update Webform Ecomail settings.
   */
  public static function ajaxEcomailListHandler(array $form, FormStateInterface $form_state) {
    return $form['settings']['ecomail'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValues();
    foreach ($this->configuration as $name => $value) {
      if (isset($values['ecomail'][$name])) {
        $this->configuration[$name] = $values['ecomail'][$name];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // If update, do nothing.
    if ($update) {
      return;
    }

    $fields = $webform_submission->toArray(TRUE);

    // Replace tokens.
    $configuration = $this->tokenManager->replace($this->configuration, $webform_submission);
    $email = $fields['data'][$configuration['email']];
    $subscriber_data = Yaml::decode($configuration['subscriber_data_standard_properties']);
    $subscriber_data_custom = Yaml::decode($configuration['subscriber_data_custom_properties']);
    $tags = Yaml::decode($configuration['tags']);

    $data = [];
    if (is_array($subscriber_data) || $subscriber_data instanceof Countable) {
      if (count($subscriber_data) > 0) {
        foreach ($subscriber_data as $ecomail_field => $webform_field) {
          if (isset($fields['data'][$webform_field])) {
            $data[$ecomail_field] = $fields['data'][$webform_field];
          }
        }
      }
    }

    // Process custom fields
    if (is_array($subscriber_data_custom) || $subscriber_data_custom instanceof Countable) {
      if (count($subscriber_data_custom) > 0) {
        foreach ($subscriber_data_custom as $ecomail_field => $webform_field) {
          if (isset($fields['data'][$webform_field])) {
            $data['custom_fields'][$ecomail_field] = $fields['data'][$webform_field];
          }
        }
      }
    }

    if ($tags && count($tags) > 0) {
      $data['tags'] = $tags;
    }

    // We have send always send email.
    $data['email'] = $email;

    $request_data = [
      'subscriber_data' => $data,
    ];

    $result = $this->ecomail->addSubscriber($configuration['list'], $request_data);

    if (!isset($result['id']) || !isset($result['email'])) {
      $this->messenger()->addError($this->t('An error occurred subscribing @email.', ['@email' => $email]));
      $this->getLogger('ecomail')->error($this->t('An error occurred subscribing @email to list @list. Response: @result', [
        '@email' => $email,
        '@list' => $configuration['list'],
        '@result' => Json::encode($result),
      ]));
    }
  }

}
