<?php

namespace Minhnhc\Util;

use Exception;

class DateTimeJP extends \DateTime
{
    // フォーマット定義追加
    const JP_DATE = 'JK年n月j日'; // 例:平成元年5月7日（明治5年以前は当時と異なる日付が出るので注意）
    const JP_TIME = 'Eg時i分s秒'; // 例:午後3時05分07秒

    const DEFAULT_TO_STRING_FORMAT = 'Y-m-d H:i:s'; // toString()で利用する表示フォーマット

    /**
     * 元号用設定
     * 日付はウィキペディアを参照しました
     *
     * @see http://ja.wikipedia.org/wiki/%E5%85%83%E5%8F%B7%E4%B8%80%E8%A6%A7_%28%E6%97%A5%E6%9C%AC%29 元号一覧 (日本)
     */
    private static array $gengoList = [
        ['name' => '令和', 'name_short' => 'R', 'timestamp' =>  1556636400],  // 2019-05-01,
        ['name' => '平成', 'name_short' => 'H', 'timestamp' =>  600188400],  // 1989-01-08,
        ['name' => '昭和', 'name_short' => 'S', 'timestamp' => -1357635600], // 1926-12-25'
        ['name' => '大正', 'name_short' => 'T', 'timestamp' => -1812186000], // 1912-07-30
        ['name' => '明治', 'name_short' => 'M', 'timestamp' => -3216790800], // 1868-01-25
    ];

    /** 日本語曜日設定 */
    private static array $weekJp = [
        0 => '日',
        1 => '月',
        2 => '火',
        3 => '水',
        4 => '木',
        5 => '金',
        6 => '土',
    ];
    /** 午前午後 */
    private static array $ampm = [
        'am' => '午前',
        'pm' => '午後',
    ];

    /**
     * 文字列に変換された際に返却するもの
     *
     * @return string
     */
    public function __toString()
    {
        return $this->format(self::DEFAULT_TO_STRING_FORMAT);
    }

    /**
     * 和暦などを追加したformatメソッド
     *
     * 追加した記号
     * J : 元号
     * b : 元号略称
     * K : 和暦年(1年を元年と表記)
     * k : 和暦年
     * x : 日本語曜日(0:日-6:土)
     * E : 午前午後
     *
     * @param string $format DateTime::formatに準ずるformat文字列
     * @return string
     */
    public function format($format): string
    {
        // 和暦関連のオプションがある場合は和暦取得
        $gengo = array();
        if (preg_match('/(?<!\\\)[J|b|K|k]/', $format)) {
            foreach (self::$gengoList as $g) {
                if ($g['timestamp'] <= $this->getTimestamp()) {
                    $gengo = $g;
                    break;
                }
            }
            // 元号が取得できない場合はException
            if (empty($gengo)) {
                throw new Exception('Can not be converted to a timestamp : '.$this->getTimestamp());
            }
        }

        // J : 元号
        if ($this->isCharactor('J', $format)) {
            $format = $this->replaceCharactor('J', $gengo['name'], $format);
        }

        // b : 元号略称
        if ($this->isCharactor('b', $format)) {
            $format = preg_replace('/b/', '¥¥' . $gengo['name_short'], $format);
        }

        // K : 和暦用年(元年表示)
        if ($this->isCharactor('K', $format)) {
            $year = date('Y', $this->getTimestamp()) - date('Y', $gengo['timestamp']) + 1;
            $year = $year == 1 ? '元' : $year;
            $format = $this->replaceCharactor('K', $year, $format);
        }

        // k : 和暦用年
        if ($this->isCharactor('k', $format)) {
            $year = date('Y', $this->getTimestamp()) - date('Y', $gengo['timestamp']) + 1;
            $format = $this->replaceCharactor('k', $year, $format);
        }

        // x : 日本語曜日
        if ($this->isCharactor('x', $format)) {
            $w = date('w', $this->getTimestamp());
            $format = $this->replaceCharactor('x', self::$weekJp[$w], $format);
        }

        // 午前午後
        if ($this->isCharactor('E', $format)) {
            $a = date('a', $this->getTimestamp());
            $value = isset(self::$ampm[$a]) ? self::$ampm[$a] : '';
            $format = $this->replaceCharactor('E', $value, $format);
        }

        return parent::format($format);
    }

    /**
     * 指定した文字があるかどうか調べる（エスケープされているものは除外）
     * @param string $char 検索する文字
     * @param string $string 検索される文字列
     * @return boolean
     */
    private function isCharactor($char, $string): bool
    {
        return preg_match('/(?<!\\\)'.$char.'/', $string);
    }

    /**
     * 指定した文字を置換する(エスケープされていないもののみ)
     * @param string $char 置換される文字
     * @param string $replace 置換する文字列
     * @param string $string 元の文字列
     * @return string 置換した文字列
     */
    private function replaceCharactor($char, $replace, $string): string
    {
        // エスケープされていないもののみ置換
        $string = preg_replace('/(?<!\\\)'.$char.'/', '${1}'.$replace, $string);
        // エスケープ文字を削除
        $string = preg_replace('/\\\\'.$char.'/', $char, $string);
        return $string;
    }
}
