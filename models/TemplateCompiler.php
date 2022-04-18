<?php

class TemplateCompiler
{
    const DERIVATIVE_NAME_THUMBNAIL = 'thumbnail';
    const DERIVATIVE_NAME_FULLSIZE = 'fullsize';
    const DERIVATIVE_NAME_ORIGINAL = 'original';
    const FILE_PROPERTY_URL = 'url';
    const FILE_PROPERTY_WIDTH = 'width';
    const FILE_PROPERTY_HEIGHT = 'height';
    const FORMAT_HTML = 'HTML';
    const FORMAT_JSON = 'JSON';
    const ITEM_PROPERTY_ID = 'id';
    const ITEM_PROPERTY_URL = 'url';
    const REPEAT_START = '[--';
    const REPEAT_END = '--]';
    const SPECIFIER_START = '${';
    const SPECIFIER_END = '}';
    const SPECIFIER_ELEMENT = 'element';
    const SPECIFIER_FILE = 'file';
    const SPECIFIER_ITEM = 'item';

    protected $derivativeSizes = [];
    protected $fileProperties = [];
    protected $fileSizeCache = [];
    protected $formats = [];
    protected $itemProperties = [];
    protected $specifiers = [];
    protected $templateFormat = "";
    protected $templateElementId = "";
    protected $templateName = "";
    protected $templateRowNumber;
    protected $templates = [];
    protected $templatesRowNumber = 0;


    public function __construct()
    {
        $this->derivativeSizes[1] = self::DERIVATIVE_NAME_THUMBNAIL;
        $this->derivativeSizes[2] = self::DERIVATIVE_NAME_FULLSIZE;
        $this->derivativeSizes[3] = self::DERIVATIVE_NAME_ORIGINAL;

        $this->fileProperties[1] = self::FILE_PROPERTY_URL;
        $this->fileProperties[2] = self::FILE_PROPERTY_WIDTH;
        $this->fileProperties[3] = self::FILE_PROPERTY_HEIGHT;

        $this->formats[1] = self::FORMAT_HTML;
        $this->formats[2] = self::FORMAT_JSON;

        $this->itemProperties[1] = self::ITEM_PROPERTY_ID;
        $this->itemProperties[2] = self::ITEM_PROPERTY_URL;

        $this->specifiers[1] = self::SPECIFIER_ELEMENT;
        $this->specifiers[2] = self::SPECIFIER_FILE;
        $this->specifiers[3] = self::SPECIFIER_ITEM;
    }

    protected function compileTemplate($templateName)
    {
        // Initialize class variables used to keep track of which template and which row is being parsed.
        $this->templateName = $templateName;
        $this->templateRowNumber = 0;

        // Process each template row to validate its specifiers and to translate element names to element Ids.
        // Keep track of the row number solely for the purpose of reporting validation errors.
        $rows = $this->templates[$templateName]['rows'];
        foreach ($rows as $row)
        {
            $this->templateRowNumber += 1;

            if ($this->isRepeatDelimiterRow($row, self::REPEAT_START))
            {
                if ($this->templates[$templateName]['repeat-start'] != 0)
                    throw new Omeka_Validate_Exception(__('Template "%s" has more than one repeat start line.', $templateName));
                $this->templates[$templateName]['repeat-start'] = $this->templateRowNumber + 2;
                $parsedRow = $row;
            }
            else if ($this->isRepeatDelimiterRow($row, self::REPEAT_END))
            {
                if ($this->templates[$templateName]['repeat-start'] == 0)
                    throw new Omeka_Validate_Exception(__('Template "%s" has a repeat end line but no start line.', $templateName));
                $this->templates[$templateName]['repeat-end'] = $this->templateRowNumber;
                $parsedRow = $row;
            }
            else
            {
                $parsedRow = $this->parseTemplateRow($row, true);
            }

            $this->templates[$templateName]['rows'][$this->templateRowNumber - 1] = $parsedRow;
        }

        // Validate the content of JSON template.
        $formatId = $this->templates[$templateName]['format'];
        if ($this->formats[$formatId] == self::FORMAT_JSON)
        {
            $json = implode(PHP_EOL, $rows);
            @json_decode($json);
            if (json_last_error() != JSON_ERROR_NONE)
                throw new Omeka_Validate_Exception(__('Template "%s" contains invalid JSON: %s.', $templateName, json_last_error_msg()));
        }

        if ($this->templates[$templateName]['repeat-start'] != 0 && $this->templates[$templateName]['repeat-end'] == 0)
            throw new Omeka_Validate_Exception(__('Template "%s" has a repeat start line but no repeat end line.', $templateName));
    }

    public function compileTemplates($text)
    {
        $this->templates = [];
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
                $this->templates[$this->templateName]['name'] = $this->templateName;
                $this->templates[$this->templateName]['identifier'] = $this->templateElementId;
                $this->templates[$this->templateName]['format'] = array_keys($this->formats, $this->templateFormat)[0];
                $this->templates[$this->templateName]['repeat-start'] = 0;
                $this->templates[$this->templateName]['repeat-end'] = 0;
                $this->templates[$this->templateName]['rows'] = [];
                continue;
            }

            if (count($this->templates) == 0)
                throw new Omeka_Validate_Exception(__('Unexpected content detected near line %s.', $this->templatesRowNumber));

            // Add the row to the rows for the current template.
            $this->templates[$this->templateName]['rows'][] = $row;
        }

        // Process each text template to validate its specifiers and to translate element names to element Ids.
        foreach ($this->templates as $templateName => $template)
        {
            $this->compileTemplate($templateName);
        }

        // Create a JSON array of templates to be stored in the database.
        return json_encode($this->templates);
    }

    public function emitTemplateLiveData($items, $template)
    {
        $repeatCount = count($items) - 1;
        $repeatStartIndex = $template['repeat-start'] - 1;
        $repeatEndIndex = $template['repeat-end'] - 1;
        $templateRepeats = $repeatStartIndex > 0 && $repeatEndIndex;

        $parsedText = "";
        $index = 0;
        $rows = $template['rows'];
        $lastRowIndex = count($rows) - 1;

        // Loop over every row in the template inserting Live Data values where the rows contain specifiers.
        // If the template has  repeating section, the row index will get reset while in that section to
        // cause it to be looped over once for each item used for the repetition.
        while ($index <= $lastRowIndex)
        {
            $repeating = $templateRepeats && $repeatCount > 0 && ($index >= $repeatStartIndex && $index <= $repeatEndIndex);
            $row = $rows[$index];

            // Discard delimiter rows.
            if ($this->isRepeatDelimiterRow($row, self::REPEAT_START) ||
                $this->isRepeatDelimiterRow($row, self::REPEAT_END))
            {
                $index += 1;
            }
            else
            {
                // Determine which item(s) to use for obtaining Live Data. When a template repeats, the first items
                // passed in $items is used for the non-repeating part of the template (the rows before and after
                // the repeating section) and the rest of the items are used one at a time for the repeating section.
                // If the template does not repeat, all of the items are made available to the template so that its
                // specifiers can choose metadata and URL values from multiple items. In contrast, specifiers in a
                // repeating template can only ever use one item.
                if ($templateRepeats)
                {
                    $itemIndex = $repeating ? count($items) - $repeatCount : 0;
                    $itemList = [$items[$itemIndex]];
                }
                else
                {
                    $itemList = $items;
                }

                // Replace the row's specifiers with Live Data values.
                $parsedText = $this->emitLiveDataIntoRow($row, $parsedText, $itemList, $template['format']);

                // Determine if/how the loop index needs to get reset to loop again over a repeating section.
                if ($repeating && $index == $repeatEndIndex)
                {
                    $repeatCount -= 1;
                    if ($repeatCount > 0)
                        $index = $repeatStartIndex;
                    else
                        $index = $repeatEndIndex + 1;
                }
                else
                {
                    $index += 1;
                }
            }
        }

        $response = "";

        $format = $this->formats[$template['format']];
        if ($format == self::FORMAT_HTML)
        {
            $data = new class {};
            $data->id = "0";
            $data->html = $parsedText;
            $response = json_encode($data);
        }
        else if ($format == self::FORMAT_JSON)
        {
            $response = $parsedText;
        }

        return $response;
    }

    protected function emitLiveDataIntoRow($row, string $parsedText, $itemList, $format): string
    {
        // Replace each specifier in the text with its Live Data value.
        $remainingText = $row;
        while (true)
        {
            // Look for a specifier in this row. A null token means no specifier found.
            $token = $this->getSpecifierToken($remainingText);
            if ($token == null)
            {
                $parsedText .= $remainingText;
                break;
            }
            $specifier = $token['specifier'];

            // Replace the specifier with a Live Data value.
            $replacement = $this->replaceSpecifierWithLiveData($itemList, $specifier);

            // Escape double quotes in a JSON response.
            if ($this->formats[$format] == self::FORMAT_JSON)
                $replacement = str_replace('"', '\\"', $replacement);

            // Insert the Live Data substitution into the original text.
            $parsedText .= substr($remainingText, 0, $token['start']);
            $parsedText .= $replacement;

            $remainingText = $token['remaining'];
        }
        return $parsedText;
    }

    protected function errorPrefix()
    {
        return __('Error on line %s of template "%s": ', $this->templateRowNumber, $this->templateName);
    }

    protected function getSpecifierToken($text)
    {
        $start = strpos($text, self::SPECIFIER_START);
        if ($start === false)
            return null;

        // Find the end of the current specifier. It is required to be on the same line as the start.
        $end = strpos($text, self::SPECIFIER_END);

        $token['specifier'] = $end === false ? "" :substr($text, $start, $end - $start + 1);
        $token['start'] = $start;
        $token['remaining'] = substr($text, $end + 1);

        return $token;
    }

    protected function isRepeatDelimiterRow($row, $delimiter)
    {
         return substr(ltrim($row), 0, strlen($delimiter)) == $delimiter;
    }

    protected function isTemplateDefinitionRow($row)
    {
        $text = trim($row);

        if (strtolower(substr($text, 0, 9)) != 'template:')
            return false;

        $restOfText = substr($text, 9);

        $args = array_map('trim', explode(',', $restOfText));
        $argsCount = count($args);

        $this->templateName = $args[0];

        if (array_key_exists($this->templateName, $this->templates))
            throw new Omeka_Validate_Exception(__('Template "%s" on line %s has already been defined.', $this->templateName, $this->templatesRowNumber));

        if ($argsCount < 2)
            throw new Omeka_Validate_Exception(__('Template definition "%s" on line %s is missing its item identifier.', $this->templateName, $this->templatesRowNumber));

        if ($argsCount < 3)
            throw new Omeka_Validate_Exception(__('Template definition "%s" on line %s does is missing its format (HTML or JSON).', $this->templateName, $this->templatesRowNumber));

        if (!$this->validateDefinitionName($this->templateName))
            throw new Omeka_Validate_Exception(__('Template name "%s" on line %s must contain only alphanumeric characters and underscore.', $this->templateName, $this->templatesRowNumber));

        if ($this->templateName == "")
            throw new Omeka_Validate_Exception(__('No template name specified on line %s.', $this->templatesRowNumber));

        $index = strpos($this->templateName, ' ');
        if ($index !== false)
            $this->templateName = substr($this->templateName, 0, $index);

        $elementName = $args[1];
        $this->templateElementId = MapsAlive::getElementIdForElementName($elementName);
        if ($this->templateElementId == 0)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not an element.', $elementName));

        $this->templateFormat = strtoupper($args[2]);
        if ($this->templateFormat != "HTML" && $this->templateFormat != "JSON")
            throw new Omeka_Validate_Exception(__('Invalid template format "%s" specified on line %s. Must be HTML or JSON.', $this->templateFormat, $this->templatesRowNumber));

        if ($argsCount > 3)
            throw new Omeka_Validate_Exception(__('Too many arguments specified for template "%s" on line %s.', $this->templateName, $this->templatesRowNumber));

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

        $remainingText = $row;
        $parsedText = "";

        // Parse the row's text from left to right until no more ${...} specifiers are found.
        // on the row. Initially no text has been parsed and the remaining text is the entire row.

        while (true)
        {
            // Look for a specifier on this row. A null token means no specifier found. An empty specifier means
            // the closing curly brace was missing. That can only happen when compiling but always check for it
            // in case the database text is malformed (should only happen during development).
            $token = $this->getSpecifierToken($remainingText);
            if ($token == null || (!$compiling && $token['specifier'] == ""))
            {
                $parsedText .= $remainingText;
                break;
            }

            if ($token['specifier'] == "")
               throw new Omeka_Validate_Exception($this->errorPrefix() . __('Closing "}" for specifier is missing'));

            // Translate an element name to an element Id when compiling or vice-versa when uncompiling.
            $translatedSpecifier = $this->translateSpecifier($token['specifier'], $compiling);

            // Replace the original specifier with the translated specifier.
            $parsedText .= substr($remainingText, 0, $token['start']);
            $parsedText .= $translatedSpecifier;

            $remainingText = $token['remaining'];
        }

        return $parsedText;
    }

    protected function replaceFileSpecifierWithLiveData(array $args, int $argsCount, $items)
    {
        $derivativeSize = $this->derivativeSizes[$args[1]];

        // When the item index is greater than the number of items specified, return an empty string.
        $itemIndex = $argsCount > 3 ? $args[3] - 1 : 0;
        if ($itemIndex > count($items) - 1)
            return "";

        // Get the specified item.
        $item = $items[$itemIndex];
        if ($item == null)
            return "";

        // Get the file index or use 1 if no index was specified.
        $fileIndex = $argsCount > 4 ? $args[4] - 1 : 0;

        // Get the URL for the specified file at the specified size. An empty string will be returned if the item
        // does not have a file attachment, or does not have as many attachments as specified by the index,
        // or its a hybrid image e.g. exported from PastPerfect.
        $imageUrl = MapsAlive::getItemFileUrl($item, $derivativeSize, $fileIndex);

        if (!$imageUrl && plugin_is_active('AvantHybrid'))
        {
            $hybridImageRecords = AvantHybrid::getImageRecords($item->id);
            if ($hybridImageRecords)
                $imageUrl = AvantHybrid::getImageUrl($hybridImageRecords[0]);
        }

        $imageUrl = str_replace('\\', '/', $imageUrl);

        $property = $args[2];
        if ($this->fileProperties[$property] == self::FILE_PROPERTY_URL)
            return $imageUrl;

        if (in_array($imageUrl, $this->fileSizeCache))
        {
            $imageSize = $this->fileSizeCache[$imageUrl];
        }
        else
        {
            $imageSize = @getimagesize($imageUrl);
            $this->fileSizeCache[$imageUrl] = $imageSize;
        }

        if ($imageSize)
            return $this->fileProperties[$property] == self::FILE_PROPERTY_WIDTH ? $imageSize[0] : $imageSize[1];

        return 0;
    }

    protected function replaceSpecifierWithLiveData($items, $specifier)
    {
        // This method replaces a specifier with a data value in response to a Live Data request.

        $args = $this->parseSpecifier($specifier);
        $argsCount = count($args);

        $specifierKind = $args[0];

        if ($this->specifiers[$specifierKind] == self::SPECIFIER_FILE)
        {
            return $this->replaceFileSpecifierWithLiveData($args, $argsCount, $items);
        }

        // When the item index is greater than the number of items specified, return an empty string.
        $itemIndex = $argsCount > 2 ? $args[2] - 1 : 0;
        if ($itemIndex > count($items) - 1)
            return "";

        // Get the item identified by the specifier's item index. Return empty string if there's no item for the index.
        $item = $items[$itemIndex];
        if ($item == null)
            return "";

        // Get the requested value.
        if ($this->specifiers[$specifierKind] == self::SPECIFIER_ITEM)
        {
            $property = $args[1];
            $value = "";
            if ($this->itemProperties[$property] == self::ITEM_PROPERTY_ID)
                $value = $item->id;
            else if ($this->itemProperties[$property] == self::ITEM_PROPERTY_URL)
                $value = WEB_ROOT . '/items/show/' . $item->id;
            return $value;
        }

        if ($this->specifiers[$specifierKind] == self::SPECIFIER_ELEMENT)
        {
            $value = MapsAlive::getElementTextFromElementId($item, $args[1]);
            return $value;
        }
    }

    protected function requires($values)
    {
        return implode(' | ', $values);
    }

    protected function translateElementSpecifier(&$args)
    {
        // Validate the arguments for an element name specifier and report an error if a problem is found.

        $argsCount = count($args);

        // Get the element Id for the element name.
        $elementId = MapsAlive::getElementIdForElementName($args[1]);
        if ($elementId == 0)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not an element.', $args[1]));

        // Replace the element name with the element Id.
        $args[1] = $elementId;

        if ($argsCount == 3)
        {
            $itemIndex = $args[2];
            if (!$this->isValidIndex($itemIndex))
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));
        }

        if ($argsCount > 3)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Element name specifier has too many arguments. The maximum is 3.'));
    }

    protected function translateFileSpecifier(&$args)
    {
        // Validate the arguments for a file specifier and report an error if a problem is found.

        $argsCount = count($args);

        if ($argsCount < 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('The file specifier requires a derivative size argument.'));

        $derivativeSize = strtolower($args[1]);
        if (!in_array($derivativeSize, $this->derivativeSizes))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid derivative size. Requires: %s', $args[1], $this->requires($this->derivativeSizes)));
        $args[1] = array_keys($this->derivativeSizes, $derivativeSize)[0];

        if ($argsCount < 3)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('The file specifier requires a property argument.'));

        $property = strtolower($args[2]);
        if (!in_array($property, $this->fileProperties))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file property. Requires: %s.', $args[2], $this->requires($this->fileProperties)));
        $args[2] = array_keys($this->fileProperties, $property)[0];

        if ($argsCount == 3)
            return;

        $itemIndex = strtolower($args[3]);
        if (!$this->isValidIndex($itemIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));

        if ($argsCount == 4)
            return;

        $fileIndex = $args[4];
        if (!$this->isValidIndex($fileIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file index. The index must be an integer >= 1', $fileIndex));

        if ($argsCount > 5)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Specifier for file has too many arguments. It should have 3 to 5.'));
    }

    protected function translateItemUrlSpecifier(&$args)
    {
        // Validate the arguments for an item-url specifier and report an error if a problem is found.

        $argsCount = count($args);

        if ($argsCount < 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('The item specifier requires a property argument.'));

        $property = strtolower($args[1]);
        if (!in_array($property, $this->itemProperties))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item property. Requires: %s.', $args[1], $this->requires($this->itemProperties)));
        $args[1] = array_keys($this->itemProperties, $property)[0];

        if ($argsCount == 2)
            return;

        $itemIndex = $args[2];
        if (!$this->isValidIndex($itemIndex))
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));

        if ($argsCount > 3)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Specifier for item-url has too many arguments. It should have 2 or 3.'));
    }

    protected function translateSpecifier($specifier, $compiling)
    {
        // When compiling a template, this method translate an element name within a specifier ${...} to an
        // element Id. When uncompiling, it translates an element Id to an element name. If the specifier is
        // file or item, this method only converts specifier argument names to Ids and vice-versa.

        // Break the specifier into its individual arguments.
        $args = $this->parseSpecifier($specifier);
        $argsCount = count($args);

        if ($argsCount == 2 && $args[1] == "")
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('No specifier arguments provided.'));

        $specifierKind = strtolower($args[0]);

        if ($compiling)
        {
            if (!in_array($specifierKind, $this->specifiers))
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid specifier kind. Requires: %s.', $args[0], $this->requires($this->specifiers)));

            // Replace the kind text with its Id.
            $args[0] = array_keys($this->specifiers, $specifierKind)[0];

            // Validate that the specifier has the correct number of arguments and that they are valid.
            // If any problems are discovered, the validation method will throw a validation exception.
            // For valid file and item-url specifiers, return the specifier as-is.
            if ($specifierKind == self::SPECIFIER_FILE)
                $this->translateFileSpecifier($args);
            else if ($specifierKind == self::SPECIFIER_ITEM)
                $this->translateItemUrlSpecifier($args);
            else
                $this->translateElementSpecifier($args);
        }
        else
        {
            // Convert the specifier back into human-readable form.

            if ($this->specifiers[$specifierKind] == self::SPECIFIER_ELEMENT)
            {
                // Replace the element Id with the element name.
                $args[1] = MapsAlive::getElementNameForElementId($args[1]);
            }
            else if ($this->specifiers[$specifierKind] == self::SPECIFIER_FILE)
            {
                $args[1] = $this->derivativeSizes[$args[1]];
                $args[2] = $this->fileProperties[$args[2]];
            }
            else if ($this->specifiers[$specifierKind] == self::SPECIFIER_ITEM)
            {
                $args[1] = $this->itemProperties[$args[1]];
            }

            $args[0] = $this->specifiers[$specifierKind];
        }

        // Reconstruct the specifier with the element name converted to an Id or vice-versa.
        return '${' . implode(', ', $args) . '}';
    }

    public function unComplileTemplates($json)
    {
        // Make sure the compiled templates are valid JSON.
        $this->templates = json_decode($json, true);
        if ($this->templates == null)
            return "";

        $uncompiledText = "";

        // Loop over each template object and create a template definition followed by the template's content.
        foreach ($this->templates as $templateName => $template)
        {
            // Insert a blank line before each template definition except for the first one.
            if (strlen($uncompiledText) > 0)
                $uncompiledText .= PHP_EOL . PHP_EOL;

            // Get the template's identifier element name from its element Id.
            $elementName = MapsAlive::getElementNameForElementId($template['identifier']);

            // Form the template definition line.
            $formatId = $this->formats[$template['format']];
            $uncompiledText .= "Template: $templateName, $elementName, $formatId";

            // Uncompile the template rows by converting specifier element Ids to element names.
            $rows = $template['rows'];
            foreach ($rows as $row)
            {
                $compiling = false;
                $parsedRow = $this->parseTemplateRow($row, $compiling);
                $uncompiledText .= PHP_EOL . $parsedRow;
            }
        }

        return $uncompiledText;
    }

    protected function validateDefinitionName($name)
    {
        $result = preg_match('/^[a-zA-Z0-9_]+$/', $name);
        return $result === 1;
    }
}