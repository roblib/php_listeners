<?php

class Relationship extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * update the object so the RELS-INT datastream contains the height and width of the 
   * selected datastream.  We are using the standard islandora RELS-INT namespace
   * @param string $dsid
   * @param string $label
   */
  function AddImageDimensionsToRels($dsid, $label = 'RELS-INT') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $item = $this->fedora_object;
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
    <width xmlns="http://islandora.ca/ontology/relsint#">XWIDTH</width>
    <height xmlns="http://islandora.ca/ontology/relsint#">XHEIGHT</height>
  </rdf:Description>
</rdf:RDF>
XML;

    if (!isset($item[$source_dsid])) {
      //no datastream to reference in RELS
      $this->log->lwrite('Source does not exist aborting', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return 'no source datastream';
    }
    if (!isset($item[$dsid])) {
      $to_replace = array('XPID', 'XWIDTH', 'XHEIGHT', 'XTIFF');
      $replace_with = array($item->id, $height_width_arr[0], $height_width_arr[1], $source_dsid);
      $rels_int_str = str_replace($to_replace, $replace_with, $rels_int_str);
      try{
        $return = $this->add_derivative($dsid, $label, $rels_int_str, 'text/xml', $log_message, FALSE, FALSE, 'X');
      } catch (Exception $e){
        $this->log->lwrite('Error updating repository', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      }
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
          $this->log->lwrite('Relationship already exists aborting', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
          return 'Relationship already Exists';
        }
      }
      $description = $doc->createElement('rdf:Description');
      $about = $doc->createAttribute('rdf:about');
      $about->value = "info:fedora/$item->id/$source_dsid";
      $description->appendChild($about);
      $width = $doc->createElement('width', $height_width_arr[0]);
      $height = $doc->createElement('height', $height_width_arr[1]);
      $width->setAttribute('xmlns', "http://islandora.ca/ontology/relsint#");
      $height->setAttribute('xmlns', "http://islandora.ca/ontology/relsint#");
      $description->appendChild($width);
      $description->appendChild($height);
      $rdf->appendChild($description);
      $xml = $doc->saveXML();
      try{
        $return = $this->add_derivative($dsid, $label, $xml, 'text/xml', $log_message, FALSE, FALSE, 'X');
      } catch (Exception $e){
        $this->log->lwrite('Error updating repository', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      }
    }
    return $return;
  }

}

