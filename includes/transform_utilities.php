<?php


/**
 *
 * @param $eml_path
 *   The path to the eml file
 * @param $itis_string
 *   The string returned from the itis service
 *
 * @return int
 *
 */
function transform_service_eml_with_itis($eml_path, $itis_string) {
  $eml_doc = new DOMDocument();
  $eml_doc->load($eml_path);
  $itis_doc = new DOMDocument();
  $itis_doc->loadXML($itis_string);
  $xpath = new DOMXPath($itis_doc);
  $xpath->registerNamespace('ax21', 'http://data.itis_service.itis.usgs.gov/xsd');
  $parent_tsn = $xpath->query('//ax21:rankName[text() = "Order"]/../ax21:parentTsn');
  $parent_tsn = $parent_tsn->item(0)->nodeValue;
  $key_value_arr = Array();
  transform_service_populate_key_value($xpath, $parent_tsn, $key_value_arr);
  $coverage = $eml_doc->getElementsByTagName('taxonomicCoverage');
  $coverage = $coverage->item(0);
  foreach ($key_value_arr as $key => $value) {
    $taxon_rank = $eml_doc->createElement('taxonRankName', $key);
    $taxon_value = $eml_doc->createElement('taxonRankValue', $value);
    $classification = $eml_doc->createElement('taxonomicClassification');
    $classification->appendChild($taxon_rank);
    $classification->appendChild($taxon_value);
    $coverage->appendChild($classification);
  }
  return $eml_doc->save($eml_path);
}

/**
 * @param $xpath
 *
 * @param $parent_tsn
 * @param $key_value_arr
 */
function transform_service_populate_key_value($xpath, $parent_tsn, &$key_value_arr) {
  $new_parent_tsn = $xpath->query('//ax21:tsn[text() = "' . $parent_tsn . '"]/../ax21:parentTsn');
  $new_parent_tsn = $new_parent_tsn->item(0)->nodeValue;
  if (!empty($new_parent_tsn)) {
    $key = $xpath->query('//ax21:tsn[text() = "' . $parent_tsn . '"]/../ax21:rankName');
    $key = $key->item(0)->nodeValue;
    $value = $xpath->query('//ax21:tsn[text() = "' . $parent_tsn . '"]/../ax21:taxonName');
    $value = $value->item(0)->nodeValue;
    $key_value_arr[$key] = $value;
    transform_service_populate_key_value($xpath, $new_parent_tsn, $key_value_arr);
  }
}

