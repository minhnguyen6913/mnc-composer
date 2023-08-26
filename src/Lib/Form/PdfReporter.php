<?php
namespace Sannomiya\Form;

use Mpdf\Mpdf;
class PdfReporter extends Bag
{
    private string $filename = "report.pdf";

    private ?Mpdf $mpdf;

    private ?string $template;

    private array $singleBlocks = [];
    private array $continuousBlocks = [];
    private array $summaryBlocks = [];

    public function __construct($template, $orientation="P", $pageSize="A4")
    {
        $this->template = file_get_contents($template);

        $this->mpdf = new Mpdf(['mode' => 'utf-8', 'orientation' => $orientation, 'format'=> $pageSize]);

        // Default footer
        $this->mpdf->SetHTMLFooter('
        <table style="width: 100%;">
            <tr>
                <td style="width: 33%;"></td>
                <td style="width: 34%;text-align: center">{PAGENO}/{nbpg}</td>
                <td style="width: 33%;text-align: right;"></td>
          </tr>
        </table>');

        // Default margin
        $this->mpdf->SetMargins(10,10,10);
    }

    public function setOutputFilename($value="report.pdf"){
        $this->filename = $value;
    }

    public function getOutputFilename(): ?string
    {
        return $this->filename;
    }

    public function setBlockSummary($blockName, $summaryBlockName, array $summaryFields) {
        if (!isset($this->summaryBlocks[$blockName] )) {
            $this->summaryBlocks[$blockName]  = [];
        }
        $this->summaryBlocks[$blockName] [$summaryBlockName] = $summaryFields;
    }

    public function setBlock($blockName, $data, $continuous = false) {
        if ($continuous) {
            $this->continuousBlocks[$blockName] = $data;
        }else{
            $this->singleBlocks[$blockName] = $data;
        }
    }
    public function deleteBlock($blockName){
        $result = $this->findBlock($blockName);
        if (isset($result)) {
            $this->replaceBlock($this->template, $blockName, '');
        }
    }

    public function removeBlockTag($blockName){
        $result = $this->findBlock($blockName);
        if (isset($result)) {
            $this->replaceBlock($this->template, $blockName, $result);
        }
    }

    private function findBlock($blockName){
        preg_match("/<%$blockName%>(.*?)<%\\/$blockName%>/s", $this->template, $matches);
        if (is_array($matches) && count($matches) == 2) {
            return $matches[1];
        }
        return null;
    }

    private function parseBlock($template, $data) {
        if (!is_array($data)) {
            return  $data;
        }
        foreach ($data as $key=>$value){
            // $pattern = "/\\[%$key(:([0-9a-zA-Z\\/\\-\\s]+))*%]/s";
            $pattern ="/\\[%$key(:([0-9a-zA-Z\\/\\-\\s]+))*%]/";
            preg_match($pattern, $template, $matches);
            if (is_array($matches) && count($matches)>0) {
                if (count($matches) == 3) {
                    $format = $matches[2];
                    if (strpos($format, "n") === 0) {
                        $decimal = str_replace("n", "", $format);
                        if (is_numeric($decimal) && is_numeric($value)) {
                            $value = number_format($value, $decimal);
                        }
                    }elseif($format==='text') {
                        $value = str_replace("\n", "<br>", $value);
                    }else{
                        // Date format
                        if (isset($value) && $value!="") {
                            if (!is_numeric($value)) {
                                $value = strtotime($value);
                            }
                            $value = date($format, $value);
                        }
                    }
                }
                $template = preg_replace($pattern, $value, $template);
            }
        }
        return $template;
    }

    private function replaceBlock(&$template, $blockName, $result) {
        $template = preg_replace("/<%$blockName%>(.*?)<%\\/$blockName%>/s", $result, $template);
    }

    /**
     * @return Mpdf|null
     */
    public function getMpdf(): ?Mpdf
    {
        return $this->mpdf;
    }

    public function output(): DownloadObject
    {
        $results = [];
        foreach ($this->singleBlocks as $blockName=>$data) {
            $template = $this->findBlock($blockName);
            if (!isset($template)) {
                continue;
            }
            $results[$blockName] = $this->parseBlock($template, $data);
        }
        $summaries = [];
        foreach ($this->continuousBlocks as $blockName=>$data) {
            $template = $this->findBlock($blockName);
            if (!isset($template)) {
                continue;
            }
            // Do this here so that can deal with case of have no record in $data
            foreach ($this->summaryBlocks[$blockName] as $summaryBlock=>$summaryFields) {
                if (!isset($summaries[$summaryBlock])) {
                    $summaries[$summaryBlock] = [];
                    foreach ($summaryFields as $field) {
                        $summaries[$summaryBlock][$field] = 0;
                    }
                }
            }

            $result = "";
            foreach ($data as $rec) {
                $result .= $this->parseBlock($template, $rec);
                if (isset($this->summaryBlocks[$blockName])){
                    foreach ($this->summaryBlocks[$blockName] as $summaryBlock=>$summaryFields) {
                        foreach ($summaryFields as $field) {
                            $summaries[$summaryBlock][$field] += $rec[$field];
                        }
                    }
                }
            }
            $results[$blockName] = $result;
        }
        foreach ($summaries as $blockName => $rec) {
            $template = $this->findBlock($blockName);
            if (!isset($template)) {
                continue;
            }
            $results[$blockName] = $this->parseBlock($template, $rec);
        }

        $buffer = $this->template;
        foreach ($results as $blockName => $result) {
            $this->replaceBlock($buffer, $blockName, $result);
        }
        $this->mpdf->WriteHTML($buffer);
        ob_start();
        $this->mpdf->Output();
        $data = ob_get_clean();
        return new DownloadObject($this->filename, $data);
    }
}
