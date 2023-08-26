<?php
namespace Minhnhc\Form;

use Minhnhc\Database\Database;
use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Psr\Log\LoggerInterface;


abstract class HelperForm extends Form
{
    private $exportExcelFilename = null;

    protected $params = null;
    protected $show_no = false;

    /**
     * @return null
     */
    public function getExportExcelFilename()
    {
        return $this->exportExcelFilename;
    }

    /**
     * @param null $exportExcelFilename
     */
    public function setExportExcelFilename($exportExcelFilename): void
    {
        $this->exportExcelFilename = $exportExcelFilename;
    }

    public function getParam(int $index, $default = null) {
        if (!isset($this->params) || !is_array($this->params)){
            return $default;
        }
        if (!isset($this->params[$index])){
            return $default;
        }
        return $this->params[$index];
    }
    protected static function getJsonContents($associative = false)
    {
        $input = json_decode(file_get_contents('php://input'), $associative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
            // throw new HttpBadRequestException($this->request, 'Malformed JSON input.');
        }
        return $input;
    }
    public function __construct($name, Database $db, ?LoggerInterface $logger, $params = null)
    {
        parent::__construct($name, $db, $logger);

        $this->params = $params;

        if (!isset($this->action) || $this->action=='') {
            return;
        }

        $this->constructFields();
        $this->constructDatabase();
        $this->constructList();
        $this->constructOther();

        if ($this->isActionExcel()) {
            $this->initExcelParam();
            $this->excel_exporter = new ExcelExporter($this);
            $this->constructExcelExporter($this->excel_exporter);
        }


        if ($this->show_no) {
            array_unshift($this->fieldOrder, 'no');
            $this->setType('no', Type::Int);
            $this->setShow('no');
            $lang = $this->getLanguagesManager();
            if (isset($lang)) {
                $this->setCaption('no', $lang->label('no'));
            }
        }
    }

    /**
     * @param array $info [Field Type => Field Names (Delimited by commas) ]
     */
    public function setInfoType(array $info) {
        foreach ($info as $type=>$fields){
            $this->setType($fields, $type);
        }
    }

    /**
     * @param string $select
     * @param string $from
     * @param null $where
     * @param null $order
     */
    public function setInfoQuery(string $select, string $from, $where=null, $order=null) {
        $this->setQueryFrom($from);
        $this->setQuerySelect($select);
        $this->setQueryWhere($where);
        $this->setQueryOrder($order);
    }

    /**
     * @param array $info [Field Name=>Database Field Name]
     */
    public function setInfoDatabaseField(array $info){
        foreach ($info as $field=>$databaseField){
            $this->getField($field)->setDatabaseField($databaseField);
        }
    }

    /**
     * Set list box info.
     * <br>LIST_VALUES => [[id, value], [id, value]],
     * <br>LIST_NAME => List Name,
     * <br>LIST_PARAM => Linked field name string or [string],
     * <br>
     * <br>LIST_AUTOCOMPLETE => true,
     * <br>LIST_AUTOCOMPLETE_DATA => "select id, name from table where id in (Constant::QueryParamParam)"
     * <br>LIST_AUTOCOMPLETE_INSERT => function (Database $db, string $value): id {}
     * <br>LIST_AUTOCOMPLETE_INSERT => "insert into table (name) values (Constant::QueryParamParam)"
     * <br>LIST_AUTOCOMPLETE_SEARCH => function (Database $db, string $value): [id1, id2] {}
     * <br>LIST_AUTOCOMPLETE_SEARCH => "select id, name from table where name like Constant::QueryParamParam"
     * <br>
     * <br>LIST_TREE => true,
     * <br>
     * <br>LIST_CHOICES => true,
     * <br>LIST_SIMPLE_CHOICES => true,
     * <br>LIST_CHOICES_AUTOCOMPLETE => true,
     * <br>LIST_CHOICES_QUERY_DATA => "select role_id from table where user_id='[id]'",
     * <br>LIST_CHOICES_QUERY_DELETE => "delete from table where user_id='[id]'",
     * <br>LIST_CHOICES_QUERY_INSERT => "insert into table (role_id, user_id) values (Constant::QueryParamValue,'[id]')",
     * <br>LIST_CHOICES_QUERY_SEARCH => "id Constant::QueryParamIn (select user_id from table where role_id Constant::QueryParamCondition)",
     * <br>LIST_CHOICES_AUTOCOMPLETE_FUNCTION => function (Database $db, string $value, $rec): id {} ,
     * <br>LIST_CHOICES_INTERFACE => new class implements ListChoicesInterface {},
     *
     *
     * @param array $info [Field Name=>[key => value]]
     */
    public function setInfoList(array $info){
        foreach ($info as $fieldName=>$rec){

            $field = $this->getField($fieldName);

            $field->listParam = @$rec[LIST_PARAM];
            $field->listFixParam = @$rec[LIST_FIX_PARAM];

            if (isset($rec[LIST_LONG])) {
                $field->listLong = $rec[LIST_LONG];
            }

            if (isset($rec[LIST_STRING_VALUE])) {
                $field->listStringValue = $rec[LIST_STRING_VALUE];
            }

            if (isset($rec[LIST_TREE])){
                $field->tree = $rec[LIST_TREE];
            }
            if (isset($rec[LIST_TEXT_FIELD])){
                $field->listTextField = $rec[LIST_TEXT_FIELD];
            }

            if (isset($rec[LIST_CHOICES])){
                $field->choices = $rec[LIST_CHOICES];
                if (isset($rec[LIST_SIMPLE_CHOICES])) {
                    $field->simpleChoices = $rec[LIST_SIMPLE_CHOICES];
                }
                if (isset($rec[LIST_CHOICES_AUTOCOMPLETE])) {
                    $field->autocompleteChoices = $rec[LIST_CHOICES_AUTOCOMPLETE];
                }
                if (isset($rec[LIST_CHOICES_AUTOCOMPLETE_FUNCTION])) {
                    $field->setChoicesAutocompleteFunction($rec[LIST_CHOICES_AUTOCOMPLETE_FUNCTION]);
                }
                if (isset($rec[LIST_CHOICES_QUERY_DATA])){
                    $field->setChoicesQueryData($rec[LIST_CHOICES_QUERY_DATA]);
                }
                if (isset($rec[LIST_CHOICES_QUERY_DELETE])){
                    $field->setChoicesQueryDelete($rec[LIST_CHOICES_QUERY_DELETE]);
                }
                if (isset($rec[LIST_CHOICES_QUERY_INSERT])){
                    $field->setChoicesQueryInsert($rec[LIST_CHOICES_QUERY_INSERT]);
                }
                if (isset($rec[LIST_CHOICES_QUERY_SEARCH])){
                    $field->setChoicesQuerySearch($rec[LIST_CHOICES_QUERY_SEARCH]);
                }

                if (isset($rec[LIST_CHOICES_INTERFACE])){
                    $field->setChoicesInterface($rec[LIST_CHOICES_INTERFACE]);
                }
            }

            if (isset($rec[LIST_AUTOCOMPLETE])){
                $field->autocomplete = $rec[LIST_AUTOCOMPLETE];
                if (isset($rec[LIST_AUTOCOMPLETE_DATA])){
                    $field->setAutocompleteData($rec[LIST_AUTOCOMPLETE_DATA]);
                }
                if (isset($rec[LIST_AUTOCOMPLETE_INSERT])){
                    $field->setAutocompleteInsert($rec[LIST_AUTOCOMPLETE_INSERT]);
                }
                if (isset($rec[LIST_AUTOCOMPLETE_SEARCH])){
                    $field->setAutocompleteSearch($rec[LIST_AUTOCOMPLETE_SEARCH]);
                }

                if (isset($rec[LIST_AUTOCOMPLETE_INTERFACE])){
                    $field->setAutocompleteInterface($rec[LIST_AUTOCOMPLETE_INTERFACE]);
                }

            }
            if (isset($rec[LIST_NAME])){
                $field->listName = $rec[LIST_NAME];
            }else{
                $field->listValues = @$rec[LIST_VALUES];
            }
        }
    }

    public function isShow($fieldName): bool
    {
        return $this->getField($fieldName)->show;
    }
    public function getDecimal($fieldName)
    {
        return $this->getField($fieldName)->decimal;
    }
    public function isHidden($fieldName): bool
    {
        return $this->getField($fieldName)->hidden;
    }

    public function setKey($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'key', $value);
    }

    public function isKey($fieldName): bool
    {
        return $this->getField($fieldName)->key;
    }

    public function setCaption($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'caption', $value);
    }

    public function setHeight($fieldNames, $value)
    {
        $this->set($fieldNames, 'height', $value);
    }

    public function setImage($fieldNames, $thumbnailWidthSize = 100, $imageParams = null)
    {
        if ($thumbnailWidthSize==false){
            $this->set($fieldNames, 'image', false);
        }else{
            $this->set($fieldNames, 'image', true);
            $this->set($fieldNames, 'thumbnailWidthSize', $thumbnailWidthSize);
            $this->set($fieldNames, 'imageParams', $imageParams);
        }
    }

    public function setWidth($fieldNames, $width = 100) {
        $this->set($fieldNames, 'width', $width);
    }

    public function getCaption($fieldName)
    {
        return $this->getField($fieldName)->caption;
    }

    /**
     * If $saveFileName=true, it will save file to database. Table must have a field that's name $fieldName
     * @param $fieldNames
     * @param $folder
     * @param false $saveFilename
     */
    public function setSaveFileFolder($fieldNames, $folder, $saveFilename = true){
        $fieldNames = explode(',', $fieldNames);
        foreach ($fieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            $field = $this->getField($fieldName);
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
            $field->setSaveFileFolder($folder);
            $field->setSaveFileName($saveFilename);
        }
    }

    public function setSaveFileFolderByMonth($fieldNames, $value = true){
        $fieldNames = explode(',', $fieldNames);
        foreach ($fieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            $field = $this->getField($fieldName);
            $field->setSaveFileFolderByMonth($value);
        }
    }

    /**
     * @param $fieldNames
     * @param array|string $extensions
     */
    public function setAllowFileExtension($fieldNames, $extensions){
        $fieldNames = explode(',', $fieldNames);
        foreach ($fieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            $field = $this->getField($fieldName);
            $field->setAllowFileExtension($extensions);
        }
    }

    public function setEditMode($fieldNames, $value = true) {
        $this->set($fieldNames, 'editMode', $value);
    }

    public function setEditable($fieldNames, $value = true, $canUpdate = true, $canInsert = true)
    {

        if (!isset($fieldNames)){
            $fieldNames = implode(",", $this->getFieldNames());
        }
        $this->set($fieldNames, 'editable', $value);
        // Update, too
        if ($canUpdate){
            $this->set($fieldNames, 'update', $value);
        }

        // Insert, too
        if ($canInsert){
            $this->set($fieldNames, 'insert', $value);
        }
    }

    public function isEditable($fieldName): bool
    {
        return $this->getField($fieldName)->editable;
    }

    public function setHidden($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'hidden', $value);
    }



    public function setDefaultValue($fieldNames, $value, $notInsert=false)
    {
        $this->set($fieldNames, 'defaultValue', $value);
        $this->set($fieldNames, 'defaultValueNotInsert', $notInsert);
    }

    public function getDefaultValue($fieldName)
    {
        return $this->getField($fieldName)->defaultValue;
    }

    public function setOrder($fields){
        if (!is_array($this->fieldOrder)){
            $this->fieldOrder = [];
        }

        if (!is_array($fields)){
            $fields = explode(',', $fields);
            // array_push($this->fieldOrder, $fields);
        }

        foreach ($fields as $field){
            $field = trim($field);
            if (!in_array($field, $this->fieldOrder )) {
                $this->fieldOrder[] = $field;
            }
        }

    }

    public function setShow($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'show', $value);
    }

    public function setColSpan($fieldNames, $value = 2)
    {
        $this->set($fieldNames, 'colSpan', $value);
    }

    public function setExcel($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'excel', $value);
    }

    public function isExcel($fieldName): bool
    {
        return $this->getField($fieldName)->excel;
    }

    public function setRequired($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'required', $value);
    }

    public function setRequiredGroup($fieldNames, $value)
    {
        $this->set($fieldNames, 'requiredGroup', $value);
    }

    public function setMinMax($fieldNames, float $min = null, float $max = null)
    {
        $this->set($fieldNames, 'min', $min);
        $this->set($fieldNames, 'max', $max);
    }

    public function setLengthMinMax($fieldNames, int $min = null, int $max = null)
    {
        $this->set($fieldNames, 'lengthMin', $min);
        $this->set($fieldNames, 'lengthMax', $max);
    }

    public function isRequired($fieldName): bool
    {
        return $this->getField($fieldName)->required;
    }
    public function setLocked($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'locked', $value);
    }

    public function isLocked($fieldName): bool
    {
        return $this->getField($fieldName)->locked;
    }

    public function setDecimal($fieldNames, $value)
    {
        $this->set($fieldNames, 'decimal', $value);
    }


    public function setCanUpdate($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'update', $value);
    }

    public function isCanUpdate($fieldName): bool
    {
        return $this->getField($fieldName)->update;
    }

    public function setCanInsert($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'insert', $value);
    }

    public function isCanInsert($fieldName): bool
    {
        return $this->getField($fieldName)->insert;
    }

    public function setSortable($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'sortable', $value);
    }

    public function isSortable($fieldName): bool
    {
        return $this->getField($fieldName)->sortable;
    }

    public function setSummary($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'summary', $value);
    }

    public function setGroupName($fieldNames, $value, $groupCols = null)
    {
        $this->set($fieldNames, 'groupName', $value);
        $this->set($fieldNames, 'groupCols', $groupCols);
    }

    public function setGroupCols($fieldNames, $value)
    {
        $this->set($fieldNames, 'groupCols', $value);
    }

    public function setLineBreak($fieldNames, $value=true)
    {
        $this->set($fieldNames, 'lineBreak', $value);
    }


    public function isSummary($fieldName): bool
    {
        return $this->getField($fieldName)->summary;
    }

    public function setSearchable($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'searchable', $value);
    }

    public function setSearchOnly($fieldNames, $value = true)
    {
        $this->set($fieldNames, 'searchOnly', $value);
        if ($value) {
            $this->setOrder($fieldNames);
            $this->setHidden($fieldNames);
        }
    }

    public function isSearchable($fieldName): bool
    {
        return $this->getField($fieldName)->searchable;
    }

    public function setType($fieldNames, $value = Type::String)
    {
        $this->set($fieldNames, 'type', $value);
    }

    public function getType($fieldName): int
    {
        return $this->getField($fieldName)->type;
    }


    protected function isOwner(): bool {
        return true;
    }

    function beforeData(&$data, &$total, &$list): bool
    {
        return true;
    }

    function afterInit(): void
    {
    }

    function afterDefine(): void
    {
    }

    function afterData(&$data, &$total, &$summary, &$list, &$extra): void
    {

        if ($this->show_no){
            $no = $this->pageSize*($this->pageIndex-1);
            foreach ($data as &$rec) {
                $rec['no'] = ++$no;
            }
        }

    }

    function afterUpdate(&$rs): void
    {

    }

    function beforeUpdate(&$rs, &$message): bool
    {
        return true;
    }

    function afterDelete(&$rs): void
    {

    }

    function beforeDelete(&$rs, &$message): bool
    {
        return true;
    }

    public function customAction($action, $rs) {
        if (true){
            $data = json_encode($rs, JSON_PRETTY_PRINT);
            throw new FormException("Action $action is not supported.\n$data");
        }
    }
    /**
     * Create general info for fields here<br>
     * Order: setOrder<br>
     * Show: setShow<br>
     * Editable: setEditable<br>
     * Searchable: setSearchable<br>
     * Sortable: setSortable<br>
     * Update: setCanUpdate<br>
     * Type: setInfoType<br>
     * Required:setRequired<br>
     * Decimal:setDecimal<br>
     *
     */
    abstract protected function constructFields(): void;


    /**
     * Create database info here.<br>
     * 1) Update table: setUpdatedTable<br>
     * 2) Keys: setKey<br>
     * 3) Queries: setInfoQuery<br>
     * 4) Database cols: setInfoDatabaseField<br>
     * 5) Required:setRequired<br>
     */
    abstract protected function constructDatabase(): void;

    /**
     * Create list field <br>
     * 1) List: setInfoList <br>
     */
    abstract protected function constructList(): void;

    /**
     * Setting other info
     */
    abstract protected function constructOther(): void;

    /**
     * @param ExcelExporter $ep
     */
    protected function constructExcelExporter(ExcelExporter $ep): void {
        if (!isset($this->fieldOrder)) {
            return;
        }
        $ep->set_output_file_name();
        $ep->set_oriental();
        $ep->set_show_zero(false);
        $ep->set_auto_filter(true);
        if (isset($this->exportExcelFilename)){
            $ep->set_output_file_name($this->exportExcelFilename);
        }

        // Export field
        $rec = $this->loadFormSetting();
        $this->applyFormSetting($rec);
        if (!isset($this->excelFields) || !is_array($this->excelFields) || count($this->excelFields) <=0 ) {
            if (!isset($this->orderFields) || !is_array($this->orderFields) || count($this->orderFields) <=0 ) {
                $fields = [];
                /**
                 * @var $field Field
                 */
                foreach ($this->fieldOrder as $fieldName) {
                    $field = $this->getField($fieldName);
                    if (!isset($field->hidden) || !$field->hidden) {
                        $fields[] = $field->name;
                    }
                }
            }else{

                // Dont export hidden fields

                $fields = [];
                /**
                 * @var $field Field
                 */
                foreach ($this->orderFields as $fieldName) {
                    $field = $this->getField($fieldName);
                    if (!isset($field->hidden) || !$field->hidden) {
                        $fields[] = $field->name;
                    }
                }
            }
        }else{
            $fields = $this->excelFields;
        }

        // $fields = ["id", "code", "type", "lang1", "lang2", "lang3", "server_side"];
        $ep->set_export_fields($fields);

        foreach ($fields as $fieldName) {
            $field = $this->getField($fieldName);
            $type = $field->type;
            if (isset($field->listName) || isset($field->listValues)) {
                $ep->set_width($fieldName);
            }elseif ($type == Type::Text) {
                $ep->set_width($fieldName, 40);
                $ep->set_wrap_text($fieldName);
            }elseif ($type == Type::String) {
                $ep->set_width($fieldName, 20);
                // $ep->set_wrap_text($fieldName);
            }elseif (!$field->image) {
                $ep->set_width($fieldName);
            }

            if (!isset($field->listValues) && !isset($field->listName)) {
                $ep->set_out_type($fieldName,ExcelExporter::OutTypeRaw);
            }

            if ($field->choices) {
                $ep->set_wrap_text($fieldName);
            }
        }

        $this->excel_exporter = $ep;
    }

    function afterExcel(ExcelExporter $ep): void {

    }

    function beforeExcel(ExcelExporter $ep, &$data) {
        if (!isset($data)) {
            return;
        }
    }

    function afterImport(ExcelImporter $ip, $data): void {
        if (isset($ip) || isset($data)) {
            return;
        }
    }
    function beforeImport(ExcelImporter $ip, &$data): void {
        if (isset($ip) || isset($data)) {
            return;
        }
    }

    /**
     * @param string $configFile 'Config Text File
     * @param $select ' Query's select part
     * @param array|null $masters ' Map of list of list user for LIST_VALUES
     * @param bool $ignoreDatabaseField ' Should ignore database field if use query as "select * from ($query) q"
     */
    public function applyFormConfig(string $configFile, &$select, array $masters = null, bool $ignoreDatabaseField = true): ?array
    {
        $start = $this->milliTime();
        $rs = self::loadTxtConfig($configFile);
        $timeLoadTxt = $this->milliTime() - $start;
        $select = [];
        $list = [];
        foreach ($rs as $rec) {
            if (!is_array($rec) || count($rec) < 16) {
                continue;
            }
            $prefix = $rec[2] ?? ''; // $sh->getCell("C$i")->getValue();
            $column = $rec[3]; // $sh->getCell("D$i")->getValue();
            $alias = $rec[4] ?? ''; // $sh->getCell("E$i")->getValue();
            $type = $rec[5]; // $sh->getCell("F$i")->getValue();
            $duplicate = $rec[6] ?? ''; // $sh->getCell("G$i")->getValue();
            $except = $rec[7]; // $sh->getCell("H$i")->getValue();
            $listQuery = $rec[8]; // $sh->getCell("I$i")->getValue();
            $listValuesName = $rec[9]; // $sh->getCell("J$i")->getValue();
            $listName = $rec[10]; // $sh->getCell("K$i")->getValue();
            $show = $rec[11]; // $sh->getCell("L$i")->getValue();
            $width = $rec[12]; // $sh->getCell("M$i")->getValue();
            $editable = $rec[13];
            $required = $rec[14];
            $groupName = $rec[15];
            $caption = @$rec[16];
            $groupCols = @$rec[17];
            $groupHW = @$rec[18];
            if (!is_numeric($groupHW)) {
                $groupHW = null;
            }
            if (!is_numeric($groupCols)) {
                $groupCols = null;
            }



            if (!isset($except) || $except!=""){
                continue;
            }
            $field = $alias != '' ? $alias : $column;
            $this->setOrder($field);
            if ($show!="") {
                $this->setShow($field);
            }
            $this->setSearchable($field);
            $this->setSortable($field);
            if ($editable!='') {
                $this->setEditable($field);
                if ($required!='') {
                    $this->setRequired($field);
                }
            }
            if ($groupName!='') {
                $this->fields[$field]->groupName = $groupName;
            }
            $this->setGroupCols($field, $groupCols);

            if (isset($caption) && $caption!='') {
                $this->setCaption($field, $caption);
            }
            if ($width!=''){
                $this->getField($field)->width = intval($width);
            }
            if ($prefix !="" || $alias!=""){
                if ($prefix!="") {
                    $select[] = "{$prefix}.{$column} as $field";
                }else{
                    $select[] = "$column as $field";
                }
                if ($alias != "") {
                    if (!$ignoreDatabaseField) {
                        if ($prefix!="") {
                            $this->setInfoDatabaseField([$field => "{$prefix}.{$column} "]);
                        }else{
                            $this->setInfoDatabaseField([$field => "$column"]);
                        }

                    }
                }
            }else{
                $select[] = $field;
            }

//            if ($duplicate!="" || $alias!="") {
//                if ($prefix!=""){
//                    $select[] = "{$prefix}.{$column} as $field";
//                    if (!$ignoreDatabaseField) {
//                        $this->setInfoDatabaseField([$field => "{$prefix}.{$column} "]);
//                    }
//                }else{
//                    $select[] = "{$column} as $field";
//                    if (!$ignoreDatabaseField) {
//                        $this->setInfoDatabaseField([$field => $column]);
//                    }
//                }
//            }else{
//                $select[] = $field;
//            }

            switch ($type){
                case 'tinyint':
                    $this->setType($field, Type::Boolean);
                    break;
                case 'int':
                case 'smallint':
                    $this->setType($field, Type::Int);
                    break;
                case 'double':
                    $this->setType($field, Type::Float);
                    $this->setDecimal($field, 2);
                    break;
                case 'date':
                    $this->setType($field, Type::Date);
                    break;
                case 'text':
                    $this->setType($field, Type::Text);
                    break;
                case 'file':
                    $this->setType($field, Type::File);
                    break;
                case 'datetime':
                    $this->setType($field, Type::DateTime);
                    break;
            }

            if ($listName!='') {
                if ($listName == 'item'){
                    $this->setInfoList([
                        $field => [
                            LIST_NAME => $listName,
                            LIST_LONG => true
                        ]
                    ]);

                }else{
                    $this->setInfoList([
                        $field => [
                            LIST_NAME => $listName,
                        ]
                    ]);

                }
            }elseif ($listValuesName!='' && is_array($masters) && isset($masters[$listValuesName])) {
                $this->setInfoList([
                    $field => [ LIST_VALUES => $masters[$listValuesName]]
                ]);
            }elseif ($listQuery!=''){
                $list[] = [$field, $listName, $listValuesName, $listQuery];
                $this->setInfoList([
                    $field => [ LIST_VALUES => $this->db->getListOfList($listQuery)]
                ]);
            }

        }
        $all = $this->milliTime() - $start;
        $select = implode(',', $select);
        return [$timeLoadTxt, $all, $list];
    }

    static public function loadExcelConfig($configExcelFile): array
    {
        $reader = new Xlsx();
        $ex = $reader->load($configExcelFile);
        $sh = $ex->getSheet(0);
        $r = 2;
        $ret = [];
        while ($sh->getCell("A$r")->getValue() != "") {
            $rec = [];
            for ($c=1; $c<=18;$c++) {
                $rec[] = $sh->getCellByColumnAndRow($c, $r)->getValue();
            }
            $ret[] = $rec;
            $r++;
        }

        return $ret;
    }

    static public function loadTxtConfig($configTxtFile, $column = 18): array
    {
        $content = file_get_contents($configTxtFile);
        $rs = explode("\n", $content);
        array_splice($rs, 0, 1);
        $ret = [];
        foreach ($rs as $rec) {
            $rec = str_replace("\r", "", $rec);
            $rec = str_replace('"', "", $rec);
            $rec = explode("\t", $rec);
            if (count($rec) < $column || $rec[0] == '') {
                continue;
            }
            $ret[] = $rec;
        }
        return $ret;
    }


    public function setSearchRequireGroup($fieldNames, $requireMessage, $set = true) {
        if (!is_array($fieldNames)) {
            $fieldNames = explode(",", $fieldNames);
            foreach ($fieldNames as &$fieldNameRef){
                $fieldNameRef = trim($fieldNameRef);
            }
        }
        if ($set) {
            $this->searchRequireGroup[$requireMessage] = $fieldNames;
        }else{
            if (isset($this->searchRequireGroup[$requireMessage])){
                $new = [];
                foreach ($this->searchRequireGroup[$requireMessage] as $fieldName) {
                    if (!in_array($fieldName, $fieldNames)) {
                        $new[] = $fieldName;
                    }
                }
                if (count($new) > 0) {
                    $this->searchRequireGroup[$requireMessage] = $new;
                }else{
                    unset($this->searchRequireGroup[$requireMessage]);
                }
            }
        }
    }

}
