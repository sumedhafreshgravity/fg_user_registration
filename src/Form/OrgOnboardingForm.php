<?php

namespace Drupal\fg_user_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\UrlHelper;


class OrgOnboardingForm extends FormBase {
/**
 * returns formID
 *
 * @return void
 */
  public function getFormId() {
    // Here we set a unique form id
    return 'org_onboarding_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
        $form['registration_type'] = [
          '#type' => 'radios',
          '#title' => t('Select Regsitration Type'),
          '#weight' =>-1,
          '#options' => [
            'Organization' => t('Organization'),
            'Organization_Memeber' => t('Organization Member'),
          ],
          '#default_value' =>'Organization',
        ];
        $form['org_details'] = array(
          '#type' => 'fieldset',
          '#weight' =>-1,
          '#title' => t('Organization Details'),
          '#collapsible' => TRUE, // Added
          '#collapsed' => FALSE,  // Added
        );
        //$form['org_details']['#attributes']['class'][] = 'hidden';
        $form['org_details']['org_name'] = [
          '#type' => 'textfield',
          '#placeholder' => 'Name of organization',
        ];
        $form['org_details']['org_website'] = [
          '#type' => 'textfield',
          '#placeholder' => 'Website URL',
        ];
        $form['org_details']['admin_name'] = [
            '#type' => 'textfield',
            '#placeholder' => 'Admin Name',
          ];
        $form['org_details']['primary_email'] = array(
            '#type' => 'textfield',
            '#placeholder' => 'E-Mail',
            '#maxlength' => 255,
        );
        $validators = array(
          'file_validate_extensions' => array('.p12','.cert'),
        );
       /* $form['org_details']['certificate_file'] = array(
          '#type' => 'managed_file',
          '#name' => 'certificate_file',
          '#title' => t('Upload Certificate'),
          '#size' => 20,
          '#description' => t('Allowed format .p12, .cert only'),
          '#upload_validators' => $validators,
          '#upload_location' => 'public://my_files/',
        ); */

        $form['org_list'] = array(
          '#type' => 'fieldset',
          '#title' => t('Organization List'),
          '#collapsible' => TRUE, // Added
          '#collapsed' => FALSE,  // Added
        );
        // Load all teams form the database
        $entity = \Drupal::entityTypeManager()->getStorage('team');
        $query = $entity->getQuery();
        $org_name = $query->execute();
        $form['org_list']['#attributes']['class'][] = 'hidden';
        $form['org_list']['org_select'] = [
          '#type' => 'select',
          '#title' => t('Orgnizations'),
          '#options' => $org_name,
        ];
        $form['org_list']['member_name'] = [
          '#type' => 'textfield',
          '#placeholder' => "Member's Name",
        ];
        $form['org_list']['member_email'] = array(
            '#type' => 'textfield',
            '#placeholder' => "Memeber's E-Mail",
            '#maxlength' => 255,
        );
        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => 'Register',
        );
       return $form;
  }

    public function validateForm(array &$form, FormStateInterface $form_state) {
      // Here we decide whether the form can be forwarded to the submitForm()
       // Check if Organization on boarding is slected and use valication for related fields
       if($form_state->getValue('registration_type') == "Organization" ){
          // Check if organization name is not empty
          if($form_state->getValue('org_name') == "") {
            $form_state->setErrorByName('Organization Name', $this->t('Enter valid organization name.'));
          }
          $url =$form_state->getValue('org_website');

          if($url == "" || !$this->isValidURL($url)) {
            $form_state->setErrorByName('Organization Website', $this->t('Enter valid organization website.'));
          }
          if($form_state->getValue('admin_name')== "") {
            $form_state->setErrorByName('Admin Name', $this->t('Enter valid name for administrator.'));
          }
          $email = $form_state->getValue('primary_email');
          if( $email == "" || !\Drupal::service('email.validator')->isValid($email)) {
            $form_state->setErrorByName('Admin Email', $this->t('Enter valid email.'));
          }
        } else if($form_state->getValue('registration_type') == "Organization_Memeber" ){
            // Check if organization name is not empty
            if($form_state->getValue('member_name') == "") {
              $form_state->setErrorByName('Memebr Name', $this->t('Enter valid name.'));
            }
            $email = $form_state->getValue('member_email');
            if( $email == "" || !\Drupal::service('email.validator')->isValid($email)) {
              $form_state->setErrorByName('Member Email', $this->t('Enter valid email.'));
            }
        }
    }
    public function isValidURL($url) {
         $regex = "((https?|ftp)\:\/\/)?";
          $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";
          $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})";
          $regex .= "(\:[0-9]{2,5})?";
          $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?";
          $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?";
          $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?";
          if (preg_match("/^$regex$/i", $url)) {
              return true;
          }
          return false;
    }
    /**
     * Creates a request for new organization.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $conn = Database::getConnection();

        if($form_state->getValue('registration_type') == "Organization_Memeber") {
            //Save Information to the database and send an email

            $team_name = $form_state->getValue("org_select");
            $conn->insert('org_membership_request')->fields(
              array(
                'org_name' => $team_name,
                'member_name' => $form_state->getValue("member_name"),
                'membership_status' => "Pending",
                'member_email' => $form_state->getValue("member_email"),
                'application_date' =>date('Y-m-d', time()),
              )
            )->execute();
            $this->send_email($form_state->getValue("member_email"));
            \Drupal::messenger()->addStatus(t('Your membership is sent for review.Check your email for further details.'));
        }else{
            $team_display_name = $form_state->getValue("org_name");
            $team_name = str_replace(" ", "_", $team_display_name);
            //Check if team already exists
            $query = \Drupal::entityTypeManager()->getStorage('team')->getQuery()->condition('name', $team_name);

            if((bool) $query->count()->execute()) {
                \Drupal::logger('ORG Registration ')->notice("CompanyAlreadyExists");
                \Drupal::messenger()->addStatus(t('Orgnization already exists'));
                return;
            }
            $conn->insert('org_registration_request')->fields(
                array(
                  'org_name' => $team_name,
                  'org_displayname' => $team_display_name,
                  'org_status' => "Pending",
                  'org_website' => $form_state->getValue('org_website'),
                  'admin_name' =>$form_state->getValue("admin_name"),
                  'admin_email' => $form_state->getValue("primary_email"),
                  'registration_date' =>date('Y-m-d', time()),
                )
              )->execute();
            $this->send_email($form_state->getValue("primary_email"));
            \Drupal::messenger()->addStatus(t('Your application is sent for review.Check your email for further details.'));
            /* print_r("Member Added in the system");
            print '<pre>';
            var_dump($form_state->getValue("org_name"));
            print '</pre>'; */
      }
    }
    private function send_email($to) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'fg_user_registration';
        $key = 'org_registration';
        $params['message'] = "Thank you for registaring with us. We will review your applcation and get back to you within two business days.\n\nReards,\nTeam";
        $params['title'] = "Registration application received";
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== true) {
          \Drupal::messenger()->addStatus(t('There was a problem sending your message and it was not sent.Use contact us page of our website for further communication.'), 'error');
        }
    }
}