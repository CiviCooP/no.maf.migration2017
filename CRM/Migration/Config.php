<?php
/**
 * Class following Singleton pattern for specific extension configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 18 April 2017
 * @license AGPL-3.0
 */
class CRM_Migration_Config {

  private static $_singleton;
  private $_defaultPostalIndividual = NULL;
  private $_defaultPostalOrganization = NULL;
  private $_defaultPostalHousehold = NULL;
  private $_defaultEmailIndividual = NULL;
  private $_defaultEmailOrganization = NULL;
  private $_defaultEmailHousehold = NULL;
  private $_defaultAddresseeIndividual = NULL;
  private $_defaultAddresseeOrganization = NULL;
  private $_defaultAddresseeHousehold = NULL;
  private $_defaultLocationTypeId = NULL;
  private $_mobilePhoneTypeId = NULL;
  private $_personnummerCustomFieldId = NULL;
	private $_takkebrevPrPostCustomFieldId = NULL;
  private $_primaryContactForCommunicationCustomFieldId = NULL;

  private $_reservertKaldPostCustomFieldId = NULL;
  private $_reservertKaldTelefonCustomFieldId = NULL;
  private $_reservertHumanitOrganisasjonerCustomFieldId = NULL;
  private $_reservertSistOppdatertCustomFieldId = NULL;

  private $_oldContactIdHistoryType = NULL;

  /**
   * Constructor method
   *
   * @param string $context
   */
  function __construct($context) {
    try {
      $this->_defaultEmailHousehold = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'email_greeting',
        'filter'=> 2,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultEmailIndividual = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'email_greeting',
        'filter'=> 1,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultEmailOrganization = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'email_greeting',
        'filter'=> 3,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultPostalHousehold = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'postal_greeting',
        'filter'=> 2,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultPostalIndividual = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'postal_greeting',
        'filter'=> 1,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultPostalOrganization = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'postal_greeting',
        'filter'=> 3,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultAddresseeHousehold = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'addressee',
        'filter'=> 2,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultAddresseeIndividual = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'addressee',
        'filter'=> 1,
        'is_default' => 1,
        'return' => 'value',
      ));
      $this->_defaultAddresseeOrganization = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'addressee',
        'filter'=> 3,
        'is_default' => 1,
        'return' => 'value',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {

    }

    $this->_defaultLocationTypeId = civicrm_api3('LocationType', 'getvalue', array(
      'is_default' => 1,
      'return' => 'id'
    ));

    $this->_mobilePhoneTypeId = civicrm_api3('OptionValue', 'getvalue', array(
      'option_group_id' => 'phone_type',
      'name'=> 'Mobile',
      'return' => 'value',
    ));

    $this->_personnummerCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'personnummer',
      'custom_group_id' => 'maf_person',
      'return' => 'id',
    ));
		$this->_takkebrevPrPostCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'takkebrev_pr_post',
      'custom_group_id' => 'maf_person',
      'return' => 'id',
    ));
    $this->_primaryContactForCommunicationCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'primary_contact_for_communication',
      'custom_group_id' => 'maf_person',
      'return' => 'id',
    ));

    $this->_organisationnummerCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'organisasjonsnummer',
      'custom_group_id' => 'maf_organisation',
      'return' => 'id',
    ));

    $this->_reservertKaldPostCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'reservert_kald_post',
      'custom_group_id' => 'reservasjonsregisteret',
      'return' => 'id',
    ));
    $this->_reservertKaldTelefonCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'reservert_kald_telefon',
      'custom_group_id' => 'reservasjonsregisteret',
      'return' => 'id',
    ));
    $this->_reservertHumanitOrganisasjonerCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'reservert_humanit_organisasjoner',
      'custom_group_id' => 'reservasjonsregisteret',
      'return' => 'id',
    ));
    $this->_reservertSistOppdatertCustomFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'name' => 'reservert_sist_oppdatert',
      'custom_group_id' => 'reservasjonsregisteret',
      'return' => 'id',
    ));

    try {
      $this->_oldContactIdHistoryType = civicrm_api3('OptionValue', 'getvalue', array(
        'name' => 'original_contact_id',
        'return' => 'value',
        'option_group_id' => "contact_id_history_type",
      ));
    } catch (Exception $e) {
      civicrm_api3('OptionValue', 'create', array(
        'name' => 'original_contact_id',
        'value' => 'original_contact_id',
        'label' => 'Original contact ID',
        'option_group_id' => "contact_id_history_type",
      ));

      $this->_oldContactIdHistoryType = civicrm_api3('OptionValue', 'getvalue', array(
        'name' => 'original_contact_id',
        'return' => 'value',
        'option_group_id' => "contact_id_history_type",
      ));
    }
  }

  /**
   * Getter for original contact id type value.
   *
   * @return array|null
   */
  public function getOriginalContactIdType() {
    return $this->_oldContactIdHistoryType;
  }

  /**
   * Getter for custom field id of field reservert_kald_post
   * @return array|null
   */
  public function getReservertKaldPostCustomFieldId() {
    return $this->_reservertKaldPostCustomFieldId;
  }

  /**
   * Getter for custom field id of field reservert_kald_telefon
   * @return array|null
   */
  public function getReservertKaldTelefonCustomFieldId() {
    return $this->_reservertKaldTelefonCustomFieldId;
  }

  /**
   * Getter for custom field id of field reservert_humanit_organisasjoner
   * @return array|null
   */
  public function getReservertHumanitOrganisasjonerCustomFieldId() {
    return $this->_reservertHumanitOrganisasjonerCustomFieldId;
  }

  /**
   * Getter for custom field id of field reservert_sist_oppdatert
   * @return array|null
   */
  public function getReservertSistOppdatertCustomFieldId() {
    return $this->_reservertSistOppdatertCustomFieldId;
  }

  /**
   * Getter for custom field id of field Personnummer
   * @return array|null
   */
  public function getPersonnummerCustomFieldId() {
    return $this->_personnummerCustomFieldId;
  }
	
	/**
   * Getter for custom field id of field Takkebrev Pr. Post.
   * @return array|null
   */
  public function getTakkebrevPrPostCustomFieldId() {
    return $this->_takkebrevPrPostCustomFieldId;
  }

  /**
   * Getter for custom field id of field primary contact for communication.
   * @return array|null
   */
  public function getPrimaryContactForCommunicationCustomFieldId() {
    return $this->_primaryContactForCommunicationCustomFieldId;
  }

  /**
   * Getter for custom field id of field Organisationnummer
   * @return array|null
   */
  public function getOrganisationnummerCustomFieldId() {
    return $this->_organisationnummerCustomFieldId;
  }

  /**
   * Getter for mobile phone type id.
   *
   * @return array|null
   */
  public function getMobilePhoneTypeId() {
    return $this->_mobilePhoneTypeId;
  }

  /**
   * Getter for default email greeting id household
   * @return array|null
   */
  public function getDefaultEmailHousehold() {
    return $this->_defaultEmailHousehold;
  }

  /**
   * Getter for default email greeting id individual
   * @return array|null
   */
  public function getDefaultEmailIndividual() {
    return $this->_defaultEmailIndividual;
  }

  /**
   * Getter for default email greeting id organization
   * @return array|null
   */
  public function getDefaultEmailOrganization() {
    return $this->_defaultEmailOrganization;
  }

  /**
   * Getter for default postal greeting id household
   * @return array|null
   */
  public function getDefaultPostalHousehold() {
    return $this->_defaultPostalHousehold;
  }

  /**
   * Getter for default postal greeting id individual
   * @return array|null
   */
  public function getDefaultPostalIndividual() {
    return $this->_defaultPostalIndividual;
  }

  /**
   * Getter for default postal greeting id organization
   * @return array|null
   */
  public function getDefaultPostalOrganization() {
    return $this->_defaultPostalOrganization;
  }

  /**
   * Getter for default addressee id household
   * @return array|null
   */
  public function getDefaultAddresseeHousehold() {
    return $this->_defaultAddresseeHousehold;
  }

  /**
   * Getter for default addressee id individual
   * @return array|null
   */
  public function getDefaultAddresseeIndividual() {
    return $this->_defaultAddresseeIndividual;
  }

  /**
   * Getter for default addressee id organization
   * @return array|null
   */
  public function getDefaultAddresseeOrganization() {
    return $this->_defaultAddresseeOrganization;
  }

  /**
   * Getter for default location type id.
   *
   * @return array|null
   */
  public function getDefaultLocationTypeId() {
    return $this->_defaultLocationTypeId;
  }


  /**
   * Singleton method
   *
   * @param string $context to determine if triggered from install hook
   * @return CRM_Migration_Config
   * @access public
   * @static
   */
  public static function singleton($context = null) {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Migration_Config($context);
    }
    return self::$_singleton;
  }
}
