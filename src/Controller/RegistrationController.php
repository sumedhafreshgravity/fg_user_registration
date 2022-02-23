<?php
namespace Drupal\fg_user_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Component\Render\FormattableMarkup;
use \Drupal\Core\Url;

/**
 * Provides route responses for the Example module.
 */
class RegistrationController extends ControllerBase {

  /**
   * Stores application in database.
   * displays the application on
   * @return array
   *
   */
  public function applications() {
    $conn = Database::getConnection();

        // Prepare _sortable_ table header
        $header = array(
            array('data' => t('Organization Name'), 'field' => 'org_displayname'),
            array('data' => t('Status'), 'field' => 'org_status'),
            array('data' => t('Website'), 'field' => 'org_website'),
            array('data' => t('Admin Name'), 'field' => 'admin_name'),
            array('data' => t('Admin Email'), 'field' => 'admin_email'),
            array('data' => t('View'),'field' => 'rid', 'sort' => 'desc'),
        );

        $query =  $conn->select('org_registration_request','reg');
        $query->fields('reg', array('org_displayname','org_status','org_website','admin_name','admin_email','rid'));
        $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
        $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
        $result = $pager->execute();
        $records =  $result->fetchAll();
        $build = array(
          '#markup' => "<h2>".t("View Applcations") ."</h2>"
        );
        $num_results = count($records);
        if($num_results == 0) {
          $build["message"] = array(
            '#markup' => t('No Applcations.')
          );
          return $build;
        }
        $pending_count = 0;
        $url = Url::fromUri('internal:/applicationstatus/');
        foreach($records as $row) {
            $a_row = (array) $row;
            if($a_row['org_status']== "Pending") {
              $a_row['rid'] =  array('data' => new FormattableMarkup('<a href=":link">@name</a>',
              [':link' => $url->toString()  . $a_row['rid'],
              '@name' => "Approve/Reject"]));
              $pending_count += 1;
            } else {
              $a_row['rid']= array('data' => '-');
            }
            $rows[] = array('data' => $a_row);
        }
        $message = "";
        if($pending_count == 0 ){
            $message ="No Pending applciations.";
        }else {
          $message ="<h4>" . $pending_count . "  Pending applciation(s). </h4><br>";
        }
        $build["message"] = array(
            '#markup' => t($message)
        );

        $build['registration_table'] = array(
            '#theme' => 'table', '#header' => $header,
            '#rows' => $rows
        );
        $build['pager'] = array(
        '#type' => 'pager'
        );
        return $build;
  }

}