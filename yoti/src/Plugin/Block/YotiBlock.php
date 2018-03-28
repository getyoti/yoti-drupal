<?php

namespace Drupal\yoti\Plugin\Block;

use Yoti\YotiClient;
use Drupal\Core\Block\BlockBase;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;

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
    $config = YotiHelper::getConfig();
    if (!$config) {
      return [];
    }

    $script = [];

    // If connect url starts with 'https://staging' then we are in staging mode.
    $isStaging = strpos(YotiClient::CONNECT_BASE_URL, 'https://staging') === 0;
    if ($isStaging) {
      // Base url for connect.
      $baseUrl = preg_replace('/^(.+)\/connect$/', '$1', YotiClient::CONNECT_BASE_URL);

      $script[] = sprintf('_ybg.config.qr = "%s/qr/";', $baseUrl);
      $script[] = sprintf('_ybg.config.service = "%s/connect/";', $baseUrl);
    }

    // Add init()
    $script[] = '_ybg.init();';
    $script = implode("\r\n", $script);

    // Prep button.
    $linkButton = '<span
            data-yoti-application-id="' . $config['yoti_app_id'] . '"
            data-yoti-type="inline"
            data-yoti-scenario-id="' . $config['yoti_scenario_id'] . '"
            data-size="small">
            %s
        </span>';

    $userId = $user->id();
    if (!$userId) {
      $button = sprintf($linkButton, YotiHelper::YOTI_LINK_BUTTON_DEFAULT_TEXT);
    }
    else {
      $dbProfile = YotiUserModel::getYotiUserById($userId);
      if ($dbProfile) {
        $button = '<strong>Yoti</strong> Linked';
      }
      else {
        $button = sprintf($linkButton, 'Link to Yoti');
      }
    }

    $html = '<div class="yoti-connect">' . $button . '</div>';

    return [
      'inside' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $html,
        'inside' => [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => '<script>' . $script . '</script>',
        ],
      ],
      '#attached' => [
        'library' => [
          'yoti/yoti',
        ],
      ],
    ];
  }

}
