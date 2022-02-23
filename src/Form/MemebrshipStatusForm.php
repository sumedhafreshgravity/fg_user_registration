<?php

namespace Drupal\fg_user_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use \Drupal\Core\Url;
use  \Symfony\Component\HttpFoundation\RedirectResponse;


class MemebrshipStatusForm extends FormBase {
/**
 * returns formID
 *
 * @return void
 */
  public function getFormId() {
    // Here we set a unique form id
    return 'membership_status_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state,  $app_id = NULL) {
        $conn = \Drupal::database();
        $result = $conn->select('org_membership_request','t')->fields('t')
                ->condition('rid', $app_id, '=')
                ->execute();
        $record = $result->fetchAssoc();


        $form['member_rid'] = [
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
        $form['member_name'] = [
          '#type' => 'textfield',
          '#title' => t('Memeber Name :-'),
          '#value' =>$record["member_name"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['member_email'] = [
          '#type' => 'textfield',
          '#title' => t('Member Email :-'),
          '#value' =>$record["member_email"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['app_date'] = [
          '#type' => 'textfield',
          '#title' => t('Application date :-'),
          '#value' => $record["application_date"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
        $form['membership_status'] = [
          '#type' => 'textfield',
          '#title' => t('Mebership  Status -'),
          '#value' =>$record["membership_status"],
          '#attributes' => array('readonly' => 'readonly'),
        ];
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
    // Here we decide whether the form can be forwarded to the submitForm()
    // function or should be sent back to the user to fix some information.
  }

  public function rejectApplication(array &$form, FormStateInterface $form_state) {
     //naviage to the lisitng page
     $org_name = $form_state->getValue("org_name");
     $url = Url::fromUri('internal:/teams/'.$org_name.'/members');
     $destination = $url->toString();
     $response = new RedirectResponse($destination);
     $response->send();
      // mark the organization as rejected in database and save the reason
      $conn = Database::getConnection();
      $num_updated = $conn->update('org_membership_request')
                    ->fields([
                      'membership_status' => "Rejected",
                      'rejection_reason' => $form_state->getValue("rejection_reason")
                    ])
                    ->condition('rid',$form_state->getValue("member_rid") , '=')
                    ->execute();
      // Just sent an email to the organization with reason
        $message = "We are sorry to inform you that your applcation for membership the organization. \n\n";
        $message.= $form_state->getValue("rejection_reason");
        $title = "Mebership application rejected.";

        $this->send_email($form_state->getValue("member_email"),$message,$title);

        \Drupal::messenger()->addStatus(t('Email sent succefully.'));

  }
    /**
     * Chnages status of application from Pending to Approved.
     * Creates an organization and sends email to org admin.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $team_name = $form_state->getValue("org_name");
        $url = Url::fromUri('internal:/teams/'.$team_name.'/members');
        $destination = $url->toString();
        $response = new RedirectResponse($destination);
        $response->send();
        // chekc if mebership already exisits
        $conn = Database::getConnection();
        $num_updated = $conn->update('org_membership_request')
                      ->fields([
                        'membership_status' => "Apporved",
                      ])
                      ->condition('rid',$form_state->getValue("member_rid") , '=')
                      ->execute();
        //send an email after membershipapproved
        $meeage = "Congradulations. Your membership has been approved. You will receive detield email soon to register your account. \n\nReards,\nTeam";
        $title = "Memebership application approved";
        $this->send_email($form_state->getValue("member_email"),$meeage,$title);
        //Invite member to join the team
        add_team_member($form_state->getValue("member_email"),$team_name,"member");

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