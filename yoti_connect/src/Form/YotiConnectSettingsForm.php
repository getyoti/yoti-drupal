<?php
namespace Drupal\yoti_connect\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yoti_connect\YotiConnectHelper;
use Yoti\YotiClient;

class YotiConnectSettingsForm extends ConfigFormBase
{

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames()
    {
        return [
            'yoti_connect.settings',
        ];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId()
    {
        return 'yoti_connect_admin_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // make sure private path exists, if not, create it
        //        $path = drupal_realpath('yoti://');
        //        if ($path && !is_dir("$path/yoti"))
        //        {
        //            mkdir("$path/yoti", 0777);
        //        }
        $path = YotiConnectHelper::uploadDir();
        if ($path && !is_dir($path))
        {
            drupal_mkdir($path, 0777);
        }

        $yoti_connect = $this->config('yoti_connect.settings');

        //    print_r($yoti_connect->get());exit;

        $form['#attributes'] = array(
            'enctype' => "multipart/form-data",
        );

        $cbUrl = \Drupal\Core\Url::fromRoute('yoti_connect.link', array(), array('absolute' => true, 'https' => true))
            ->toString();
        $form['yoti_connect_settings'] = array(
            '#type' => 'details',
            '#title' => $this->t('Yoti Dashboard'),
            '#open' => true,
            '#description' => $this->t('You need to first create a Yoti App at <a href="@yoti-dev" target="_blank">@yoti-dev</a>.', array('@yoti-dev' => YotiClient::DASHBOARD_URL)) . '</br >' .
                $this->t('Note: On the Yoti Dashboard the callback URL should be set to: <code>@cb</code>', array(
                    '@cb' => $cbUrl,
                )),
        );

        $form['yoti_connect_settings']['yoti_app_id'] = array(
            '#type' => 'textfield',
            '#required' => true,
            '#title' => $this->t('App ID'),
            '#default_value' => $yoti_connect->get('yoti_app_id'),
            '#description' => $this->t('Copy the App ID of your Yoti App here'),
        );

        $form['yoti_connect_settings']['yoti_sdk_id'] = array(
            '#type' => 'textfield',
            '#required' => true,
            '#title' => $this->t('SDK ID'),
            '#default_value' => $yoti_connect->get('yoti_sdk_id'),
            '#description' => $this->t('Copy the SDK ID of your Yoti App here'),
        );

        $form['yoti_connect_settings']['yoti_pem'] = array(
            '#type' => 'managed_file',
            '#required' => true,
            '#title' => $this->t('PEM File'),
            '#default_value' => $yoti_connect->get('yoti_pem'),
            '#upload_location' => 'private://yoti',
            '#description' => $this->t('Upload the PEM file of your Yoti App here'),
            '#upload_validators' => array(
                'file_validate_extensions' => array('pem'),
                //        'file_validate_size' => array(25600000),
            ),
        );

        // Load the file.
        $pemFile = $yoti_connect->get('yoti_pem');
        $file = \Drupal\file\Entity\File::load($pemFile[0]);
        // Change status to permanent.
        if (gettype($file) == 'object')
        {
            $file->status = FILE_STATUS_PERMANENT;
            // Save.
            $file->save();
            $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            $file->setOwner($user);
            // Record the module (in this example, user module) is using the file.
            \Drupal::service('file.usage')->add($file, 'yoti_connect', 'yoti_connect', $file->id());
            //      $_SESSION['intermedia'] = 'nothing';
            //drupal_set_message('File Saved');
        }

        return parent::buildForm($form, $form_state);
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);

        //    var_dump([$form,$form_state]);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $this->config('yoti_connect.settings')
            ->set('yoti_app_id', $values['yoti_app_id'])
            ->set('yoti_sdk_id', $values['yoti_sdk_id'])
            ->set('yoti_pem', $values['yoti_pem'])
            //      ->set('post_login_path', $values['post_login_path'])
            //      ->set('redirect_user_form', $values['redirect_user_form'])
            //      ->set('disable_admin_login', $values['disable_admin_login'])
            //      ->set('disabled_roles', $values['disabled_roles'])
            ->save();

        parent::submitForm($form, $form_state);
    }
}