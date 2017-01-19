<?php

/**
 * @param $form
 * @param $form_state
 * @return mixed
 */
function yoti_connect_api_keys_settings($form, &$form_state)
{
    // show instructions to README.txt and Drupal account settings
    //  drupal_set_message(t('Installation and configuration instructions can be found from the README.txt'));
    //  drupal_set_message(t('Also check <a href="@url">Drupal account settings</a>', array('@url' => url('admin/config/people/accounts'))));

    // make sure private path exists, if not, create it
    $uploadDir = YotiConnectHelper::uploadDir(true);
    if ($uploadDir && !is_dir($uploadDir))
    {
        mkdir($uploadDir, 0777, true);
    }

    $form['some_text'] = array(
        '#markup' => '<div>' .
            t('You need to first create a Yoti App at <a href="@yoti-dev" target="_blank">@yoti-dev</a>.', array('@yoti-dev' => \Yoti\YotiClient::DASHBOARD_URL)) . '</br >' .
            t('Note: On the Yoti Dashboard the callback URL should be set to:') . ' <strong>' . url('/yoti-connect/link', array('absolute' => true)) . '</strong>' .
            '</div>',
    );

    $form['#attributes'] = array(
        'enctype' => "multipart/form-data",
    );

    $form['yoti_connect_settings']['yoti_app_id'] = array(
        '#type' => 'textfield',
        '#required' => true,
        '#title' => t('App ID'),
        '#default_value' => variable_get('yoti_app_id'),
        '#description' => t('Copy the App ID of your Yoti App here'),
    );

    $form['yoti_connect_settings']['yoti_sdk_id'] = array(
        '#type' => 'textfield',
        '#required' => true,
        '#title' => t('SDK ID'),
        '#default_value' => variable_get('yoti_sdk_id'),
        '#description' => t('Copy the SDK ID of your Yoti App here'),
    );

    $form['yoti_connect_settings']['yoti_pem'] = array(
        '#type' => 'managed_file',
        '#field_name' => 'yoti_pem',
        '#required' => true,
        '#title' => t('PEM File'),
        '#default_value' => variable_get('yoti_pem'),
        '#upload_location' => YotiConnectHelper::uploadDir(false),
        //        '#upload_location' => YotiConnectHelper::uploadDir(),
        '#description' => t('Upload the PEM file of your Yoti App here'),
        '#upload_validators' => array(
            'file_validate_extensions' => array('pem'),
            //        'file_validate_size' => array(25600000),
        ),
    );

    // Load the file.
    $pemFile = variable_get('yoti_pem');
    if ($pemFile)
    {
        $file = file_load($pemFile);
        // Change status to permanent.
        if (gettype($file) == 'object')
        {
            $file->status = FILE_STATUS_PERMANENT;
            // Save.
            file_save($file);
            //        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            //        $file->setOwner($user);
            // Record the module (in this example, user module) is using the file.
            //        \Drupal::service('file.usage')->add($file, 'yoti_connect', 'yoti_connect', $file->id());
            file_usage_add($file, 'yoti_connect', 'yoti_pem', $file->fid);
            //      $_SESSION['intermedia'] = 'nothing';
            //drupal_set_message('File Saved');
        }
    }

    return system_settings_form($form);
}
