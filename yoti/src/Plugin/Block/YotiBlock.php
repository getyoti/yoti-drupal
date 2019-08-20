<?php

namespace Drupal\yoti\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'Yoti' Block.
 *
 * @Block(
 *   id = "yoti_block",
 *   admin_label = @Translation("Yoti"),
 * )
 */
class YotiBlock extends BlockBase {

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
    $user = \Drupal::currentUser();

    // No config? no button.
    $config = \Drupal::service('yoti.config');
    if (!$config->getSettings()) {
      return [];
    }

    // Set button text based on current user.
    $userId = $user->id();
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
      '#client_sdk_id' => $config->getClientSdkId(),
      '#scenario_id' => $config->getScenarioId(),
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
