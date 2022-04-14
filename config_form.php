<?php
$view = get_view();

$templates = MapsAliveConfig::getOptionTextForTemplates();
$templatesRows = max(2, count(explode(PHP_EOL, $templates)));
?>

<div class="plugin-help learn-more">
    <a href="https://digitalarchive.us/plugins/mapsalive/" target="_blank"><?php echo __("Learn about the configuration options on this page"); ?></a>
</div>

<div class="field">
    <div class="two columns alpha">
        <label>Templates</label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __("Templates for Live Data response HTML"); ?></p>
        <?php echo $view->formTextarea(MapsAliveConfig::OPTION_TEMPLATES, $templates, array('rows' => $templatesRows)); ?>
    </div>
</div>
