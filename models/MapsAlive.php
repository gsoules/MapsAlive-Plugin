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

    public static function getItemFileUrl($item, $derivativeSize, $fileIndex)
    {
        $url = '';
        $file = $item->getFile($fileIndex);
        if (!empty($file) && $file->hasThumbnail())
        {
            $url = $file->getWebPath($derivativeSize);

            $supportedImageMimeTypes = self::supportedImageMimeTypes();

            if (!in_array($file->mime_type, $supportedImageMimeTypes))
            {
                // The original image is not a jpg (it's probably a pdf) in which case return a smaller size.
                if ($derivativeSize === "original")
                    $derivativeSize = "fullsize";
                $url = $file->getWebPath($derivativeSize);
            }
        }
        return $url;
    }

    public static function getItemForIdentifier($elementId, $identifier)
    {
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

    public static function supportedImageMimeTypes()
    {
        return array(
            'image/jpg',
            'image/jpeg',
            'image/png'
        );
    }
}
