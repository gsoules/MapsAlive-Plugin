<?php

class LiveDataRequest
{
    const DATA_SEPARATOR = '~~';

    protected $data = "";
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
        $ids = explode(';', $this->itemIdentifiers);

        $nonRepeatingItems = [];
        $nonRepeatingIds = explode(',', $ids[0]);
        foreach ($nonRepeatingIds as $id)
        {
            $id = trim($id);
            if ($id == "" || $id == "0")
                continue;
            $nonRepeatingItems[] = MapsAlive::getItemForIdentifier($template['identifier'], $id);
        }

        $repeatingItems = [];
        $repeatingIds = count($ids) == 1 || $ids[1] == "" ? [] : explode(',',  $ids[1]);
        foreach ($repeatingIds as $id)
        {
            $id = trim($id);
            if (trim($id) == "")
                continue;
            $repeatingItems[] = MapsAlive::getItemForIdentifier($template['identifier'], $id);
        }

        $data = [];
        if ($this->data != "")
            $data = array_map('trim', explode(self::DATA_SEPARATOR, $this->data));

        // Create the Live Data response for the requested template and items.
        $parser = new TemplateCompiler();
        $response = $parser->emitTemplateLiveData($template, $nonRepeatingItems, $repeatingItems, $data);

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

        $this->templateName = isset($_GET['template']) ? $_GET['template'] : "";
        if ($this->templateName == "")
            return "No template name provided";

        $this->data = isset($_GET['data']) ? $_GET['data'] : "";

        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $this->templates = json_decode($raw, true);

        if (!array_key_exists($this->templateName, $this->templates))
            return "Unknown template '$this->templateName'";

        return "";
    }
}
