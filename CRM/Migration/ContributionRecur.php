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
    $printedGiroData = $this->generatePrintedGiroData();
    if (!empty($printedGiroData)) {
      $sql = 'INSERT INTO civicrm_value_maf_partners_non_avtale (entity_id, maf_partners_start_date, 
        maf_partners_campaign, maf_partners_frequency, maf_partners_amount, maf_partners_end_date) 
        VALUES(%1, %2, %3, %4, %5, %6)';
      CRM_Core_DAO::executeQuery($sql, $printedGiroData);
    } else {
      $this->_logger->logMessage('Error', 'Could not generate printed giro data for recurring contribution ID '
        .$this->_sourceData['id'].', not migrated!');
    }
  }

  /**
   * Method to generate the data for the printed giro
   *
   * @return array
   */
  private function generatePrintedGiroData() {
    return array(
      1 => array($this->_sourceData['contact_id'], 'Integer',),
      2 => array($this->_sourceData['start_date'], 'String',),
      3 => array($this->_sourceData['contribution_campaign_id'], 'Integer',),
      4 => array($this->_sourceData['frequency_unit'], 'Integer',),
      5 => array($this->_sourceData['amount'], 'Money'),
      6 => array($this->_sourceData['end_date'], 'String')
    );
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
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Could not create the SEPA Mandate, error from the SepaMandate createfull API: '.$ex->getMessage());
        return FALSE;
      }
      $avtaleData = $this->generateAvtaleData($mandate);
      if (!empty($avtaleData)) {
        $config = CRM_Mafsepa_Config::singleton();
        $tableName = $config->getAvtaleGiroCustomGroup('table_name');
        $maxAmountCustomField = $config->getAvtaleGiroCustomField('maf_maximum_amount');
        $notificationCustomField = $config->getAvtaleGiroCustomField('maf_notification_bank');
        $sql = 'INSERT INTO ' . $tableName . ' (entity_id, ' . $maxAmountCustomField['column_name'] . ', ' . $notificationCustomField['column_name']
          . ') VALUES(%1, %2, %3)';
        CRM_Core_DAO::executeQuery($sql, $avtaleData);
        return $mandate;
      } else {
        $this->_logger->logMessage('Error', 'Could not generate avtale data for recurring contribution ID '
          .$this->_sourceData['id'].', half migrated, mandate was created!');
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
    if (!CRM_Core_DAO::checkTableExists('civicrm_value_maf_avtale_giro') == FALSE) {
      throw new Exception('Could not find table civicrm_value_maf_partners_non_avtale in '
        .__METHOD__.', contact your system administrator!');
    }
    if (!CRM_Core_DAO::checkTableExists('civicrm_value_maf_avtale_giro') == FALSE) {
      throw new Exception('Could not find table civicrm_value_maf_partners_non_avtale in '
        .__METHOD__.', contact your system administrator!');
    }
    return TRUE;
  }
}