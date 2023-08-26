<?php
namespace Sannomiya\Form;

use Sannomiya\Util\LanguagesInterface;

class UploadException extends FormException
{

    public function __construct($code, LanguagesInterface $lang) {
        $message = self::codeToMessage($code, $lang);
        parent::__construct($message, $code);
    }

    public static function codeToMessage($code, ?LanguagesInterface $lang): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $fileSize = ini_get("upload_max_filesize");
                $message =  sprintf($lang->message("Uploaded file size exceeds %s"), $fileSize);
                // $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = $lang->message("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = $lang->message("The uploaded file was only partially uploaded");
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = $lang->message("No file was uploaded");
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = $lang->message("Missing a temporary folder");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = $lang->message("Failed to write file to disk");
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = $lang->message("File upload stopped by extension");
                break;

            default:
                $message = $lang->message("Unknown upload error");
                break;
        }
        return $message;
    }
}
