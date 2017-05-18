<?php

/**
 * Class for MAF Norge Individual Migration to CiviCRM
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @date 15 May 2017
 * @license AGPL-3.0
 */
class CRM_Migration_Individual extends CRM_Migration_MAF {

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      try {
        $apiParams = $this->setApiParams();
        $created = civicrm_api3('Contact', 'create', $apiParams);
        $this->addMobile($created['id']);
        $this->addAddress($created['id']);
        $this->addCustomData();
        return $created;
      } catch (CiviCRM_API3_Exception $ex) {
        $message = 'Could not add or update contact, error from API Contact create: '.$ex->getMessage().'. Source data is ';
        $paramMessage = array();
        foreach ($apiParams as $paramKey => $paramValue) {
          $paramMessage[] = $paramKey.' with value '.$paramValue;
        }
        $message .= implode('; ', $paramMessage);
        $this->_logger->logMessage('Error', $message);
        return FALSE;
      }
    }
    return false;
  }
  /**
   * Method to create params for contact create (remove id as we need a new contact)
   */
  private function setApiParams() {
    $config = CRM_Migration_Config::singleton();
    $columnsToIgnore = array(
      'id',
      'contact_sub_type',
      'legal_identifier',
      'external_identifier',
      'sort_name',
      'display_name',
      'nick_name',
      'legal_name',
      'image_URL',
      'preferred_language',
      'hash',
      'api_key',
      'source',
      'household_name',
      'primary_contact_id',
      'organization_name',
      'sic_code',
      'user_unique_id',
      'employer_id',
      'is_deleted',
      'created_date',
      'modified_date',
      'address_id',
      'street_address',
      'street_number',
      'street_number_suffix',
      'street_name',
      'city',
      'country_id',
      'county_id',
      'state_province_id',
      'postal_code',
      'master_id',
      'new_contact_id',
      'is_processed',
      'personsnummer',
      'mobile',
      'contact_source_maf_source',
      'contact_source_date',
      'contact_source_motivation',
      'contact_source_note',
      'reserved_cold_mail',
      'reserved_cold_phone',
      'reserved_humantarian_organisations',
      'reserved_last_updated',
      'primary_contact_for_communication'
    );

    $apiParams = $this->_sourceData;
    foreach ($apiParams as $paramKey => $paramValue) {
      if (is_array($paramValue)) {
        unset($apiParams[$paramKey]);
      }
    }
    foreach ($columnsToIgnore as $removeKey) {
      unset($apiParams[$removeKey]);
    }

    if (isset($this->_sourceData['new_contact_id']) && !empty($this->_sourceData['new_contact_id'])) {
      $apiParams['id'] = $this->_sourceData['new_contact_id'];
    }

    $apiParams['custom_'.$config->getPersonnummerCustomFieldId()] = $this->_sourceData['personsnummer'];
    $apiParams['custom_'.$config->getPrimaryContactForCommunicationCustomFieldId()] = $this->_sourceData['primary_contact_for_communication'];

    $apiParams['custom_'.$config->getReservertKaldPostCustomFieldId()] = $this->_sourceData['reserved_cold_mail'];
    $apiParams['custom_'.$config->getReservertKaldTelefonCustomFieldId()] = $this->_sourceData['reserved_cold_phone'];
    $apiParams['custom_'.$config->getReservertHumanitOrganisasjonerCustomFieldId()] = $this->_sourceData['reserved_humantarian_organisations'];
    $apiParams['custom_'.$config->getReservertSistOppdatertCustomFieldId()] = $this->_sourceData['reserved_last_updated'];

    return $apiParams;
  }

  private function addMobile($contact_id) {
    if (isset($this->_sourceData['mobile']) && !empty($this->_sourceData['mobile'])) {
      return;
    }

    $config = CRM_Migration_Config::singleton();
    $apiParams = array();

    // Retrieve current primary phone if it exists.
    try {
      $apiParams['id'] = civicrm_api3('Phone', 'getvalue', array(
        'contact_id' => $contact_id,
        'is_primary' => '1',
        'return' => 'id'
      ));
    } catch (Exception $e) {
      // Do nothing
    }

    $apiParams['contact_id'] = $contact_id;
    $apiParams['phone'] = $this->_sourceData['mobile'];
    $apiParams['phone_type_id'] = $config->getMobilePhoneTypeId();
    try {
      civicrm_api3('Phone', 'create', $apiParams);
    } catch (Exception $e) {
      $this->_logger->logMessage('Warning', 'Could not add a mobile for contact '
        .$this->_sourceData['display_name'].', mobile ignored. Reason for failing: '.$e->getMessage());
    }
  }

  private function addAddress($contact_id) {
    $config = CRM_Migration_Config::singleton();
    $address_fields = array(
      'street_address',
      'street_number',
      'street_number_suffix',
      'street_name',
      'city',
      'country_id',
      'county_id',
      'state_province_id',
      'postal_code',
    );

    if (isset($this->_sourceData['master_id']) && !empty($this->_sourceData['master_id'])) {
      $this->_logger->logMessage('Warning', 'Could not add an address because it is linked to another address for contact '
        .$this->_sourceData['display_name'].', address ignored');
      return;
    }

    $apiParams = array();

    foreach($address_fields as $field) {
      if (isset($this->_sourceData[$field]) && !empty($this->_sourceData[$field])) {
        $apiParams[$field] = $this->_sourceData[$field];
      }
    }

    if (empty($apiParams)) {
      // No data to set.
      return;
    }

    $apiParams['contact_id'] = $contact_id;
    $apiParams['location_type_id'] = $config->getDefaultLocationTypeId();

    // Retrieve current primary phone if it exists.
    try {
      $apiParams['id'] = civicrm_api3('Address', 'getvalue', array(
        'contact_id' => $contact_id,
        'is_primary' => '1',
        'return' => 'id'
      ));
    } catch (Exception $e) {
      // Do nothing
    }

    try {
      civicrm_api3('Address', 'create', $apiParams);
    } catch (Exception $e) {
      $this->_logger->logMessage('Warning', 'Could not add an address for contact '
        .$this->_sourceData['display_name'].' because '.$e->getMessage().', address ignored');
    }
  }

  /**
   * Implementation of method to validate if source data is good enough for contact
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['id'])) {
      $this->_logger->logMessage('Error', 'Contact has no contact_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    // check address
    if (!$this->checkAddress()) {
      $this->_logger->logMessage('Error', 'Contact has invalid address data (master id is set and we don\'t know how to process this yet) , not migrated. Source data is '.implode(';', $this->_sourceData));
      return false;
    }
    // check if email and postal greeting exists, if not use default
    $this->checkGreeting();
    return TRUE;
  }

  /**
   * Method to check whether the address data is valid.
   *
   * @return bool
   */
  protected function checkAddress() {
    if (!empty($this->_sourceData['country_id'])) {
      try {
        $count = civicrm_api3('Country', 'getcount', array('id' => $this->_sourceData['country_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', 'Address with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid country_id (' . $count . ' of ' . $this->_sourceData['country_id']
            . 'found), address created without country');
          $this->_sourceData['country_id'] = 0;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error retrieving country from CiviCRM for address with contact_id '
          . $this->_sourceData['contact_id'] . ' and country_id' . $this->_sourceData['country_id']
          . ', address migrated without country. Error from API Country getcount : ' . $ex->getMessage());
        $this->_sourceData['country_id'] = 0;
      }
    }
    return true;
  }

  /**
   * Method to check if the email and/or postal greeting exist and are valid for the contact types involved. If not, use default
   */
  protected function checkGreeting() {
    $config = CRM_Migration_Config::singleton();
    $defaultEmail = NULL;
    $defaultPostal = NULL;
    $filter = NULL;
    // warning if both email greeting custom and id set
    if (!empty($this->_sourceData['email_greeting_custom']) && !empty($this->_sourceData['email_greeting_id'])) {
      $this->_logger->logMessage('Warning', 'Both email_greeting_id and email_greeting_custom set for contact '
        .$this->_sourceData['display_name'].', email_greeting_id ignored');
      $this->_sourceData['email_greeting_id'] = NULL;
    }
    // warning if both postal greeting custom and id set
    if (!empty($this->_sourceData['postal_greeting_custom']) && !empty($this->_sourceData['postal_greeting_id'])) {
      $this->_logger->logMessage('Warning', 'Both postal_greeting_id and postal_greeting_custom set for contact '
        .$this->_sourceData['display_name'].', postal_greeting_id ignored');
      $this->_sourceData['postal_greeting_id'] = NULL;
    }

    if (!empty($this->_sourceData['addressee_custom']) && !empty($this->_sourceData['addressee_id'])) {
      $this->_logger->logMessage('Warning', 'Both addressee_id and addressee_custom set for contact '
        .$this->_sourceData['display_name'].', postal_greeting_id ignored');
      $this->_sourceData['postal_greeting_id'] = NULL;
    }

    // set filter based on contact type
    switch ($this->_sourceData['contact_type']) {
      case "Individual":
        $filter = 1;
        $defaultEmail = $config->getDefaultEmailIndividual();
        $defaultPostal = $config->getDefaultPostalIndividual();
        $defaultAddressee = $config->getDefaultAddresseeIndividual();
        break;
      case "Household":
        $filter = 2;
        $defaultEmail = $config->getDefaultEmailHousehold();
        $defaultPostal = $config->getDefaultPostalHousehold();
        $defaultAddressee = $config->getDefaultAddresseeHousehold();
        break;
      case "Organization":
        $filter = 3;
        $defaultEmail = $config->getDefaultEmailOrganization();
        $defaultPostal = $config->getDefaultPostalOrganization();
        $defaultAddressee = $config->getDefaultAddresseeOrganization();
        break;
    }
    // check email greeting
    if (isset($this->_sourceData['email_greeting_id']) && !empty($this->_sourceData['email_greeting_id'])) {
      try {
        $emailCount = civicrm_api3('OptionValue', 'getcount', array(
          'option_group_id' => 'email_greeting',
          'value' => $this->_sourceData['email_greeting_id'],
          'filter' => $filter,
        ));
        if ($emailCount == 0) {
          $this->_logger->logMessage('Warning', 'Could not find email_greeting_id '.$this->_sourceData['email_greeting_id']
            .' for contact with name '.$this->_sourceData['display_name'].' and contact type '.$this->_sourceData['contact_type']
            .', replaced with the default email greeting id '.$defaultEmail);
          $this->_sourceData['email_greeting_id'] = $defaultEmail;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error from API OptionValue getcount in '.__METHOD__.' for email_greeting_id '
          .$this->_sourceData['email_greeting_id'].', contact with name '.$this->_sourceData['display_name'].' and contact type '
          .$this->_sourceData['contact_type'].', replaced with the default email greeting id '.$defaultEmail);
        $this->_sourceData['email_greeting_id'] = $defaultEmail;
      }
    }
    // check postal greeting
    if (isset($this->_sourceData['postal_greeting_id']) && !empty($this->_sourceData['postal_greeting_id'])) {
      try {
        $postalCount = civicrm_api3('OptionValue', 'getcount', array(
          'option_group_id' => 'postal_greeting',
          'value' => $this->_sourceData['postal_greeting_id'],
          'filter' => $filter,
        ));
        if ($postalCount == 0) {
          $this->_logger->logMessage('Warning', 'Could not find postal_greeting_id '.$this->_sourceData['postal_greeting_id']
            .' for contact with name '.$this->_sourceData['display_name'].' and contact type '.$this->_sourceData['contact_type']
            .', replaced with the default postal greeting id '.$defaultPostal);
          $this->_sourceData['postal_greeting_id'] = $defaultPostal;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error from API OptionValue getcount in '.__METHOD__.' for postal_greeting_id '
          .$this->_sourceData['postal_greeting_id'].', contact with name '.$this->_sourceData['display_name'].' and contact type '
          .$this->_sourceData['contact_type'].', replaced with the default postal greeting id '.$defaultPostal);
        $this->_sourceData['postal_greeting_id'] = $defaultPostal;
      }
    }
    // check addressee
    if (isset($this->_sourceData['addressee_id']) && !empty($this->_sourceData['addressee_id'])) {
      try {
        $postalCount = civicrm_api3('OptionValue', 'getcount', array(
          'option_group_id' => 'addressee',
          'value' => $this->_sourceData['addressee_id'],
          'filter' => $filter,
        ));
        if ($postalCount == 0) {
          $this->_logger->logMessage('Warning', 'Could not find addressee_id '.$this->_sourceData['addressee_id']
            .' for contact with name '.$this->_sourceData['display_name'].' and contact type '.$this->_sourceData['contact_type']
            .', replaced with the default addressee id '.$defaultAddressee);
          $this->_sourceData['addressee_id'] = $defaultAddressee;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error from API OptionValue getcount in '.__METHOD__.' for addressee_id '
          .$this->_sourceData['addressee_id'].', contact with name '.$this->_sourceData['display_name'].' and contact type '
          .$this->_sourceData['contact_type'].', replaced with the default addressee id '.$defaultAddressee);
        $this->_sourceData['addressee_id'] = $defaultAddressee;
      }
    }
  }

  /**
   * Method to add contact custom data if necessary
   *
   * @access private
   */
  private function addCustomData() {
  }
}