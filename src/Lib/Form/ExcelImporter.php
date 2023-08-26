<?php
namespace Sannomiya\Form;
use App\Application\Handlers\BusinessLogicException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use Sannomiya\Database\Database;
use Sannomiya\Util\Helper;


abstract class ExcelImporter{

    protected array $fields = [];
    protected array $indexes = [];
    protected array $types = [];
    protected array $names = [];
    protected array $requires = [];

    protected int $start_row = 1;
    protected string $key_col = 'A';
    protected string $filename = 'sample.xlsx';
    protected string $date_format = 'Y/m/d';
    protected ?array $samples_data = null;
    protected ?array $config = null;

    protected int $maxSample = 1000;
    protected ?array $importFields = null;
    private bool $deleteData = false;

    const REQUIRE_READONLY = 2;
    const REQUIRE_IMPORT_IF_NOT_NULL = 3;

    /**
     * @var HelperForm
     */
    protected $fm = null;

    public function __construct(Form $fm) {
        $this->fm = $fm;

        $config = $this->config();
        if (isset($config)) {
            $this->init($config);
        }else{
            $this->init($fm);
        }
        $this->samples_data = $this->sample();
    }

    protected function init($data){
        $this->fields = [];
        if ($data instanceof Form) {
            // Init by form
            $this->fm = $data;
            $fields = $this->fm->getFieldOrder();

            /**
             * @var Field $field
             */
            $lang = $this->fm->getLanguagesManager();
            foreach ($fields as $fieldName) {
                $field = $this->fm->getField($fieldName);

                if (!$field->editable && !in_array($fieldName, $this->fm->getImportKeys()) && !in_array($fieldName, $this->fm->getImportReferenceFields())) {
                    continue;
                }

                // Dont import file
                if ($field->type == Type::File) {
                    continue;
                }
                // No multi
                if ($field->choices) {
                    continue;
                }
                $this->fields[] = $field->name;
                $this->types[$field->name] = $field->type;
                if (in_array($fieldName, $this->fm->getImportReferenceFields())) {
                    $this->requires[$field->name] = ExcelImporter::REQUIRE_READONLY;
                }else{
                    $this->requires[$field->name] = $field->required;
                }


                if (isset($field->caption) && $field->caption != $field->name) {
                    if (isset($field->groupName)){
                        $this->names[$field->name] = $field->groupName . '/' . $field->caption;
                    }else{
                        $this->names[$field->name] = $field->caption;
                    }
                }else{
                    $this->names[$field->name] = $lang->label($field->name);
                }
            }
        }else{
            // Init by data
            foreach ($data as $item){
                $field = $item[0];
                $this->fields[] = $field;
                $this->types[$field] = $item[1];
                $this->names[$field] = $item[2];
                $this->requires[$field] = @$item[3];
            }
        }
    }

    public function getType($field){
        return @$this->types[$field];
    }

    public function isRequire($field): bool
    {
        return @$this->requires[$field]===true;
    }

    public function isReadonly($field): bool
    {
        return @$this->requires[$field]===ExcelImporter::REQUIRE_READONLY;
    }

    public function isImportIfNotNull($field): bool
    {
        return @$this->requires[$field]===ExcelImporter::REQUIRE_IMPORT_IF_NOT_NULL;
    }

    public function getName($field){
        return @$this->names[$field];
    }

    /**
     * Start row, include header
     * @return int
     */
    public function getStartRow(): int
    {
        return $this->start_row;
    }


    public function getKeyCol(): string
    {
        return $this->key_col;
    }

    /**
     * @return int
     */
    public function getMaxSample(): int
    {
        return $this->maxSample;
    }

    /**
     * @param int $maxSample
     */
    public function setMaxSample(int $maxSample): void
    {
        $this->maxSample = $maxSample;
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @throws BusinessLogicException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws FormException
     */
    public function getImportDataOld($filepath, $csv = false): ?array
    {
        if ($csv) {
            $reader = new Csv();
        }else{
            $reader = new Xlsx();
        }
        $spreadsheet = $reader->load($filepath);
        $spreadsheet->setActiveSheetIndex ( 0 );
        $sh = $spreadsheet->getActiveSheet();

        $r = $this->getStartRow() + 1;
        $count = count($this->fields);

        $ret = [];
        $c = $this->getKeyCol();
        while ($sh->getCell("$c$r")->getValue() != ''){
            $rec = [];
            for ($i=0;$i<$count;$i++){
                $value = trim($sh->getCellByColumnAndRow($i+1,$r)->getValue());
                $type = $this->getType($this->fields[$i]);
                $require = $this->isRequire($this->fields[$i]);
                $caption = $this->getName($this->fields[$i]);
                $field = $this->fm->getField($this->fields[$i]);

                if ($require===true && (!isset($value) || $value==='')) {
                    throw new FormException("[Line $r] $caption is required.");
                }

                if ($type == Type::Int || $type == Type::Number || $type == Type::Float) {
                    if ($value!=''){
                        //  && ( (!isset($field)) || (!isset($field->listValues) && !isset($field->listName)))

                        if (!is_numeric($value) && (!isset($field) || (!isset($field->listValues) && !isset($field->listName)))) {
                            throw new FormException("[Line $r] $caption must be a number. ($value)");
                        }
                    }else{
                        $value = null;
                    }
                }
                $rec[$this->fields[$i]] = $value;
            }
            $ret[] = $rec;
            $r++;
        }

        if (count($ret) == 0) {
            $lang = $this->fm->getLanguagesManager();
            throw new BusinessLogicException($lang->message('Have no data to import.' . $this->key_col));
        }

        return $ret;
    }

    static function alpha2num($column) {
        $number = 0;
        foreach(str_split($column) as $letter){
            $number = ($number * 26) + (ord(strtolower($letter)) - 96);
        }
        return $number;
    }

    public function getImportData($filepath, $csv = false): ?array
    {
        if ($csv) {
            $reader = ReaderEntityFactory::createCSVReader();
        }else{
            $reader = ReaderEntityFactory::createXLSXReader();
        }
        $reader->setShouldPreserveEmptyRows(true);
        $reader->open($filepath);

        // $reader->getSheetIterator()->next();
        // $sh = $reader->getSheetIterator()->current();
        $ret = [];
        $c = $this->getKeyCol();
        $cNumber = self::alpha2num($c)-1;
        $count = count($this->fields);
        foreach ($reader->getSheetIterator() as $sh) {
            foreach ($sh->getRowIterator() as $r => $row) {
                if ($r <= $this->getStartRow()) {
                    continue;
                }
                // do stuff with the row
                $cells = $row->getCells();
                $cellCount = count($cells);

                if ($cNumber >= $cellCount) {
                    break;
                }
                $value = $cells[$cNumber]->getValue();
                // End
                if  (!isset($value) || $value=='') {
                    break;
                }
                $rec = [];
                for ($i=0;$i<$count;$i++){
                    if ($i < $cellCount) {
                        $cell = $cells[$i];
                        $value = $cell->getValue();
                    }else{
                        $value = null;
                    }
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                    $type = $this->getType($this->fields[$i]);
                    $require = $this->isRequire($this->fields[$i]);
                    $caption = $this->getName($this->fields[$i]);
                    $field = $this->fm->getField($this->fields[$i]);

                    if ($require===true && (!isset($value) || $value==='')) {
                        throw new FormException("[Line $r] $caption is required.");
                    }

                    if ($type == Type::Int || $type == Type::Number || $type == Type::Float) {
                        if ($value!=''){
                            //  && ( (!isset($field)) || (!isset($field->listValues) && !isset($field->listName)))

                            if (!is_numeric($value) && (!isset($field) || (!isset($field->listValues) && !isset($field->listName)))) {
                                throw new FormException("[Line $r] $caption must be a number. ($value)");
                            }
                        }else{
                            $value = null;
                        }
                    }else if ($type == Type::Date || $type == Type::DateTime) {
                        if (isset($value)) {
                            if ($value instanceof \DateTime) {
                                $value = $value->format('Y/m/d H:i:s');
                            }
                        }
                    }

                    // Date time convert
                    if ($value instanceof \DateTime) {
                        $value = $value->format('Y/m/d H:i:s');
                    }

                    $rec[$this->fields[$i]] = $value;
                }
                $ret[] = $rec;
            }
            break;
        }

        if (count($ret) == 0) {
            $lang = $this->fm->getLanguagesManager();
            throw new BusinessLogicException($lang->message('Have no data to import.' . $this->key_col));
        }

        return $ret;
    }

    /**
     * @return bool
     */
    public function isDeleteData(): bool
    {
        return $this->deleteData;
    }

    /**
     * @param bool $deleteData
     */
    public function setDeleteData(bool $deleteData): void
    {
        $this->deleteData = $deleteData;
    }

    /**
     * @throws BusinessLogicException
     */
    protected function standardizeImportData(&$rs){
        $line = $this->start_row + 1;
        foreach ($rs as &$rec) {
            foreach ($this->fields as $fieldName) {
                $value = $rec[$fieldName];
                $field = $this->fm->getField($fieldName);
                $type = $this->getType($fieldName);
                if (!isset($value) || $value=='') {
                    $rec[$fieldName] = null;
                }else{
                    if (isset($field->listName) || isset($field->listValues)){
                        if ($field->autocomplete) {
                            $rec[$fieldName] = $this->fm->getAutocompleteInsert($field, $value);
                        }else{
                            $key = $this->fm->getReverseValue($fieldName, $value);
                            if (!isset($key)) {
                                if ($this->isRequire($fieldName)){
                                    $caption = $this->getName($fieldName);
                                    throw new BusinessLogicException("[Line $line] $caption's $value is invalid");
                                }else{
                                    $rec[$fieldName] = null;
                                }
                            }else{
                                $rec[$fieldName] = $key;
                            }
                        }
                    }

                    if ($type == Type::Date || $type == Type::DateTime) {
                        if (is_numeric($value)) {
                            $rec[$fieldName] = Helper::fromExcelDate($value);
                        }else{
                            $rec[$fieldName] = Helper::toDate($value, $this->date_format);
                        }
                    }
                }
            }
            $line++;
        }
    }

    protected function doHelperImport($rs)
    {
        $table = $this->fm->getUpdatedTable();
        $fields = [];
        $values = [];

        $wheres = [];
        $defaultValues = [];
        $db = Database::getInstance();

        $importKeys = $this->fm->getImportKeys();

        /**
         * @var $field Field
         */
        foreach ($this->fm->getFields() as $field){
            if (isset($field->defaultValue)) {
                if ($field->update || $field->insert) {
                    continue;
                }
                $fields[] = $field->getDatabaseField();
                $values[] = '?';
                $defaultValues[] = $field->defaultValue;
            }
        }
        foreach ($this->fields as $fieldName) {
            $field = $this->fm->getField($fieldName);
            if (!$field->update && !$field->insert && @$this->requires[$fieldName]===ExcelImporter::REQUIRE_READONLY) {
                continue;
            }
            if ($field->insert) {
                $fields[] = $field->getDatabaseField();
                $values[] = '?';
            }
        }

        // Key
        $keys = $this->fm->getKeyFields();
        $idsMax = [];
        foreach ($keys as $key) {
            $query = $this->fm->getQueryGetMaxId($key);
            if (isset($query)) {
                $idsMax[$key] = $db->get($query) + 1;
            }
        }

        foreach ($idsMax as $fieldName => $idMax) {
            $fields[] = $fieldName;
            $values[] = '?';
        }

        $importKeysForInsert = [];
        foreach ($importKeys as $key) {
            if ($key == 'id') {
                // Dont insert autonumber
                continue;
            }
            if (!in_array($key, $fields)) {
                $importKeysForInsert[] = $key;
                $fields[] = $key;
                $values[] = '?';
            }
        }


        $values = implode(', ', $values);
        $fields = implode(', ', $fields);

        // Insert query
        $query = "insert into $table ($fields) values ($values)";
        $queryCheck = null;

        // Update
        if (count($importKeys) > 0) {
            foreach ($importKeys as $key) {
                $field = $this->fm->getField($key);
                $wheres[] = $field->getDatabaseField() . " = ?";
            }
            $queryCheck = "select {$importKeys[0]} from $table where " . implode(" and ", $wheres );

        }

        $db->begin();
        $line = 2;
        foreach ($rs as $rec) {
            $values = $defaultValues;
            $updateValues = [];
            $set = [];
            foreach ($this->fields as $fieldName) {
                $field = $this->fm->getField($fieldName);
                if (!$field->update && !$field->insert) {
                    continue;
                }

                if ($field->insert) {
                    $values[] = $rec[$fieldName];
                }

                if ($field->update) {
                    if (!$this->isImportIfNotNull($fieldName) || (isset($rec[$fieldName]) && $rec[$fieldName]!='')) {
                        $set[] = $field->getDatabaseField() . " = ?";
                        $updateValues[] = $rec[$fieldName];
                    }

                }
            }


            $update = false;
            if (isset($queryCheck)) {
                $whereValues = [];
                foreach ($importKeys as $key) {
                    if (!isset($rec[$key])) {
                        $field = $this->fm->getField($key);
                        $value = $field->getDefaultValue();
                    }else{
                        $value = $rec[$key];
                    }
                    $whereValues[] = $value;
                }
                $check = $db->get($queryCheck, $whereValues);
                if (isset($check)) {
                    // Update
                    $update = true;
                    foreach ($whereValues as $value) {
                        $updateValues[] = $value;
                    }
                }
            }
            if ($update) {
                $queryUpdate = "update $table set " . implode(", ", $set) . " where " . implode(" and ", $wheres );
                $db->execute($queryUpdate, $updateValues);
            }else{
                foreach ($idsMax as $field=>$idMax) {
                    $values[] = $idMax;
                    $rec[$field] = $idMax;
                    $idsMax[$field]++;
                }
                foreach ($importKeysForInsert as $key) {
                    $values[] = $rec[$key];
                }
                $db->execute($query, $values);
                if (!isset($rec['id'])) {
                    $rec['id'] = $db->insertedId();
                }
            }

            $this->doAfterLineImport($db, $rec, $update);

            $line++;
        }
        $db->commit();
    }

    protected function doAfterLineImport(Database $db, array $rec, bool $update) {

    }

    /**
     * @param $rs
     * @param $message
     * @return boolean
     */
    public abstract function doCheckData(&$rs, &$message): bool;

    public abstract function doImport($rs);

    public abstract function doAfterImport($tmp_path, $filename, $note);

    public abstract function doBeforeImport($tmp_path, $filename, $note): bool;

    /**
     * Return sample data as record set. Return null if want to get data from Form object
     * @return array|null
     */
    protected abstract function sample(): ?array;

    /**
     * Format: [[field, type, name, required, name_to_code, name_to_code_insert]]<br>
     * Return null if you want to config from Form object
     * @return array|null
     */
    protected abstract function config(): ?array;

    /**
     * @return DownloadObject
     * @throws Exception
     */
    public function doSample(): DownloadObject
    {
        $data = $this->samples_data ?? $this->fm->getAllData($this->maxSample);
        $fm = $this->fm;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $fields = $this->getFields();

        $count = count($fields);
        $last_column = Coordinate::stringFromColumnIndex($count);

        $row = $this->getStartRow();

        // Header
        $col = 1;
        $sheet->getStyle("A$row:$last_column$row")->applyFromArray(array(
            'font' => ['bold' => true, 'color' => ['rgb' => 'F9F9F9']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '000000']]
        ));

        foreach ($fields as $fieldName) {
            $sheet->setCellValueByColumnAndRow($col, $row, $this->getName($fieldName));
            if ($this->isRequire($fieldName)) {
                $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray(array(
                    'font' => ['bold' => true, 'color' => ['rgb' => 'F9F9F9']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FF0000']]
                ));
            }else if ($this->isReadonly($fieldName)) {
                $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray(array(
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E1E1E1']]
                ));
            }else if ($this->isImportIfNotNull($fieldName)) {
                $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray(array(
                    'font' => ['bold' => true, 'color' => ['rgb' => '00FF00']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '000000']]
                ));
            }
            $col++;
        }

        // Data
        $fm->prepareListValues($data);
        for ($i=0; $i<count($data); $i++) {
            $row++;
            $rec = $data[$i];
            $col = 1;
            foreach ($fields as $fieldName) {
                $type = $this->getType($fieldName);
                $value = $fm->getValue($fieldName, $rec);
                if ($type == Type::Date || $type == Type::DateTime) {
                    $value = Helper::toExcelDate($value);
                }

                // String with =
                if (is_string($value)){
                    $value = trim($value);
                    if (strpos($value, '=')===0){
                        $value = "'$value";
                    }
                }
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
        }

        for ($col = 0; $col < $count; $col++) {
            $colName = Coordinate::stringFromColumnIndex($col+1);
            $fieldName = $fields[$col];
            $type = $this->getType($fieldName);
            if ($type == Type::Text) {
                $sheet->getColumnDimension($colName)->setWidth(50);
                $sheet->getStyle("{$colName}1:{$colName}$count")
                    ->getAlignment()->setWrapText(true);
            }else{
                if ($type == Type::Date || $type == Type::DateTime) {
                    $sheet->getStyle("{$colName}2:$colName$row")->getNumberFormat()->setFormatCode("yyyy/mm/dd");
                    $sheet->getColumnDimension($colName)->setWidth(12);
                    $sheet->getColumnDimension($colName)->setAutoSize(false);
                }else{
                    $sheet->getColumnDimension($colName)->setAutoSize(true);
                }
            }
        }

        ob_start();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $buf = ob_get_clean();
        $fileName = $this->filename;
        if ($fileName == 'sample.xlsx') {
            $fileNames = explode('\\', get_class($this->fm)) ;
            $fileName = array_pop($fileNames) . ' import sample.xlsx';
        }

        return new DownloadObject($fileName, $buf);
    }

    public static function fromJson(ExcelImporter $ei, $json) {
        $ei->start_row = $json['start_row'] ?? $ei->start_row;
        $ei->key_col = $json['key_col'] ?? $ei->key_col;
        $ei->date_format = $json['date_format'] ?? $ei->date_format;
        $ei->config = $json['cols'] ?? null;

        $config = [];
        foreach ($json['cols'] as $field => $rec) {
            $item = [];
            // field, type, name, required
            $item[] = $field;
            switch (@$rec['type']) {
                case "number":
                    $item[] = Type::Float;
                    break;
                case "date":
                    $item[] = Type::Date;
                    break;
                default:
                    $item[] = Type::String;
            }
            $item[] = @$rec['title'];
            $item[] = @$rec['required'];
            $config[] = $item;
        }
        $ei->config = $config;
    }

}
