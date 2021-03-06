<?php

// This class contains methods that directly access Omeka methods. In a Digital Archive installation,
// these methods are provided by AvantCommon's ItemMetadata class, but since this MapsAlive plugin
// is designed to be used with no dependencies on other plugins, the methods need to exist here too.

class MapsAlive
{
    public static function getElementIdForElementName($elementName)
    {
        $db = get_db();
        $elementTable = $db->getTable('Element');
        $element = $elementTable->findByElementSetNameAndElementName('Dublin Core', $elementName);
        if (empty($element))
            $element = $elementTable->findByElementSetNameAndElementName('Item Type Metadata', $elementName);
        return empty($element) ? 0 : $element->id;
    }

    public static function getElementNameForElementId($elementId)
    {
        $db = get_db();
        $element = $db->getTable('Element')->find($elementId);
        return isset($element) ? $element->name : '';
    }

    public static function getElementTextFromElementId($item, $elementId, $asHtml = true)
    {
        $db = get_db();
        $element = $db->getTable('Element')->find($elementId);
        $text = '';
        if (!empty($element))
        {
            $texts = $item->getElementTextsByRecord($element);
            $text = isset($texts[0]['text']) ? $texts[0]['text'] : '';
        }
        return $asHtml ? html_escape($text) : $text;
    }

    public static function getItemFileUrl($item, $derivativeSize, $fileIndex, $fileProperty)
    {
        $file = $item->getFile($fileIndex);
        if (empty($file))
            return "";

        if ($derivativeSize == "original")
        {
            if ($fileProperty === TemplateCompiler::FILE_PROPERTY_IMG &&
                !in_array($file->mime_type, array('image/jpg', 'image/jpeg', 'image/png')))
            {
                // The specifier is for the original image, but the original file is not an image.
                // It could be a PDF, audio, or video file.
                if ($file->hasThumbnail())
                {
                    // Return the derivative image, which for a PDF would be an image of the PDF file's first page.
                    $derivativeSize = "fullsize";
                }
                else
                {
                    // There's no image for this file.
                    return "";
                }
            }
        }
        else if (!$file->hasThumbnail())
        {
            // There's no fullsize or thumbnail image for this file.
            return "";
        }

        $url = $file->getWebPath($derivativeSize);

        return $url;
    }

    public static function getItemForIdentifier($elementId, $identifier)
    {
        if ($elementId == 0)
        {
            // An element Id of 0 means that the item identifier is the Omeka item Id.
            $item = get_record_by_id('item', $identifier);
            return $item;
        }

        $params = array(
            'search' => '',
            'advanced' => array(
                array(
                    'element_id' => $elementId,
                    'type' => 'is exactly',
                    'terms' => $identifier)));

        $records = get_records('Item', $params);

        if (empty($records))
            return null;

        // Return the first or only item resulting from the search.
        return $records[0];
    }
}
