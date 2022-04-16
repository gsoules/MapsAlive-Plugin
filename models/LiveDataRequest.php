<?php

class LiveDataRequest
{
    const REQUEST_TYPE_HTML = 1;
    const REQUEST_TYPE_JSON = 2;

    protected $itemIdentifiers = "";
    protected $templateName = "";

    public function errorResponse($errorMessage)
    {
        $data = new class {};
        $data->error = $errorMessage;
        $response = json_encode($data);
        return $response;
    }

    public function handleLiveDataRequest()
    {
        // Validate the request arguments.
        $errorMessage = $this->validateRequest();
        if ($errorMessage)
            return $this->errorResponse($errorMessage);

        // Get the requested template from the database.
        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $this->templates = json_decode($raw, true);
        $template = $this->templates[$this->templateName];

        // Create an array of Omeka items corresponding to the requested item identifiers.
        // If no item is found for an identifier, its slot in the items array will be null;
        $items = [];
        $identifiers = explode(',', $this->itemIdentifiers);
        foreach ($identifiers as $identifier)
            $items[] = MapsAlive::getItemForIdentifier($template['identifier'], $identifier);

        // Create the Live Data response for the requested template and items.
        $parser = new TemplateCompiler();
        $response = $parser->emitTemplateLiveData($items, $template);

        return $response;
    }

    function validateRequest()
    {
        $this->itemIdentifiers = isset($_GET['items']) ? $_GET['items'] : "";

        if ($this->itemIdentifiers == "")
            return "No item identifier(s) provided";

        $this->templateName = isset($_GET['template']) ? $_GET['template'] : "";
        if ($this->templateName == "")
            return "No template name provided";

        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $this->templates = json_decode($raw, true);

        if (!array_key_exists($this->templateName, $this->templates))
            return "Unknown template '$this->templateName'";

        return "";
    }
}
