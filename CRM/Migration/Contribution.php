<?php

/**
 * Class for MAF Norge Contribution Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 23 May 2017
 * @license AGPL-3.0
 */
class CRM_Migration_Contribution extends CRM_Migration_MAF {
  private $_contributionData = array();

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      // set generic data
      $this->generateContributionData();
      // process depending on type
      switch ($this->_sourceData['maf_type']) {
        case 'avtale':
          $created = $this->migrateAvtaleGiro();
          return $created;
          break;
          // printed
        case 'printed':
          $created = $this->migratePrintedGiro();
          return $created;
          break;
        case 'standalone':
          $created = $this->migrateStandAlone();
          return $created;
          break;
        default:
          return FALSE;
        }
    }
    return FALSE;
  }

  /**
   * Method to create a stand alone contribution
   *
   * @return bool|array
   */
  private function migrateStandAlone() {
    try {
      $newContribution = civicrm_api3('Contribution', 'create', $this->_contributionData);
      return $newContribution['values'][$newContribution['id']];
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not create contribution ID '.$this->_sourceData['id'].', error from API Contribution create');
      return FALSE;
    }
  }

  /**
   * Method to create a contribution for a printed giro
   *
   * @return bool|array
   */
  private function migratePrintedGiro() {
    // find new recurring id
    $sql = "SELECT new_recur_id FROM migration_recurring_contribution WHERE id = %1";
    $newRecurId = CRM_Core_DAO::singleValueQuery($sql, array(1 => array($this->_sourceData['contribution_recur_id'], 'Integer')));
    if ($newRecurId) {
      $this->_contributionData['source'] = $this->_contributionData['source'].' (Printed Giro '.$newRecurId.')';
    } else {
      $this->_contributionData['source'] = $this->_contributionData['source'].' (Printed Giro)';
    }

    try {
      $newContribution = civicrm_api3('Contribution', 'create', $this->_contributionData);
      return $newContribution['values'][$newContribution['id']];

    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not create contribution ID '.$this->_sourceData['id'].', error from API Contribution create');
      return FALSE;
    }
  }

  /**
   * Method to create an avtale contribution
   *
   * @return bool|array
   */
  private function migrateAvtaleGiro() {
    // find new recur, error if not exists
    $sql = "SELECT new_recur_id FROM migration_recurring_contribution WHERE id = %1";
    $newRecurId = CRM_Core_DAO::singleValueQuery($sql, array(1 => array($this->_sourceData['contribution_recur_id'], 'Integer')));
    if ($newRecurId) {
      $this->_contributionData['contribution_recur_id'] = $newRecurId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find recurring contribution for avtale contribution ID '.$this->_sourceData['id'].', not migrated');
      return FALSE;
    }
    // set payment instrument to RCUR
    $this->_contributionData['payment_instrument_id'] = "RCUR";
    try {
      $newContribution = civicrm_api3('Contribution', 'create', $this->_contributionData);
      return $newContribution['values'][$newContribution['id']];
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not create contribution ID '.$this->_sourceData['id'].', error from API Contribution create');
      return FALSE;
    }
  }

  /**
   * Implementation of method to validate if source data is good enough for contribution
   *
   * @return bool
   * @throws Exception when required custom table not found
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Contribution has no contact_id, not migrated. Contribution id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
       $this->_sourceData['contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', contribution not migrated');
      return FALSE;
    }
    // find payment instrument
    try {
      $count = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => 'payment_instrument',
        'value' => $this->_sourceData['payment_instrument_id'],
      ));
      if ($count == 0) {
        $this->_logger->logMessage('Error', 'Could not find payment instrument ID ' . $this->_sourceData['payment_instrument_id']
          . ' for contribution ID ' . $this->_sourceData['id'] . ', not migrated.');
        return FALSE;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not find payment instrument ID '.$this->_sourceData['payment_instrument_id']
        .' for contribution ID '.$this->_sourceData['id'].', not migrated.');
      return FALSE;
    }
    // find contribution status
    try {
      $count = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => 'contribution_status',
        'value' => $this->_sourceData['contribution_status_id'],
      ));
      if ($count == 0) {
        $this->_logger->logMessage('Error', 'Could not find contribution status ID ' . $this->_sourceData['contribution_status_id']
          . ' for contribution ID ' . $this->_sourceData['id'] . ', not migrated.');
        return FALSE;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not find contribution status ID '.$this->_sourceData['contribution_status_id']
        .' for contribution ID '.$this->_sourceData['id'].', not migrated.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Method to generate generic contribution data
   *
   */
  private function generateContributionData() {
    $config = CRM_Mafsepa_Config::singleton();
    $this->_contributionData = array(
      'contact_id' => $this->_sourceData['contact_id'],
      'financial_type_id' => $config->getDefaultMandateFinancialTypeId(),
      'payment_instrument_id' => $this->_sourceData['payment_instrument_id'],
      'receive_date' => $this->_sourceData['receive_date'],
      'currency' => $this->_sourceData['currency'],
      'contribution_status_id' => $this->_sourceData['contribution_status_id'],
      'campaign_id' => $this->earmarkingToCampaign($this->_sourceData['earmarking']),
      'check_number' => $this->_sourceData['check_number'],
    );
    if (empty($this->_sourceData['source'])) {
      $this->_contributionData['source'] = 'Migration 2017';
    } else {
      $this->_contributionData['source'] = $this->_sourceData['source'];
    }
    $emptyChecks = array('non_deductible_amount', 'total_amount', 'fee_amount', 'net_amount', 'trxn_id', 'invoice_id',
      'cancel_date', 'cancel_reason', 'receipt_date', 'thankyou_date', 'amount_level', 'is_pay_later', 'address_id',
      'tax_amount', 'creditnote_id', 'revenue_recognition_date', 'contribution_page_id');
    foreach ($emptyChecks as $emptyCheck) {
      if (isset($this->_sourceData[$emptyCheck]) && !empty($this->_sourceData[$emptyCheck])) {
        $this->_contributionData[$emptyCheck] = $this->_sourceData[$emptyCheck];
      }
    }
    return;
  }
}