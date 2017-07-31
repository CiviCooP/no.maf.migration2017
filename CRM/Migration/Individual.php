<?php

/**
 * Class for MAF Norge Individual Migration to CiviCRM
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @date 15 May 2017
 * @license AGPL-3.0
 */
class CRM_Migration_Individual extends CRM_Migration_Contact {

  protected function setApiParams() {
    $config = CRM_Migration_Config::singleton();
    $apiParams = parent::setApiParams();

    $apiParams['custom_'.$config->getPersonnummerCustomFieldId()] = $this->_sourceData['personsnummer'];
    $apiParams['custom_'.$config->getPrimaryContactForCommunicationCustomFieldId()] = $this->_sourceData['primary_contact_for_communication'];

    $apiParams['custom_'.$config->getReservertKaldPostCustomFieldId()] = $this->_sourceData['reserved_cold_mail'];
    $apiParams['custom_'.$config->getReservertKaldTelefonCustomFieldId()] = $this->_sourceData['reserved_cold_phone'];
    $apiParams['custom_'.$config->getReservertHumanitOrganisasjonerCustomFieldId()] = $this->_sourceData['reserved_humantarian_organisations'];
    $apiParams['custom_'.$config->getReservertSistOppdatertCustomFieldId()] = $this->_sourceData['reserved_last_updated'];

    return $apiParams;
  }

  /**
   * Returns a list with columns to ignore for setting api parameters.
   *
   * @return array
   */
  protected function getColumnsToIgnore() {
    $columnsToIgnore = array(
      'id',
      'contact_sub_type',
      'legal_identifier',
      'external_identifier',
      'sort_name',
      'display_name',
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
      'email_greeting_id',
      'email_greeting_custom',
      'email_greeting_display',
      'postal_greeting_id',
      'postal_greeting_custom',
      'postal_greeting_display',
      'addressee_id',
      'addressee_custom',
      'addressee_display',
      'address_id',
      'preferred_communication_method',
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
      'master_street_address',
      'master_street_number',
      'master_street_number_suffix',
      'master_street_name',
      'master_city',
      'master_country_id',
      'master_county_id',
      'master_state_province_id',
      'master_postal_code',
      'master_master_id',
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
      'primary_contact_for_communication',
      'date_added'
    );
    return $columnsToIgnore;
  }

}
