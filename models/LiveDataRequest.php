<?php

class LiveDataRequest
{
    const DATA_SEPARATOR = '~~';

    protected $data = "";
    protected $itemIdentifiers = "";
    protected $templateName = "";
    protected $showWarnings = 0;

    public function errorResponse($errorMessage)
    {
        $data = new class {};
        $data->error = $errorMessage;
        $response = json_encode($data);
        return $response;
    }

    public function handleFindRequest($results)
    {
        $identifiers = [];

        // Create an array of the Identifiers for the results of the find request.
        foreach ($results as $result)
        {
            $identifiers[] = ItemMetadata::getItemIdentifier($result);
        }

        return json_encode($identifiers);
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
        $this->nonRepeatingIds = explode(',', $ids[0]);
        foreach ($this->nonRepeatingIds as $id)
        {
            $id = trim($id);
            if ($id == "" || $id == "0")
                continue;
            $item = MapsAlive::getItemForIdentifier($template['identifier'], $id);
            $nonRepeatingItems[] = array('item' => $item, 'id' => $id);
        }

        $repeatingItems = [];
        $this->repeatingIds = count($ids) == 1 || $ids[1] == "" ? [] : explode(',',  $ids[1]);
        foreach ($this->repeatingIds as $id)
        {
            $id = trim($id);
            if (trim($id) == "")
                continue;
            $item = MapsAlive::getItemForIdentifier($template['identifier'], $id);
            $repeatingItems[] = array('item' => $item, 'id' => $id);
        }

        $data = [];
        if ($this->data != "")
            $data = array_map('trim', explode(self::DATA_SEPARATOR, $this->data));

        // Create the Live Data response for the requested template and items.
        $parser = new TemplateCompiler();
        $response = $parser->emitTemplateLiveData($template, $nonRepeatingItems, $repeatingItems, $data, $this->showWarnings);

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

        $this->showWarnings = isset($_GET['warnings']) ? strtolower($_GET['warnings']) != "off" : true;

        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $this->templates = json_decode($raw, true);

        if (!array_key_exists($this->templateName, $this->templates))
            return "Unknown template '$this->templateName'";

        return "";
    }
}
