<?php

class MapsAliveConfig
{
    const OPTION_TEMPLATES = 'mapsalive_templates';

    public static function getOptionTextForTemplates()
    {
        // When a configuration occurs, the Configure Plugin page posts back to
        // itself to display the error after the user presses the Save button.
        if (isset($_POST['install_plugin']))
        {
            $text = $_POST[self::OPTION_TEMPLATES];
        }
        else
        {
            $raw = get_option(self::OPTION_TEMPLATES);
            $parser = new TemplateParser();
            $text = $parser->unparseJsonTemplates($raw);
        }

        return $text;
    }

    public static function saveConfiguration()
    {
        self::saveOptionTextForTemplates();
    }

    public static function saveOptionTextForTemplates()
    {
        $text = $_POST[self::OPTION_TEMPLATES];
        $parser = new TemplateParser();
        $parsed = $parser->parseTextTemplates($text);
        set_option(self::OPTION_TEMPLATES, $parsed);
    }
}