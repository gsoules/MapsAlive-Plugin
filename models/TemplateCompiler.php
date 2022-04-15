<?php

class TemplateCompiler
{
    protected $templateRowNumber;
    protected $templatesRowNumber = 0;
    protected $templateName = "";
    protected $parsedTextTemplates = [];

    protected function compileTemplate($templateName, $rows)
    {
        // Initialize class variables used to keep track of which template and which row is being parsed.
        $this->parsedTextTemplates[$templateName] = [];
        $this->templateName = $templateName;
        $this->templateRowNumber = 0;

        // Process each template row to validate its specifiers and to translate element names to element Ids.
        // Keep track of the row number solely for the purpose of reporting validation errors.
        foreach ($rows as $row)
        {
            $this->templateRowNumber += 1;
            $parsedRow = $this->parseTemplateRow($row, true);

            // Add the parsed row to the template.
            $this->parsedTextTemplates[$templateName][] = $parsedRow;
        }
    }

    public function compileTemplates($text)
    {
        $templates = [];
        $rows = explode(PHP_EOL, $text);

        // Loop over every row in the configuration page's templates text area.
        // Identify which rows below to which templates and create an array of templates.
        foreach ($rows as $row)
        {
            $this->templatesRowNumber += 1;

            // Skip blank rows.
            if (trim($row) == "")
                continue;

            // Start creating a new template if this row specifies "Template: <template-name>".
            if ($this->isTemplateDefinitionRow($row))
            {
                if (array_key_exists($this->templateName, $templates))
                    throw new Omeka_Validate_Exception(__('Template name "%s" on line %s has already been defined.', $this->templateName, $this->templatesRowNumber));

                $templates[$this->templateName] = [];
                continue;
            }

            // Add the row to the rows for the current template.
            $templates[$this->templateName][] = $row;
        }

        // Process each text template to validate its specifiers and to translate element names to element Ids.
        foreach ($templates as $templateName => $rows)
        {
            $this->compileTemplate($templateName, $rows);
        }

        // Create a JSON array of templates to be stored in the database.
        return json_encode($this->parsedTextTemplates);
    }

    public function emitTemplateLiveDataHtml($items, $templateName)
    {
        $raw =  get_option(MapsAliveConfig::OPTION_TEMPLATES);
        $templates = json_decode($raw, true);

        if (!array_key_exists($templateName, $templates))
            return "No such template name '$templateName'";

        $rows = $templates[$templateName];

        $html = "";

        foreach ($rows as $row)
        {
            $remaining = $row;
            while (true)
            {
                // Look for a specifier in the remaining text on this row.
                $start = strpos($remaining, '${');
                if ($start === false)
                {
                    // There's no specifier. Keep the rest of the row text and go onto the next.
                    $html .= $remaining;
                    break;
                }
                $end = strpos($remaining, '}');
                $end += 1;

                // Get the specifier including the ${...} wrapper.
                $specifier = substr($remaining, $start, $end - $start);

                // Replace the entire specifier with a data value.
                $replacement = $this->replaceSpecifierWithLiveData($items, $specifier);
                $html .= substr($remaining, 0, $start);
                $html .= $replacement;

                $remaining = substr($remaining, $end);
            }
        }

        return $html;
    }

    public function emitTemplateLiveDataJson($items, $templateName)
    {
        $html = $this->emitTemplateLiveDataHtml($items, $templateName);

        $data = new class {};
        $data->id = "1100";
        $data->html = $html;

        $response = json_encode($data);
        return $response;
    }

    protected function errorPrefix()
    {
        return __('Error on line %s of template "%s": ', $this->templateRowNumber, $this->templateName);
    }

    protected function isTemplateDefinitionRow($row)
    {
        $args = array_map('trim', explode(':', $row));
        if (count($args) < 2 || strtolower($args[0]) != 'template')
            return false;
        $this->templateName = $args[1];

        if (!$this->validateDefinitionName($this->templateName))
            throw new Omeka_Validate_Exception(__('Template name "%s" on line %s must contain only alphanumeric characters and underscore.', $this->templateName, $this->templatesRowNumber));

        if ($this->templateName == "")
            throw new Omeka_Validate_Exception(__('No template name specified on line %s.', $this->templatesRowNumber));

        $index = strpos($this->templateName, ' ');
        if ($index !== false)
            $this->templateName = substr($this->templateName, 0, $index);
        return true;
    }

    protected function isValidIndex($value)
    {
        if (!is_numeric($value))
            return false;
        return intval($value) >= 1;
    }

    protected function parseSpecifier($specifier)
    {
        // Get the content of the specifier (the part that's between the curly braces) and split it into its arguments.
        $content = substr($specifier, 2, strlen($specifier) - 3);
        $args = array_map('trim', explode(',', $content));
        return $args;
    }

    protected function parseTemplateRow($row, $compiling)
    {
        // This method translates the specifiers in a template row from one form to another. When compiling a
        // template's text from the configuration page, it translates element names to element Ids. The result
        // can then be saved in the datebase as JSON. When used to uncompile a template from the database, it
        // translates element Ids to element names so that the template can be displayed on the configuration page.

        $textRemainingOnRow = $row;
        $parsedText = "";

        // Parse the row's text from left to right until no more ${...} specifiers are found.
        // on the row. Initially no text has been parsed and the remaining text is the entire row.

        while (true)
        {
            $start = strpos($textRemainingOnRow, '${');
            if ($start === false)
            {
                // There's no specifier in the remaining text.
                $parsedText .= $textRemainingOnRow;
                break;
            }
            else
            {
                // Find the end of the current specifier. It is required to be on the same line as the start.
                $end = strpos($textRemainingOnRow, '}');
                if ($end === false)
                    throw new Omeka_Validate_Exception($this->errorPrefix() . __('Closing "}" is missing'));

                // Get the complete specifier including its opening '${' and closing '}'.
                $end += 1;
                $specifier = substr($textRemainingOnRow, $start, $end - $start);

                // Translate an element name to an element Id when compiling or vice-versa when uncompiling.
                $translatedSpecifier = $this->translateSpecifier($specifier, $compiling);

                // Replace the original specifier with the translated specifier.
                $parsedText .= substr($textRemainingOnRow, 0, $start);
                $parsedText .= $translatedSpecifier;

                // Reduce the remaining text to what's left after the just-translated specifier.
                $textRemainingOnRow = substr($textRemainingOnRow, $end);
            }
        }

        return $parsedText;
    }

    protected function replaceSpecifierWithLiveData($items, $specifier)
    {
        // This method replaces a specifier with a data value in response to a Live Data request.

        $parts = $this->parseSpecifier($specifier);
        $argsCount = count($parts);

        $elementId = $parts[0];

        if ($elementId == 'file-url')
        {
            $derivativeSize = $parts[1];

            // When the item index is greater than the number of items specified, return an empty string.
            $itemIndex = $argsCount > 2 ? $parts[2] - 1 : 0;
            if ($itemIndex > count($items) - 1)
                return "";

            // Get the specified item.
            $item = $items[$itemIndex];

            // Get the file index or use 1 if no index was specified.
            $fileIndex = $argsCount > 3 ? $parts[3] - 1 : 0;

            // Get the URL for the specified file at the specified size. An empty string will be returned if the item
            // does not have a file attachment, or does not have as many attachments as specified by the index.
            $value = MapsAlive::getItemFileUrl($item, $derivativeSize, $fileIndex);
            return $value;
        }

        // When the item index is greater than the number of items specified, return an empty string.
        $itemIndex = $argsCount > 1 ? $parts[1] - 1 : 0;
        if ($itemIndex > count($items) - 1)
            return "";

        // Get the item identified by specifier's item index.
        $item = $items[$itemIndex];

        // Get the requested value.
        if ($elementId == 'item-url')
            $value = WEB_ROOT . '/items/show/' . $item->id;
        else
            $value = MapsAlive::getElementTextFromElementId($item, $elementId);

        return $value;
    }

    protected function translateSpecifier($specifier, $compiling)
    {
        // When compiling a template, this method translate an element name within a specifier ${...} to an
        // element Id. When uncompiling, it translates an element Id to an element name. If the specifier is
        // file-url or item-url, this method only validates the specifier, but does no translation.

        // Break the specifier into its individual arguments.
        $args = $this->parseSpecifier($specifier);
        $argsCount = count($args);

        if ($argsCount == 1 && $args[0] == "")
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('No specifier provided.'));

        $firstArg = $args[0];

        if ($compiling)
        {
            // Validate that the specifier has the correct number of arguments and that they are valid.
            // If any problems are discovered, the validation method will throw a validation exception.
            // For valid file-url and item-url specifiers, return the specifier as-is.
            if ($firstArg == 'file-url')
            {
                $this->validateFileUrlSpecifier($args);
                return $specifier;
            }

            if ($firstArg == 'item-url')
            {
                $this->validateItemUrlSpecifier($args);
                return $specifier;
            }

            $this->validateElementSpecifier($args);

            // Replace the element name with its element Id.
            $elementId = MapsAlive::getElementIdForElementName($firstArg);
            if ($elementId == 0)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not an element.', $firstArg));

            $args[0] = $elementId;
        }
        else
        {
            if ($firstArg == 'file-url' || $firstArg == 'item-url')
                return $specifier;

            // Replace the element Id with the element name.
            $elementName = MapsAlive::getElementNameForElementId($firstArg);
            $args[0] = $elementName;
        }

        // Reconstruct the specifier with the element name converted to an Id or vice-versa.
        return '${' . implode(',', $args) . '}';
    }

    public function unComplileTemplates($json)
    {
        // Make sure the compiled templates are valid JSON.
        $templates = json_decode($json, true);
        if ($templates == null)
            return "";

        $textForAllTemplates = "";

        // Loop over each template object and create a template definition followed by the template's content.
        foreach ($templates as $templateName => $rows)
        {
            // Insert a blank line before each template definition except for the first one.
            if (strlen($textForAllTemplates) > 0)
                $textForAllTemplates .= PHP_EOL . PHP_EOL;

            $textForAllTemplates .= "Template: $templateName";
            foreach ($rows as $row)
            {
                $compiling = false;
                $parsedRow = $this->parseTemplateRow($row, $compiling);
                $textForAllTemplates .= PHP_EOL . $parsedRow;
            }
        }

        return $textForAllTemplates;
    }

    protected function validateFileUrlSpecifier($args)
    {
        // Validate the arguments for a file-url specifier and report an error if a problem is found.

        $argsCount = count($args);

        if ($argsCount < 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('The file-url specifier requires a size argument.'));

        $secondArg = $args[1];
        if (!in_array($secondArg, ['thumbnail', 'fullsize', 'original']))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file size. Use thumbnail, fullsize, or original.', $secondArg));

        if ($argsCount == 2)
            return;

        $itemIndex = $args[2];
        if (!$this->isValidIndex($itemIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));

        if ($argsCount == 3)
            return;

        $fileIndex = $args[3];
        if (!$this->isValidIndex($fileIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file index. The index must be an integer >= 1', $fileIndex));

        if ($argsCount > 4)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Specifier for file-url has too many arguments. It should have 2 to 4.'));
    }

    protected function validateElementSpecifier($parts)
    {
        // Validate the arguments for an element name specifier and report an error if a problem is found.

        $argsCount = count($parts);

        if ($argsCount == 2)
        {
            $itemIndex = $parts[1];
            if (!$this->isValidIndex($itemIndex))
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));
        }

        if ($argsCount > 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Element name specifier has too many arguments. It should have 1 or 2.'));
    }

    protected function validateItemUrlSpecifier($parts)
    {
        // Validate the arguments for an item-url specifier and report an error if a problem is found.

        $argsCount = count($parts);

        if ($argsCount == 1)
            return;

        $itemIndex = $parts[1];
        if (!$this->isValidIndex($itemIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));

        if ($argsCount > 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Specifier for item-url has too many arguments. It should have 1 or 2.'));
    }

    protected function validateDefinitionName($name)
    {
        $result = preg_match('/^[a-zA-Z0-9_]+$/', $name);
        return $result === 1;
    }
}