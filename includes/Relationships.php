<?php

require_once 'Derivatives.php';

class Relationship extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Update the object so the RELS-INT datastream contains the height and width of the
   * selected datastream.  We are using the standard islandora RELS-EXT namespace
   * @param string $dsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   *
   * @return int|string
   */
  function addImageDimensionsToRels($dsid, $label = 'RELS-INT') {
    $item = $this->fedora_object;
    $return = MS_SYSTEM_EXCEPTION;
    $source_dsid = $this->incoming_dsid;
    $height_width_arr = getimagesize($this->temp_file);
    if ($height_width_arr === FALSE) {
      $this->log->lwrite('Error reading image height and width', 'PROCESS_DATASTREAM', $this->pid, $dsid);
      return 'Error reading image height and width for datastream';
    }
    $log_message = "$dsid derivative created using PHP  with command - getImageSize || SUCCESS";
    $rels_int_str = <<<XML
    <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="info:fedora/XPID/XTIFF">
    <width xmlns="http://islandora.ca/ontology/relsext#">XWIDTH</width>
    <height xmlns="http://islandora.ca/ontology/relsext#">XHEIGHT</height>
  </rdf:Description>
</rdf:RDF>
XML;

    if (!isset($item[$source_dsid])) {
      //no datastream to reference in RELS
      $this->log->lwrite('Source does not exist aborting', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return MS_SUCCESS;//return success for now as we don't want to loop as this maybe unrecoverable as there is no datastream to get the height and width from
    }
    if (!isset($item[$dsid])) {
      $to_replace = array('XPID', 'XWIDTH', 'XHEIGHT', 'XTIFF');
      $replace_with = array($item->id, $height_width_arr[0], $height_width_arr[1], $source_dsid);
      $rels_int_str = str_replace($to_replace, $replace_with, $rels_int_str);
      $return = $this->add_derivative($dsid, $label, $rels_int_str, 'text/xml', $log_message, FALSE, FALSE, 'X');
    }
    else {
      $rels_ds = $item[$dsid];
      $doc = DomDocument::loadXML($rels_ds->content);
      $rdf = $doc->documentElement;
      $descriptions = $rdf->getElementsByTagName('Description');
      foreach ($descriptions as $description) {
        $about = $description->getAttribute('rdf:about');
        $length = strlen($source_dsid);
        if (substr($about, -$length) === $source_dsid) {
          $this->log->lwrite('Relationship already exists aborting', 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid);
          return MS_SUCCESS; //we tell taverna everything is ok as we don't want it to try again
        }
      }
      //TODO update this to use tuque relationship functions
      $description = $doc->createElement('rdf:Description');
      $about = $doc->createAttribute('rdf:about');
      $about->value = "info:fedora/$item->id/$source_dsid";
      $description->appendChild($about);
      $width = $doc->createElement('width', $height_width_arr[0]);
      $height = $doc->createElement('height', $height_width_arr[1]);
      $width->setAttribute('xmlns', "http://islandora.ca/ontology/relsext#");
      $height->setAttribute('xmlns', "http://islandora.ca/ontology/relsext#");
      $description->appendChild($width);
      $description->appendChild($height);
      $rdf->appendChild($description);
      $xml = $doc->saveXML();
      try{
        $return = $this->add_derivative($dsid, $label, $xml, 'text/xml', $log_message, FALSE, FALSE, 'X');
      } catch (Exception $e){
        $return = MS_FEDORA_EXCEPTION;
        $this->log->lwrite('Error updating repository', 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid);
      }
    }
    return $return;
  }

  /**
   * Update the object RELS-EXT datastream to add given cmodel type.
   * @param string $outputdsid
   *    The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   * @return int|string
   */
  function addCModelToObject($outputdsid, $label = 'RELS-EXT', $params) {
    $item = $this->fedora_object;
    $cmodel = $params['cmodel'];
    $return = MS_SUCCESS;
    try{
      $item->relationships->add('info:fedora/fedora-system:def/model#', 'hasModel', $cmodel);
      $this->log->lwrite("$cmodel CModel relationship successfully added.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    } catch (Exception $e){
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("$cmodel CModel relationship failed to add.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }

  /**
   * TAkes an old Drupal 6 pageCmodel rels-ext and corrects namespaces.
   * Also changes some relationships to use literals.
   * @param $outputdsid
   * @param string $label
   * @param $params
   */
  function correctPageRelsExt($outputdsid, $label = "RELS-EXT", $params) {
    $page_object = $this->fedora_object;
    $page_number_arr = $page_object->relationships->get("info:islandora/islandora-system:def/pageinfo#", 'isPageNumber');
    if (empty($page_number_arr)) {
      // This script may have already been run and updated the isPageNumber namespace so we'll try with the new namespace
      $page_number_arr = $page_object->relationships->get("http://islandora.ca/ontology/relsext#", 'isPageNumber');
    }
    $page_number = $page_number_arr[0]['object']['value'];
    $this->log->lwrite('Started correctPageRelsExt function with page number ' + $page_number,
      'PROCESS_DATASTREAM', $this->pid, $outputdsid);

    // Assumes only one parent
    $parent_pid_arr = $page_object->relationships->get("info:fedora/fedora-system:def/relations-external#", "isMemberOf");
    $parent_pid = $parent_pid_arr[0]['object']['value'];
    $return = MS_SUCCESS;

    try {
      $page_object->relationships->remove('info:islandora/islandora-system:def/pageinfo#', 'isSequenceNumber');
      $page_object->relationships->remove("info:islandora/islandora-system:def/pageinfo#", 'isPageNumber');
      $page_object->relationships->remove("info:islandora/islandora-system:def/pageinfo#", 'isPageOf');
      $page_object->relationships->remove('http://islandora.ca/ontology/relsext#', 'isSequenceNumber');
      $page_object->relationships->remove("http://islandora.ca/ontology/relsext#", 'isPageNumber');
      $page_object->relationships->remove("http://islandora.ca/ontology/relsext#", 'isPageOf');
      $page_object->relationships->remove("http://islandora.ca/ontology/relsext#", 'hasLanguage');
      $page_object->relationships->add("http://islandora.ca/ontology/relsext#", 'isPageNumber', $page_number, TRUE);
      $page_object->relationships->add("http://islandora.ca/ontology/relsext#", 'isSequenceNumber', $page_number, TRUE);
      $page_object->relationships->add("http://islandora.ca/ontology/relsext#", "hasLanguage", "eng", TRUE);
      $page_object->relationships->add("http://islandora.ca/ontology/relsext#", "isPageOf", $parent_pid, FALSE);
      $this->log->lwrite("updated RELS-EXT relationships successfully added.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    }
    catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Failed updating relationships.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }

  /**
   * Update the object RELS-EXT datastream to remove given cmodel type. We are using the standard islandora RELS-EXT namespace
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param $params
   * @return int|string
   */
  function removeCModelFromObject($outputdsid, $label, $params) {
    $item = $this->fedora_object;
    $cmodel = $params['cmodel'];
    $return = MS_SUCCESS;
    try {
      $item->relationships->remove('info:fedora/fedora-system:def/model#', 'hasModel', $cmodel);
      $this->log->lwrite("$cmodel CModel relationship successfully removed.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    } catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("$cmodel CModel relationship failed to remove.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }
}

