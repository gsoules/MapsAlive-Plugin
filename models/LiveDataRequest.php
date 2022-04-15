<?php

class LiveDataRequest
{
    const REQUEST_TYPE_HTML = 1;
    const REQUEST_TYPE_JSON = 2;

    protected $templateName = "";

    public function handleLiveDataRequest($requestType)
    {
        $itemIdentifiers = isset($_GET['items']) ? $_GET['items'] : 0;

        $errorMessage = $this->validateRequest($itemIdentifiers);
        if ($errorMessage)
        {
            $data = new class {};
            $data->error = $errorMessage;
            $response = json_encode($data);
            return $response;
        }

        $identifiers = explode(',', $itemIdentifiers);

        $identifierElementId = MapsAlive::getElementIdForElementName("Identifier");

        $items = [];
        foreach ($identifiers as $identifier)
        {
            $records = get_records('Item', array('search' => '', 'advanced' => array(array('element_id' => $identifierElementId, 'type' => 'is exactly', 'terms' => $identifier))));
            if (empty($records))
                $items[] = null;
            else
                $items[] = $records[0];
        }

        $parser = new TemplateCompiler();

        if ($requestType == self::REQUEST_TYPE_HTML)
            $response = $parser->emitTemplateLiveDataHtml($items, $this->templateName);
        else if ($requestType == self::REQUEST_TYPE_JSON)
            $response = $parser->emitTemplateLiveDataJson($items, $this->templateName);

        return $response;
    }

    function validateRequest($itemIdentifiers)
    {
        $itemIdentifiers = isset($_GET['items']) ? $_GET['items'] : 0;
        if ($itemIdentifiers == 0)
            return "No item identifier(s) provided";

        $this->templateName = isset($_GET['template']) ? $_GET['template'] : "";
        if ($this->templateName == "")
            return "No template name provided";

        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $this->templates = json_decode($raw, true);

        if (!array_key_exists($this->templateName, $this->templates))
            return "No such template name '$this->templateName'";

        return "";
    }
}
