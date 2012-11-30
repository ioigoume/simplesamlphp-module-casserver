<?php

class sspmod_sbcasserver_Cas_TicketStore_AttributeStoreTicketStore extends sspmod_sbcasserver_Cas_TicketStore_TicketStore {

  private $attributeStoreUrl;
  private $attributeStorePrefix;

  public function __construct($config) {
    parent::__construct($config);

    $storeConfig = $config->getValue('ticketstore');

    if(!is_string($storeConfig['attributeStoreUrl'])) {
      throw new Exception('Missing or invalid attributeStoreUrl option in config.');
    }

    if(!is_string($storeConfig['attributeStorePrefix'])) {
      throw new Exception('Missing or invalid attributeStorePrefix option in config.');
    }

    $this->attributeStoreUrl = preg_replace('/\/$/','',$storeConfig['attributeStoreUrl']);
    $this->attributeStorePrefix = $storeConfig['attributeStorePrefix'];
  }

  protected function generateTicketId() {
    return str_replace( '_', 'ST-', SimpleSAML_Utilities::generateID() );
  }

  protected function validateTicketId($ticket) {
    if (!preg_match('/^(ST|PT|PGT)-?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');
  }

  protected function retrieveTicket($ticket) {

    $scopedTicketId = $this->scopeTicketId($ticket);

    $content = $this->getTicketFromAttributeStore($scopedTicketId);

    if (is_null($content)) {
      throw new Exception('Could not find ticket');
    } else {
      return $content['value'];
    }
  }

  protected function storeTicket($ticket, $value) {
    $scopedTicketId = $this->scopeTicketId($ticket);

    $this->addTicketToAttributeStore($scopedTicketId, $value);
  }

  protected function deleteTicket($ticket) {
    /*    $filename = $this->pathToTicketDirectory . '/' . $ticket;

    if (file_exists($filename)) {
      unlink($filename);
    }
    */
  }

  private function getTicketFromAttributeStore($scopedTicketId) {
    $getParameters = array('http' => array('method' => 'GET', 'header' => array('Content-Type: application/json'),
                                           'ignore_errors' => true));

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: looking up ticket: ' . var_export($scopedTicketId, TRUE));

    $getUrl = $this->attributeStoreUrl.'/'.urlencode($scopedTicketId);

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: get url: ' . var_export($getUrl, TRUE));

    $context = stream_context_create($getParameters);
    $response = file_get_contents($getUrl, false, $context);

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: response: ' . var_export($response, TRUE));

    if(!is_null($response && $response != '')) {
      $attribute = json_decode($response, true);

      return json_decode($attribute['value']);
    } else {
      return null;
    }
  }

  private function addTicketToAttributeStore($scopedTicketId, $content) {
    $attribute = array('key' => $scopedTicketId, 'value' => json_encode($content));

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: adding ticket: ' . var_export($scopedTicketId, TRUE) . ' with content: '. var_export($content, TRUE));

    $postParameters = array('http' => array('method' => 'POST', 'header' => array('Content-Type: application/json'),
                                            'content' => json_encode($attribute),'ignore_errors' => true));

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: posting: ' . var_export($postParameters, TRUE));

    $context = stream_context_create($postParameters);
    $response = file_get_contents($this->attributeStoreUrl, false, $context);

    SimpleSAML_Logger::debug('AttributeStoreTicketStore: response: ' . var_export($response, TRUE));

    return $response;
  }

  private function scopeTicketId($ticketId) {
    return urlencode($this->attributeStorePrefix.'.'.$ticketId);
  }

  private function unscopeTicketId($ticketId) {
    return str_replace($this->attributeStorePrefix.'.','',urldecode($ticketId));
  }
  }
?>