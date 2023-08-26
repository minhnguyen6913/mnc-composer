<?php
namespace Sannomiya\Form;

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;

define("EXCEL_REPORTER_FI_EXPORT",1);
define("EXCEL_REPORTER_FI_WIDTH",2);
define("EXCEL_REPORTER_FI_FUNCTION",3);
define("EXCEL_REPORTER_FI_TYPE",4);
define("EXCEL_REPORTER_FI_WRAP",5);

class ExcelReporter extends Bag
{
    private $excel_template = null;

    private $last_column = 0;
    private $last_row = 0;

    private $main_data = [];
    private $detail_data = [];

    private $file_name = "report.xlsx";

    private ?LoggerInterface $logger;
    /**
     * @var Spreadsheet
     */
    private $excel = null;

    public function __construct($excel_template, $last_column, $last_row)
    {
        $this->set_excel_template($excel_template);
        $this->set_last_column($last_column);
        $this->set_last_row($last_row);
    }

    public function set_output_file_name($value="report.xlsx"){
        $this->file_name = $value;
    }

    public function get_output_file_name(){
        return $this->file_name;
    }

    public function set_main_data($value, $start_row, $end_row){
        $this->main_data[] = ["data" =>$value, "start_row"=>$start_row, "end_row" =>$end_row];
    }

    public function set_detail_data($value, $start_row, $end_row, $base_height_col=null, $step = 1, $start_col = null, $end_col = null ){
        $this->detail_data[] = ["data" =>$value, "start_row"=>$start_row,
            "end_row" =>$end_row, "step" =>$step, "base_height_col"=>$base_height_col,
            "start_col" => $start_col, "end_col" => $end_col
        ];
    }

    public function set_last_column($value){
        $this->last_column = $value;
    }

    public function set_last_row($value){
        $this->last_row = $value;
    }

    public function get_last_column(){
        return $this->last_column;
    }

    public function get_last_row(){
        return $this->last_row;
    }


    /**
     * @return Spreadsheet
     */
    public function get_excel(){
        return $this->excel;
    }


    public function set_excel_template($template){
        $this->excel_template = $template;

    }

    private function log(string $log) {
        if (isset($this->logger)) {
            $this->logger->debug("ExcelReporter - " . $log);
        }
    }

    public function create_excel()
    {
        ini_set('memory_limit', '-1');

        if (isset($this->excel_template)){
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($this->excel_template);
        }else{
            $spreadsheet = new Spreadsheet();

        }
        
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();

        $this->excel = $spreadsheet;


        // Set main data
        foreach ($this->main_data as $data_set){
            $data = $data_set["data"];
            $start_row = $data_set["start_row"];
            $end_row = $data_set["end_row"];
            for ($r=$start_row;$r<=$end_row;$r++){
                for ($c=0;$c<=$this->last_column;$c++){
                    $this->set_map_value($sheet, $c, $r, $data);
                }
            }
        }


        // Set detail
        $count = 0;
        $this->log("Start create detail data");
        foreach ($this->detail_data as &$data_set){
            $rs = $data_set["data"];
            $start_row = $data_set["start_row"];
            $step = $data_set["step"];
            $end_row = $start_row + $step;

            $start_col = $data_set['start_col'] ?? 0;
            $end_col = $data_set['end_col'] ?? $this->last_column;

            $base_height_col = $data_set["base_height_col"];
            foreach ($rs as $data){
                for ($r=$start_row;$r < $end_row;$r++){
                    $height = 0;
                    for ($c=$start_col;$c<=$end_col;$c++){
                        $temp_height = $this->set_map_value($sheet, $c, $r, $data);
                        if ($temp_height > $height){
                            $height = $temp_height;
                        }
                    }
                    if (isset($base_height_col)){
                        $value = $sheet->getCell("$base_height_col$r")->getValue();
                        $width = $sheet->getColumnDimension ( $base_height_col )->getWidth () ;
                        $temp_height = $this->get_row_height( $value, $width ) ;
                        if ($temp_height > $height){
                            $sheet->getRowDimension ( $r )->setRowHeight ($temp_height);
                        }
                        //$sheet->setCellValue("A$r", $width);
                    }
                }
                $start_row = $end_row;
                $end_row = $start_row + $step;
            }
            $end_row--;

            // Real end row
			$data_set["real_end_row"] = $end_row;
        }
        $this->log("End create detail data");

        // Delete some row and update last row
        foreach ($this->detail_data as $rec){
            if ($rec["real_end_row"] < $rec["end_row"]) {
                // $rows = $rec["end_row"]-$rec["real_end_row"];
                $start_row = $rec["real_end_row"];
                $start_col = $rec['start_col'] ?? 0;
                $end_col = $rec['end_col'] ?? $this->last_column;

                for ($r=$start_row;$r <= $rec["end_row"];$r++){
                    for ($c=$start_col;$c<=$end_col;$c++){
                        $this->set_map_value($sheet, $c, $r, []);
                    }
                }

                // Delete
                // $sheet->removeRow($start_row, $rows);

                // Update
                // $this->last_row -= $rows;
            }
        }

        $last_column = ExcelExporter::get_excel_column_name($this->get_last_column());
        $last_row = $this->get_last_row();

        if ($last_row > 5) {
            // Print
            $sheet->getPageSetup()->setPrintArea("A1:$last_column$last_row");
            $sheet->getPageSetup()->setFitToWidth(1);
            $sheet->getPageSetup()->setFitToHeight(0);
            $sheet->getHeaderFooter()->setOddFooter('&C&P / &N');
            $sheet->getHeaderFooter()->setEvenFooter('&C&P / &N');
        }


        return $spreadsheet;
    }

    private function get_row_count($text, $width) {

        $rc = 0;
        $line = explode ( "\n", $text );
        foreach ( $line as $source ) {
            $rc ++;
            $row = ceil ( strlen ( $source ) / ($width * (2/3)) );
            if ($row > 1) {
                $rc += ($row - 1);
            }
        }
        return $rc;
    }

    private function get_row_height($text, $width) {
        $rc = $this->get_row_count($text, $width);
        return $rc * 6 + 10;
    }


    /**
     * @param $sheet
     * @param $c
     * @param $r
     * @param $data
     * @return float|int
     */
    private function set_map_value(Worksheet $sheet, $c, $r, $data){
        $value = $sheet->getCellByColumnAndRow($c,$r)->getValue();
        if ($value!=""){
            if ($value instanceof RichText) {
                $value = $value->getPlainText();
            }
            $new_value = $this->get_map_value($data, $value);
            if ($new_value!=$value){
                if (strpos($new_value, "#image#")===0){
                    $sheet->setCellValueByColumnAndRow($c,$r,null);
                    $image_path = substr($new_value,7);
                    if (file_exists($image_path)) {
                        $col = ExcelExporter::get_excel_column_name($c);
                        ExcelExporter::add_excel_image($sheet, "$col$r", $image_path);
                        list ( , $h) = getimagesize($image_path);
                        $points = Drawing::pixelsToPoints($h) + 15;
                        $sheet->getRowDimension($r)->setRowHeight($points);
                    }
                }else{
                    $sheet->setCellValueByColumnAndRow($c,$r,$new_value);
                }
            }
        }

        if (isset($points)){
            return $points;
        }else{
            return 0;
        }
    }

    private function get_map_value($data, $value){
        if (!isset($value) || !is_string($value) || $value==""){
            return $value;
        }

        preg_match_all('/\[([a-zA-Z0-9_]+)]/', $value, $matches, PREG_SET_ORDER);
        //preg_match_all('/\{\[([a-zA-Z0-9_]+)\]\}/', $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match){
            $field = $match[1];
            $replacement = null;
            if (isset($data[$field])){
                $replacement = $data[$field];
            }
            $value = str_replace($match[0], $replacement, $value);
        }
        return $value;
    }


    public function output_excel(){
        /**
         * @var Spreadsheet $excel
         */

        if (!isset($this->excel)) {
            $this->create_excel();
        }

        $writer = new Xlsx($this->excel);
        ob_start();
        $this->log("Start save data");
        $writer->save('php://output');
        $this->log("End save data");
        return ob_get_clean();
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
