<?php

class Relationships extends Derivative {

  /**
   * 
   * @param  $file 
   *   path to an fits xml file
   */
  function update_rels_from_tech($file) {

    $sxml = simplexml_load_file($file);
    $sxml->registerXPathNamespace('fits', "http://hul.harvard.edu/ois/xml/ns/fits/fits_output");
    $image_height = $sxml->xpath('//fits:imageHeight');
    $image_height = (string) $image_height[0];
    $image_width = $sxml->xpath('//fits:imageWidth');
    $image_width = (string) $image_width[0];

    $height_width_arr = array(
      'height' => $image_height,
      'width' => $image_width,
    );

    update_or_create_relsint($dsid, $height_width_arr);
  }

  /**
   * update the object so the RELS-INT datastream contains the height and width of the 
   * selected datastream.  We are using the standard islandora RELS-INT namespace
   * @param string $pid
   * @param string $dsid
   * @param array $height_width_arr 
   */
  function update_or_create_relsint($dsid, $height_width_arr) {
    if (!isset($height_width_arr['width']) || !isset($height_width_arr['height'])) {
      watchdog('islandora', t('Error adding RELS-INT stream for object %pid. no height or width specified', array('pid%' => $pid)));
    }
    $rels_int_str = <<<XML
    <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="info:fedora/XPID/XTIFF">
    <width xmlns="http://islandora.ca/ontology/relsint#">XWIDTH</width>
    <height xmlns="http://islandora.ca/ontology/relsint#">XHEIGHT</height>
  </rdf:Description>
</rdf:RDF>
XML;
    $item = $this->object($pid);
    if (!isset($item[$dsid])) {
      //no datastream to create a rels-int for
      return FALSE;
    }
    if (!isset($item['RELS-INT'])) {
      $to_replace = array('XPID', 'XWIDTH', 'XHEIGHT', 'XTIFF');
      $replace_with = array($pid, $height_width_arr['width'], $height_width_arr['height'], $dsid);
      $rels_int_str = str_replace($to_replace, $replace_with, $rels_int_str);
      try {
        $rels_int_ds = $item->constructDatastream('RELS-INT', 'X');
        $rels_int_ds->mimetype = 'text/xml';
        $rels_int_ds->label = 'RELS-INT';
        $rels_int_ds->content = $rels_int_str;
        $item->ingestDatastream($rels_int_ds); //create rels-int
      } catch (Exception $e) {
        watchdog('islandora', t('Error adding RELS-INT stream for object %pid', array('pid%' => $pid)));
      }
    }
    else {
      //we are assuming our entries do not exist as we have just tried to load this info
      $rels_ds = $item['RELS-INT'];
      $doc = DomDocument::loadXML($rels_ds->content);
      $rdf = $doc->documentElement;
      $description = $doc->createElement('rdf:Description');
      $about = $doc->createAttribute('rdf:about');
      $about->value = "info:fedora/$pid/$dsid";
      $description->appendChild($about);
      $width = $doc->createElement('width', $height_width_arr['width']);
      $height = $doc->createElement('height', $height_width_arr['width']);
      $width->setAttribute('xmlns', "http://islandora.ca/ontology/relsint#");
      $height->setAttribute('xmlns', "http://islandora.ca/ontology/relsint#");
      $description->appendChild($width);
      $description->appendChild($height);
      $rdf->appendChild($description);
      $xml = $doc->saveXML();
      $item['RELS-INT']->content = $xml;
    }
  }

}

?>
