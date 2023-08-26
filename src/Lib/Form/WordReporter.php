<?php

namespace Sannomiya\Form;

use DateTime;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class WordReporter extends Bag
{
    private $word_template = null;

    private $main_data = [];
    private $detail_data = [];

    private $file_name = "report.docx";

    /**
     * @var PhpWord
     */
    private $word = null;

    /**
     * @var TemplateProcessor
     */
    private $processor = null;

    public function __construct($word_template)
    {
        $this->set_word_template($word_template);
    }

    public function set_output_file_name($value){
        $this->file_name = $value;
    }

    public function get_output_file_name(): ?string
    {
        return $this->file_name;
    }

    public function set_main_data($data) {
        $this->main_data[] = $data;
    }

    public function set_detail_data($key, $data ){
        $this->detail_data[$key] = $data;
    }

    /**
     * @return PhpWord
     */
    public function get_word(): ?PhpWord
    {
        return $this->word;
    }

    public function set_word_template($template){
        $this->word_template = $template;
    }

    public function create_word()
    {
        $this->processor = new TemplateProcessor($this->word_template);
        foreach ($this->main_data as $rec) {
            foreach ($rec as $key=>$value) {
                $this->processor->setValue($key, $value);
            }
        }
        foreach ($this->detail_data as $key=>$values) {
            $this->processor->cloneRowAndSetValues($key, $values);
        }
    }

    public function output_sword($filename=null): DownloadObject
    {
        if (!isset($this->processor)) {
            $this->create_word();
        }
        $tmp_filename = $this->processor->save();
        $this->word = IOFactory::load($tmp_filename);
        $writer = IOFactory::createWriter($this->word);
        ob_start();
        $writer->save('php://output');
        if (!isset($filename)){
            $filename = $this->get_output_file_name();
        }
        unlink($tmp_filename);
        return new DownloadObject($filename, ob_get_clean());
    }
    public function save_word($filepath){
        if (!isset($this->processor)) {
            $this->create_word();
        }
        $this->processor->saveAs($filepath);
    }

    public function save_pdf($filepath, $wordPath = null){
        Settings::setPdfRendererName(Settings::PDF_RENDERER_MPDF);
        Settings::setPdfRendererPath(__DIR__ . '/../../vendor/mpdf/mpdf');
        if (!isset($this->processor)) {
            $this->create_word();
        }
        if (!isset($wordPath)) {
            $tmp_filename = $this->processor->save();
        }else{
            $tmp_filename = $wordPath;
        }
        $this->word = IOFactory::load($tmp_filename);
        $writer = IOFactory::createWriter($this->word, 'PDF');
        $writer->save($filepath);
        if (!isset($wordPath)) {
            unlink($tmp_filename);
        }
    }

    public function output_pdf($filename=null): DownloadObject
    {
        Settings::setPdfRendererName(Settings::PDF_RENDERER_MPDF);
        Settings::setPdfRendererPath(__DIR__ . '/../../vendor/mpdf/mpdf');

        if (!isset($this->processor)) {
            $this->create_word();
        }
        $tmp_filename = $this->processor->save();
        $this->word = IOFactory::load($tmp_filename);
        $writer = IOFactory::createWriter($this->word, 'PDF');
        ob_start();
        $writer->save('php://output');
        if (!isset($filename)){
            $filename = "report.pdf";
        }
        unlink($tmp_filename);
        return new DownloadObject($filename, ob_get_clean());
    }

    protected function get_temp_filename(): string
    {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
        return __DIR__ . DIRECTORY_SEPARATOR .'tmp_' . $d->format("Ymd_His.u");
    }
}
