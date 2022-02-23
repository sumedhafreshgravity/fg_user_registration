<?php

namespace Drupal\fg_user_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use \Drupal\Core\Url;
use  \Symfony\Component\HttpFoundation\RedirectResponse;


class ApplicationStatusForm extends FormBase {
/**
 * returns formID
 *
 * @return void
 */
  public function getFormId() {
    // Here we set a unique form id
    return 'application_status_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state,  $app_id = NULL) {
        $conn = \Drupal::database();
        $result = $conn->select('org_registration_request','t')->fields('t')
                ->condition('rid', $app_id, '=')
                ->execute();
        $record = $result->fetchAssoc();


        $form['org_rid'] = [
          '#type' => 'hidden',
          '#title' => t('Record ID :-'),
          '#value' =>$record["rid"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['org_name'] = [
          '#type' => 'hidden',
          '#title' => t('Record ID :-'),
          '#value' =>$record["org_name"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['org_displayname'] = [
          '#type' => 'textfield',
          '#title' => t('Orgnaization Name :-'),
          '#value' =>$record["org_displayname"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['app_date'] = [
          '#type' => 'textfield',
          '#title' => t('Registration date :-'),
          '#value' => $record["registration_date"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['org_website'] = [
          '#type' => 'textfield',
          '#title' => t('Website :-'),
          '#value' =>$record["org_website"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['admin_name'] = [
            '#type' => 'textfield',
            '#title' => t('Admin :-'),
            '#value' =>$record["admin_name"],
            '#attributes' => array('readonly' => 'readonly'),
          ];
        $form['primary_email'] = array(
            '#type' => 'textfield',
            '#title' => t('Admin Email :-'),
            '#value' =>$record["admin_email"],
            '#attributes' => array('readonly' => 'readonly'),
        );
        $form['app_status'] = array(
          '#type' => 'textfield',
          '#value' =>$record["org_status"],
          '#title' => t('Current Status :-'),
          '#attributes' => array('readonly' => 'readonly'),
        );
        $form['rejection_reason'] = array(
          '#type' => 'textarea',
          '#placeholder' => 'Reason for rejection (if applicable)',
          '#maxlength' => 300,
        );
        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => 'Mark as apporved',
        );
        $form['reject'] = array(
          '#type' => 'submit',
          '#submit' => array([$this,'rejectApplication']),
          '#value' => 'Mark as rejected',
        );

       return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {


  }

  public function rejectApplication(array &$form, FormStateInterface $form_state) {
     //naviage to the lisitng page
     $url = Url::fromUri('internal:/admin/config/registration/');
     $destination = $url->toString();
     $response = new RedirectResponse($destination);
     $response->send();

      // mark the organization as rejected in database and save the reason
      $conn = Database::getConnection();
      $num_updated = $conn->update('org_registration_request')
                    ->fields([
                      'org_status' => "Rejected",
                      'rejection_reason' => $form_state->getValue("rejection_reason")
                    ])
                    ->condition('rid',$form_state->getValue("org_rid") , '=')
                    ->execute();
      // Just sent an email to the organization with reason
        $message = "We are sorry to inform you that your applcation for registaring the organization with our portal has been rejected. \n\n";
        $message.= $form_state->getValue("rejection_reason");
        $title = "Registration application rejected.";

        $this->send_email($form_state->getValue("primary_email"),$message,$title);

        \Drupal::messenger()->addStatus(t('Email sent succefully.'));

  }
    /**
     * Chnages status of application from Pending to Approved.
     * Creates an organization and sends email to org admin.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $team_name = $form_state->getValue("org_name");
        //Check if team already exists
        $query = \Drupal::entityTypeManager()->getStorage('team')->getQuery()->condition('name', $team_name);

        if((bool) $query->count()->execute()) {
            \Drupal::logger('ORG Registration ')->notice("CompanyAlreadyExists");
            \Drupal::messenger()->addStatus(t('Orgnization already exists'));
            //TODO - ideally the applicatoin should be rejected in this situation ...
            return;
        }
        $conn = Database::getConnection();
        $num_updated = $conn->update('org_registration_request')
                      ->fields([
                        'org_status' => "Apporved",
                      ])
                      ->condition('rid',$form_state->getValue("org_rid") , '=')
                      ->execute();
        //Create organization in system and adding admin.
        // Create an Organization with given  values
          $team_display_name = $form_state->getValue("org_displayname");
          $team_storage = \Drupal::entityTypeManager()->getStorage('team');
          $team = $team_storage->create([
            'name' => $team_name,
            'displayName' => $team_display_name,
            'status' => 'active',
            'team_website' =>  $form_state->getValue("org_website")
          ]);
          $team->save();
        //naviage to the lisitng page
        $url = Url::fromUri('internal:/admin/config/registration/');
        $destination = $url->toString();
        $response = new RedirectResponse($destination);
        $response->send();

        //send an email after org creation
        $meeage = "Congradulations. Your organization has been registerd on our portal. You will receive detield email soon to register your account. \n\nReards,\nTeam";
        $title = "Registration application approved";
        $this->send_email($form_state->getValue("primary_email"),$meeage,$title);
        \Drupal::messenger()->addStatus(t('Organization created succesfully.'));
        //Invite member to join the team
        add_team_member($form_state->getValue("primary_email"),$team_name,"admin");

    }
    /**
     * Sends email message where ever applicable.
     */
    private function send_email($to, $message,$title) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'fg_user_registration';
        $key = 'org_registration';
        $params['message'] =$message;
        $params['title'] = $title;
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== true) {
          \Drupal::messenger()->addStatus(t('There was a problem sending your message and it was not sent.Use contact us page of our website for further communication.'), 'error');
        }
    }
}