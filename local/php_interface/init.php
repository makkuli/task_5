<?php

use Bitrix\Main\Loader;
Loader::includeModule('dev.site');

AddEventHandler("iblock", "OnAfterIBlockElementAdd", Array("\Dev\Site\Handlers\IblockElement", "afterAdd"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("\Dev\Site\Handlers\IblockElement", "afterUpdate"));
