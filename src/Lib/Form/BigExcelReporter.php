<?php

namespace Minhnhc\Form;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use XLSXWriter;

define("EXCEL_REPORTER_FI_EXPORT",1);
define("EXCEL_REPORTER_FI_WIDTH",2);
define("EXCEL_REPORTER_FI_FUNCTION",3);
define("EXCEL_REPORTER_FI_TYPE",4);
define("EXCEL_REPORTER_FI_WRAP",5);

class BigExcelReporter extends Bag
{
    private ?string $sheetName = "Export";

    private ?LoggerInterface $logger;

    private array $details = [];
    private array $styles = [];
    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    public function setData($startRow, $rs, $styles = []){
        $this->details[$startRow] = $rs;
        $this->styles[$startRow] = $styles;
    }

    private function log(string $log) {
        if (isset($this->logger)) {
            $this->logger->debug("ExcelReporter - " . $log);
        }
    }

    public function excel($filename = null): ?string{
        /**
         * @var Spreadsheet $excel
         */

        ini_set('memory_limit', '-1');
        /**
         * @var XLSXWriter $excel
         */
        require_once __DIR__ . '/xlsxwriter.class.php';
        // Header
        $writer = new XLSXWriter ();

        foreach ($this->details as $row => $rs) {
            $writer->writeSheetPartial($row, $rs, $this->sheetName, $this->styles[$row]);
        }
        $writer->finalizeSheet($this->sheetName);
        if (isset($filename)) {
            $this->log("Start save data");
            $writer->writeToFile($filename);
            $this->log("End save data");
            return null;
        }else{
            ob_start();
            $this->log("Start save data");
            $writer->writeToStdOut ();
            $this->log("End save data");
            return ob_get_clean();
        }
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

    /**
     * @return string|null
     */
    public function getSheetName(): ?string
    {
        return $this->sheetName;
    }

    /**
     * @param string|null $sheetName
     */
    public function setSheetName(?string $sheetName): void
    {
        $this->sheetName = $sheetName;
    }
}
