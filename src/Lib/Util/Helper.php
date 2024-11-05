<?php
namespace Minhnhc\Util;
use Cocur\Slugify\Slugify;
use DateTime;
use Exception;
use Minhnhc\Database\Database;
use Minhnhc\Form\Type;

class Helper
{
    const dateFormat = 'Y/m/d';
    static public function queryEncode($value, $type = Type::String, $null='null', $quote = "'") {
        if (!isset($value) || $value === '') {
            return $null;
        }

        switch ($type) {
            case Type::String:
            case Type::Text:
            case Type::Password:
                $value = str_replace("'", "''", $value);
                $value = $quote. str_replace("\\", "\\\\", $value) . $quote;
                break;
            case Type::Date:
            case Type::Time:
            case Type::DateTime:
                $value = $value;
                break;
            case Type::Boolean:
                if ($value==1 ||  strtolower($value)=='true') {
                    $value = 'true';
                }else{
                    $value = 'false';
                }
                break;
            default:
                // Check numeric
                if (!is_numeric($value)){
                    $value = $null;
                }
        }
        return $value;
    }

    static public function toStandardFormatDate($date): ?string
    {
        if (!isset($date)) {
            return null;
        }
        return str_replace('-', '/', $date);
    }

    public static function isValidFileExtension($file_name, $extension): bool
    {
        $parts = explode('.', $file_name);
        $ext = strtolower(end($parts));
        if ($ext==""){
            return false;
        }
        if (!str_contains($extension, $ext)){
            return false;
        }
        return true;
    }

    public static function getFileExtension($file_name): ?string
    {
        $parts = explode('.', $file_name);
        if (count($parts)<=1) {
            return null;
        }
        return strtolower(end($parts));
    }

    public static function getFileName($file_name): ?string
    {
        return preg_replace("/.+[\\\\\/]/", "", $file_name);
    }

    public static function resizeImage($file_source, $file_destination, $imageType, $w, $h, $crop = false) {
        list ( $width, $height ) = getimagesize ( $file_source );
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil ( $width - ($width * abs ( $r - $w / $h )) );
            } else {
                $height = ceil ( $height - ($height * abs ( $r - $w / $h )) );
            }
            $new_width = $w;
            $new_height = $h;
        } else {
            if ($w <= 0 ) {
                $new_width = $h * $r;
                $new_height = $h;
            }elseif ($h <= 0){
                $new_width = $w;
                $new_height = $w / $r;
            }else{
                if ($w / $h > $r) {
                    $new_width = $h * $r;
                    $new_height = $h;
                } else {
                    $new_height = $w / $r;
                    $new_width = $w;
                }
            }
        }
        if (strtolower ( $imageType ) == 'png') {
            $src = imagecreatefrompng ( $file_source );
        } else {
            $src = imagecreatefromjpeg ( $file_source );
        }

        $dst = imagecreatetruecolor ( $new_width, $new_height );
        imagecopyresampled ( $dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

        // Default destination is PNG
        $ext = self::getFileExtension($file_destination);
        if (!isset($ext) || strtolower($ext)=='png'){
            imagepng( $dst, $file_destination );
        }else{
            imagejpeg( $dst, $file_destination );
        }
        imagedestroy ( $src );
        imagedestroy ( $dst );
    }

    public static function debugBacktraceAsString($trace, $fromIndex = 0): string
    {
        // $description = sprintf("File: %s\nLine: %s\n%s", $file, $line, $description);
        $ret = "";
        for ($i=0;$i<count($trace);$i++) {
            $rec = $trace[$i];
            if ($i >=$fromIndex) {
                $ret .= sprintf("File: %s\nLine: %s\nFunction: %s\n", $rec['file'], $rec['line'], $rec['function']);
            }
        }
        $ret .= sprintf("%s", json_encode($trace[count($trace)-1]['args']));

        return $ret;
    }

    public static function errorBacktraceAsString($trace, $fromIndex = 0): string
    {
        $ret = "";
        for ($i=0;$i<count($trace);$i++) {
            $rec = $trace[$i];
            if ($i >=$fromIndex) {
                $ret .= sprintf("File: %s\nLine: %s\nFunction: %s\n", $rec['file'], $rec['line'], $rec['function']);
            }
        }
        $ret .= sprintf("\n%s", $trace[0]['description']);
        return $ret;
    }

    public static function errorBacktrace(Exception $e, $level = 3): array
    {
        $ret = [];
        $debug = $e->getTrace();
        $description = $e->getMessage();
        $count = count($debug);
        for ($i=0;$i<$count;$i++) {
            $rec = $debug[$i];
            $ret[] = [
                'file' => @$rec['file'],
                'line' => @$rec['line'],
                'function' => @$rec['function'],
                'description' => $description
            ];
            if ($i+1>$level) {
                break;
            }
        }
        return $ret;
    }

    public static function debugBacktrace($level = 3): array
    {
        $ret = [];
        $debug = debug_backtrace();
        $count = count($debug);
        for ($i=1;$i<$count;$i++) {
            $rec = $debug[$i];
            $ret[] = [
                'file' => @$rec['file'],
                'line' => @$rec['line'],
                'function' => @$rec['function'],
                'class' => @$rec['class'],
                'type' => @$rec['type'],
                'args' => @$rec['args'],
            ];

            if ($i+1>$level) {
                break;
            }
        }
        return $ret;
    }

    public static function toMap($rs, $keyField): array
    {
        $ret = [];
        foreach ($rs as $rec) {
            $ret[$rec[$keyField]] = $rec;
        }
        return $ret;
    }
    public static function toNumber($input, $default = -1) {
        if (!is_numeric($input)) {
            return $default;
        }
        return $input;
    }

    public static function getFirstDateOfWeek($year, $week): ?string {
        $timestamp = mktime( 0, 0, 0, 1, 1,  $year ) + ( $week * 7 * 24 * 60 * 60 );
        $timestamp_for_monday = $timestamp - 86400 * ( date( 'N', $timestamp ) - 1 );
        return date( self::dateFormat, $timestamp_for_monday );
    }

    public static function toUSDate($input = -1): ?string {
        if (! isset ( $input )) {
            return null;
        }else if ($input == -1){
            $tm = time ();
        } else {
            $tm = self::toTimestamp ( $input );
        }
        if (is_null ( $tm )) {
            return null;
        }
        return date ( 'd-M-y', $tm );
    }

    public static function toTimestamp($mixed, $def = null): ?int {
        if (! isset ( $mixed ))
            return $def;
        if (is_numeric ( $mixed )) {
            return $mixed;
        } elseif ($mixed == "") {
            return $def;
        } else {
            $mixed = strtotime ( $mixed );
            if ($mixed != - 1) {
                return $mixed;
            } else
                return $def;
        }
    }

    public static function toDate($data, $fromFormat=null, $toFormat=self::dateFormat): ?string
    {
        if (!isset($fromFormat)) {
            $t = strtotime($data);
            $dat = new DateTime();
            $dat->setTimestamp($t);
        }else{
            $dat = DateTimeJP::createFromFormat($fromFormat, $data);
        }
        if ($dat) {
            return $dat->format($toFormat);
        } else {
            return $data;
        }
    }

    public static function toExcelDate($date)
    {
        global $timezoneAdjust;
        if (!isset($timezoneAdjust)) {
            $timezoneAdjust = 7; 
        }
        if (!isset($date)) {
            return '';
        }

        if ($date == -2) {
            $t = time();
        }else{
            $t = Helper::toTimestamp($date);
        }
        if (isset ($t)) {
            //return 25569 + ceil ( $t / 86400 );
            //return 25569 + (($t / 86400)) + 7 / 24.0;
            // Set default time zone to VIETNAM so don need to adjust  + 7 / 24.0
            return 25569 + (($t / 86400)) + $timezoneAdjust / 24.0;
        } else {
            return '';
        }
    }

    public static function fromExcelDate($date)
    {
        global $timezoneAdjust;
        if (!isset($timezoneAdjust)) {
            $timezoneAdjust = 7;
        }
        if (!is_numeric($date)) {
            return null;
        }

        $t = ($date - 25569 -  $timezoneAdjust / 24.0) * 86400;
        return date('Y/m/d H:i:s', $t);
    }

    public static function numberFormat($num,$decimal = null): ?string
    {
        if (is_numeric($num)){
            return number_format($num, $decimal);
        }else{
            return $num;
        }
    }

    public static function dateDiff($interval, $date_start, $date_end, $def = null) {
        $date_start = self::toTimestamp($date_start);
        $date_end = self::toTimestamp($date_end);
        if(is_null($date_start) || is_null($date_end)) {
            return $def;
        }
        $ret = $def;

        if ($interval == 'y'){
            $ye = date('Y',$date_end);
            $ys = date('Y',$date_start);
            return $ye - $ys;
        }elseif ($interval=='m'){
            $ye = (int) date('Y',$date_end);
            $ys = (int) date('Y',$date_start);
            $me = (int) date('m',$date_end);
            $ms = (int) date('m',$date_start);
            return ($ye - $ys)*12 + $me -$ms;
        }

        // get the number of seconds between the two dates
        $timeDiff	= $date_end - $date_start;
        switch ($interval) {
            case 'w':
                $ret	= floor($timeDiff/604800);
                break;
            case 'd':
                $ret	= floor($timeDiff/86400);
                break;
            case 'h':
                $ret	=floor($timeDiff/3600);
                break;
            case 'n':
                $ret	= floor($timeDiff/60);
                break;
            case 's':
                $ret	= $timeDiff;
                break;
        }
        return $ret;
    }

    public static function dateAdd($interval, $number, $date, $def = null) {
        $date = self::toTimestamp ( $date );
        if (is_null ( $date )) {
            return $def;
        }

        $date_time_array = getdate ( $date );
        $hours = $date_time_array ['hours'];
        $minutes = $date_time_array ['minutes'];
        $seconds = $date_time_array ['seconds'];
        $month = $date_time_array ['mon'];
        $day = $date_time_array ['mday'];
        $year = $date_time_array ['year'];

        switch ($interval) {

            case 'y' :
                $year += $number;
                break;
            case 'q' :
                $year += ($number * 3);
                break;
            case 'm' :
                $month += $number;
                break;
            case 'd' :
                $day += $number;
                break;
            case 'w' :
                $day += ($number * 7);
                break;
            case 'h' :
                $hours += $number;
                break;
            case 'n' :
                $minutes += $number;
                break;
            case 's' :
                $seconds += $number;
                break;
        }
        return mktime ( $hours, $minutes, $seconds, $month, $day, $year );
    }

    public static function age($birthday, $def = null){
        $birthday = self::toTimestamp($birthday);

        if (is_null($birthday)) {
            return $def;
        }

        $m = self::dateDiff('m',$birthday, date(self::dateFormat));

        $y = floor($m/12);

        if ($m % 12 == 0){
            if (date('d',$birthday) > date('d')){
                $y--;
            }
        }

        return $y;
    }

    public static function getProcessIds($command="php.exe"): array {
        if (str_starts_with(php_uname(), "Windows")){
            $str = trim(`wmic process where name="$command" get ProcessID`);
            $str = str_replace("\r", "", $str);
            $rs = explode("\n", $str);
            $ret = [];
            foreach ($rs as $pid) {
                if (is_numeric($pid)) {
                    $ret[] = $pid;
                }
            }
            return $ret;
        }else{
            return [];
        }
    }

    public static function execBackground($cmd) {
        if (str_starts_with(php_uname(), "Windows")){
//            $cmd = "php --version";
//
//            $a = shell_exec($cmd);
//
//            $output=null;
//            $retval=null;
//             exec('php.exe --help 2>&1', $output, $retval);
//            //exec('echo %PATH% 2>&1', $output, $retval);
//            ob_start();
//            echo "Returned with status $retval and output:\n";
//            print_r($output);
//            $a = ob_get_clean();
            $before = self::getProcessIds();
            pclose(popen("start /B ". $cmd . ' > NUL', "r"));
            $after = self::getProcessIds();

            $pid = null;
            foreach ($after as $tmp) {
                if (!in_array($tmp, $before)) {
                    $pid = $tmp;
                    break;
                }
            }
            return $pid;
            // throw new BusinessLogicException("start /B ". $cmd . ' > NUL');
            // throw new BusinessLogicException($a);
        }
        else {
            exec($cmd . " > /dev/null &");
            return null;
        }
    }

    public static function executeAsyncShellCommand($cmd){
        if(!isset($cmd)){
            throw new Exception("No command given");
        }

        // If windows, else
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system($cmd." > NUL");
        }else{
            shell_exec("/usr/bin/nohup ".$cmd." >/dev/null 2>&1 &");
        }
    }

    public static function isAllKatakana($str): bool {
        return preg_match("/^[ァ-ヾ]+$/u",$str);
    }

    public static function isValidEmail($str): bool {
        return  filter_var($str, FILTER_VALIDATE_EMAIL);
    }


    public static function createFileParam(string $content): string {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
        $fileName = 'tmp_file_param_' . $d->format("Ymd_His.u");
        $folder =  __DIR__ . '/../../../tmp/';
        if (!is_dir($folder)) {
            mkdir($folder);
        }
        $filePath =  $folder . $fileName;
        file_put_contents($filePath, $content);
        return $fileName;
    }

    public static function getFileParam($name): string {
        $folder =  __DIR__ . '/../../../tmp/';
        if (!is_dir($folder)) {
            mkdir($folder);
        }
        $filePath =  $folder . $name;
        if (str_starts_with($name, 'tmp_file_param') && file_exists($filePath)) {
            $ret = file_get_contents($filePath);
            unlink($filePath);
            return $ret;
        }else{
            return $name;
        }
    }

    public static function outputCsv($buffer)
    {
        return str_replace("\n", "\r\n", $buffer);
    }

    public static function addParentRecord(Database $db, &$data, $table, $idField = 'id', $parentField = 'parent') {
        $ids = [];
        // Find parent
        foreach ($data as $rec){
            $ids[] = $rec[$idField];
        }
        $parents = [];
        foreach ($data as $rec) {
            if (isset($rec[$parentField]) && $rec[$parentField]!=0 && !in_array($rec[$parentField], $ids)) {
                $parent = Helper::getParentRecord($db, $rec[$parentField], $table, $idField, $parentField);
                foreach ($parent as $item) {
                    $ids[] = $item[$idField];
                }
                $parents = array_merge($parents, $parent);
            }
        }
        $data = array_merge($parents, $data);
    }

    private static function getParentRecord(Database $db, $parent, $table, $idField, $parentField): array {
        $query = "select * from $table where $idField=$parent";
        $rec = $db->getRecord($query);
        if (!isset($rec)) {
            return [];
        }
        $ret = [$rec];
        if (isset($rec[$parentField]) && $rec[$parentField] != 0){
            $ret = array_merge($ret, self::getParentRecord($db, $rec[$parentField], $table, $idField, $parentField));
        }
        return $ret;
    }

    public static function createTempIDSTable($tableName, array $ids) {
        $db = Database::getInstance();
        $db->execute("CREATE TEMPORARY TABLE $tableName (id int);");
        $values = [];
        foreach ($ids as $id) {
            $values[] = "($id)";
            if (count($values) > 500 ) {
                $valuesString = implode(",", $values);
                $db->execute("insert into $tableName (id) values $valuesString");
                $values = [];
            }
        }
        if (count($values) > 0 ) {
            $valuesString = implode(", ", $values);
            $db->execute("insert into $tableName (id) values $valuesString");
        }
    }

    /**
     * Get yyyy/mm/dd by date format
     * @param $format
     * @param $date
     * @return string|null
     */
    public static function parseDate($format, $date): ?string
    {
        $dt = \DateTime::createFromFormat($format, $date);
        if ($dt==false) {
            return null;
        }
        return $dt->format("Y/m/d");
    }

    /**
     * @param $text
     * @return null|string|string[]
     */
    public static function slugify($text)
    {
        if (empty($text)) {
            return 'n-a';
        }
        $slugify = new Slugify();
        return $slugify->slugify($text);
    }

    public static function capitalizeJson($content) {
        if (is_object($content) || is_array($content)) {
            $content = json_encode($content);
        }
        return preg_replace_callback('/\"([a-zA-Z][a-zA-Z_0-9]*)\"\s*:/',  function($m) {
            $string = ucfirst($m[1]);
            return "\"$string\":";
        }, $content);
    }

    /**
     * Convert $a->$b->array to $a->array
     * @param $json
     * @param null $pattern
     * @param null $parentKey
     * @param false $parentObject
     * @return array|mixed|object|null
     */
    public static function reduceObject($json, $pattern = null, $parentKey = null, bool $parentObject = false) {
        $object = false;
        if (is_object($json)) {
            $json = (array) $json;
            $object = true;
        }
        if (is_array($json)) {
            if (count($json) == 1 && $object){
                $key = array_key_first($json);
                $value = $json[$key];
                if (isset($pattern)) {
                    if (preg_match($pattern, $key, $m) > 0) {
                        return self::reduceObject($value, $pattern, $parentKey . $key);
                    }
                }elseif (is_array($value) && $parentObject){
                    return self::reduceObject($value, $pattern, $parentKey .$key);
                }
            }
            $ret = [];
            foreach ($json as $key => $value) {
                $ret[$key] = self::reduceObject($value, $pattern, $parentKey . ($object ? $key: null), $object);
            }
            if ($object) {
                return (object) $ret;
            }else{
                return $ret;
            }
        } else {
            if (is_string($json) && $json == "string") {
                $json = $parentKey;
            }
            return  $json;
        }
    }

    /**
     * Convert $date1, $date2 to date and compare.
     * 0: date1=date2
     * 1: date1>date2
     * -1: date1<date2
     * @param String|null $date1
     * @param String|null $date2
     */
    public static function compareDate(?String $date1, ?String $date2, $ignoreTime = false): int
    {
        if ($ignoreTime) {
            $dateFormat = "Y/m/d";
        }else{
            $dateFormat = "Y/m/d H:i:s";
        }

        $d1 = Helper::toDate($date1, null, $dateFormat);
        $d2 = Helper::toDate($date2, null, $dateFormat);

        return strcmp($d1, $d2);
    }

    public static function createTmpTableFromRecordSet($table, $types, $rs, $db=null){
        if ($db == null) {
            $db = Database::getInstance();
        }

        // Drop
        $db->execute("DROP TEMPORARY TABLE IF EXISTS $table;"); //TEMPORARY
        $fields = [];
        $values = [];
        foreach ($types as $field=>$type) {
            $fields[] = "$field $type";
            $values[] = "?";
        }
        $tmp = implode(", ", $fields);
        $createQuery = "CREATE TEMPORARY TABLE $table ($tmp)"; //TEMPORARY
        $db->execute($createQuery);

        if (isset($rs) && count($rs) > 0) {
            $tmp = implode(", ", array_keys($types));
            $values = implode(", ", $values);

            $insertQuery = "insert into $table ($tmp) values ($values)";
            foreach ($rs as $rec) {
                $param = [];
                foreach (array_keys($types) as $field) {
                    $param[] = $rec[$field] ?? null;
                }
                $db->execute($insertQuery, $param);
            }
        }
    }
}
