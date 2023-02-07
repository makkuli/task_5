<?php

namespace Dev\Site\Handlers;


class IblockElement
{
    public static function afterAdd($arFields)
    {
        if ($arFields['IBLOCK_ID'] == 23) {
            return;
        }

        $resblock = \CIBlock::GetByID($arFields["IBLOCK_ID"]);
        if ($arresblock = $resblock->GetNext()) {
            $iBlockName = $arresblock["NAME"]; // название инфоблока
        }

        $sectionID = $arFields["IBLOCK_SECTION"][0];
        $ressection = \CIBlockSection::GetByID($sectionID);
        $sectionName = $ressection->GetNext()['NAME'];

        $logSectionID = null;
        $arFilter = ['IBLOCK_ID' => 23, 'NAME' => $sectionName];
        $db_list = \CIBlockSection::GetList([], $arFilter);
        if ($ar_result = $db_list->GetNext()) {
            $logSectionID = $ar_result['ID'];
        } else {
            $log = new \CIBlockSection();
            $logSectionID = $log->Add([
                "NAME" => $sectionName,
                "IBLOCK_ID" => 23,
            ]);
        }

        $list = \CIBlockSection::GetNavChain($arFields["IBLOCK_ID"], $sectionID, array(), true);
        $arName[] = $iBlockName;
        foreach ($list as $arSectionPath) {
            $arName[] = $arSectionPath['NAME'];
        }
        $arName[] = $arFields['ID'];

        $el = new \CIBlockElement;
        global $USER;
        $today = date("d.m.Y H:i:s");
        $previewText = implode('->', $arName);

        $arLoadProductArray = array(
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => $logSectionID,
            "CODE" => "LOG",
            "IBLOCK_ID" => 23,
            "NAME" => $arFields["ID"],
            "ACTIVE" => "Y",
            "DATE_ACTIVE_FROM" => $today,
            "PREVIEW_TEXT" => $previewText,
        );


        if ($PRODUCT_ID = $el->Add($arLoadProductArray))
            return "New ID: " . $PRODUCT_ID;
        else
            return "Error: " . $el->LAST_ERROR;

    }

    public static function afterUpdate($arFields)
    {


    }


    public static function addLog()
    {
        // Здесь напиши свой обработчик
    }

    function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        $iQuality = 95;
        $iWidth = 1000;
        $iHeight = 1000;
        /*
         * Получаем пользовательские свойства
         */
        $dbIblockProps = \Bitrix\Iblock\PropertyTable::getList(array(
            'select' => array('*'),
            'filter' => array('IBLOCK_ID' => $arFields['IBLOCK_ID'])
        ));
        /*
         * Выбираем только свойства типа ФАЙЛ (F)
         */
        $arUserFields = [];
        while ($arIblockProps = $dbIblockProps->Fetch()) {
            if ($arIblockProps['PROPERTY_TYPE'] == 'F') {
                $arUserFields[] = $arIblockProps['ID'];
            }
        }
        /*
         * Перебираем и масштабируем изображения
         */
        foreach ($arUserFields as $iFieldId) {
            foreach ($arFields['PROPERTY_VALUES'][$iFieldId] as &$file) {
                if (!empty($file['VALUE']['tmp_name'])) {
                    $sTempName = $file['VALUE']['tmp_name'] . '_temp';
                    $res = \CAllFile::ResizeImageFile(
                        $file['VALUE']['tmp_name'],
                        $sTempName,
                        array("width" => $iWidth, "height" => $iHeight),
                        BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                        false,
                        $iQuality);
                    if ($res) {
                        rename($sTempName, $file['VALUE']['tmp_name']);
                    }
                }
            }
        }

        if ($arFields['CODE'] == 'brochures') {
            $RU_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_RU');
            $EN_IBLOCK_ID = \Only\Site\Helpers\IBlock::getIblockID('DOCUMENTS', 'CONTENT_EN');
            if ($arFields['IBLOCK_ID'] == $RU_IBLOCK_ID || $arFields['IBLOCK_ID'] == $EN_IBLOCK_ID) {
                \CModule::IncludeModule('iblock');
                $arFiles = [];
                foreach ($arFields['PROPERTY_VALUES'] as $id => &$arValues) {
                    $arProp = \CIBlockProperty::GetByID($id, $arFields['IBLOCK_ID'])->Fetch();
                    if ($arProp['PROPERTY_TYPE'] == 'F' && $arProp['CODE'] == 'FILE') {
                        $key_index = 0;
                        while (isset($arValues['n' . $key_index])) {
                            $arFiles[] = $arValues['n' . $key_index++];
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'L' && $arProp['CODE'] == 'OTHER_LANG' && $arValues[0]['VALUE']) {
                        $arValues[0]['VALUE'] = null;
                        if (!empty($arFiles)) {
                            $OTHER_IBLOCK_ID = $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? $EN_IBLOCK_ID : $RU_IBLOCK_ID;
                            $arOtherElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => $OTHER_IBLOCK_ID,
                                    'CODE' => $arFields['CODE']
                                ], false, false, ['ID'])
                                ->Fetch();
                            if ($arOtherElement) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arOtherElement['ID'], $OTHER_IBLOCK_ID, $arFiles, 'FILE');
                            }
                        }
                    } elseif ($arProp['PROPERTY_TYPE'] == 'E') {
                        $elementIds = [];
                        foreach ($arValues as &$arValue) {
                            if ($arValue['VALUE']) {
                                $elementIds[] = $arValue['VALUE'];
                                $arValue['VALUE'] = null;
                            }
                        }
                        if (!empty($arFiles && !empty($elementIds))) {
                            $rsElement = \CIBlockElement::GetList([],
                                [
                                    'IBLOCK_ID' => \Only\Site\Helpers\IBlock::getIblockID('PRODUCTS', 'CATALOG_' . $RU_IBLOCK_ID == $arFields['IBLOCK_ID'] ? '_RU' : '_EN'),
                                    'ID' => $elementIds
                                ], false, false, ['ID', 'IBLOCK_ID', 'NAME']);
                            while ($arElement = $rsElement->Fetch()) {
                                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                                \CIBlockElement::SetPropertyValues($arElement['ID'], $arElement['IBLOCK_ID'], $arFiles, 'FILE');
                            }
                        }
                    }
                }
            }
        }
    }

}
