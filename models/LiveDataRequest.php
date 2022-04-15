<?php

class LiveDataRequest
{
    const REQUEST_TYPE_HTML = 1;
    const REQUEST_TYPE_JSON = 2;

    public function handleLiveDataRequest($requestType)
    {
        $itemIdentifiers = isset($_GET['items']) ? $_GET['items'] : 0;
        if ($itemIdentifiers == 0)
            return "No item identifier(s) provided";

        $templateName = isset($_GET['template']) ? $_GET['template'] : "";
        if ($templateName == "")
            return "No template name provided";

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
            $response = $parser->emitTemplateLiveDataHtml($items, $templateName);
        else if ($requestType == self::REQUEST_TYPE_JSON)
            $response = $parser->emitTemplateLiveDataJson($items, $templateName);

        return $response;
    }
}
