<?php

namespace Drupal\yoti_connect\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\yoti_connect\YotiConnectHelper;

/**
 * Provides a 'Yoti' Block.
 *
 * @Block(
 *   id = "yoti_connect_block",
 *   admin_label = @Translation("Yoti Connect"),
 * )
 */
class YotiBlock extends BlockBase
{

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
    public function build()
    {
        $user = \Drupal::currentUser();

        /*switch ($delta) {
        case 'yoti_connect_link' :
            $block['content'] = my_block_view();
            break;
    }*/

        $testToken = null;
        if (YotiConnectHelper::mockRequests()) {
            $testToken = file_get_contents(__DIR__ . '/sdk/sample-data/connect-token.txt');
        }

        // no config? no button
        $config = YotiConnectHelper::getConfig();
        if (!$config && !$testToken) {
            return array();
        }

        $script = [];

        // if connect url starts with 'https://staging' then we are in staging mode
        $isStaging = strpos(\Yoti\YotiClient::CONNECT_BASE_URL, 'https://staging') === 0;
        if ($isStaging) {
            // base url for connect
            $baseUrl = preg_replace('/^(.+)\/connect$/', '$1', \Yoti\YotiClient::CONNECT_BASE_URL);

            $script[] = sprintf('_ybg.config.qr = "%s/qr/";', $baseUrl);
            $script[] = sprintf('_ybg.config.service = "%s/connect/";', $baseUrl);
        }

        // add init()
        $script[] = '_ybg.init();';
        $script = implode("\r\n", $script);

        // prep button
        $linkButton = '<span
            data-yoti-application-id="' . $config['yoti_app_id'] . '"
            data-yoti-type="inline"
            data-yoti-scenario-id="' . $config['yoti_scenario_id'] . '"
            data-size="small">
            %s
        </span>';

        $userId = $user->id();
        if (!$userId) {
            $button = sprintf($linkButton, 'Login with Yoti');
        }
        else {
            $tableName = YotiConnectHelper::tableName();
            $dbProfile = \Drupal::database()->query("SELECT * from `{$tableName}` WHERE uid=$userId")->fetchAssoc();
            if (!$dbProfile) {
                $button = sprintf($linkButton, 'Link Yoti account');
            }
            else {
                $url = \Drupal\Core\Url::fromRoute('yoti_connect.unlink');
                $label = 'Unlink Yoti account';
                $button = '<a class="yoti-connect-button" href="' . $url . '">' . $label . '</a>';
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
                ]
            ],
            '#attached' => array(
                'library' => array(
                    'yoti_connect/yoti_connect',
                ),
            )
        ];

        return array(
            '#type' => 'inline_template',
            '#template' => '{{ var }}',
            '#context' => [
                'var' => $html,
            ],
//            '#markup' => $html,
//            '#allowed_tags' => ['script'],
            '#attached' => array(
                'library' => array(
                    'yoti_connect/yoti_connect',
                ),
//                'page_bottom' => array(
//                    array(
//                        array(
//                            '#tag' => 'script',
//                            '#value' => $script,
//                        ),
//                    )
//                )
            )
        );
    }
}
