<?php

class MapsAliveConfig
{
    const OPTION_TEMPLATES = 'mapsalive_templates';

    public static function getOptionTextForTemplates()
    {
        if (isset($_POST['install_plugin']))
        {
            // When a configuration occurs, the Configure Plugin page posts back to
            // itself to display the error after the user presses the Save button.
            $text = $_POST[self::OPTION_TEMPLATES];
        }
        else
        {
            $raw = get_option(self::OPTION_TEMPLATES);
            $compiler = new TemplateCompiler();
            $text = $compiler->unComplileTemplates($raw);
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
        $compiler = new TemplateCompiler();
        $compiledTemplates = $compiler->compileTemplates($text);
        set_option(self::OPTION_TEMPLATES, $compiledTemplates);
    }
}