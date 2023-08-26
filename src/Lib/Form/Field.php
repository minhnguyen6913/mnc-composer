<?php


namespace Sannomiya\Form;


class Field
{
    public $type = Type::String;
    public $name = null;
    public $editable = false;
    public $searchable = false;
    public $sortable = false;
    public $caption = null;
    public $show = false;
    public $excel = false;
    public $update = false;
    public $insert = false;
    public $required = false;
    public $requiredGroup = null;
    public $key = false;
    public $decimal = null;
    public $choices = false;
    public $simpleChoices = true;
    public $autocomplete = false;
    public $autocompleteChoices = false;
    public $tree = false;
    public $defaultValue = null;
    public $defaultValueNotInsert = false;
    public $hidden = false;
    public $listValues = null;
    public $listName = null;
    public $listLong = false;
    public $listStringValue = false;
    public $listParam = null;
    public $listFixParam = null;
    public $listLinkedParam = [];
    public $summary = false;
    public $width = null;
    public $dropdownWidth = null;
    public $groupName = null;
    public $locked = false;
    public $editMode = false;
    public $height = null;
    public ?bool $searchOnly = null;
    public ?int $colSpan = 1;
    public ?int $groupCols = null;
    public bool $lineBreak = false;
    public ?string $listTextField = null;

    public ?bool $multiSelectRadioMode = null;
    public ?bool $multiSelectCheckMode = null;
    public ?bool $multiSelectShowAll = null;
    public ?int $multiSelectCols = null;
    public ?int $multiSelectMaxHeight = null;
    public ?string $multiSelectLabelPosition = null;

    private $databaseField = null;

    public $image = false;
    public $imageParams = null;
    public $thumbnailWidthSize = null;


    public ?float $min = null;
    public ?float $max = null;

    public ?int $lengthMin = null;
    public ?int $lengthMax = null;

    protected $choices_autocomplete_function = null;
    protected $choices_query_data = null;
    protected $choices_query_insert = null;
    protected $choices_query_delete = null;
    protected $choices_query_search = null;
    protected $autocomplete_data = null;
    protected $autocomplete_insert = null;
    protected $autocomplete_search = null;
    private $save_file_folder = null;
    private $save_file_name = true;
    private $allow_file_extension = null;
    private bool $save_file_folder_by_month = false;

    public function __construct($name, $type=Type::String)
    {
        $this->name = $name;
        $this->caption = $name; // Default caption = name
        $this->databaseField = $name;
        $this->type = $type;
    }

    public function getFilePath($id, $fileName = null): ?string
    {
        if (!isset($this->save_file_folder)) {
            return null;
        }
        $month = '';
        if (isset($fileName)) {
            $i = strpos($fileName,'/');
            if ($i > 0) {
                $month = substr($fileName, 0, $i) . DIRECTORY_SEPARATOR;
            }
        }
        return $this->getSaveFileFolder() . DIRECTORY_SEPARATOR . "{$month}{$id}_{$this->name}";
    }

    public function getThumbnailPath($id, $fileName = null): ?string
    {
        if (!isset($this->save_file_folder)) {
            return null;
        }
        $month = '';
        if (isset($fileName)) {
            $i = strpos($fileName,'/');
            if ($i > 0) {
                $month = substr($fileName, 0, $i) . DIRECTORY_SEPARATOR;
            }
        }
        return $this->getSaveFileFolder() . DIRECTORY_SEPARATOR . "{$month}{$id}_thumb_{$this->name}";
    }

    /**
     * @return null
     */
    public function getChoicesQueryData()
    {
        return $this->choices_query_data;
    }

    /**
     * @param null $choices_query_data
     */
    public function setChoicesQueryData($choices_query_data): void
    {
        $this->choices_query_data = $choices_query_data;
    }

    /**
     * @return null
     */
    public function getChoicesQueryInsert()
    {
        return $this->choices_query_insert;
    }

    /**
     * @param null $choices_query_insert
     */
    public function setChoicesQueryInsert($choices_query_insert): void
    {
        $this->choices_query_insert = $choices_query_insert;
    }

    /**
     * @return null
     */
    public function getChoicesQueryDelete()
    {
        return $this->choices_query_delete;
    }

    /**
     * @param null $choices_query_delete
     */
    public function setChoicesQueryDelete($choices_query_delete): void
    {
        $this->choices_query_delete = $choices_query_delete;
    }

    /**
     * @return null
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param null $defaultValue
     */
    public function setDefaultValue($defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    private ?ListChoicesInterface $choices_interface = null;

    public function getChoicesInterface(): ?ListChoicesInterface
    {
        return $this->choices_interface;
    }

    public function setChoicesInterface(?ListChoicesInterface $interface): void
    {
        $this->choices_interface = $interface;
    }

    private ?ListAutocompleteInterface $autocomplete_interface = null;
    public function getAutocompleteInterface(): ?ListAutocompleteInterface
    {
        return $this->autocomplete_interface;
    }
    public function setAutocompleteInterface(?ListAutocompleteInterface $interface): void
    {
        $this->autocomplete_interface = $interface;
    }

    /**
     * @return null
     */
    public function getChoicesQuerySearch()
    {
        return $this->choices_query_search;
    }

    /**
     * @param null $choices_query_search
     */
    public function setChoicesQuerySearch($choices_query_search): void
    {
        $this->choices_query_search = $choices_query_search;
    }

    /**
     * @return null
     */
    public function getSaveFileFolder()
    {
        return $this->save_file_folder;
    }

    /**
     * @param null $save_file_folder
     */
    public function setSaveFileFolder($save_file_folder): void
    {
        $this->save_file_folder = $save_file_folder;
    }

    /**
     * @return null
     */
    public function getAllowFileExtension()
    {
        return $this->allow_file_extension;
    }

    /**
     * @param null $allow_file_extension
     */
    public function setAllowFileExtension($allow_file_extension): void
    {
        if (isset($allow_file_extension) && !is_array($allow_file_extension)){
            $allow_file_extension = explode(",", $allow_file_extension);
        }

        $ret = null;
        if (is_array($allow_file_extension)){
            $ret = [];
            foreach ($allow_file_extension as $ext){
                $ret[] = strtolower($ext);
            }
        }
        $this->allow_file_extension =  $ret;
    }

    /**
     * @return null
     */
    public function getDatabaseField()
    {
        return $this->databaseField;
    }

    /**
     * @param null $databaseField
     */
    public function setDatabaseField($databaseField): void
    {
        $this->databaseField = $databaseField;
    }

    /**
     * @return null
     */
    public function getAutocompleteData()
    {
        return $this->autocomplete_data;
    }

    /**
     * @param null $autocomplete_data
     */
    public function setAutocompleteData($autocomplete_data): void
    {
        $this->autocomplete_data = $autocomplete_data;
    }

    /**
     * @return null
     */
    public function getAutocompleteInsert()
    {
        return $this->autocomplete_insert;
    }

    /**
     * @param null $autocomplete_insert
     */
    public function setAutocompleteInsert($autocomplete_insert): void
    {
        $this->autocomplete_insert = $autocomplete_insert;
    }

    /**
     * @return null
     */
    public function getAutocompleteSearch()
    {
        return $this->autocomplete_search;
    }

    /**
     * @param null $autocomplete_search
     */
    public function setAutocompleteSearch($autocomplete_search): void
    {
        $this->autocomplete_search = $autocomplete_search;
    }

    /**
     * @return bool
     */
    public function isSaveFileName(): bool
    {
        return $this->save_file_name;
    }

    /**
     * @param bool $save_file_name
     */
    public function setSaveFileName(bool $save_file_name): void
    {
        $this->save_file_name = $save_file_name;
    }

    /**
     * @return null
     */
    public function getChoicesAutocompleteFunction()
    {
        return $this->choices_autocomplete_function;
    }

    /**
     * @param null $choices_autocomplete_function
     */
    public function setChoicesAutocompleteFunction($choices_autocomplete_function): void
    {
        $this->choices_autocomplete_function = $choices_autocomplete_function;
    }

    /**
     * @return bool
     */
    public function isSaveFileFolderByMonth(): bool
    {
        return $this->save_file_folder_by_month;
    }

    /**
     * @param bool $save_file_folder_by_month
     */
    public function setSaveFileFolderByMonth(bool $save_file_folder_by_month): void
    {
        $this->save_file_folder_by_month = $save_file_folder_by_month;
    }

}
