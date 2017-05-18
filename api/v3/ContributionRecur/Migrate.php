<?php


/**
 * ContributionRecur.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'contributionrecur';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 1000;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_recurring_contribution WHERE is_processed = 0 ORDER BY id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $civiRecur = new CRM_Migration_ContributionRecur($entity, $daoSource, $logger);
    $newMandate = $civiRecur->migrate();
    if ($newMandate == FALSE) {
      $logCount++;
      $updateQuery = 'UPDATE migration_recurring_contribution SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
    } else {
      $createCount++;
      $updateQuery = 'UPDATE migration_recurring_contribution SET is_processed = %1, new_recur_id = %2 WHERE id = %3';
      CRM_Core_DAO::executeQuery($updateQuery, array(
        1 => array(1, 'Integer',),
        2 => array($newMandate['entity_id'], 'Integer',),
        3 => array($daoSource->id, 'Integer',),));
    }
  }

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more recurring contributions to migrate';
  } else {
    $returnValues[] = $createCount.' recurring contributions migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'ContributionRecur', 'Migrate');}
