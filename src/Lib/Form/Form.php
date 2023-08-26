<?php

namespace Sannomiya\Form;


use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Sannomiya\Util\Helper;
use Sannomiya\Database\Database;
use Exception;
use Psr\Log\LoggerInterface;
use Sannomiya\Util\LanguagesDefault;
use Sannomiya\Util\LanguagesInterface;
use Sannomiya\Util\ListDataDefault;
use Sannomiya\Util\ListDataInterface;

define('ACTION_DELETE', 'delete');
define('ACTION_UPDATE', 'update');
define('ACTION_BULK_UPDATE', 'bulk_update');
define('ACTION_INSERT', 'insert');
define('ACTION_COPY', 'copy');
define('ACTION_BULK_COPY', 'bulk_copy');
define('ACTION_DATA', 'data');
define('ACTION_DEFINE', 'define');
define('ACTION_DEFINE_WITH_DATA', 'define_with_data');
define('ACTION_SETTING', 'setting');
define('ACTION_RESET_SETTING', 'reset_setting');
define('ACTION_EXPORT', 'export');
define('ACTION_DOWNLOAD', 'download');
define('ACTION_EXCEL', 'excel');
define('ACTION_WORD', 'excel');
define('ACTION_PDF', 'pdf');
define('ACTION_IMPORT', 'import');
define('ACTION_SAMPLE', 'sample');

define('ACTION_PARAM_NAME', '__action__');
define('SERIAL_PARAM_NAME', '__serial__');

define('DOWNLOAD_FIELD_PARAM_NAME', '__download_field__');
define('DOWNLOAD_ID_PARAM_NAME', '__download_id__');
define('DOWNLOAD_THUMBNAIL_PARAM_NAME', '__download_thumbnail__');

define('LIST_VALUES', 'values');
define('LIST_NAME', 'name');
define('LIST_PARAM', 'param');
define('LIST_FIX_PARAM', 'fix_param');
define('LIST_TREE', 'tree');
define('LIST_CHOICES', 'choices');
define('LIST_STRING_VALUE', 'string_value');
define('LIST_LONG', 'long');
define('LIST_SIMPLE_CHOICES', 'simple_choices');
define('LIST_AUTOCOMPLETE', 'autocomplete');
define('LIST_TEXT_FIELD', 'text_field');

define('LIST_AUTOCOMPLETE_DATA', 'autocomplete_data');
define('LIST_AUTOCOMPLETE_INSERT', 'autocomplete_insert');
define('LIST_AUTOCOMPLETE_SEARCH', 'autocomplete_search');
define('LIST_AUTOCOMPLETE_INTERFACE', 'autocomplete_interface');

define('LIST_CHOICES_AUTOCOMPLETE', 'choices_autocomplete');
define('LIST_CHOICES_AUTOCOMPLETE_FUNCTION', 'choices_autocomplete_function');
define('LIST_CHOICES_QUERY_DATA', 'choices_data');
define('LIST_CHOICES_QUERY_INSERT', 'choices_insert');
define('LIST_CHOICES_QUERY_DELETE', 'choices_delete');
define('LIST_CHOICES_QUERY_SEARCH', 'choices_search');
define('LIST_CHOICES_INTERFACE', 'choices_interface');

abstract class Form extends FormBase
{

    protected ?string $name = null;


    protected bool $optionDelete = false;
    protected bool $optionInsert = false;
    protected bool $optionCopy = false;
    protected bool $optionUpdate = false;
    protected bool $optionImport = false;
    protected bool $optionExcel = false;
    protected bool $optionWord = false;
    protected bool $optionPDF = false;

    protected bool $gridWrapHeader = true;
    protected bool $gridHeaderFilterable = true;
    protected bool $gridHeaderResizable = true;
    protected bool $gridAutosize = true;
    protected bool $gridSelection = false;
    protected bool $gridView = true;
    protected bool $gridExcelColumnSelectable = true;
    protected bool $gridInCellEditable = true;


    protected ?int $limit = null;

    protected $querySelect;
    protected $queryFrom;
    protected $queryWhere;
    protected $queryOrder;
    protected array $queryGetMaxIds = [] ;
    protected $updatedTable = null;
    protected ?array $fieldOrder = null;
    protected $m_group_summarize_fields = null;

    protected $m_unique_name=null;

    protected ?bool $singleOutline = null;
    protected ?int $singleHeaderWidth = null;
    protected ?string $singleLabelPosition = null;
    protected ?int $singleCols = null;

    protected ?array $formTemplate = null;

    private $usePhpOffice = true;

    protected array $importKeys = [];
    protected array $importRefFields = [];
    protected array $uniqueFields = [];
    protected bool $checkUniqueWhenImport = true;


    protected array $searchRequireGroup = [];

    /**
     * @var ExcelExporter
     */
    protected $excel_exporter;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var Database $db
     */
    public $db;

    /**
     * @var ListDataInterface
     */
    protected $listDataManager;



    public function __construct($name, Database $db, ?LoggerInterface $logger)
    {
        $this->name = $name;
        $this->db = $db;
        $this->logger = $logger;
        $this->action = @$_REQUEST[ACTION_PARAM_NAME];
        $this->languagesManager = new LanguagesDefault();
        $this->listDataManager = new ListDataDefault();
    }

    public function setImportKey(string $fields, $reset = false) {
        if ($reset) {
            $this->importKeys = [];
        }
        if (!isset($fields) || $fields=="") {
            return;
        }
        $fieldList = explode(",", $fields);
        foreach ($fieldList as $field) {
            $this->importKeys[] = trim($field);
        }
    }

    public function getImportKeys(): array {
        return $this->importKeys;
    }
    public function setImportReferenceField(string $fields, $reset = false) {
        if ($reset) {
            $this->importRefFields = [];
        }
        if (!isset($fields) || $fields=="") {
            return;
        }
        $fieldList = explode(",", $fields);
        foreach ($fieldList as $field) {
            $this->importRefFields[] = trim($field);
        }
    }

    public function getImportReferenceFields(): array {
        return $this->importRefFields;
    }


    public function setUniqueField(string $fields, $reset = false) {
        if ($reset) {
            $this->uniqueFields = [];
        }

        if (!isset($fields) || $fields=="") {
            return;
        }
        $fieldList = explode(",", $fields);
        $keys = [];
        foreach ($fieldList as $field) {
            $keys[]  = trim($field);
        }
        $this->uniqueFields[] = $keys;
    }

    public function setGroupForSubSummarize($fields)
    {
        $fields = explode(",", $fields);
        if (!isset($this->m_group_summarize_fields)) {
            $this->m_group_summarize_fields = [];
        }
        foreach ($fields as $field) {
            $field = trim($field);
            if (!in_array($field, $this->m_group_summarize_fields)) {
                $this->m_group_summarize_fields [] = $field;
            }
        }
    }

    public function getGroupForSubSummarize(){
        return $this->m_group_summarize_fields;
    }

    public function isSubSummarize(): bool
    {
        return is_array($this->m_group_summarize_fields) && count($this->m_group_summarize_fields) > 0;
    }

    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    public function &getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return bool
     */
    public function isOptionDelete(): bool
    {
        return $this->optionDelete;
    }

    /**
     * @param bool $optionDelete
     */
    public function setOptionDelete(bool $optionDelete=true): void
    {
        $this->optionDelete = $optionDelete;
    }

    /**
     * @return bool
     */
    public function isOptionInsert(): bool
    {
        return $this->optionInsert;
    }

    public function isOptionCopy(): bool
    {
        return $this->optionCopy;
    }

    /**
     * @return bool
     */
    public function isOptionUpdate(): bool
    {
        return $this->optionUpdate;
    }

    /**
     * @param bool $optionUpdate
     */
    public function setOptionUpdate(bool $optionUpdate = true): void
    {
        $this->optionUpdate = $optionUpdate;
    }

    /**
     * @return bool
     */
    public function isOptionImport(): bool
    {
        return $this->optionImport;
    }

    /**
     * @param bool $optionImport
     */
    public function setOptionImport(bool $optionImport=true): void
    {
        $this->optionImport = $optionImport;
    }

    /**
     * @return bool
     */
    public function isOptionExcel(): bool
    {
        return $this->optionExcel;
    }

    /**
     * @param bool $optionExcel
     */
    public function setOptionExcel(bool $optionExcel=true): void
    {
        $this->optionExcel = $optionExcel;
    }

    /**
     * @return bool
     */
    public function isOptionWord(): bool
    {
        return $this->optionWord;
    }

    /**
     * @param bool $optionWord
     */
    public function setOptionWord(bool $optionWord=true): void
    {
        $this->optionWord = $optionWord;
    }

    /**
     * @return bool
     */
    public function isOptionPDF(): bool
    {
        return $this->optionPDF;
    }

    /**
     * @param bool $optionPDF
     */
    public function setOptionPDF(bool $optionPDF=true): void
    {
        $this->optionPDF = $optionPDF;
    }

    /**
     * @param bool $optionInsert
     */
    public function setOptionInsert(bool $optionInsert=true): void
    {
        $this->optionInsert = $optionInsert;
        // Set copy is same as insert
        $this->optionCopy = $optionInsert;
    }

    /**
     * @param bool $value
     */
    public function setOptionCopy(bool $value=true): void
    {
        $this->optionCopy = $value;
    }

    public function setOptionIUD(bool $value=true): void
    {
        $this->optionInsert = $value;
        $this->optionCopy = $value;
        $this->optionUpdate = $value;
        $this->optionDelete = $value;
    }

    /**
     * @return array|null
     */
    public function getSearchInfo(): ?array
    {
        return $this->searchInfo;
    }

    public function getSearchInfoObject(): SearchInfo
    {
        $ret = new SearchInfo($this->searchInfo);
        $ret->form = $this;
        return $ret;
    }

    public function haveSearchCondition(): bool
    {
        return is_array($this->searchInfo) && count($this->searchInfo) > 0;
    }

    /**
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * @param int $pageSize
     */
    public function setPageSize(int $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * @return ListDataInterface
     */
    public function getListDataManager(): ?ListDataInterface
    {
        return $this->listDataManager;
    }

    /**
     * @param ListDataInterface $listDataManager
     */
    public function setListDataManager(ListDataInterface $listDataManager): void
    {
        $this->listDataManager = $listDataManager;
    }

    /**
     * @return LanguagesInterface
     */
    public function getLanguagesManager(): ?LanguagesInterface
    {
        return $this->languagesManager;
    }

    /**
     * @param LanguagesInterface $languagesManager
     */
    public function setLanguagesManager(LanguagesInterface $languagesManager): void
    {
        $this->languagesManager = $languagesManager;
    }

    /**
     * @return bool
     */
    public function isUsePhpOffice(): bool
    {
        return $this->usePhpOffice;
    }

    /**
     * @param bool $usePhpOffice
     */
    public function setUsePhpOffice(bool $usePhpOffice): void
    {
        $this->usePhpOffice = $usePhpOffice;
    }

    /**
     * @return null
     */
    public function getUniqueName()
    {
        return $this->m_unique_name;
    }

    /**
     * @param null $m_unique_name
     */
    public function setUniqueName($m_unique_name): void
    {
        $this->m_unique_name = $m_unique_name;
    }

    /**
     * @return bool
     */
    public function isGridWrapHeader(): bool
    {
        return $this->gridWrapHeader;
    }

    /**
     * @param bool $gridWrapHeader
     */
    public function setGridWrapHeader(bool $gridWrapHeader=true): void
    {
        $this->gridWrapHeader = $gridWrapHeader;
    }

    /**
     * @return bool
     */
    public function isGridHeaderFilterable(): bool
    {
        return $this->gridHeaderFilterable;
    }

    /**
     * @param bool $gridHeaderFilterable
     */
    public function setGridHeaderFilterable(bool $gridHeaderFilterable=true): void
    {
        $this->gridHeaderFilterable = $gridHeaderFilterable;
    }

    /**
     * @return bool
     */
    public function isGridAutosize(): bool
    {
        return $this->gridAutosize;
    }

    /**
     * @param bool $gridAutosize
     */
    public function setGridAutosize(bool $gridAutosize=true): void
    {
        $this->gridAutosize = $gridAutosize;
    }

    /**
     * @return bool
     */
    public function isGridSelection(): bool
    {
        return $this->gridSelection;
    }

    /**
     * @param bool $gridSelection
     */
    public function setGridSelection(bool $gridSelection=true): void
    {
        $this->gridSelection = $gridSelection;
    }

    /**
     * @return bool
     */
    public function isGridView(): bool
    {
        return $this->gridView;
    }

    /**
     * @param bool $gridView
     */
    public function setGridView(bool $gridView=true): void
    {
        $this->gridView = $gridView;
    }

    /**
     * @return bool
     */
    public function isGridHeaderResizable(): bool
    {
        return $this->gridHeaderResizable;
    }

    /**
     * @param bool $gridHeaderResizable
     */
    public function setGridHeaderResizable(bool $gridHeaderResizable=true): void
    {
        $this->gridHeaderResizable = $gridHeaderResizable;
    }

    /**
     * @return bool
     */
    public function isGridExcelColumnSelectable(): bool
    {
        return $this->gridExcelColumnSelectable;
    }

    /**
     * @param bool $gridExcelColumnSelectable
     */
    public function setGridExcelColumnSelectable(bool $gridExcelColumnSelectable=true): void
    {
        $this->gridExcelColumnSelectable = $gridExcelColumnSelectable;
    }

    /**
     * @return array|null
     */
    public function getFieldOrder(): ?array
    {
        return $this->fieldOrder;
    }

    /**
     * @return string|null
     */
    public function getSingleLabelPosition(): ?string
    {
        return $this->singleLabelPosition;
    }

    public function setSingleLabelPositionHorizontal(): void
    {
        $this->singleLabelPosition = 'horizontal';
    }
    public function setSingleLabelPositionVertical(): void
    {
        $this->singleLabelPosition = 'vertical';
    }

    /**
     * @return int|null
     */
    public function getSingleCols(): ?int
    {
        return $this->singleCols;
    }

    /**
     * @param int|null $singleCols
     */
    public function setSingleCols(?int $singleCols): void
    {
        $this->singleCols = $singleCols;
    }

    /**
     * @return int|null
     */
    public function getSingleHeaderWidth(): ?int
    {
        return $this->singleHeaderWidth;
    }

    /**
     * @param int|null $singleHeaderWidth
     */
    public function setSingleHeaderWidth(?int $singleHeaderWidth): void
    {
        $this->singleHeaderWidth = $singleHeaderWidth;
    }

    /**
     * @return bool
     */
    public function isGridInCellEditable(): bool
    {
        return $this->gridInCellEditable;
    }

    /**
     * @param bool $gridInCellEditable
     */
    public function setGridInCellEditable(bool $gridInCellEditable): void
    {
        $this->gridInCellEditable = $gridInCellEditable;
    }

    /**
     * @return array|null
     */
    public function getFormTemplate(): ?array
    {
        return $this->formTemplate;
    }

    /**
     * @param array|null $formTemplate
     */
    public function setFormTemplate(?array $formTemplate): void
    {
        $this->formTemplate = $formTemplate;
    }

    /**
     * @return bool|null
     */
    public function getSingleOutline(): ?bool
    {
        return $this->singleOutline;
    }

    /**
     * @param bool|null $singleOutline
     */
    public function setSingleOutline(?bool $singleOutline): void
    {
        $this->singleOutline = $singleOutline;
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     */
    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param bool $checkUniqueWhenImport
     */
    public function setCheckUniqueWhenImport(bool $checkUniqueWhenImport): void
    {
        $this->checkUniqueWhenImport = $checkUniqueWhenImport;
    }

    protected function set(string $fieldNames, $property, $value)
    {
        $fieldNames = explode(',', $fieldNames);
        foreach ($fieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            $field = $this->getField($fieldName);
            $field->$property = $value;
        }
    }

    public function getField($name): Field
    {
        if (!isset($this->fields[$name])) {
            $this->fields[$name] = new Field($name);
        }
        return $this->fields[$name];
    }

    private function getFieldsByProperty($property, $value): ?array
    {
        $ret = [];
        foreach ($this->fields as $field) {
            if ($field->$property === $value){
                $ret[] = $field;
            }
        }

        if (count($ret) > 0) {
            return $ret;
        }

        return  null;
    }

    public function getSummaryFields(): ?array
    {
        return $this->getFieldsByProperty('summary', true);
    }

    private int $startTime;
    public function action($action = null)
    {
        $this->startTime = $this->milliTime();
        if (!isset($action)) {
            $action = $this->action;
        }
        $data = null;
        switch ($action) {
            case ACTION_DATA:
                $data = $this->data();
                break;
            case ACTION_DEFINE_WITH_DATA:
                $data = ["define" => $this->define(), "data" => $this->data()];
                break;
            case ACTION_DELETE:
                if (!$this->isOptionDelete()) {
                    throw new FormException("DELETE option is false. Please check form define");
                }
                $data = $this->delete();
                break;
            case ACTION_INSERT:
            case ACTION_COPY:
                if (!$this->isOptionInsert()) {
                    throw new FormException("INSERT option is false. Please check form define");
                }
                $data = $this->insert();
                break;
            case ACTION_UPDATE:
                if (!$this->isOptionUpdate()) {
                    throw new FormException("UPDATE option is false. Please check form define");
                }
                $data = $this->update();
                break;
            case ACTION_BULK_UPDATE:
                if (!$this->isOptionUpdate()) {
                    throw new FormException("UPDATE option is false. Please check form define");
                }
                $data = $this->update(true);
                break;
            case ACTION_BULK_COPY:
                if (!$this->isOptionInsert()) {
                    throw new FormException("INSERT option is false. Please check form define");
                }
                $data = $this->insert(true);
                break;
            case ACTION_SETTING:
                if ($this->saveFormSetting()){
                    $data = "Save success.";
                }else{
                    throw new FormException("Can not save user setting");
                }
                break;
            case ACTION_RESET_SETTING:
                if ($this->saveFormSetting(true)){
                    $data = "Reset success.";
                }else{
                    throw new FormException("Can not reset user setting");
                }
                break;
            case ACTION_DEFINE:
                $data = $this->define();
                break;
            case ACTION_DOWNLOAD:
                return $this->download();
            case ACTION_EXCEL:
                return $this->excel();
            case ACTION_SAMPLE:
                return $this->sample();
            case ACTION_IMPORT:
                return $this->import();
            default:
                $this->init();
                $data = $this->customAction($this->action, $this->updateInfo);
        }
        return $data;
    }

    /**
     * @return ExcelImporter
     */
    protected function getImporter(): ExcelImporter
    {
        $class = $this->getImporterClass();

        /**
         * @var ExcelImporter $importer
         */
        $importer = new $class($this);

        return $importer;
    }

    protected function sample(): DownloadObject
    {
        $this->init();
        $importer = $this->getImporter();
        return $importer->doSample();
    }

    protected function import(): ?string
    {
        $this->init();
        // Get file
        $file = @$_FILES['file'];
        $note = @$_REQUEST['note'];
        $deleteData = @$_REQUEST['deleteData'];
        if (!isset($file)) {
            throw new FormException('File not be uploaded.');
        }

        if ($file['error']!=UPLOAD_ERR_OK) {
            $message = UploadException::codeToMessage($file['error'], $this->languagesManager);
            throw new FormException($message);
            // throw new UploadException($file['error']);
        }

        // Check
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext!='xlsx' && $ext!='csv') {
            throw new FormException(sprintf($this->languagesManager->message("File extension %s not allowed. Accept only xlsx or csv"), $ext));
        }

        $importer = $this->getImporter();
        $importer->setDeleteData($deleteData == 'true' || $deleteData === true);
        $next = $importer->doBeforeImport($file['tmp_name'], $file['name'], $note);
        if (!$next) {
            return "Done";
        }
        $dat = $importer->getImportData($file['tmp_name'], $ext == 'csv');
        $this->beforeImport($importer, $dat);
        if (!$importer->doCheckData($dat, $message)) {
            throw new FormException($message);
        }

        if ($this->checkUniqueWhenImport && !$this->checkUniques($dat, $message, null, true)) {
            throw new FormException($message);
        }

        $importer->doImport($dat);
        $importer->doAfterImport($file['tmp_name'], $file['name'], $note);
        $this->afterImport($importer, $dat);
        return "Done";
    }

    protected function prepareExcel(Worksheet $sh = null) {
        if (!isset($this->excel_exporter)) {
            throw new FormException('Export excel not supported');
        }
        $this->init();
        $this->name2Caption();
        $this->excel_exporter->create_excel($sh);
        $this->afterExcel($this->excel_exporter);
    }

    protected function excel(): DownloadObject
    {
        $this->prepareExcel();
        $data = $this->excel_exporter->output_excel();
        $fileName = $this->excel_exporter->get_output_file_name();
        if ($fileName == 'export.xlsx') {
            $fileNames = explode('\\', get_class($this)) ;
            $fileName = array_pop($fileNames) . ' Export.xlsx';
        }
        return new DownloadObject($fileName, $data);

//        $data = $this->excel_exporter->output_pdf();
//        $filename = str_replace(".xlsx", ".pdf", $this->excel_exporter->get_output_file_name());
//        return new DownloadObject($filename, $data);

    }

    protected function download(): DownloadObject
    {
        $this->init();

        // Get param
        $download_id = @$_REQUEST[DOWNLOAD_ID_PARAM_NAME];
        $download_field = @$_REQUEST[DOWNLOAD_FIELD_PARAM_NAME];
        $thumbnail = @$_REQUEST[DOWNLOAD_THUMBNAIL_PARAM_NAME];
        // Check
        if (!isset($download_field)) {
            throw new FormException('Invalid download parameter(field name).');
        }

        $field = $this->getField($download_field);
        if (!isset($field) || $field->type != Type::File) {
            throw new FormException('Invalid download field');
        }

        if (isset($field->imageParams)){
            $downloadIds = explode(',', $download_id);
            if ($thumbnail==1){
                $file_path = $this->getThumbnailPath($field->name, $downloadIds);
            }else {
                $file_path = $this->getFilePath($field->name, $downloadIds);
            }
        }else{
            // Check
            if (!is_numeric($download_id)) {
                throw new FormException('Invalid download parameter (download id)');
            }
            $file_name = $this->db->get("select"." $download_field from {$this->getUpdatedTable()} where id={$download_id}");
            if ($thumbnail==1){
                $file_path = $field->getThumbnailPath($download_id, $file_name);
            }else {
                $file_path = $field->getFilePath($download_id, $file_name);
            }
        }

        if (!isset($file_path) || !file_exists($file_path)) {
            throw new FormException('Download file does not exist: ' . $file_path);
        }

        // File name
        if ($field->isSaveFileName() && ! isset($field->imageParams)){
            $file_name = $this->db->get("select"." $download_field from {$this->getUpdatedTable()} where id={$download_id}");
            $file_name = preg_replace('/^.+\//', '', $file_name);
        }else{
            $file_name = 'filename';
        }
        if (!isset($file_name)) {
            throw new FormException('Download file name does not exist');
        }

        // Read file content
        ob_start();
        readfile($file_path);
        $buf = ob_get_clean();


        //throw new FormException($buf);
        return new DownloadObject($file_name, $buf);
    }

    public function getThumbnailPath($field, $downloadIds): ?string {
        return null;
    }
    public function getFilePath($field, $downloadIds): ?string{
        return null;
    }

    protected function handleUploadFiles() {
        /**
         * @var $field Field
         */
        $fileCaches = [];
        foreach ($this->fields as $field){
            if ($field->type==Type::File && !is_null($field->getSaveFileFolder())){
                // save file
                foreach ($this->updateInfo as &$rec){
                    $files = $rec[$field->name];
                    if (isset($files)){
                        if (isset($files['error']) && $files['error']!=UPLOAD_ERR_OK) {
                            $this->db->rollback();
                            $message = UploadException::codeToMessage($files['error'], $this->languagesManager);
                            throw new FormException($message);
                        }
                        $id = $rec['id'];
                        if ($files == '__delete__'){

                            $table = $this->getUpdatedTable();
                            $filename = $this->db->get("select $field->name from $table where id=?", [$id]);
                            $filepath = $field->getFilePath($id, $filename);
                            if (file_exists($filepath)){
                                unlink($filepath);
                                $thumbnail_filepath = $field->getThumbnailPath($id, $filename);
                                if (file_exists($thumbnail_filepath)){
                                    unlink($thumbnail_filepath);
                                }
                            }
                            $rec[$field->name] = null;
                            if ($field->isSaveFileName()) {
                                $this->db->execute("update"." {$this->getUpdatedTable()} set {$field->name}=null where id=?",
                                    [$rec['id']]);
                            }
                        }else{
                            $filename = $files['name'];
                            if ($field->isSaveFileFolderByMonth()) {
                                $filename = date('Ym') . '/' . $filename;
                            }
                            $filepath = $field->getFilePath($id, $filename);

                            // Create folder
                            $tmp1 = Helper::getFileName($filepath);
                            $tmp2 = str_replace($tmp1, "", $filepath);
                            if (!file_exists($tmp2)) {
                                mkdir($tmp2, 0777, true);
                            }
                            if (isset($fileCaches[$files['tmp_name']])) {
                                copy($fileCaches[$files['tmp_name']], $filepath);
                            }else{
                                move_uploaded_file($files['tmp_name'], $filepath);
                                $fileCaches[$files['tmp_name']] = $filepath;
                            }

                            if ($field->update || $field->insert){

                                $rec[$field->name] = $filename;
                                // Update file name to data
                                if ($field->isSaveFileName()) {
                                    $this->db->execute("update"." {$this->getUpdatedTable()} set {$field->name}=? where id=?",
                                        [$filename, $rec['id']]);
                                }
                                // Resize image
                                $thumbnail_filepath = $field->getThumbnailPath($id, $filename);
                                if ($field->image) {
                                    Helper::resizeImage($filepath, $thumbnail_filepath, Helper::getFileExtension($filename),
                                        $field->thumbnailWidthSize, 0);
                                }
                                // type/tmp_name/error/size/name
                            }
                        }
                    }
                }
            }
        }
    }

    private $m_importer_class;

    public function setImporterClass($class){
        $this->m_importer_class = $class;
    }

    public function getImporterClass($def = ExcelImporterHelper::class): ?string
    {
        return $this->m_importer_class ?? $def;
    }

    public function getAllData($max = -1): array {
        if (!$this->beforeData($data, $total, $list)){
            return $data;
        }
        $db = $this->db;
        $query = $this->createSelectQuery();
        return $db->getRecordSet($query, $t, 0, $max);
    }

    private $list_value_map = [];
    private $reverse_list_value_map = [];
    public function prepareListValues($data=null) {
        $ids = [];
        if (isset($this->listDataManager) && (!isset($data) || count($data) > 0)) {
            foreach ($this->fields as $fieldName => $field) {
                if (isset($field->listName) && ! $field->autocomplete) {
                    $ids[$field->listName] = [];
                    if (isset($data)) {
                        foreach ($data as $rec) {
                            if (isset($rec[$field->name]) && !in_array($rec[$field->name], $ids[$field->listName])){
                                $ids[$field->listName][] = $rec[$field->name];
                            }
                        }
                    }
                    $params = $this->getListParamValues($field);
                    // 2021/12/20 Don't limit 1000 more. Use temporary table to deal with big data.
                    //if (isset($ids[$field->listName]) && count($ids[$field->listName]) < 1000){
                    $this->listDataManager->setIDs($ids[$field->listName]);
                    //}
                    /**
                     * @var $field Field
                     */
                    $this->list_value_map[$fieldName] = $this->listDataManager->map($field->listName, $params, $field->listFixParam);
                }
            }
        }
    }

    /**
     * Get id of list type field. (id=>value)
     * @param $fieldName
     * @param $value
     */
    public function getReverseValue($fieldName, $value) {
        $value = mb_strtolower($value);
        $field = $this->getField($fieldName);
        if (isset($field->listValues)){
            if (!isset($this->reverse_list_value_map[$fieldName])){
                $this->reverse_list_value_map[$fieldName] = [];
                foreach ($field->listValues as $rec) {
                    $this->reverse_list_value_map[$fieldName][mb_strtolower($rec[1])] = $rec[0];
                }
            }
            return @$this->reverse_list_value_map[$fieldName][$value];
        }elseif (isset($field->listName)){
            $value = mb_strtolower($value);
            if (!isset($this->reverse_list_value_map[$fieldName])){
                $this->reverse_list_value_map[$fieldName] = [];
            }
            if (!isset($this->reverse_list_value_map[$fieldName][$value])){
                if (isset($this->listDataManager)) {
                    $this->reverse_list_value_map[$fieldName][$value] =
                        $this->listDataManager->reverse($field->listName, $value);
                }
            }
            return @$this->reverse_list_value_map[$fieldName][$value];
        }
        return null;
    }

    /**
     * Get value of list type field. (id=>value)
     * @param $fieldName
     * @param $record
     * @return string|null
     */
    public function getValue($fieldName, $record): ?string
    {
        $field = $this->getField($fieldName);
        $value = @$record[$fieldName];
        if (isset($field->listValues)){
            if (!isset($this->list_value_map[$fieldName])){
                $this->list_value_map[$fieldName] = [];
                foreach ($field->listValues as $rec) {
                    $this->list_value_map[$fieldName][$rec[0]] = $rec[1];
                }
            }
            return @$this->list_value_map[$fieldName][$value];
        }elseif (isset($field->listName)){
            // 2021/10/04 add || count($this->list_value_map[$fieldName]) == 0 so it cant get data witch have ids
            if (!isset($this->list_value_map[$fieldName]) || count($this->list_value_map[$fieldName]) == 0){
                $this->list_value_map[$fieldName] = [];
                if (isset($this->listDataManager)) {
                    $list = $this->listDataManager->list($field->listName, null, $field->listFixParam);
                    foreach ($list as $rec) {
                        $this->list_value_map[$fieldName][$rec[0]] = $rec[1];
                    }
                }
            }

            // 2022/06/24 handle list long
            if ($field->listLong) {
                if (!isset($this->list_value_map[$fieldName][$value])) {
                    if (isset($this->listDataManager)) {
                        $this->listDataManager->setIDs([$value]);
                        $list = $this->listDataManager->list($field->listName, null, $field->listFixParam);
                        foreach ($list as $rec) {
                            $this->list_value_map[$fieldName][$rec[0]] = $rec[1];
                        }
                    }
                }
            }

            if ($field->choices){
                $func = $field->getChoicesInterface();
                if (isset($func)) {
                    $query = $func->getDataQuery($record);
                }else{
                    $query = $field->getChoicesQueryData();
                    if (isset($query)) {
                        $query = $this->format($query, $record);
                    }
                }
                if (isset($query)) {
                    if (!isset($this->list_value_map[$fieldName][$query])){
                        $temp = [];
                        $l = $this->db->getList($query);
                        foreach ($l as $v) {
                            $temp[] = @$this->list_value_map[$fieldName][$v];
                        }
                        $this->list_value_map[$fieldName][$query] = implode("\r\n", $temp);
                    }
                    return @$this->list_value_map[$fieldName][$query];
                }
                return '';
            } else {
                return @$this->list_value_map[$fieldName][$value];
            }
        }
        return $value;
    }

    protected function milliTime(): int {
        return round(microtime(true) * 1000);
    }

    protected function treeToList($rs): array {
        $ret = [];
        foreach ($rs as $rec){
            $ret[] = $rec;
            if (isset($rec['items']) && is_array($rec['items'])) {
                $children = $this->treeToList($rec['items']);
                if (count($children) > 0) {
                    $ret = array_merge($ret, $children);
                }
            }
        }
        return  $ret;
    }

    public function data(): array
    {
        $timeStart = $this->milliTime();
        $this->init();
        $timeInit = $this->milliTime();
        $db = $this->db;

        // Check search require
        $si = $this->getSearchInfoObject();
        foreach ($this->searchRequireGroup as $message => $fieldNames) {
            $check = false;
            foreach ($fieldNames as $fieldName) {
                if (isset($si) && $si->exists($fieldName)) {
                    $check = true;
                    break;
                }
            }
            if (!$check) {
                return ['data' => [], 'total' => 0];
            }
        }

        if (!$this->beforeData($data, $total, $list)){
            list($map, $list, $listTime) = $this->prepareListBoxTextValue($data);
            $this->updateListBoxTextValue($map, $data);
            $this->afterData($data, $t, $summary, $list, $extra);
            return ['data' => $data, 'total' => $total, 'summary' => $summary, 'list' => $list, 'extra'=>$extra];
        }
        $timeBeforeData = $this->milliTime();


        $dataQuery = $this->createSelectQuery();
        // Check

        try {

            $data = $db->getRecordSet($dataQuery, $t, ($this->pageIndex - 1) * $this->pageSize, $this->pageSize);
            if ($this->getLimit() > 0) {
//                $total = $db->getTotal($dataQuery);
//                if ($total > $this->getLimit()) {
//                    throw new FormException("Data limit exceed ($total over $this->limit). Please filter data more.");
//                }
                if ($t > $this->getLimit()) {
                    $t = $this->getLimit();
                }
            }
        } catch (Exception $exception) {
            throw new FormException($this->name . "\n" . $exception->getMessage() . "\n" . $dataQuery);
        }

        $timeQuery = $this->milliTime();

        /**
         * @var Field $field
         */

        // Boolean field
        foreach ($this->fields as $fieldName => $field) {
            if ($field->type == Type::Boolean) {
                foreach ($data as &$recRef) {
                    if (isset($recRef[$fieldName]) && $recRef[$fieldName] >= 1) {
                        $recRef[$fieldName] = true;
                    }else{
                        $recRef[$fieldName] = false;
                    }

                }
            }
        }
        $timeAdjust = $this->milliTime();

        // Prepare for list field.
        list($map, $list, $listTime) = $this->prepareListBoxTextValue($data);
        $this->updateListBoxTextValue($map, $data);

        $timeList = $this->milliTime();

        // Prepare choices
        $choicesField = [];
        foreach ($this->fields as $fieldName => $field) {
            if ($field->choices) {
                $choicesField[] = $fieldName;
            }
        }
        if (count($choicesField) > 0) {
            foreach ($data as &$rec) {
                foreach ($choicesField as $fieldName) {
                    $field = $this->getField($fieldName);
                    $func = $field->getChoicesInterface();
                    if (isset($func)) {
                        $query = $func->getDataQuery($rec);
                    }else{
                        $query = $field->getChoicesQueryData();
                        if (isset($query)) {
                            $query = $this->format($query, $rec);
                        }
                    }
                    if (isset($query)) {
                        $l = $this->db->getList($query);
                        $rec[$fieldName] = $l;
                    }
                }
            }
        }

        // If auto complete, need to convert id to name
        foreach ($this->fields as $fieldName => $field) {
            if ($field->autocomplete &&
                (!is_null($field->getAutocompleteData()) || !is_null($field->getAutocompleteInterface()))) {
                // Get list
                $params = [];
                foreach ($data as &$rec) {
                    if (isset($rec[$fieldName])){
                        $params[] = $rec[$fieldName];
                    }
                }

                if (count($params) > 0) {
                    $map = $this->getAutocompleteData($field, $params);
                    foreach ($data as &$rec) {
                        $id = $rec[$fieldName];
                        if (isset($map[$id])){
                            $rec[$fieldName]=$map[$id];
                        }
                    }
                }
            }
        }
        $timeChoices = $this->milliTime();

        // Get summary
        $summary = null;
        $query = $this->createSummaryQuery();
        if (isset($query)){
            $summary = $this->db->getRecord($query);
            if (isset($summary)){
                foreach ($summary as $field => $value) {
                    $summary[$field] = (double) $value;
                }
            }
        }

        $timeSummary = $this->milliTime();

        // Convert decimal
        foreach ($data as &$rec) {
            foreach ($this->fields as $fieldName => $field) {
                if ($field->type == Type::Double || $field->type == Type::Float || $field->type == Type::Number) {
                    if (isset($rec[$fieldName]) && is_numeric($rec[$fieldName])) {
                        $rec[$fieldName] = (double) $rec[$fieldName];
                    }
                }elseif ($field->type == Type::Int) {
                    if (isset($rec[$fieldName]) && is_numeric($rec[$fieldName])) {
                        $rec[$fieldName] = (int) $rec[$fieldName];
                    }
                }
            }
        }

        $extra = [
            'debug'=> [
                'query' => $dataQuery,
                'time' => [
                    '1all' => $this->milliTime() - $this->startTime,
                    '2init' => $timeInit - $timeStart,
                    '3beforeData' => $timeBeforeData - $timeInit,
                    '4query' => ($timeQuery-$timeBeforeData),
                    '5adjust' => ($timeAdjust-$timeQuery),
                    '6list' => ($timeList-$timeAdjust) ,
                    '7choices' => ($timeChoices-$timeList),
                    '8summary' => ($timeSummary-$timeChoices),
                    'listTime' => $listTime,
                ]
            ]
        ];

        $this->afterData($data, $t, $summary, $list, $extra);

        return ['data' => $data, 'total' => $t, 'list' => $list, 'summary'=> $summary, 'extra' => $extra];
    }

    private function prepareListBoxTextValue($data): array
    {
        $map = [];
        $list = [];
        $listTime = ['count' => 0];
        if (isset($this->listDataManager) && count($data) > 0) {
            foreach ($this->fields as $fieldName => $field) {
                /**
                 * @var $field Field
                 */
                if (isset($field->listName) && ! $field->autocomplete) {
                    // If data < 1000, should be optimize list
                    // 2021/09/22 comment out because angular does not update text after update
//                    if (isset($field->listTextField) && (!isset($field->tree) || !$field->tree)) {
//                        $map[$fieldName] = [];
//                        $list[$field->listName] = [];
//                        foreach ($data as $rec) {
//                            if (isset($rec[$field->listTextField])){
//                                $map[$fieldName][$rec[$fieldName]] = $rec[$field->listTextField];
//                                $list[$field->listName][] = ['text' => $rec[$field->listTextField],
//                                    'value' => $rec[$fieldName]];
//                            }
//                        }
//                        continue;
//                    }
                    $ids = [];
                    // 2021/12/20 Don't limit 1000 more. Use temporary table to deal with big data.
                    // if (count($data) < 1000) {
                    $ids[$field->listName] = [];
                    foreach ($data as $rec) {
                        if (isset($rec[$field->name])){
                            $ids[$field->listName][] = $rec[$field->name];
                        }
                    }
                    // }
                    $params = $this->getListParamValues($field);
                    $tmpStart = $this->milliTime();
                    if (isset($ids[$field->listName])){
                        $this->listDataManager->setIDs($ids[$field->listName]);
                    }
                    $map[$fieldName] = $this->listDataManager->map($field->listName, $params, $field->listFixParam);
                    $listTime['count'] += count($map[$fieldName]);
                    $listTime[$field->listName] = $this->milliTime() - $tmpStart;
                    if ($field->choices){
                        $list[$field->listName] = [];
                        foreach ($map[$fieldName] as $value=>$text) {
                            if ($field->listStringValue) {
                                // PHP auto convert array key to int show must handle here
                                $value = (string) $value;
                            }
                            $list[$field->listName][] = ['text' => $text, 'value' => $value];
                        }
                    }elseif ($field->tree){
                        $list[$field->listName] = $this->listDataManager->list($field->listName, $params, $field->listFixParam);
                        $list[$field->listName . '_tree'] = $this->treeToList($list[$field->listName] );
                    }

                }elseif (isset($field->listValues)) {
                    $m = [];
                    foreach ($field->listValues as $rec){
                        $m[$rec[0]] = $rec[1];
                    }
                    $map[$fieldName] = $m;
                }
            }
        }

        return [$map, $list, $listTime];
    }

    private array $nonDefaultMap = [];
    private function updateListBoxTextValue($map, &$data) {
        if (count($map) > 0) {
            // throw  new FormException(json_encode($map));
            foreach ($data as &$recRef) {
                foreach ($map as $fieldName => &$m) {
                    if ($this->getField($fieldName)->choices) {
                        continue;
                    }
                    if (isset($recRef[$fieldName])){
                        $value = $recRef[$fieldName];

                        if (!isset($m[$value])) {
                            // Not in default, must to get new list
                            $field = $this->getField($fieldName);
                            $params = $this->getListParamValues($field, $recRef);
                            $mapKey = $fieldName. json_encode($params);
                            if (!isset($this->nonDefaultMap[$mapKey])) {
                                $this->nonDefaultMap[$mapKey] = $this->listDataManager->map($field->listName, $params, $field->listFixParam);
                            }
                            $m[$value] = @$this->nonDefaultMap[$mapKey][$value];
                        }

                        $text = $m[$value];
                        $recRef["@{$fieldName}"] = ['value'=> $value, 'text' => $text];
                    }
                }
            }
            // Set default
        }
    }

    /**
     * If rec = null, return default
     * @param Field $field
     * @param $rec
     * @return mixed
     */
    private function getListParamValues(Field $field, $rec = null)
    {
        $params = $field->listParam;
        if (!isset($params)) {
            return null;
        }
        if (is_array($params)) {
            $ret = [];
            foreach ($params as $param) {
                $paramField = $this->getField($param);
                if (isset($rec)){
                    $ret[] = @$rec[$paramField->name];
                }else{
                    $ret[] = $paramField->getDefaultValue();
                }
            }
            return $ret;
        }else{
            $paramField = $this->getField($params);
            if (isset($rec)){
                return @$rec[$paramField->name];
            }else{
                return $paramField->getDefaultValue();
            }
        }
    }
    /**
     * @param $field
     * @param $params
     * @return array
     */
    private function getAutocompleteData(Field $field, $params): array
    {
        $o = $field->getAutocompleteInterface();
        if (isset($o)) {
            return $o->getData($this->db, $params);
        }else{
            $autocomplete = $field->getAutocompleteData();
            if (is_callable($autocomplete)) {
                return $autocomplete($this->db, $params);
            }else{
                $query = str_replace(Constant::QueryParamParam, implode(',', $params), $autocomplete);
                return $this->db->getMap($query);
            }
        }


    }

    protected function init()
    {
        $this->initDefine();
        switch ($this->action) {
            case ACTION_SAMPLE:
            case ACTION_DATA:
            case ACTION_DEFINE_WITH_DATA:
            case ACTION_EXCEL:
                $this->initDataParam();
                break;
            case ACTION_DELETE:
            case ACTION_UPDATE:
            case ACTION_BULK_UPDATE:
            case ACTION_BULK_COPY:
            case ACTION_INSERT:
            case ACTION_COPY:
                $this->initUpdateParam();
                break;
            case ACTION_DEFINE:
            case ACTION_DOWNLOAD:
            case ACTION_IMPORT:
                break;
            default:
                $this->initDataParam();
                $this->initUpdateParam();
        }
        if ($this->action == ACTION_COPY || $this->action==ACTION_BULK_COPY) {
            $this->initCopyId();
        }

        $this->afterInit();
    }

    protected function initExcelParam(){
        if (isset($_REQUEST['excelFields']) && $_REQUEST['excelFields'] != '') {
            $this->excelFields = json_decode($_REQUEST['excelFields'], true);
            if (!isset($this->excelFields)) {
                $this->excelFields = explode(",",$_REQUEST['excelFields']);
            }
        }
        if (isset($_REQUEST['orderFields'])  && $_REQUEST['orderFields'] != '' ) {
            $this->orderFields = json_decode($_REQUEST['orderFields'], true);
            if (!isset($this->orderFields)) {
                $this->orderFields = explode(",",$_REQUEST['orderFields']);
            }
        }
    }

    protected function initCopyId() {
        $keyFields = $this->getFieldsByProperty('key', true);
        foreach ($this->updateInfo as &$rec) {
            foreach ($keyFields as $field){
                $key = $field->name;
                $rec["copy_{$key}"] = $rec[$key];
            }
        }
    }

    public function getCopyId($rec, $key = 'id') {
        return @$rec["copy_{$key}"];
    }

    public function getFileInfo($rec, $fieldName): ?FileInfo
    {
        $files = @$rec[$fieldName];
        if (isset($files)) {
            return new FileInfo($files['name'], $files['type'], $files['tmp_name'], $files['size'], $files['error']);
        }else{
            return null;
        }
    }

    protected function createSelectQueryOrderPart(): ?string
    {
        // Sort
        $sort = '';

        if (isset($this->sortInfo)) {
            foreach ($this->sortInfo as $sortInfo) {
                $field = $this->getField($sortInfo['name']);
                if ($sort != '') {
                    $sort = $sort . ', ';
                }
                if (isset($field->listTextField)) {
                    $sort = $sort . $field->listTextField;
                }else{
                    $sort = $sort . $field->getDatabaseField();
                }

                if (@$sortInfo['type'] == Constant::SortTypeDesc) {
                    $sort = $sort . ' DESC';
                }
            }
        }

        if ($sort == '') {
            $sort = $this->queryOrder;
        }

        return $sort;
    }

    protected function createSelectQueryWherePart(): ?string
    {
        $where = null;
        if (isset($this->searchInfo)) {
            //throw new FormException(json_encode($this->searchInfo));
            $where = '';
            foreach ($this->searchInfo as $fieldName => $searchInfo) {
                $field = $this->getField($fieldName);
                // This field can not search with query. It must process individually and set to $this->queryWhere
                if ($field->searchOnly) {
                    continue;
                }
                if (!$field->searchable) {
                    continue;
                }
                $expression = $this->createSearchExpression($fieldName);
                if (isset($expression)) {
                    if ($where != '') {
                        $where .= ' AND ';
                    }
                    $where .= $expression;
                }
            }
        }

        if (isset($this->queryWhere)) {
            if (isset($where) && $where !='') {
                $where = "({$this->queryWhere}) AND $where";
            } else {
                $where = $this->queryWhere;
            }
        }

        return $where;
    }

    public function createSelectQuery(): ?string
    {
        $query = sprintf('select %s from %s', $this->querySelect, $this->queryFrom);

        // Sort
        $sort = $this->createSelectQueryOrderPart();
        $where = $this->createSelectQueryWherePart();

        if (isset($where) && $where != '') {
            $query = $query . "\nWHERE $where";
        }

        if (isset($sort) && $sort != '') {
            $query = $query . "\nORDER BY $sort";
        }

        return $query;
    }

    protected function createSummaryQuery(): ?string
    {
        $fields = $this->getSummaryFields();
        if (!isset($fields)) {
            return null;
        }

        // Create summary query
        $select = '';
        foreach ($fields as $field){
            if ($select!=''){
                $select .= ',';
            }
            $select .= "SUM({$field->name}) AS {$field->name}";
        }
        $query = sprintf('select %s from %s', $select, $this->queryFrom);
        $where = $this->createSelectQueryWherePart();
        if (isset($where) && $where != '') {
            $query = $query . "\nWHERE $where";
        }
        return $query;
    }

    public function createSearchExpression(string $fieldName, $col = null)
    {
        if (!isset($this->searchInfo)) {
            return null;
        }

        if (!isset($this->searchInfo[$fieldName])) {
            return null;
        }

        $searchInfo = $this->searchInfo[$fieldName];
        $field = $this->getField($fieldName);

        $searchType = $searchInfo['type'];

        if (!isset($col)) {
            $col = $field->getDatabaseField();
        }

        if ($field->autocomplete){
            $value = trim(@$searchInfo['value1']); // Helper::queryEncode(@$searchInfo['value1'], $field->type);

            // Search null
            if ($searchType == Constant::SearchTypeIsNull || $searchType == Constant::SearchTypeIsNotNull) {
                $value = null;
                if ($searchType == Constant::SearchTypeIsNull) {
                    $searchType = Constant::SearchTypeEqual;
                }else{
                    $searchType = Constant::SearchTypeNotEqual;
                }
            }

            if (!isset($value) || $value==''){
                switch ($searchType) {
                    case Constant::SearchTypeStartWith:
                    case Constant::SearchTypeEndWith:
                    case Constant::SearchTypeContains:
                    case Constant::SearchTypeEqual:
                        return "$col is null";
                    case Constant::SearchTypeNotEqual:
                    case Constant::SearchTypeNotContains:
                        return "$col is not null";
                }
            }else{
                switch ($searchType) {
                    case Constant::SearchTypeStartWith:
                        $value = $value . '%';
                        break;
                    case Constant::SearchTypeEndWith:
                        $value = '%'. $value;
                        break;
                    case Constant::SearchTypeContains:
                        $value = '%'. $value . '%';
                        break;

                }
                $listId = $this->getAutocompleteSearch($field, $value);

                if (isset($listId)){
                    if (is_array($listId)){
                        if (count($listId) == 0){
                            return false;
                        }else{
                            $listId = implode(",", $listId);
                            if ($searchType == Constant::SearchTypeNotEqual){
                                return "$col not in ($listId)";
                            }else{
                                return "$col in ($listId)";
                            }
                        }
                    }else{
                        if ($searchType == Constant::SearchTypeNotEqual){
                            return "$col <> $listId";
                        }else{
                            return "$col = $listId";
                        }
                    }
                }else{
                    return "LOWER($col) like '$value'";
                }
            }
            return null;
        }

        if ($field->choices){


            $values = $searchInfo['values'];

            // Search null
            if ($searchType == Constant::SearchTypeIsNull || $searchType == Constant::SearchTypeIsNotNull) {
                $values = null;
                if ($searchType == Constant::SearchTypeIsNull) {
                    $searchType = Constant::SearchTypeIn;
                }else{
                    $searchType = Constant::SearchTypeNotIn;
                }
            }else{
                if (!is_array($values) || count($values) <= 0) {
                    $values = null;
                }else{
                    $values = implode(',', $values);
                }
            }

            $func = $field->getChoicesInterface();
            if (isset($func)) {
                return $func->getSearchQueryCondition($searchType != Constant::SearchTypeIn, $values);
            }else{
                $query = $field->getChoicesQuerySearch();
                if (is_callable($query)){
                    return $query($searchType != Constant::SearchTypeIn, $values);
                }elseif (isset($query)){
                    $in = 'in';
                    if (isset($values)){
                        $condition = "in ($values)";
                        if ($searchType != Constant::SearchTypeIn){
                            $in = 'not in';
                        }
                    }else{
                        if ($searchType == Constant::SearchTypeIn){
                            $in = 'not in';
                        }
                        $condition = "is not null";
                    }
                    $query = str_replace(Constant::QueryParamCondition, $condition, $query);
                    return str_replace(Constant::QueryParamIn, $in, $query);
                }
            }
            return null;
        }

        $where = null;
        switch ($searchType) {
            case Constant::SearchTypeIsNull:
                $where = "$col is null";
                break;
            case Constant::SearchTypeIsNotNull:
                $where = "$col is not null";
                break;
            case Constant::SearchTypeEqual:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type);
                if ($value == 'null') {
                    $where = "$col is null";
                } else {
                    $where = "$col = $value";
                }
                break;
            case Constant::SearchTypeNotEqual:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type);
                if ($value == 'null') {
                    $where = "$col is not null";
                } else {
                    $where = "$col <> $value";
                }
                break;
            case Constant::SearchTypeContains:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type, '', '');
                $value = mb_strtolower($value);
                $where = "LOWER($col) like '%$value%'";
                break;
            case Constant::SearchTypeNotContains:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type, '', '');
                $value = mb_strtolower($value);
                $where = "LOWER($col) not like '%$value%'";
                break;
            case Constant::SearchTypeEndWith:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type, '', '');
                $value = mb_strtolower($value);
                $where = "LOWER($col) like '%$value'";
                break;
            case Constant::SearchTypeStartWith:
                $value = Helper::queryEncode(trim(@$searchInfo['value1']), $field->type, '', '');
                $value = mb_strtolower($value);
                $where = "LOWER($col) like '$value%'";
                break;
            case Constant::SearchTypeIn:
                $value1 = Helper::queryEncode(@$searchInfo['value1'], $field->type, null);
                $value2 = Helper::queryEncode(@$searchInfo['value2'], $field->type, null);
                $values = @$searchInfo['values'];
                if (is_array($values) && !isset($value1) && !isset($value2)) {
                    if (count($values) > 0) {
                        $newValues = [];
                        foreach ($values as $id) {
                            if (!is_numeric($id) || $field->listStringValue || ($field->type!=Type::Int && $field->type!=Type::Float && $field->type!=Type::Number)) {
                                $newValues[] = "'$id'";
                            }else{
                                $newValues[] = $id;
                            }

                        }
                        $values = $newValues;
                        $values = implode(',', $values);
                        $where = "$col in ($values)";
                    } else {
                        $where = "$col is null";
                    }
                } else {
                    if (!isset($value1) && !isset($value2)) {
                        $where = "$col is null";
                    } elseif (isset($value1) && isset($value2)) {
                        $where = "($col >= $value1 AND $col <= $value2)";
                    } elseif (isset($value1)) {
                        $where = "$col >= $value1";
                    } else {
                        $where = "$col <= $value2";
                    }
                }
                break;
            case Constant::SearchTypeNotIn:
                $value1 = Helper::queryEncode(@$searchInfo['value1'], $field->type, null);
                $value2 = Helper::queryEncode(@$searchInfo['value2'], $field->type, null);
                $values = @$searchInfo['values'];
                if (is_array($values) && !isset($value1) && !isset($value2)) {
                    if (count($values) > 0) {
                        $newValues = [];
                        foreach ($values as $id) {
                            if (!is_numeric($id) || $field->listStringValue  || ($field->type!=Type::Int && $field->type!=Type::Float && $field->type!=Type::Number)) {
                                $newValues[] = "'$id'";
                            }else{
                                $newValues[] = $id;
                            }

                        }
                        $values = $newValues;
                        $values = implode(',', $values);
                        $where = "$col not in ($values)";
                    } else {
                        $where = "$col is not null";
                    }
                } else {
                    if (!isset($value1) && !isset($value2)) {
                        $where = "$col is not null";
                    } elseif (isset($value1) && isset($value2)) {
                        $where = "($col < $value1 OR $col > $value2)";
                    } elseif (isset($value1)) {
                        $where = "$col < $value1";
                    } else {
                        $where = "$col > $value2";
                    }
                }

                break;
        }

        return $where;
    }

    public static function format(?string $string, array $record)
    {
        if (preg_match_all("/'\[([0-9a-zA-Z_]+)]'/", $string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = @$record[$match[1]];
                if (isset($value)) {
                    $string = str_replace($match [0], $value, $string);
                } else {
                    // Null param, return null
                    return null;
                }
            }
        }
        return $string;
    }

    public function delete()
    {
        $this->init();

        // Cancel update
        if (!$this->beforeDelete($this->updateInfo, $message)) {
            if (isset($message)){
                // Having error
                throw new FormException($message);
            }else{
                // Just skip update
                return $this->updateInfo;
            }
        }

        // Delete file
        $file_fields = [];
        /**
         * @var $field Field
         */
        foreach ($this->fields as $field) {
            if ($field->type == Type::File) {
                if (!is_null($field->getSaveFileFolder())) {
                    $file_fields[] = $field;
                }
            }
        }

        $this->db->begin();

        foreach ($this->updateInfo as $rec) {
            $queries = $this->createChoicesDeleteQuery($rec);
            foreach ($queries as $query) {
                $this->db->execute($query);
            }
            $query = $this->createDeleteQuery($rec);
            $this->db->execute($query);

            // Delete file
            foreach ($file_fields as $field) {
                $current_file = $field->getFilePath(@$rec['id'], @$rec[$field->name]);
                if (file_exists($current_file)) {
                    unlink($current_file);
                    $thumbnail_file = $field->getThumbnailPath(@$rec['id'], @$rec[$field->name]);
                    if (file_exists($thumbnail_file)) {
                        unlink($thumbnail_file);
                    }
                }
            }
        }
        $this->db->commit();

        $this->afterDelete($this->updateInfo);

        return $this->updateInfo;
    }

    protected function createChoicesDeleteQuery($rec): array
    {
        $ret = [];
        /**
         * @var Field $field
         */
        foreach ($this->fields as $field) {
            if (!($field->insert || $field->update)) {
                continue;
            }
            if ($field->choices) {
                $func = $field->getChoicesInterface();
                if (isset($func)) {
                    $query= $func->getDeleteQuery($rec);
                    if (isset($query)) {
                        $ret[] = $query;
                    }
                }else{
                    $query = $field->getChoicesQueryDelete();
                    if (isset($query)) {
                        $query = $this->format($query, $rec);
                        if (isset($query)) {
                            $ret[] = $query;
                        }
                    }
                }
            }
        }
        return $ret;
    }

    protected function createDeleteQuery($rec): ?string
    {
        /**
         * @var Field $field
         */
        $where = '';
        foreach ($this->fields as $field) {
            $this->createQueryWhere($where, $field, $rec, true);
        }

        if ($where == '') {
            throw new FormException("Can not delete table {$this->updatedTable} without key");
        }

        return "delete from {$this->updatedTable} where $where";
    }

    private function createQueryWhere(&$where, $field, $rec, $forUpdate = false)
    {
        if ($field->key) {
            $value = @$rec[$field->name];
            if (!isset($value)) {
                throw new FormException("Key {$field->name} have no data");
            }
            $value = Helper::queryEncode($value, $field->type);
            if ($where != '') {
                $where .= ' AND ';
            }

            if ($forUpdate) {
                $where .= "{$field->name}=$value";
            }else{
                $where .= "{$field->getDatabaseField()}=$value";
            }

        }
    }

    private function prepareUpdate(){
        // Adjust autocomplete data
        /**
         * @var Field $field
         */
        foreach ($this->fields as $field) {
            if ($field->autocomplete){
                if (!is_null($field->getAutocompleteInterface())
                    || (!is_null($field->getAutocompleteInsert()) && !is_null($field->getAutocompleteSearch()))) {
                    foreach ($this->updateInfo as &$rec) {
                        $rec[$field->name . '_autocomplete_backup_'] = $rec[$field->name];
                        $rec[$field->name] = $this->getAutocompleteInsert($field, $rec[$field->name]);
                    }
                }
            }
        }
    }

    private function getAutocompleteSearch(Field $field, $value, $partialMatch=true): ?array{
        $o = $field->getAutocompleteInterface();
        $listId = null;
        if (isset($o)) {
            $listId = $o->search($this->db, $value, $partialMatch);
        }else{
            $search = $field->getAutocompleteSearch();
            if (isset($search)) {
                if (is_callable($search)) {
                    $listId = $search($this->db, $value);
                }else{
                    $querySearch = str_replace(Constant::QueryParamParam, Helper::queryEncode($value), $search);
                    $listId = $this->db->getList($querySearch);
                }
            }
        }
        return $listId;
    }

    public function getAutocompleteInsert(Field $field, $value): ?int
    {
        $listId = $this->getAutocompleteSearch($field, $value, false);
        if (isset($listId) && count($listId) > 0) {
            $id = $listId[0];
        }else{
            $id = null;
        }
        if (isset($id)){
            return $id;
        }else{
            // Dont need to insert null value
            if (!isset($value)) {
                return null;
            }
            $o = $field->getAutocompleteInterface();
            if (isset($o)) {
                return $o->insert($this->db, $value);
            }else{
                $insert = $field->getAutocompleteInsert();
                if (is_callable($insert)) {
                    return $insert($this->db, $value);
                }else{
                    $queryInsert = str_replace(Constant::QueryParamParam, Helper::queryEncode($value), $insert);
                    $this->db->execute($queryInsert);
                    return $this->db->insertedId();
                }

            }
        }
    }

    public function getKeyFields(){
        $ret = [];
        /**
         * @var $field Field
         */
        foreach ($this->fields as $field) {
            if ($field->key) {
                $ret[] = $field->name;
            }
        }
        return $ret;
    }
    public function insert($bulkUpdate = false)
    {
        $this->init();

        if ($bulkUpdate) {
            // Last is update data
            $this->setBulkUpdate();
            $this->action = ACTION_COPY;

        }

        // Cancel update
        if (!$this->beforeUpdate($this->updateInfo, $message)) {
            if (isset($message)){
                // Having error
                throw new FormException($message);
            }else{
                // Just skip update
                return $this->updateInfo;
            }
        }

        if (!$this->checkUniques($this->updateInfo, $message)) {
            throw new FormException($message);
        }

        $this->db->begin();

        $this->prepareUpdate();

        $keys = $this->getKeyFields();
        if (count($keys) < 0) {
            throw new FormException("Cant insert because have no key set.");
        }

        $idsMax = [];
        foreach ($keys as $key) {
            $query = $this->getQueryGetMaxId($key);
            if (isset($query)) {
                $idsMax[$key] = $this->db->get($query) + 1;
            }
        }

        foreach ($this->updateInfo as &$rec) {
            $query = $this->createInsertQuery($rec, $idsMax);
            $this->db->execute($query);
            foreach ($keys as $key) {
                if (isset($idsMax[$key])) {
                    $rec[$key] = $idsMax[$key];
                    $idsMax[$key]++;
                } else {
                    // Auto number
                    // TODO: Check key can be edit.
                    if ($key == 'id' || !isset($rec[$key]) || !is_numeric($rec[$key])) {
                        $id = $this->db->insertedId();
                        $rec[$key] = $id;
                    }
                }
            }

            $queries = $this->createChoicesInsertQuery($rec);
            foreach ($queries as $choices_query) {
                $this->db->execute($choices_query);
            }
        }
        $this->handleUploadFiles();
        $this->db->commit();

        // List box value
        list($map, , ) = $this->prepareListBoxTextValue($this->updateInfo);
        $this->updateListBoxTextValue($map, $this->updateInfo);
        $this->afterUpdate($this->updateInfo);
        return $this->updateInfo;
    }

    protected function checkUnique(array $rs, string $table, $checkFields, ?string &$errorValues, $where = null, $keyFields='id'): bool {
        return $this->db->checkUnique($rs, $table, $checkFields, $errorValues, $where, $keyFields, $this->isActionUpdate());
    }

    public function checkUniques(array $rs, &$message, $table = null, $isImport = false): bool {
        if (!isset($table)) {
            $table = $this->getUpdatedTable();
        }

        if (!isset($table)) {
            return true;
        }

        if (count($this->uniqueFields) == 0) {
            return true;
        }

        $importKeys = $this->getImportKeys();
        foreach ($this->uniqueFields as $checkFields) {
            if ($isImport && count(array_diff($importKeys, $checkFields)) == 0) {
                continue;
            }
            $res = $this->checkUnique($rs, $table, $checkFields, $errorValues, null, $this->getKeyFields());
            if (!$res) {
                $caption = [];
                foreach ($checkFields as $field) {
                    $caption[] = $this->getCaption($field);
                }
                $caption = implode(", ", $caption);
                $message = "Duplicated data ($errorValues) for $caption";
                return false;
            }
        }

        return true;
    }

    private function setBulkUpdate() {
        // Last is set data
        $updateData = array_pop($this->updateInfo);

        // Update that set
        foreach ($this->updateInfo as &$recRef) {
            foreach ($updateData as $key=>$value) {
//                if (str_starts_with($key, 'copy_')) {
//                    continue;
//                }
                if (isset($value) && $key != SERIAL_PARAM_NAME) {
                    $recRef[$key]=$value;
                }
            }
        }
    }
    public function update($bulkUpdate = false)
    {

        $this->init();
        if (!isset($this->updateInfo)) {
            // Nothing to do
            $this->updateInfo = [];
        }
        $message = null;

        if ($bulkUpdate) {
            $this->setBulkUpdate();
            $this->action = ACTION_UPDATE;
        }

        // Cancel update
        if (!$this->beforeUpdate($this->updateInfo, $message)) {
            if (isset($message)){
                // Having error
                throw new FormException($message);
            }else{
                // Just skip update
                return $this->updateInfo;
            }
        }

        // Check unique
        // TODO: copy may be problem
        if (!$this->checkUniques($this->updateInfo, $message)) {
            throw new FormException($message);
        }

        $this->db->begin();

        $this->prepareUpdate();

        foreach ($this->updateInfo as &$rec) {
            $query = $this->createUpdateQuery($rec);
            $queries = $this->createChoicesDeleteQuery($rec);
            foreach ($queries as $choices_query) {
                $this->db->execute($choices_query);
            }
            $this->db->execute($query);

            // Delete again in case of it key have been updated
            foreach ($queries as $choices_query) {
                $this->db->execute($choices_query);
            }

            $queries = $this->createChoicesInsertQuery($rec);
            foreach ($queries as $choices_query) {
                $this->db->execute($choices_query);
            }
        }
        $this->handleUploadFiles();
        $this->db->commit();
        $this->afterUpdate($this->updateInfo);
        // throw new FormException(json_encode($this->updateInfo));

        // Return value for auto complete
        foreach ($this->updateInfo as &$rec) {
            foreach ($this->fields as $field) {
                if ($field->autocomplete){
                    if (isset($rec[$field->name . '_autocomplete_backup_'])) {
                        $rec[$field->name] = $rec[$field->name . '_autocomplete_backup_'];
                        unset($rec[$field->name . '_autocomplete_backup_']);
                    }
                }
            }
        }
        return $this->updateInfo;
    }

    protected function isActionDefine(): bool
    {
        return $this->action == ACTION_DEFINE || $this->action == ACTION_DEFINE_WITH_DATA;
    }
    protected function isActionData(): bool
    {
        return $this->action == ACTION_DATA || $this->action == ACTION_DEFINE_WITH_DATA;
    }

    protected function isActionUpdate(): bool
    {
        return $this->action == ACTION_UPDATE;
    }

    protected function isActionCopy(): bool
    {
        return $this->action == ACTION_COPY;
    }

    protected function isActionInsert(): bool
    {
        return $this->action == ACTION_INSERT;
    }

    protected function isActionExcel(): bool
    {
        return $this->action== ACTION_EXCEL;
    }

    protected function isActionExport(): bool
    {
        return $this->action== ACTION_EXPORT;
    }

    protected function isActionImport(): bool
    {
        return $this->action == ACTION_IMPORT;
    }

    protected function isActionSample(): bool
    {
        return $this->action == ACTION_SAMPLE;
    }

//    protected function isActionSetting(): bool
//    {
//        return $this->action == ACTION_SETTING;
//    }

    protected function applyFormSetting($rec) {
        if (!isset($rec)) {
            return;
        }

        if (is_numeric($rec['page_size'])){
            $this->pageSize = intval($rec['page_size']);
        }

        if (isset($rec['order_fields'])){
            $fields =explode(",", $rec['order_fields']);
            // Check if new field added.
            if (is_array($this->fieldOrder)){
                foreach ($this->fieldOrder as $fieldName) {
                    if (!in_array($fieldName, $fields)){
                        $fields[]=$fieldName;
                    }
                }
            }
            $widths = [];
            if (isset($rec['width_fields'])){
                $widths = explode(",", $rec['width_fields']);
            }
            $locked = [];
            if (isset($rec['locked_fields'])){
                $locked = explode(",", $rec['locked_fields']);
            }

            $registeredFields = $this->fieldOrder;
            $this->fieldOrder = [];
            for ($i=0;$i<count($fields);$i++){
                $fieldName = trim($fields[$i]);

                // Fields that removed
                if (!in_array($fieldName, $registeredFields)) {
                    continue;
                }
                if (!in_array($fieldName, $this->fieldOrder)){
                    $this->fieldOrder[] = $fields[$i];
                }
                if ($i<count($widths) && is_numeric($widths[$i])){
                    $this->getField($fieldName)->width = intval($widths[$i]);
                }
                $this->getField($fieldName)->locked = false;
                if ($i<count($locked)){
                    if ( $locked[$i] === 1 || $locked[$i] === true || $locked[$i] === 'true') {
                        if (is_null($this->getField($fieldName)->groupName)){
                            $this->getField($fieldName)->locked = true;
                        }
                    }
                }
            }
        }


        if (isset($rec['excel_fields']) && (!is_array($this->excelFields) || count($this->excelFields) == 0)) {
            $excelFields = explode(',', $rec['excel_fields']);
            // Reset
            foreach ($this->getFieldNames() as $fieldName){
                $this->fields[$fieldName]->excel = false;
            }

            foreach ($excelFields as $fieldName){
                if (isset($this->fields[$fieldName])){
                    $this->fields[$fieldName]->excel=true;
                }
            }
            $this->excelFields = $excelFields;
        }

        if (isset($rec['show_fields'])){
            $showFields = explode(',', $rec['show_fields']);
            // Reset
            foreach ($this->getFieldNames() as $fieldName){
                $this->fields[$fieldName]->show = false;
            }

            foreach ($showFields as $fieldName){
                if (isset($this->fields[$fieldName])){
                    $this->fields[$fieldName]->show=true;
                }
            }
        }
        if (isset($rec['search_info'])){
            $this->searchInfo = json_decode($rec['search_info'], true);
            $keys = array_keys($this->searchInfo);
            foreach ($keys as $field) {
                if (!isset($this->fields[$field])) {
                    unset($this->searchInfo[$field]);
                }
            }
        }
        if (isset($rec['sort_info'])){
            $this->sortInfo = json_decode($rec['sort_info'], true);
            $new = [];
            foreach ($this->sortInfo as $item) {
                if (isset($this->fields[$item['name']])) {
                    $new[] = $item;
                }
            }
            $this->sortInfo = $new;
        }
    }

    public function define(): array
    {
        $this->init();

        $ret = [];
        $ret['name'] = $this->name;
        $ret['optionDelete'] = $this->isOptionDelete();
        $ret['optionInsert'] = $this->isOptionInsert();
        $ret['optionCopy'] = $this->isOptionCopy();
        $ret['optionUpdate'] = $this->isOptionUpdate();
        $ret['optionImport'] = $this->isOptionImport();
        $ret['optionExcel'] = $this->isOptionExcel();
        $ret['optionWord'] = $this->isOptionWord();
        $ret['optionPDF'] = $this->isOptionPDF();

        $ret['gridAutosize'] = $this->isGridAutosize();
        $ret['gridHeaderFilterable'] = $this->isGridHeaderFilterable();
        $ret['gridExcelColumnSelectable'] = $this->isGridExcelColumnSelectable();
        $ret['gridHeaderResizable'] = $this->isGridHeaderResizable();
        $ret['gridSelection'] = $this->isGridSelection();
        $ret['gridView'] = $this->isGridView();
        $ret['gridInCellEditable'] = $this->isGridInCellEditable();
        $ret['gridWrapHeader'] = $this->isGridWrapHeader();

        $ret['singleCols'] = $this->getSingleCols();
        $ret['singleLabelPosition'] = $this->getSingleLabelPosition();
        $ret['singleHeaderWidth'] = $this->getSingleHeaderWidth();
        $ret['singleOutline'] = $this->getSingleOutline();
        $ret['uploadMaxFilesize'] = ini_get("upload_max_filesize");

        // Load form setting
        $rec = $this->loadFormSetting();
        // throw new FormException(json_encode($rec));
        $this->name2Caption();

        $this->applyFormSetting($rec);

        $ret['pageSize'] = $this->pageSize;


        $fields = [];
        $fieldNames = $this->fieldOrder;
        if (!isset($fieldNames)){
            $fieldNames = $this->getFieldNames();
        }
        foreach ($fieldNames as $fieldName) {
            $field =  $this->getField($fieldName);
            $fields[] = $field;
        }


        // Điều chỉnh group name: Không cần nửa, đã xữ lý bên server
//        $groupName = null;
//        foreach ($fields as $field) {
//            if (isset($groupName) && $groupName == $field->groupName) {
//                $field->groupName = null;
//            }else {
//                $groupName = $field->groupName;
//            }
//        }
        $ret['fields'] = $fields;

        $ret["searchInfo"] = $this->searchInfo;

        if (is_array($this->sortInfo)) {
            $sortInfo = [];
            foreach ($this->sortInfo as $item){
                $sortInfo[$item['name']] = $item['type'];
            }
            $ret["sortInfo"] = $sortInfo;
        }


        $ret['formSetting'] = $this->getFormSettingList();

        $ret['searchRequireGroup'] = $this->searchRequireGroup;

        $ret['formTemplate'] = $this->formTemplate;

        $this->afterDefine();
        return $ret;
    }

    public function name2Caption() {
        $fieldNames = $this->getFieldNames();
        $lang = $this->languagesManager;
        foreach ($fieldNames as $fieldName) {
            $caption = $this->fields[$fieldName]->caption;
            if (!isset($caption) || $caption == $this->fields[$fieldName]->name) {
                $this->fields[$fieldName]->caption = $lang->label($this->fields[$fieldName]->name);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getQuerySelect()
    {
        return $this->querySelect;
    }

    /**
     * @param mixed $querySelect
     */
    public function setQuerySelect($querySelect): void
    {
        $this->querySelect = $querySelect;
    }

    /**
     * @return mixed
     */
    public function getQueryFrom()
    {
        return $this->queryFrom;
    }

    /**
     * @param mixed $queryFrom
     */
    public function setQueryFrom($queryFrom): void
    {
        $this->queryFrom = $queryFrom;
    }

    /**
     * @return mixed
     */
    public function getQueryWhere()
    {
        return $this->queryWhere;
    }

    /**
     * @param mixed $queryWhere
     */
    public function setQueryWhere($queryWhere): void
    {
        $this->queryWhere = $queryWhere;
    }

    /**
     * @return mixed
     */
    public function getQueryOrder()
    {
        return $this->queryOrder;
    }

    /**
     * @param mixed $queryOrder
     */
    public function setQueryOrder($queryOrder): void
    {
        $this->queryOrder = $queryOrder;
    }

    /**
     * @return string
     */
    public function getUpdatedTable(): ?string
    {
        return $this->updatedTable;
    }

    /**
     * @param string $updatedTable
     */
    public function setUpdatedTable(string $updatedTable): void
    {
        $this->updatedTable = $updatedTable;
    }

    protected function createInsertQuery($rec, $idsMax = null): ?string
    {
        $fields = '';
        $values = '';

        /**
         * @var Field $field
         */
        foreach ($this->fields as $field) {
            if ($field->insert && $field->type != Type::File && !$field->choices) {
                $fields .= ', ' . $field->getDatabaseField();
                $values .= ', ' . Helper::queryEncode(@$rec[$field->name], $field->type);
            } elseif ($field->type != Type::File && isset($field->defaultValue) && !$field->defaultValueNotInsert){
                $fields .= ', ' . $field->getDatabaseField();
                $values .= ', ' . Helper::queryEncode($field->defaultValue, $field->type);
            }elseif ($field->key && isset($idsMax) && isset($idsMax[$field->name])) {
                $fields .= ', ' . $field->getDatabaseField();
                $values .= ', ' . $idsMax[$field->name];
            }
        }
        if ($fields == '') {
            throw new FormException("Have no insert field for table {$this->updatedTable}");
        }
        $fields = substr($fields, 2);
        $values = substr($values, 2);
        return "insert into {$this->updatedTable} ($fields) values ($values)";

    }

    protected function createUpdateQuery($rec): ?string
    {
        $set = '';
        $where = '';
        /**
         * @var Field $field
         */
        foreach ($this->fields as $field) {
            $this->createQuerySet($set, $field, $rec);
            $this->createQueryWhere($where, $field, $rec, true);
        }
        if ($where == '') {
            throw new FormException("Can not delete table {$this->updatedTable} without key");
        }
        if ($set == '') {
            throw new FormException("Have no update field for table {$this->updatedTable}");
        }
        return "update {$this->updatedTable} set $set where $where";
    }


    /**
     * @param $set
     * @param Field $field
     * @param $rec
     */
    private function createQuerySet(&$set, Field $field, $rec)
    {
        if ($field->update && $field->type != Type::File && !$field->choices) {
            $value = @$rec[$field->name];
            $value = Helper::queryEncode($value, $field->type);
            if ($set != '') {
                $set .= ', ';
            }
            $set .= "{$field->getDatabaseField()}=$value";
            // $set .= "{$field->name}=$value";
        }
    }

    protected function createChoicesInsertQuery(&$rec): array
    {
        $ret = [];
        /**
         * @var Field $field
         */
        foreach ($this->fields as $field) {
            if (!($field->insert || $field->update)) {
                continue;
            }
            if ($field->choices && $field->editable) {
                $func = $field->getChoicesInterface();
                if (isset($func)) {
                    $values = @$rec[$field->name];
                    if (is_array($values)) {
                        $queries = $func->getInsertQueries($this->db, $rec, $values);
                        $rec[$field->name] = $values;
                        $ret = array_merge($ret, $queries);
                    }
                }else{
                    $query = $field->getChoicesQueryInsert();
                    $query_temp = $this->format($query, $rec);
                    if (!isset($query_temp)){
                        throw new FormException("Can't format query\n.$query");
                    }else{
                        $query = $query_temp;
                    }
                    if (isset($query)) {
                        $values = @$rec[$field->name];
                        if (is_array($values)) {
                            // throw new FormException(json_encode($values));
                            $newValues = [];
                            foreach ($values as $value) {
                                if (isset($value) && $value!=''){
                                    if (is_numeric($value)){
                                        $newValues[] = $value;
                                        $ret[] = str_replace(Constant::QueryParamValue, $value, $query);
                                    }else{
                                        // Time to insert by function
                                        $function = $field->getChoicesAutocompleteFunction();
                                        if (isset($function)){
                                            $value = $function($this->db, $value, $rec);

                                            $newValues[] = (int) $value;
                                            $ret[] = str_replace(Constant::QueryParamValue, $value, $query);
                                        }
                                    }
                                }
                            }
                            $rec[$field->name] = $newValues;
                        }
                    }
                }

            }
        }
        return $ret;
    }

    /**
     * @return mixed
     */
    public function getQueryGetMaxId($field)
    {
        return @$this->queryGetMaxIds[$field];
    }

    /**
     * @param $field
     * @param $queryGetMaxId
     */
    public function setQueryGetMaxId($field, $queryGetMaxId): void
    {
        $this->queryGetMaxIds[$field] = $queryGetMaxId;
    }

    protected function throwInputError($message, $fields) {
        if (!is_array($fields)){
            $fields = explode(",", $fields);
        }
        throw new FormException(json_encode([
            'message' => $message,
            'fields' => $fields
        ]));
    }

    /**
     * This function call when action is not define/data/update/insert/setting/reset_setting/excel/import/sample/pdf/word
     * @param $action
     * @param $rs
     * @return mixed
     */
    abstract function customAction($action, $rs);

    /**
     * This function call before updated or inserted.
     * If it return false, it mean cancel updated and throw error.
     * Use this to validate data or do something.
     * @param $message
     * @param $rs
     * @return bool
     */
    abstract function beforeUpdate(&$rs, &$message): bool;

    /**
     * This function call after updated or inserted.
     * Use it to update $updated record set
     * @param $rs
     */
    abstract function afterUpdate(&$rs): void;

    abstract function afterInit(): void;

    abstract function afterDefine(): void;

    /**
     * This function call before get data.
     * If it return false, it mean cancel get data.
     * Use this customize get data
     * @param $data
     * @param $total
     * @param $list
     * @return bool
     */
    abstract function beforeData(&$data, &$total, &$list): bool;

    /**
     * This function call after get data.
     * Use it to adjust data gotten
     * @param $data
     * @param $total
     * @param $summary
     * @param $list
     * @param $extra
     */
    abstract function afterData(&$data, &$total, &$summary, &$list, &$extra): void ;

    abstract function beforeDelete(&$rs, &$message): bool;

    abstract function afterDelete(&$rs): void;

    abstract public function afterExcel(ExcelExporter $ep);

    abstract public function beforeExcel(ExcelExporter $ep, &$data);

    abstract public function afterImport(ExcelImporter $ip, $data): void;

    abstract public function beforeImport(ExcelImporter $ip, &$data): void;

    abstract protected function isOwner(): bool;

    abstract public function saveFormSetting($reset = false): bool;

    abstract public function getFormSettingList(): ?array;

    abstract public function loadFormSetting(): ?array;

    // TODO: Angular key khong phai la ID, insert no cung nghi la copy.
    // Search may cai khong co decimal thi dung hien dau phay gium cai
    // Xong: Editmode thi khi check select khong reset data OK?

    // TODO: Lay data ve cho het
}

