<?php

/**
 * Class to build PREMIS events.
 * TODO: Finish this class
 * 
 * @author Richard Wincewicz
 */
class PREMIS {

  public $identifierType = NULL;
  public $identifierValue = NULL;
  public $type = NULL;
  public $date = NULL;
  public $outcome = NULL;
  public $outcomeDetail = NULL;

  function __construct() {
    $this->date = date("c");
  }

  function build() {
    $premis_xml = new DOMDocument();
    $event = $premis_xml->createElementNS("info:lc/xmlns/premis-v2", 'event');

    $identifier = $premis_xml->createElement('eventIdentifier');

    $identifier_type = $premis_xml->createElement('eventIdentifierType', $this->identifierType);
    $identifier->appendChild($identifier_type);

    $identifier_value = $premis_xml->createElement('eventIdentifierValue', $this->identifierValue);
    $identifier->appendChild($identifier_value);

    $event->appendChild($identifier);

    $event_type = $premis_xml->createElement('eventType', $this->type);
    $event->appendChild($event_type);

    $event_date = $premis_xml->createElement('eventDateTime', $this->date);
    $event->appendChild($event_date);

    $outcome_information = $premis_xml->createElement('eventOutcomeInformation');

    $outcome = $premis_xml->createElement('eventOutcome', $this->outcome);
    $outcome_information->appendChild($outcome);

    $outcome_detail = $premis_xml->createElement('eventOutcomeDetail');

    $outcome_detail_note = $premis_xml->createElement('eventOutcomeDetailNote', $this->outcomeDetail);
    $outcome_detail->appendChild($outcome_detail_note);

    $outcome_information->appendChild($outcome_detail);

    $event->appendChild($outcome_information);

    $premis_xml->appendChild($event);
    
    $event->setAttributeNS("http://www.w3.org/2001/XMLSchema-instance", "xsi:schemaLocation", "info:lc/xmlns/premis-v2 http://www.loc.gov/standards/premis/premis.xsd");

    return $premis_xml->saveXML();
  }

}

?>