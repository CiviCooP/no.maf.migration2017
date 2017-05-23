<?php

/**
 * Class for MAF Norge Recurring Contribution Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 16 May 2017
 * @license AGPL-3.0
 */
class CRM_Migration_ContributionRecur extends CRM_Migration_MAF {

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      // process depending on payment_instrument_id
      switch ($this->_sourceData['payment_instrument_id']) {
        // avtale
        case 7:
          $created = $this->migrateAvtaleGiro();
          return $created;
          break;
          // printed
        case 12:
          $created = $this->migratePrintedGiro();
          return $created;
          break;
        default:
          return FALSE;
          break;
        }
    }
    return FALSE;
  }

  /**
   * Method to create a printed giro custom data
   *
   * @return bool|array
   */
  private function migratePrintedGiro() {
    $config = CRM_Mafsepa_Config::singleton();
    $startDate = new DateTime($this->_sourceData['start_date']);
    try {
      $frequency = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'maf_partners_frequency',
        'name' => $this->_sourceData['frequency_unit'],
        'return' => 'value',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      $frequency = 1;
      $this->_logger->logMessage('Warning', 'Frequency '.$this->_sourceData['frequency_unit']
        .' for printed giro ID '.$this->_sourceData['id'].' not found in CiviCRM, migrated as month! Needs to be checked');
    }
    $sqlParams =  array(
      1 => array($this->_sourceData['contact_id'], 'Integer',),
      2 => array($startDate->format('Ymd'), 'String',),
      3 => array($config->getDefaultFundraisingCampaignId(), 'Integer',),
      4 => array($frequency, 'Integer',),
      5 => array($this->_sourceData['amount'], 'Money'),
    );
    if (!empty($this->_sourceData['end_date'])) {
      $endDate = new DateTime($this->_sourceData['end_date']);
      $sqlParams[6] = array($endDate->format('Ymd'),'String');
      $sql = 'INSERT INTO civicrm_value_maf_partners_non_avtale (entity_id, maf_partners_start_date, 
      maf_partners_campaign, maf_partners_frequency, maf_partners_amount, maf_partners_end_date) VALUES(%1, %2, %3, %4, %5, %6)';
    } else {
      $sql = 'INSERT INTO civicrm_value_maf_partners_non_avtale (entity_id, maf_partners_start_date, 
      maf_partners_campaign, maf_partners_frequency, maf_partners_amount) VALUES(%1, %2, %3, %4, %5)';
    }
    try {
      CRM_Core_DAO::executeQuery($sql, $sqlParams);
      $latest = CRM_Core_DAO::executeQuery('SELECT id FROM civicrm_value_maf_partners_non_avtale ORDER BY id DESC LIMIT 1');
      $latest->fetch();
      return array('entity_id' => $latest->id);
    }
    catch (Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to create an avtale recurring contribution with avtale giro custom data
   *
   * @return bool|array
   */
  private function migrateAvtaleGiro() {
    $mandateData = $this->generateMandateData();
    if (!empty($mandateData)) {
      try {
        $mandate = civicrm_api3('SepaMandate', 'createfull', $mandateData);
        $avtaleData = $this->generateAvtaleData($mandate['values'][$mandate['id']]);
        if ($avtaleData == TRUE) {
          return $mandate['values'][$mandate['id']];
        } else {
          $this->_logger->logMessage('Error', 'Could not generate avtale data for recurring contribution ID '
            .$this->_sourceData['id'].', half migrated, mandate was created!');
          return FALSE;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Could not create the SEPA Mandate with recur ID '.$this->_sourceData['id']
          .', error from the SepaMandate createfull API: '.$ex->getMessage());
        return FALSE;
      }
    } else {
      $this->_logger->logMessage('Error', 'Could not generate mandate data for recurring contribution ID '
        .$this->_sourceData['id'].', not migrated!');
      return FALSE;
    }
  }

  /**
   * Implementation of method to validate if source data is good enough for contact
   *
   * @return bool
   * @throws Exception when required custom table not found
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Recurring contribution has no contact_id, not migrated. Recur id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
       $this->_sourceData['contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', recurring contribution not migrated');
      return FALSE;
    }
    // do not migrate if start and end date are equal
    if (!empty($this->_sourceData['end_date'])) {
      $startDate = new DateTime($this->_sourceData['start_date']);
      $endDate = new DateTime($this->_sourceData['end_date']);
      if ($startDate == $endDate) {
        $this->_logger->logMessage('Ignored', 'Start date and end date of the recurring contribution '.$this->_sourceData['id']
          .' are the same, ignored. (Contact '.$this->_sourceData['contact_id']);
        return FALSE;
      }
    }
    if (!CRM_Core_DAO::checkTableExists('civicrm_value_maf_avtale_giro')) {
      throw new Exception('Could not find table civicrm_value_maf_avtale_giro in '
        .__METHOD__.', contact your system administrator!');
    }
    if (!CRM_Core_DAO::checkTableExists('civicrm_value_maf_partners_non_avtale')) {
      throw new Exception('Could not find table civicrm_value_maf_partners_non_avtale in '
        .__METHOD__.', contact your system administrator!');
    }
    return TRUE;
  }

  /**
   * Method to generate the data for a sdd mandate
   *
   * @return array
   * @throws Exception
   */
  private function generateMandateData() {
    $mandateData = array();
    $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if (!empty($creditor)) {
      $config = CRM_Mafsepa_Config::singleton();
      $defaultCampaignId = $config->getDefaultFundraisingCampaignId();
      if (!empty($defaultCampaignId)) {
        try {
          $reference = $this->generateUniqueReference();
          $mandateData = array(
            'creditor_id' => $creditor->creditor_id,
            'contact_id' => $this->_sourceData['contact_id'],
            'financial_type_id' => 1,
            'status' => $this->_sourceData['contribution_status_id'],
            'type' => $config->getDefaultMandateType(),
            'currency' => $this->_sourceData['currency'],
            'source' => 'Migration 2017',
            'reference' => $reference,
            'kid' => $reference,
            'frequency_interval' => $this->_sourceData['frequency_interval'],
            'frequency_unit' => $this->_sourceData['frequency_unit'],
            'amount' => $this->_sourceData['amount'],
            'campaign_id' => $defaultCampaignId,
            'cycle_day' => $this->_sourceData['cycle_day'],
          );
          if (isset($this->_sourceData['start_date']) && !empty($this->_sourceData['start_date'])) {
            $startDate = new DateTime($this->_sourceData['start_date']);
            $mandateData['start_date'] = $startDate->format('d-m-Y');
          }
          if (isset($this->_sourceData['end_date']) && !empty($this->_sourceData['end_date'])) {
            $endDate = new DateTime($this->_sourceData['end_date']);
            $mandateData['end_date'] = $endDate->format('d-m-Y');
          }
          if (isset($this->_sourceData['create_date']) && !empty($this->_sourceData['create_date'])) {
            $createDate = new DateTime($this->_sourceData['create_date']);
            $mandateData['creation_date'] = $createDate->format('d-m-Y');
            $mandateData['validation_date'] = $createDate->format('d-m-Y');
          }
        } catch (CiviCRM_API3_Exception $ex) {
        }
      }
    }
    return $mandateData;
  }

  /**
   * Method to generate avtale data
   *
   * @param $mandate
   * @return bool
   */
  private function generateAvtaleData($mandate) {
    $config = CRM_Mafsepa_Config::singleton();
    $tableName = $config->getAvtaleGiroCustomGroup('table_name');
    $maxAmountCustomField = $config->getAvtaleGiroCustomField('maf_maximum_amount');
    $notificationCustomField = $config->getAvtaleGiroCustomField('maf_notification_bank');
    $sql = 'INSERT INTO ' . $tableName . ' (entity_id, ' . $maxAmountCustomField['column_name'] . ', ' . $notificationCustomField['column_name']
      . ') VALUES(%1, %2, %3)';
    $sqlParams = array(
      1 => array($mandate['entity_id'], 'Integer',),
      2 => array($this->_sourceData['maximum_amount'], 'Money',),
      3 => array($this->_sourceData['notification_for_bank'], 'Integer',),);
    try {
      CRM_Core_DAO::executeQuery($sql, $sqlParams);
      return TRUE;
    }
    catch (Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to generate a unique reference for the mandate if the contact has more than one
   *
   * @return string
   */
  private function generateUniqueReference() {
    $config = CRM_Mafsepa_Config::singleton();
    $defaultCampaignId = $config->getDefaultFundraisingCampaignId();
    $kid = civicrm_api3('Kid', 'generate', array(
      'contact_id' => $this->_sourceData['contact_id'],
      'campaign_id' => $defaultCampaignId,
    ));
    $reference = $kid['kid_number'];
    // first check if mandate reference already exists and if so, add letter until it does not
    $unique = FALSE;
    $suffixes = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h');
    while ($unique == FALSE) {
      $count = civicrm_api3('SepaMandate', 'getcount', array(
        'reference' => $reference,
      ));
      if ($count > 0) {
        $reference = $kid['kid_number'].current($suffixes);
        next($suffixes);
      } else {
        $unique = TRUE;
      }
    }
    return $reference;
  }
}