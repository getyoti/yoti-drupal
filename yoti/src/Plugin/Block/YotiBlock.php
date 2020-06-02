<?php

namespace Drupal\yoti\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\yoti\Models\YotiUserModel;
use Drupal\yoti\YotiConfigInterface;
use Drupal\yoti\YotiHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Yoti' Block.
 *
 * @Block(
 *   id = "yoti_block",
 *   admin_label = @Translation("Yoti"),
 * )
 */
class YotiBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * Config key for Scenario ID.
   */
  private const CONFIG_SCENARIO_ID = 'scenario_id';

  /**
   * Config key for Button Text.
   */
  private const CONFIG_BUTTON_TEXT = 'button_text';

  /**
   * Default button text for new users.
   */
  private const NEW_USER_BUTTON_TEXT = YotiHelper::YOTI_LINK_BUTTON_DEFAULT_TEXT;

  /**
   * Default button text for linked users.
   */
  private const EXISTING_USER_BUTTON_TEXT = 'Link to Yoti';

  /**
   * Yoti configuration.
   *
   * @var \Drupal\yoti\YotiConfigInterface
   */
  private $yotiConfig;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * YotiBlock Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\yoti\YotiConfigInterface $yoti_config
   *   Yoti configuration.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    YotiConfigInterface $yoti_config,
    AccountInterface $current_user
  ) {
    $this->yotiConfig = $yoti_config;
    $this->currentUser = $current_user;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('yoti.config'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $configuration = $this->getConfiguration();

    $form[self::CONFIG_BUTTON_TEXT] = [
      '#type' => 'textfield',
      '#title' => t('Button Text'),
      '#description' => t('Leave empty to use the default button text'),
      '#required' => FALSE,
      '#default_value' => !empty($configuration[self::CONFIG_BUTTON_TEXT]) ? $configuration[self::CONFIG_BUTTON_TEXT] : '',
    ];

    $form[self::CONFIG_SCENARIO_ID] = [
      '#type' => 'textfield',
      '#title' => t('Scenario ID'),
      '#description' => t('Leave empty to use the default Scenario ID'),
      '#required' => FALSE,
      '#default_value' => !empty($configuration[self::CONFIG_SCENARIO_ID]) ? $configuration[self::CONFIG_SCENARIO_ID] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue(
      self::CONFIG_BUTTON_TEXT,
      trim($form_state->getValue(self::CONFIG_BUTTON_TEXT))
    );

    $this->setConfigurationValue(
      self::CONFIG_SCENARIO_ID,
      trim($form_state->getValue(self::CONFIG_SCENARIO_ID))
    );
  }

  /**
   * Gets the Scenario ID for this block.
   *
   * @return string
   *   The Scenario ID for this block.
   */
  private function getScenarioId(): string {
    $config = $this->getConfiguration();
    if (!empty($config[self::CONFIG_SCENARIO_ID])) {
      return $config[self::CONFIG_SCENARIO_ID];
    }

    return $this->yotiConfig->getScenarioId();
  }

  /**
   * Gets the button text for this block.
   *
   * @return string
   *   The button text for this block.
   */
  private function getButtonText(): string {
    $config = $this->getConfiguration();
    if (!empty($config[self::CONFIG_BUTTON_TEXT])) {
      return $config[self::CONFIG_BUTTON_TEXT];
    }

    return empty($this->currentUser->id()) ? self::NEW_USER_BUTTON_TEXT : self::EXISTING_USER_BUTTON_TEXT;
  }

  /**
   * Determines that the user is linked to Yoti.
   *
   * @return bool
   *   The user is linked to Yoti.
   */
  private function userIsLinked(): bool {
    $userId = $this->currentUser->id();

    if (!$userId) {
      return FALSE;
    }

    return YotiUserModel::getYotiUserById($userId) ? TRUE : FALSE;
  }

  /**
   * Builds and returns the renderable array for this block plugin.
   *
   * If a block should not be rendered because it has no content, then this
   * method must also ensure to return no content: it must then only return an
   * empty array, or an empty array with #cache set (with cacheability metadata
   * indicating the circumstances for it being empty).
   *
   * @return array
   *   A renderable array representing the content of the block.
   *
   * @see \Drupal\block\BlockViewBuilder
   */
  public function build() {
    // No config? no button.
    if (!$this->yotiConfig->getSettings()) {
      return [];
    }

    return [
      '#theme' => 'yoti_button',
      '#button_id' => 'yoti-button',
      '#client_sdk_id' => $this->yotiConfig->getClientSdkId(),
      '#scenario_id' => $this->getScenarioId(),
      '#button_text' => $this->getButtonText(),
      '#is_linked' => $this->userIsLinked(),
      '#attached' => [
        'library' => [
          'yoti/yoti',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
