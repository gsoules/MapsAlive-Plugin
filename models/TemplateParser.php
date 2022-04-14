<?php

class TemplateParser
{
    protected $templateRowNumber;
    protected $templatesRowNumber = 0;
    protected $templateName = "";
    protected $parsedTextTemplates = [];

    public function convertTemplateToHtml($items, $templateName)
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
                // Look for a substitution in the remaining text on this row.
                $start = strpos($remaining, '${');
                if ($start === false)
                {
                    // There's no substitution. Keep the rest of the row text and go onto the next.
                    $html .= $remaining;
                    break;
                }
                $end = strpos($remaining, '}');
                $end += 1;

                // Get the substitution including the ${...} wrapper.
                $substitution = substr($remaining, $start, $end - $start);

                // Replace the entire substitution with a data value.
                $replacement = $this->replaceSubstitution($items, $substitution);
                $html .= substr($remaining, 0, $start);
                $html .= $replacement;

                $remaining = substr($remaining, $end);
            }
        }

        return $html;
    }

    public function convertTemplateToJson($items, $templateName)
    {
        $html = $this->convertTemplateToHtml($items, $templateName);

        $data = new class {};
        $data->id = "1100";
        $data->html = $html;
        $response = $data;

        return $response;
    }

    protected function errorPrefix()
    {
        return __('Error on line %s of template "%s": ', $this->templateRowNumber, $this->templateName);
    }

    protected function isTemplateDenitionRow($row)
    {
        $parts = array_map('trim', explode(':', $row));
        if (count($parts) < 2 || strtolower($parts[0]) != 'template')
            return false;
        $this->templateName = $parts[1];

        if ($this->templateName == "")
            throw new Omeka_Validate_Exception(__('No template name specified on line %s.', $this->templatesRowNumber));

        $index = strpos($this->templateName, ' ');
        if ($index !== false)
            $this->templateName = substr($this->templateName, 0, $index);
        return true;
    }

    protected function parseSubstitution($substitution, $convertElementNamesToIds)
    {
        // This method converts a substitution value that is within ${...} to either use an element name or element Id.

        $content = substr($substitution, 2, strlen($substitution) - 3);
        $parts = array_map('trim', explode(',', $content));
        $argsCount = count($parts);

        if ($argsCount == 1 && $parts[0] == "")
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('No substitution provided.'));

        $firstArg = $parts[0];

        if ($firstArg == 'file-url')
        {
            if ($argsCount < 2)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('The file-url specifier requires a size argument.'));

            $secondArg = $parts[1];
            if (!in_array($secondArg, ['thumbnail', 'fullsize', 'original']))
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file size. Use thumbnail, fullsize, or original.', $secondArg));

            if ($argsCount == 2)
                return $substitution;

            $itemIndex = $parts[2];
            if (intval($itemIndex) < 1)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index. The index must be an integer >= 1', $itemIndex));

            if ($argsCount == 3)
                return $substitution;

            $fileIndex = $parts[3];
            if (intval($fileIndex) < 1)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid file index. The index must be an integer >= 1', $fileIndex));

            if ($argsCount > 4)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('Too many arguments specified for file-url'));

            return $substitution;
        }

        if ($firstArg == 'item-url')
        {
            if ($argsCount == 1)
                return $substitution;

            $itemIndex = $parts[1];
            if (intval($itemIndex) < 1)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index.The index must be an integer >= 1', $itemIndex));

            if ($argsCount > 2)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('Too many arguments specified for item-url'));

            return $substitution;
        }

        if ($argsCount == 2)
        {
            $itemIndex = $parts[1];
            if (!$this->isIndex($itemIndex))
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not a valid item index.The index must be an integer >= 1', $itemIndex));
        }

        if ($argsCount > 2)
            throw new Omeka_Validate_Exception($this->errorPrefix() . __('Too many arguments specified for element name'));

        if ($convertElementNamesToIds)
        {
            $elementId = MapsAlive::getElementIdForElementName($firstArg);
            if ($elementId == 0)
                throw new Omeka_Validate_Exception($this->errorPrefix() . __('"%s" is not an element.', $firstArg));

            $parts[0] = $elementId;
        }
        else
        {
            $elementName = MapsAlive::getElementNameForElementId($firstArg);
            $parts[0] = $elementName;
        }

        return '${' . implode(',', $parts) . '}';
    }

    protected function isIndex($value)
    {
        if (!is_numeric($value))
            return false;
        return intval($value) >= 1;
    }

    protected function parseTemplateRow($row, $convertElementNamesToIds)
    {
        // This method converts a text template row that contains elements names to a json template row that
        // contains element Ids. It also converts a json template row that contains element Ids to a text
        // template row that contains element names.

        $remaining = $row;
        $parsed = "";
        $done = false;

        while (!$done)
        {
            $start = strpos($remaining, '${');
            if ($start === false)
            {
                $done = true;
                $parsed .= $remaining;
            }
            else
            {
                $end = strpos($remaining, '}');
                if ($end === false)
                {
                    throw new Omeka_Validate_Exception($this->errorPrefix() . __('Closing "}" is missing'));
                }
                else
                {
                    $end += 1;
                    $substitution = substr($remaining, $start, $end - $start);
                    $parsedSubstitution = $this->parseSubstitution($substitution, $convertElementNamesToIds);
                    $parsed .= substr($remaining, 0, $start);
                    $parsed .= $parsedSubstitution;
                    $remaining = substr($remaining, $end);
                }
            }
        }

        return $parsed;
    }

    protected function parseTextTemplateRows($templateName, $rows)
    {
        $this->parsedTextTemplates[$templateName] = [];
        $this->templateName = $templateName;
        $this->templateRowNumber = 0;
        foreach ($rows as $row)
        {
            $this->templateRowNumber += 1;
            $parsedRow = $this->parseTemplateRow($row, true);
            $this->parsedTextTemplates[$templateName][] = $parsedRow;
        }
    }

    public function parseTextTemplates($text)
    {
        $templates = [];
        $rows = explode(PHP_EOL, $text);

        foreach ($rows as $row)
        {
            $this->templatesRowNumber += 1;

            if (trim($row) == "")
                continue;

            if ($this->isTemplateDenitionRow($row))
            {
                $templates[$this->templateName] = [];
                continue;
            }

            $templates[$this->templateName][] = $row;
        }

        foreach ($templates as $templateName => $rows)
        {
            $this->parseTextTemplateRows($templateName, $rows);
        }

        return json_encode($this->parsedTextTemplates);
    }

    protected function replaceSubstitution($items, $substitution)
    {
        $content = substr($substitution, 2, strlen($substitution) - 3);
        $parts = array_map('trim', explode(',', $content));
        $argsCount = count($parts);

        $elementId = $parts[0];

        if ($elementId == 'file-url')
        {
            $derivative = $parts[1];

            $itemIndex = $argsCount > 2 ? $parts[2] - 1 : 0;
            if ($itemIndex > count($items) - 1)
                return "";

            $fileIndex = $argsCount > 3 ? $parts[3] - 1 : 0;
            $item = $items[$itemIndex];
            $value = MapsAlive::getItemFileUrl($item, $derivative, $fileIndex);
        }
        else
        {
            $itemIndex = $argsCount > 1 ? $parts[1] - 1 : 0;
            if ($itemIndex > count($items) - 1)
                return "";

            $item = $items[$itemIndex];

            if ($elementId == 'item-url')
            {
                $value = WEB_ROOT . '/items/show/' . $item->id;
            }
            else
            {
                $value = MapsAlive::getElementTextFromElementId($item, $elementId);
            }
        }
        return $value;
    }

    public function unparseJsonTemplates($json)
    {
        $text = "";
        $templates = json_decode($json, true);
        if ($templates == null)
            return "";

        foreach ($templates as $templateName => $rows)
        {
            $text .= "Template: $templateName";
            foreach ($rows as $row)
            {
                $parsedRow = $this->parseTemplateRow($row, false);
                $text .= PHP_EOL . $parsedRow;
            }
            $text .= PHP_EOL . PHP_EOL;
        }

        return $text;
    }
}