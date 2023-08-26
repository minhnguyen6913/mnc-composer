<?php

namespace Minhnhc\Form;

use Minhnhc\Util\LanguagesInterface;

class FormBase
{
    protected $action = null;
    protected int $pageIndex = 1;
    protected int $pageSize = 20;
    protected $searchInfo = null;
    protected $sortInfo = null;
    protected $excelFields = null;
    protected $orderFields = null;
    protected $updateInfo = null;
    protected array $fields = [];

    /**
     * @var LanguagesInterface
     */
    protected $languagesManager;

    protected function initDefine()
    {
        /**
         * Add list param to linked param list
         * @var Field $field
         */
        foreach ($this->fields as $name => $field) {
            if (isset($field->listParam)) {

                if (is_array($field->listParam)){
                    $params = $field->listParam;
                }else{
                    $params = [$field->listParam];
                }
                foreach ($params as $paramFieldName) {
                    $paramField = $this->getField($paramFieldName);
                    if (!in_array($name, $paramField->listLinkedParam)) {
                        $paramField->listLinkedParam[] = $name;
                    }
                }
            }
        }
    }

    public function initDataParam()
    {
        // Init request param to get search info
        if (isset($_REQUEST['pageIndex']) && is_numeric($_REQUEST['pageIndex'])) {
            $this->pageIndex = $_REQUEST['pageIndex'];
        }

        if (isset($_REQUEST['pageSize']) && is_numeric($_REQUEST['pageSize'])) {
            $this->pageSize = $_REQUEST['pageSize'];
        }

        if (isset($_REQUEST['sortInfo'])) {
            $this->sortInfo = json_decode($_REQUEST['sortInfo'], true);
        }
        if (isset($_REQUEST['searchInfo'])) {
            $this->searchInfo = json_decode($_REQUEST['searchInfo'], true);
        }
    }

    public function initUpdateParam()
    {

        $serials = @$_REQUEST[SERIAL_PARAM_NAME];
        if (!isset($serials) || !is_array($serials)) {
            // $this->logger->debug("Form {$this->name} have no serial. " . json_encode($_REQUEST));
            return;
        }
        $count = count($serials);
        $rs = [];
        foreach ($serials as $serial) {
            $rec = [];
            $rec[SERIAL_PARAM_NAME] = (int)$serial;
            $rs[] = $rec;
        }
        /**
         * @var Field $field
         */
        foreach ($this->fields as $fieldName => $field) {
            if ($field->type!=Type::File && isset($_REQUEST[$fieldName])) {
                $values = $_REQUEST[$fieldName];
                if (isset($field->listValues) || (isset($field->listName) && !$field->autocomplete)){
                    $listFieldValues = @$_REQUEST["@{$fieldName}"];
                }
                $countValues = count($values);
                if ($countValues != $count){
                    throw new FormException("Data length of field $fieldName ($countValues) invalid (<> $count).\n". json_encode($_REQUEST));
                }
                for ($i = 0; $i < $count; $i++) {
                    $value = $values[$i];
                    if ($value == '') {
                        if ($field->type == Type::Boolean) {
                            $value = false;
                        }else{
                            $value = null;
                        }
                    } else {
                        if ($field->choices) {
                            $value = json_decode($value);
                        } else {
                            switch ($field->type) {
                                case Type::Int:
                                    if (!$field->autocomplete || is_numeric($value)) {
                                        $value = (int)$value;
                                    }
                                    break;
                                case Type::Float:
                                case Type::Number:
                                    if (!$field->autocomplete || is_numeric($value)) {
                                        $value = (double)$value;
                                    }
                                    break;
                                case Type::Boolean:
                                    if ($value === true || $value == 'true' || $value == 1) {
                                        $value = true;
                                    }else{
                                        $value = false;
                                    }
                                case Type::DateTime:
                                case Type::Date:
                                case Type::Time:
                                    // $value = strtotime($value);

                            }
                        }
                    }
                    $rs[$i][$fieldName] = $value;
                    // List field
                    if (isset($listFieldValues) && isset($listFieldValues[$i])){
                        $rs[$i]["@{$fieldName}"] = json_decode($listFieldValues[$i]);
                    }
                }
            }
            // Files
            if ($field->type==Type::File && isset($_FILES[$fieldName]) && is_array([$fieldName])){
                $values = $_FILES[$fieldName];
                $countValues = count($values['name']);
                if ($countValues != $count){
                    throw new FormException("Data length of field $fieldName ($countValues) invalid (<> $count).\n". json_encode($values));
                }
                $allow_extensions = $field->getAllowFileExtension();
                for ($i = 0; $i < $count; $i++) {
                    if ($values['name'][$i]=='__delete__'){
                        $rs[$i][$fieldName] = '__delete__';
                    }elseif ($values['size'][$i]!=0 && $values['name'][$i]!=''){
                        // Check extension
                        if (isset($allow_extensions)) {
                            $ext = strtolower(pathinfo($values['name'][$i], PATHINFO_EXTENSION));
                            if (!in_array($ext, $allow_extensions)) {
                                throw new FormException(sprintf($this->languagesManager->message("File extension %s not allowed. Accept only %s"), $ext, implode(", ", $allow_extensions)));
                            }
                        }

                        // type/tmp_name/error/size/name
                        $rs[$i][$fieldName] = ['name'=>$values['name'][$i], 'type'=>$values['type'][$i], 'tmp_name'=>$values['tmp_name'][$i], 'size'=>$values['size'][$i], 'error'=>$values['error'][$i]];
                    }else{
                        if ($values['error'][$i]!=UPLOAD_ERR_OK && $values['error'][$i]!=UPLOAD_ERR_NO_FILE) {
                            $rs[$i][$fieldName] = ['error'=>$values['error'][$i]];
                        }else{
                            $rs[$i][$fieldName] = null;
                        }

                    }
                }
            }
        }

        $this->updateInfo = $rs;
    }
}
