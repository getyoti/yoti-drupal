<?php

namespace Drupal\yoti\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
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

    // Set button text based on current user.
    $userId = $this->currentUser->id();
    if (!$userId) {
      $button_text = YotiHelper::YOTI_LINK_BUTTON_DEFAULT_TEXT;
      $is_linked = FALSE;
    }
    else {
      $button_text = 'Link to Yoti';
      $is_linked = YotiUserModel::getYotiUserById($userId) ? TRUE : FALSE;
    }

    return [
      '#theme' => 'yoti_button',
      '#button_id' => Html::getUniqueId('yoti-button-' . $this->getPluginId()),
      '#client_sdk_id' => $this->yotiConfig->getClientSdkId(),
      '#scenario_id' => $this->yotiConfig->getScenarioId(),
      '#button_text' => $button_text,
      '#is_linked' => $is_linked,
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
