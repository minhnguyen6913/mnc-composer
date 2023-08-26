<?php
namespace Minhnhc\Form;

class ExcelImporterHelper extends ExcelImporter {

    public function doCheckData(&$rs, &$message): bool
    {
        return true;
    }

    public function doImport($rs)
    {
        $this->standardizeImportData($rs);
        $this->doHelperImport($rs);
    }

    public function doAfterImport($tmp_path, $filename, $note)
    {

    }

    protected function sample(): ?array
    {
        return null;
    }

    protected function config(): ?array
    {
        return null;
    }

    public function doBeforeImport($tmp_path, $filename, $note): bool
    {
        return true;
    }
}
