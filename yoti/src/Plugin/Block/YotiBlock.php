<?php

namespace Drupal\yoti\Plugin\Block;

use Yoti\YotiClient;
use Drupal\Core\Block\BlockBase;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;
use Drupal\Core\Cache\Cache;

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

    $qr_url = NULL;
    $service_url = NULL;

    // If connect url starts with 'https://staging' then we are in staging mode.
    $is_staging = strpos(YotiClient::CONNECT_BASE_URL, 'https://staging') === 0;
    if ($is_staging) {
      // Base url for connect.
      $base_url = preg_replace('/^(.+)\/connect$/', '$1', YotiClient::CONNECT_BASE_URL);
      $qr_url = sprintf('%s/qr/', $base_url);
      $service_url = sprintf('%s/connect/', $base_url);
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
      '#app_id' => $config->getAppId(),
      '#scenario_id' => $config->getScenarioId(),
      '#button_text' => $button_text,
      '#is_linked' => $is_linked,
      '#is_staging' => $is_staging,
      '#qr_url' => $qr_url,
      '#service_url' => $service_url,
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
