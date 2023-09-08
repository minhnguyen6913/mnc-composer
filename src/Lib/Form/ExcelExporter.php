<?php

namespace Minhnhc\Form;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Minhnhc\Database\Database;
use Minhnhc\Util\Helper;
use XLSXWriter;

define("EXCEL_EXPORTER_FI_EXPORT",1);
define("EXCEL_EXPORTER_FI_WIDTH",2);
define("EXCEL_EXPORTER_FI_FUNCTION",3);
define("EXCEL_EXPORTER_FI_TYPE",4);
define("EXCEL_EXPORTER_FI_WRAP",5);
define("EXCEL_EXPORTER_FI_PERCENTAGE",6);
class ExcelExporter extends Bag
{
    const HEADER_BACKGROUND_COLOR = 'B4CFDA';
    const HEADER_FORE_COLOR = '000000';


    const AutoWidth = -1;

    const OutTypeRaw = 1;
    const OutTypeValue = 2;

    const OrientalPortrait = 1;
    const OrientalLandscape = 2;
    const PageSizeA3 = 2;
    const PageSizeA4 = 1;

    /**
     * @var Form
     */
    private $fm = null;

    private $file_name = "export.xlsx";

    private $oriental = ExcelExporter::OrientalPortrait;
    private $excel_template = null;
    private $excel_start_row = 1;
    private $auto_filter = false;
    private $page_size = null;
    private $freeze_pane = "B2";
    private $use_xlsx_writer = false;
    private $show_zero = false;
    private $title = null;
    private $format_print = true;
    private $draw_header = true;

    private $export_fields = [];
    public $last_column = 0;
    public $last_row = 0;


    private $excels = [];
    private $currentIndex = 0;

    private int $max_excel_page_size = 3000;

    public $data = null;

    public function set_use_xlsx_writer($value=true){
        $this->use_xlsx_writer = $value;
    }

    public function is_use_xlsx_writer(){
        return $this->use_xlsx_writer;
    }

    /**
     * ExcelExporter constructor.
     * @param Form $fm
     */
    public function __construct(Form $fm)
    {
        $this->set_form($fm);
    }

    /**
     * @param $fm Form
     */
    private function set_form(Form $fm){
        $this->fm = $fm;
    }


    /**
     * @return Form
     */
    public function get_form(){
        return $this->fm;
    }

    public function set_title($title){
        $this->title = $title;
        if (isset($title)){
            $this->excel_start_row = 3;
            $this->set_freeze_pane("B4");
        }else{
            $this->excel_start_row = 1;
            $this->set_freeze_pane("B2");
        }

    }


    public function get_title(){
        return $this->title;
    }

    public function set_freeze_pane($value){
        $this->freeze_pane = $value;
    }


    /**
     * @return Spreadsheet
     */
    public function get_excel(){
        if ($this->is_use_xlsx_writer()) {
            return null;
        }
        if ($this->currentIndex >= $this->get_excel_count()) {
            return null;
        }
        return $this->excels[$this->currentIndex];
    }

    public function get_excel_count(){
        return count($this->excels);
    }

    public function resetExportOrder() {
        $this->export_fields = [];
    }

    public function setExportOrder($fields) {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $field) {
            $field = trim($field);
            $this->export_fields[] = $field;
        }
    }

    public function set_export_fields($fields, $value=true){
        $this->setBag($fields, EXCEL_EXPORTER_FI_EXPORT, $value);
    }

    public function set_width($fields, $value=ExcelExporter::AutoWidth){
        $this->setBag($fields, EXCEL_EXPORTER_FI_WIDTH, $value);
    }

    public function get_width($field){
        return $this->getBag($field, EXCEL_EXPORTER_FI_WIDTH);
    }

    public function set_out_type($fields, $value){
        $this->setBag($fields, EXCEL_EXPORTER_FI_TYPE, $value);
    }

    public function get_out_type($field){
        return $this->getBag($field, EXCEL_EXPORTER_FI_TYPE);
    }

    public function set_wrap_text($fields, $value=true){
        $this->setBag($fields, EXCEL_EXPORTER_FI_WRAP, $value);
    }

    public function is_wrap_text($field){
        return $this->getBag($field, EXCEL_EXPORTER_FI_WRAP);
    }

    public function set_function($fields, $value){
        $this->setBag($fields, EXCEL_EXPORTER_FI_FUNCTION, $value);
    }

    public function get_function($field){
        return $this->getBag($field, EXCEL_EXPORTER_FI_FUNCTION);
    }

    public function set_show_percentage($fields, $value=true){
        $this->setBag($fields, EXCEL_EXPORTER_FI_PERCENTAGE, $value);
    }

    public function is_show_percentage($field){
        return $this->getBag($field, EXCEL_EXPORTER_FI_PERCENTAGE);
    }

    public function set_output_file_name($value="export.xlsx"){
        $this->file_name = $value;
    }

    public function get_output_file_name(){
        return $this->file_name;
    }

    public function set_oriental($value = ExcelExporter::OrientalPortrait){
        $this->oriental = $value;
    }

    public function set_excel_template($template, $start_row, $freeze_pane){
        $this->excel_template = $template;
        $this->excel_start_row = $start_row;
        $this->set_freeze_pane($freeze_pane);
    }

    public function set_start_row($value){
        $this->excel_start_row = $value;
    }

    public function set_page_size($value=ExcelExporter::PageSizeA4){
        $this->page_size = $value;
    }

    public function set_auto_filter($value){
        $this->auto_filter = $value;
    }

    public static function get_excel_column_name($num)
    {
        // Base 1 to base 0
        if ($num>0) {
            $num = $num - 1;
        }

        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return ExcelExporter::get_excel_column_name($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }

    public static function add_excel_image(Worksheet $sheet, $cell, $path, $offset_x = 5, $offset_y = 5): bool
    {
        if (!file_exists($path)){
            return false;
        }
        $objDrawing =  new Drawing();
        $id = date('Ymd_his_u');
        $objDrawing->setName($id);
        $objDrawing->setDescription($id);
        $objDrawing->setPath($path);
        $objDrawing->setCoordinates($cell);
        $objDrawing->setOffsetX($offset_x);
        $objDrawing->setOffsetY($offset_y);
        $objDrawing->setWorksheet($sheet);
        return true;
    }

    public function create_excel(Worksheet $sh=null){
        ini_set('memory_limit', '-1');


        if (!$this->fm->isUsePhpOffice()){
            ini_set('max_execution_time', 300);
            $this->fm->prepareListValues();
            $this->use_xlsx_writer=true;
		    $query = $this->fm->createSelectQuery();
		    $db = $this->fm->db; // Database::getInstance('mirror');
		    $db->setFetchType(Database::FETCH_TYPE_ASSOC);
		    $db->prepare($query);
            $ret[] = $this->create_long_excel_xslx_writer($db);
        }else{
            $this->data = $this->fm->getAllData();
            $this->fm->afterData($this->data, $t, $s, $l, $e);
            $ret = $this->fm->beforeExcel($this, $this->data);

            if (!isset($ret)){
                $total = count($this->data);
                $excel_page_size = $this->getMaxExcelPageSize();
                $ret = [];
                if ($excel_page_size<$total){
                    $this->use_xlsx_writer=true;
                    $ret[]  = $this->create_excel_xslx_writer($this->data);
                }else{
                    $this->use_xlsx_writer=false;
                    $ret[]  = $this->create_excel_spreadsheet($this->data, $sh);
                }
            }else{
                $this->use_xlsx_writer=false;
                $ret = [$ret];
            }
        }
        $this->excels = $ret;


        return $ret;
    }

    private function create_long_excel_xslx_writer(Database $db){
        /**
         * @var XLSXWriter $excel
         */
        require_once __DIR__ . '/xlsxwriter.class.php';
        // Header
        $header = $this->create_excel_xslx_header();
        $writer = new XLSXWriter ();
        $writer->writeSheetHeader ( 'Export', $header ); // optional
        if (count($this->export_fields) > 0) {
            $fields = $this->export_fields;
        }else{
            $fields = $this->getFields();
        }
        $rec = $db->fetch();
        while ($rec) {
            $row = [];
            foreach ($fields as $fieldName) {
                $value = $this->fm->getValue($fieldName, $rec);
                // $value = @$rec[$fieldName];
                if (is_string($value)){
                    $value = preg_replace('/^=/',"", trim($value));
                }
                $row[] = $value;
            }
            $writer->writeSheetRow ( 'Export', $row );
            $rec = $db->fetch();
        }

        return $writer;
    }
    private function create_excel_xslx_header() {
        if (count($this->export_fields) > 0) {
            $fields = $this->export_fields;
        }else{
            $fields = $this->getFields();
        }
        $this->last_column = count($fields);
        $fm = $this->fm;

        $header = [];
        foreach ($fields as $fieldName) {
            $field = $fm->getField($fieldName);
            $type = $field->type;
            $caption = isset($field->caption) ? $field->caption : $field->name;
            if (isset($field->groupName) && $field->groupName!='') {
                $caption = "($field->groupName) $caption";
            }
            if (isset( $header[$caption])) {
                $caption = $field->name;
            }
            if (isset($field->listValues) || isset($field->listName)){
                $header[$caption] = "string";
            }else{
                if ($type==Type::Number || $type==Type::Float || $type==Type::Int){
                    if (!is_null($field->decimal)){
                        $header[$caption] = "#,##0";
                    }else{
                        $header[$caption] = "integer";
                    }
                }elseif ($type==Type::Date){
                    $header[$caption] = "yyyy/mm/dd";
                }else{
                    $header[$caption] = "string";
                }
            }
        }
        return $header;

    }

    private function create_excel_xslx_writer($data)
    {
        /**
         * @var XLSXWriter $excel
         */
        require_once __DIR__ . '/xlsxwriter.class.php';


        // Header
        $header = $this->create_excel_xslx_header();
        $writer = new XLSXWriter ();
        $writer->writeSheetHeader ( 'Export', $header ); // optional
        if (count($this->export_fields) > 0) {
            $fields = $this->export_fields;
        }else{
            $fields = $this->getFields();
        }

        foreach ($data as $rec) {
            $row = [];
            foreach ($fields as $fieldName) {
                $value = $this->fm->getValue($fieldName, $rec);
                // $value = @$rec[$fieldName];
                if (is_string($value)){
                    $value = preg_replace('/^=/',"", trim($value));
                }
                $row[] = $value;
            }
            $writer->writeSheetRow ( 'Export', $row );
        }
        return $writer;
    }

    private function create_summary_row($sheet, $summary_fields, $summary_field_values, $summary_columns, $row,
                                        $last_column, $color=self::HEADER_FORE_COLOR, $background_color=self::HEADER_BACKGROUND_COLOR) {
        foreach ($summary_fields as $field) {
            $colName = $this->get_excel_column_name($summary_columns[$field]);
            if ($summary_field_values [$field]!=0 || $this->is_show_zero()){
                $sheet->setCellValue("$colName$row", $summary_field_values [$field]);
            }
        }
        $sheet->getStyle("A$row:$last_column$row")->applyFromArray(array(
            'font' => ['bold' => false, 'color' => ['rgb' => $color]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $background_color]]
        ));
    }

    private function create_sub_summary_row($sheet, $fields, $group_summarize_fields, $summary_fields, &$summary_field_values, $summary_columns, $row,
                                            $last_column, $color='393939', $background_color='F2F2F2') {
        foreach ($summary_fields as $field) {
            $colName = $this->get_excel_column_name($summary_columns[$field]);
            if ($summary_field_values [$field]!=0 || $this->is_show_zero()){
                $sheet->setCellValue("$colName$row", $summary_field_values [$field]);
            }
            $summary_field_values [$field] = 0;
        }

        // Field for group's values
        $col = 1;
        foreach ($fields as $field) {
            if (in_array($field, $group_summarize_fields)) {
                $sheet->setCellValueByColumnAndRow($col, $row, $sheet->getCellByColumnAndRow($col, $row - 1)->getValue());
            }
            $col++;
        }

        $sheet->getStyle("A$row:$last_column$row")->applyFromArray(array(
            'font' => ['bold' => true, 'color' => ['rgb' => $color]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $background_color]],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '#525252']
                ]
            ]
        ));
    }

    public function create_excel_spreadsheet($data, Worksheet $sh = null)
    {
        $fm = $this->fm;

        // Lay data ra va tao file excel
        if (isset($sh)) {
            $sheet = $sh;
        }else{
            if (isset($this->excel_template)){
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spreadsheet = $reader->load($this->excel_template);
            }else{
                $spreadsheet = new Spreadsheet();

            }
            $spreadsheet->setActiveSheetIndex(0);
            $sheet = $spreadsheet->getActiveSheet();
        }


        if (count($this->export_fields) > 0) {
            $fields = $this->export_fields;
        }else{
            $fields = $this->getFields();
        }
        $count = count($fields);
        $last_column = $this->get_excel_column_name($count);
        $this->last_column = count($fields);

        // Title
        if (isset($this->title)){
            $sheet->setCellValue("A1", $this->title);
            $sheet->getStyle("A1")->applyFromArray(array(
                'font' => ['bold' => true, 'size'=>15]
            ));
        }


        $row = $this->excel_start_row;
        $image_columns = [];
        $summary_columns = [];
        $summary_fields = [];
        $summary_field_values = [];

        // Summary prepare
        $col = 1;
        foreach ($fields as $fieldName) {
            $field = $fm->getField($fieldName);
            if ($field->summary) {
                $summary_columns[$fieldName] = $col;
                // Prepare for sub summary
                $summary_fields[] = $fieldName;
                $summary_field_values [$fieldName] = 0;
            }
            $col++;
        }

        // Header
        if ($this->isDrawHeader()) {
            $col = 1;
            $currentGroupName = null;
            $groupingHeader = false;

            foreach ($fields as $fieldName) {
                $field = $fm->getField($fieldName);
                $sheet->setCellValueByColumnAndRow($col, $row, $field->caption);
                if ($row > 1 && isset($field->groupName) && $field->groupName != $currentGroupName) {
                    $groupingHeader = true;
                    $sheet->setCellValueByColumnAndRow($col, $row-1, $field->groupName);
                    // Merge and color
                    $i = $col;
                    while ($i < count($fields)) {
                        $tmpField = $fm->getField($fields[$i]);
                        if (!isset($tmpField->groupName) || $field->groupName != $tmpField->groupName) {
                            break;
                        }
                        $i++;
                    }
                    if ($i > $col) {
                        $sheet->mergeCellsByColumnAndRow($col, $row-1, $i, $row-1);
                    }

//                    $sheet->getStyleByColumnAndRow($col, $row-1, $i, $row-1)->applyFromArray([
//                        'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_FORE_COLOR]],
//                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HEADER_BACKGROUND_COLOR]],
//                        'borders' => [
//                            'allBorders' => [
//                                'borderStyle' => Border::BORDER_THIN,
//                                'color' => ['rgb' => '101010'],
//                            ],
//                        ],
//                        ]
//
//                    );

                    $currentGroupName = $field->groupName;
                }
                $col++;
            }
            $sheet->getStyle("A$row:$last_column$row")->applyFromArray(array(
                'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_FORE_COLOR]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HEADER_BACKGROUND_COLOR]],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '101010'],
                    ],
                ],
            ));

            if ($groupingHeader) {
                $row1 = $row-1;
                $sheet->getStyle("A$row1:$last_column$row1")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_FORE_COLOR]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HEADER_BACKGROUND_COLOR]],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '101010'],
                            ],
                        ],
                    ]
                );
            }
        }

        $current_value = null;
        $sub_summary_field_values = null;
        if ($fm->isSubSummarize()) {
            $current_value = array();
            $sub_summary_field_values = array();
            foreach ($summary_fields as $field) {
                $sub_summary_field_values [$field] = 0;
            }
        }
        $group_summarize_fields = $fm->getGroupForSubSummarize();

        // Data
        $fm->prepareListValues($data);
        for ($i=0; $i<count($data); $i++) {
            $row++;
            $rec = $data[$i];
            // Sub summary
            if ($fm->isSubSummarize()) {
                if ($i > 0) {
                    $changed = false;
                    foreach ($group_summarize_fields as $field) {
                        $value = $rec[$field];
                        if ($current_value [$field] != $value) {
                            $changed = true;
                            break;
                        }
                    }
                    if ($changed) {
                        $this->create_sub_summary_row($sheet, $fields, $group_summarize_fields,
                            $summary_fields, $sub_summary_field_values, $summary_columns, $row, $last_column);
                        $row++;
                    }
                }

                // Sub summary
                foreach ($summary_fields as $field) {
                    $function = $this->get_function($field);
                    if (is_null($function)) {
                        $sub_summary_field_values [$field] += $rec[$field];
                    } else {
                        $sub_summary_field_values [$field] += call_user_func($function, $rec);
                    }
                }

                // Save current value for check changed
                foreach ($group_summarize_fields as $field) {
                    $value = $rec[$field];
                    $current_value [$field] = $value;
                }
            }

            // Total summary
            foreach ($summary_fields as $field) {
                $function = $this->get_function($field);
                if (is_null($function)) {
                    $summary_field_values [$field] += $rec[$field];
                } else {
                    $summary_field_values [$field] += call_user_func($function, $rec);
                }
            }

            $col = 1;
            foreach ($fields as $fieldName) {
                $field = $fm->getField($fieldName);
                $out_type = $this->get_out_type($fieldName);
                $type = $field->type;


                if ($field->image) {
                    $file_id = @$rec['id'];
                    if (isset($field->imageParams)) {
                        $imageParams = explode(",", $field->imageParams);
                        $params = [];
                        foreach ($imageParams as $imageParam) {
                            $params[] = $rec[$imageParam];
                        }
                        $image_path = $fm->getThumbnailPath($field->name, $params);
                    }else{
                        $image_path = $field->getThumbnailPath($file_id, @$rec[$field->name]);
                    }

                    $cell = $this->get_excel_column_name($col) . $row;
                    if (file_exists($image_path)) {
                        $this->add_excel_image($sheet, $cell, $image_path);
                        list ($w, $h) = getimagesize($image_path);
                        $points = \PhpOffice\PhpSpreadsheet\Shared\Drawing::pixelsToPoints($h);
                        $sheet->getRowDimension($row)->setRowHeight($points + 5 + ($points/100)*12);
                        if (!isset($image_columns[$col]) || $image_columns[$col] < $w) {
                            $image_columns[$col] = $w;
                        }
                    }
                } else {
                    $function = $this->get_function($fieldName);
                    if (!isset($out_type) || $out_type == ExcelExporter::OutTypeValue || isset($function) ) {
                        // Get field's value
                        if (isset($function)){
                            $value = call_user_func($function, $rec);
                        }else{
                            $value = $fm->getValue($fieldName, $rec);
                        }
                    } else {
                        $value = @$rec[$fieldName];
                    }

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
                }
                $col++;
            }
        }

        // Last row of sub summary
        if ($fm->isSubSummarize() && count($summary_columns) > 0) {
            $row++;
            $this->create_sub_summary_row($sheet, $fields, $group_summarize_fields,
                $summary_fields, $sub_summary_field_values, $summary_columns, $row, $last_column);
        }

        // Image width
        foreach ($image_columns as $col => $w) {
            $points = \PhpOffice\PhpSpreadsheet\Shared\Drawing::pixelsToPoints($w);
            $sheet->getColumnDimension($this->get_excel_column_name($col))->setWidth($points * 40 / 220 + ($points/100)*5);
        }
        // Summary
        if (count($summary_columns) > 0) {
            $row++;
            $this->create_summary_row($sheet, $summary_fields, $summary_field_values, $summary_columns, $row, $last_column);
        }


        // Borders
        $i = $this->excel_start_row;
        $borders = $sheet->getStyle("A$i:$last_column$row")->getBorders();
        $borders->getVertical()->setBorderStyle(Border::BORDER_THIN);
        $borders->getRight()->setBorderStyle(Border::BORDER_THIN);
        $borders->getLeft()->setBorderStyle(Border::BORDER_THIN);
        $borders = $sheet->getStyle("A$row:$last_column$row")->getBorders();
        $borders->getBottom()->setBorderStyle(Border::BORDER_THIN);

        if ($this->auto_filter){
            $sheet->setAutoFilter("A$i:{$last_column}$i");
        }

        // Auto width & wrap text
        for ($col = 0; $col < $count; $col++) {
            $fieldName = $fields[$col];
            if (!isset($image_columns[$col+1])) {

                $width = $this->get_width($fieldName);
                if ($width == ExcelExporter::AutoWidth){
                    $sheet->getColumnDimension($this->get_excel_column_name($col+1))->setAutoSize(true);
                }elseif (is_numeric($width)){
                    $sheet->getColumnDimension($this->get_excel_column_name($col+1))->setAutoSize(false);
                    $sheet->getColumnDimension($this->get_excel_column_name($col+1))->setWidth($width);
                }
            }
            if ($this->is_wrap_text($fieldName)){
                $data_row = $this->excel_start_row+1;
                $sheet->getStyle("A$data_row:$last_column$row")
                    ->getAlignment()->setWrapText(true);
            }
        }


        // Font
        $sheet->getStyle("A$i:$last_column$row")->applyFromArray(
            ['font' => ['name' => 'Calibri']]
        );

        // Format
        $col = 1;
        foreach ($fields as $fieldName) {
            $field = $fm->getField($fieldName);
            $type = $field->type;
            $colName = $this->get_excel_column_name($col);
            if ($type == Type::Date || $type == Type::DateTime) {
                $sheet->getStyle("{$colName}2:$colName$row")->getNumberFormat()->setFormatCode("yyyy/mm/dd");
                $sheet->getColumnDimension($colName)->setWidth(12);
                $sheet->getColumnDimension($colName)->setAutoSize(false);
            } elseif ($type == Type::Text) {
                $sheet->getStyle("{$colName}2:$colName$row")->getAlignment()->setWrapText(true);
            } elseif ($this->is_show_percentage($fieldName)) {
                $sheet->getStyle("{$colName}2:$colName$row")->getNumberFormat()->setFormatCode('0.0%;[Red]-0.0%');
            } elseif ($type == Type::Number || $type == Type::Int || $type == Type::Float) {
                $decimalPoint = $field->decimal;
                if (is_numeric($decimalPoint)) {
                    $format = "#,###";
                    if ($decimalPoint > 0) {
                        $format = "#,##0.";
                        for ($iD = 0; $iD < $decimalPoint; $iD++) {
                            $format = $format . "0";
                        }
                    }
                    $sheet->getStyle("{$colName}2:$colName$row")->getNumberFormat()->setFormatCode($format);
                }
            }
            $col++;
        }

        // Print setup
        if ($this->format_print)
        {
            $sheet->getPageSetup()->setPrintArea("A1:$last_column$row");
            $sheet->getPageSetup()->setFitToWidth(1);
            $sheet->getPageSetup()->setFitToHeight(0);
            $sheet->getPageSetup()
                ->setRowsToRepeatAtTopByStartAndEnd(1, $this->excel_start_row);

            if ($this->oriental==ExcelExporter::OrientalLandscape){
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            }
            if ($this->page_size==ExcelExporter::PageSizeA3){
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
            }
            if (isset($this->freeze_pane)){
                $sheet->freezePane($this->freeze_pane);
            }
            //https://github.com/PHPOffice/PHPExcel/blob/develop/Documentation/markdown/Overview/08-Recipes.md#setting-the-print-header-and-footer-of-a-worksheet
            $sheet->getHeaderFooter()->setOddFooter('&C&P / &N');
            $sheet->getHeaderFooter()->setEvenFooter('&C&P / &N');

        }

        $this->last_row = $row;

        if (isset($sh)) {
            return $sh->getParent();
        }else{
            return $spreadsheet;
        }
    }

    public function countExcel(): int {
        if (is_array($this->excels)) {
            return count($this->excels);
        }
        return 0;
    }
    public function getExcel($index = 0): ?Spreadsheet {
        if (is_array($this->excels) && count($this->excels) > $index) {
            return $this->excels[$index];
        }
        return null;
    }

    public function output_pdf(){
        $ret = [];
        foreach ($this->excels as $excel){
            /**
             * @var Spreadsheet $excel
             */
            $sh = $excel->getActiveSheet();
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($excel);
            ob_start();
            $writer->save('php://output');
            $ret[] = ob_get_clean();
        }
        return $ret;
    }

    public function output_excel(){
        $ret = [];
        foreach ($this->excels as $excel){
            if ($this->use_xlsx_writer){
                /**
                 * @var XLSXWriter $excel
                 */
                require_once __DIR__ . '/xlsxwriter.class.php';
                ob_start ();
                $excel->writeToStdOut ();
                $ret[] = ob_get_clean();
            }else{
                /**
                 * @var Spreadsheet $excel
                 */
                $writer = new Xlsx($excel);
                ob_start();
                $writer->save('php://output');
                $ret[] = ob_get_clean();
            }

        }
        return $ret;
    }

    /**
     * @return bool
     */
    public function is_show_zero(): bool
    {
        return $this->show_zero;
    }

    /**
     * @param bool $show_zero
     */
    public function set_show_zero(bool $show_zero): void
    {
        $this->show_zero = $show_zero;
    }

    /**
     * @return bool
     */
    public function isDrawHeader(): bool
    {
        return $this->draw_header;
    }

    /**
     * @param bool $draw_header
     */
    public function setDrawHeader(bool $draw_header): void
    {
        $this->draw_header = $draw_header;
    }

    /**
     * @return int
     */
    public function getMaxExcelPageSize(): int
    {
        return $this->max_excel_page_size;
    }

    /**
     * @param int $max_excel_page_size
     */
    public function setMaxExcelPageSize(int $max_excel_page_size): void
    {
        $this->max_excel_page_size = $max_excel_page_size;
    }
}
