<?php
/** pahooCalendar.php
 * 暦計算に関わるクラス
 *
 * @copyright	(c)studio pahoo
 * @author		パパぱふぅ
 * @動作環境	PHP 4/5/7/8
 * @参考URL		https://www.pahoo.org/e-soul/webtech/php02/php02-44-01.shtm
*/

// pahooCalendarクラス ======================================================
class pahooCalendar {
	var $CONVERGE = 0.00005;			//逐次近似計算収束判定値
	var $ASTRO_REFRACT = 0.585556;		//大気差
	var $TDIFF = 9.0;			//日本標準時（世界時との時差）【変更不可】
	var $error, $errmsg;		//エラーフラグ，エラーメッセージ
	var $year, $month, $day;	//西暦年月日
	var $tblmoon;				//グレゴリオ暦＝旧暦テーブル
	var $language;				//表示言語（jp：日本語, en：英語，en3：英語略記）

/**
 * コンストラクタ
 * @param	なし
 * @return	なし
*/
function __construct() {
	$this->error  = FALSE;
	$this->errmsg = '';
	$this->year  = date('Y');
	$this->month = date('n');
	$this->day   = date('j');
	$this->language = 'jp';
}

/**
 * デストラクタ
 * @return	なし
*/
function __destruct() {
	unset($this->items);
}

// 基本 =====================================================================
/**
 * 表示言語を設定する
 * @param	string $language 表示言語（jp：日本語, en：英語，en3：英語略記）
 * @return	なし
*/
function setLanguage($lang) {
	$this->language = $lang;
}

/**
 * 角度の正規化（$angle を 0≦$angle＜360 にする）
 * @param	double $angle 角度
 * @return	double 角度（正規化後）
*/
function __angle($angle) {
	if ($angle < 0) {
		$angle1  = $angle * (-1);
		$angle2  = floor($angle1 / 360.0);
		$angle1 -= 360 * $angle2;
		$angle1  = 360 - $angle1;
	} else {
		$angle1  = floor($angle / 360.0);
		$angle1  = $angle - 360.0 * $angle1;
	}
	return $angle1;
}

/**
 * 角度（度）→時分
 * @param	double $deg 角度
 * @return	string 時分
*/
function deg2hhmm($deg) {
	$sign = ($deg < 0) ? '-' : '';
	$deg = abs($deg);
	$hh1 = $deg / 15;
	$hh2 = floor($hh1);
	$mm1 = ($hh1 - $hh2) * 15;
	$mm2 = $mm1 / 360 * 24 * 60;
	$mm3 = floor($mm2);
	$mm4 = round(($mm2 - $mm3) * 10);

	return sprintf('%s%02dh%02dm.%1d', $sign, $hh2, $mm3, $mm4);
}

/**
 * 角度（度）→度分
 * @param	double $deg 角度
 * @return	string 度分
*/
function deg2ddmm($deg) {
	$sign = ($deg < 0) ? '-' : '';
	$dd1 = abs($deg);
	$dd2 = floor($dd1);
	$mm1 = $dd1 - $dd2;
	$mm2 = $mm1 * 60;
	$mm3 = floor($mm2);
	$mm4 = round(($mm2 - $mm3) * 10);

	return sprintf('%s%02d°%02d’%1d', $sign, $dd2, $mm3, $mm4);
}

/**
 * 日（小数点以下）→時分
 * @param	double $dd 日
 * @return	string 時分
*/
function day2hhmm($dd) {
	$sign = ($dd < 0) ? '-' : '';
	$hh1 = abs($dd) * 24;
	$hh2 = floor($hh1);
	$mm1 = $hh1 - $hh2;
	$mm2 = floor($hh1);
	$mm3 = round(($hh1 - $hh2) * 60);
	if ($mm3 == 60) {			//v.2.82 bug-fix
		$mm3 = 0;
		$hh2++;
	}

	return sprintf('%02d:%02d', $hh2, $mm3);
}

/**
 * 閏年かどうか判定する
 * @param	int $year 西暦年
 * @return	bool TRUE:閏年である／FALSE:平年である
*/
function isleap($year) {
	$ret = FALSE;
	if ($year % 4 == 0)	$ret = TRUE;
	if ($year % 100 == 0)	$ret = FALSE;
	if ($year % 400 == 0)	$ret = TRUE;
	return $ret;
}

/**
 * 指定した月の日数を返す
 * @param	int $year  西暦年
 * @param	int $month 月
 * @return	int 日数／FALSE:引数の異常
*/
function getDaysInMonth($year, $month) {
	static $days = array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	if ($month < 1 || $month > 12)	return FALSE;
	$days[2] = $this->isleap($year) ? 29 : 28;		//閏年の判定

	return $days[$month];
}

/**
 * 曜日番号を求める（ツェラーの公式）
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	int 曜日番号（0:日曜日, 1:月曜日...6:土曜日）
*/
function getWeekNumber($year, $month, $day) {
	if ($month <= 2) {
		$month += 12;
		$year--;
	}
	$c = floor($year / 100);
	$y = $year % 100;
	$t = -2 * $c + floor($c / 4);
	$h = $day + floor(26 * ($month + 1) / 10) + $y + floor($y / 4) + $t - 1;
	while ($h < 0)	$h += 7;	//Ver.3.1 bug-fix
	$h = $h % 7;

	return $h;
}

/**
 * 曜日（文字列）を求める
 * @param	int $wn 曜日番号（0:日曜日, 1:月曜日...6:土曜日）
 * @return	string 曜日（文字列）
*/
function __getWeekString($wn) {
	static $table = array(
'en' => array(
0 => 'Sunday',
1 => 'Monday',
2 => 'Tuesday',
3 => 'Wednesday',
4 => 'Thursday',
5 => 'Friday',
6 => 'Saturday'
), 
'en3' => array(
0 => 'Sun',
1 => 'Mon',
2 => 'Tue',
3 => 'Wed',
4 => 'Thu',
5 => 'Fri',
6 => 'Sat'
), 
'jp' => array(
0 => '日',
1 => '月',
2 => '火',
3 => '水',
4 => '木',
5 => '金',
6 => '土'
)
);
	//エラーチェック
	if ($wn < 0 || $wn > 7)	return FALSE;

	$lng = 'jp';	//デフォルトは日本語
	foreach ($table as $key=>$arr) {
		if (preg_match('/^' . $key . '$/i', $this->language) > 0) {	//v.3.22
			$lng = $key;
			break;
		}
	}

	return $table[$lng][$wn];
}

/**
 * 曜日（文字列）を求める
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 曜日（文字列）
*/
function getWeekString($year, $month, $day) {
	$wn = $this->getWeekNumber($year, $month, $day);		//曜日番号

	return $this->__getWeekString($wn);
}

/**
 * グレゴリオ暦→ユリウス日 変換
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double ユリウス日
*/
function Gregorian2JD($year, $month, $day, $hour, $min, $sec) {
	if ($month <= 2) {
		$month += 12;
		$year--;
	}

	$jd = floor(365.25 * $year) - floor($year / 100) + floor($year / 400);
	$jd += floor(30.59 * ($month - 2)) + $day + 1721088;
	$jd += $hour / 24.0 + $min / (24.0 * 60.0) + $sec / (24.0 * 60.0 * 60.0);

	return $jd;
}

/**
 * ユリウス暦→ユリウス日 変換
 * @param	int $year, $month, $day  ユリウス暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double ユリウス日
*/
function Julian2JD($year, $month, $day, $hour, $min, $sec) {
	if ($month <= 2) {
		$month += 12;
		$year--;
	}

	$jd = floor(365.25 * $year);
	$jd += floor(30.59 * ($month - 2)) + $day + 1721086;
	$jd += $hour / 24.0 + $min / (24.0 * 60.0) + $sec / (24.0 * 60.0 * 60.0);

	return $jd;
}

/**
 * 西暦→ユリウス日 変換
 * @param	int $year, $month, $day  西暦による年月日
 *				1581年以前はユリウス暦，1582年以降はグレゴリオ暦として計算
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double ユリウス日
*/
function AD2JD($year, $month, $day, $hour, $min, $sec) {
	if ($year <= 1581) {
		$jd = $this->Julian2JD($year, $month, $day, $hour, $min, $sec);
	} else {
		$jd = $this->Gregorian2JD($year, $month, $day, $hour, $min, $sec);
	}
	return $jd;
}

/**
 * ユリウス日⇒グレゴリオ暦　変換
 * @param	double $jd ユリウス日
 * @return	array($year, $month, $day, $hour, $min, $sec)  西暦年月日，世界時
*/
function JD2Gregorian($jd) {
	$x0 = floor($jd + 68570);
	$x1 = floor($x0 / 36524.25);
	$x2 = $x0 - floor(36524.25 * $x1 + 0.75);
	$x3 = floor(($x2 + 1) / 365.2425);
	$x4 = $x2 - floor(365.25 * $x3) + 31;
	$x5 = floor(floor($x4) / 30.59);
	$x6 = floor(floor($x5) / 11.0);

	$day   = $x4 - floor(30.59 * $x5);
	$month = $x5 - 12 * $x6 + 2;
	$year  = 100 * ($x1 - 49) + $x3 + $x6;

	if ($month == 2 && $day > 28) {
		$day = $this->isleap($year) ? 29 : 28;
	}

	$tm = 86400 * ($jd - floor($jd));
	$hour = floor($tm / 3600.0);
	$min  = floor(($tm - 3600 * $hour) / 60.0);
	$sec  = floor($tm - 3600 * $hour - 60 * $min);

	return array($year, $month, $day, $hour, $min, $sec);
}


/**
 * 2000年1月1日力学時正午からの経過日数
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	double 経過日数（日本標準時）
*/
function J2000($year, $month, $day) {
	$year -= 2000;
	if ($month <= 2) {
		$month += 12;
		$year--;
	}

	$j2000 = 365.0 * $year + 30.0 * $month + $day - 33.5 - $this->TDIFF / 24.0;
	$j2000 += floor(3.0 * ($month + 1) / 5.0);
	$j2000 += floor($year / 4.0);

	return $j2000;
}

/**
 * 2000.0からの経過年数
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 2000.0からの経過年数
*/
function Gregorian2JY($year, $month, $day, $hour, $min, $sec) {
	$t  = $hour * 60.0 * 60.0;
	$t += $min * 60.0;
	$t += $sec;
	$t /= 86400.0;

	//地球自転遅れ補正値(日)計算
	$rotate_rev = (57.0 + 0.8 * ($year - 1990)) / 86400.0;

	//2000年1月1日力学時正午からの経過日数(日)計算
	$day_progress = $this->J2000($year, $month, $day);

	//経過ユリウス年(日)計算
	//( 2000.0(2000年1月1日力学時正午)からの経過年数 (年) )
	return ($day_progress + $t + $rotate_rev) / 365.25;
}

/**
 * 恒星時（度）
 * @param	double $jy 2000.0からの経過年数
 * @param	double $t  時刻
 * @param	double $longitude 観測地点の経度（東経は正数）
 * @return	double 恒星時（度）
*/
function __sidereal($jy, $t, $longitude) {
	$val  = 325.4606;
	$val += 360.007700536 * $jy;
	$val += 0.00000003879 * $jy * $jy;
	$val += 360.0 * $t;
	$val += $longitude;

	return $this->__angle($val);
}

// 座標変換 ===============================================================
/**
 * 黄道→赤道変換
 * @param	double $ramda, $beta 黄経，黄緯
 * @param	double $jy 2000.0からの経過年数
 * @return	double (赤経,赤緯)
*/
function __eclip2equat($rambda, $beta, $jy) {
	$e  = deg2rad(23.439291 - 0.000130042 * $jy);		//黄道傾角
	$rambda = deg2rad($rambda);
	$beta   = deg2rad($beta);

	$a  =      cos($beta) * cos($rambda);
	$b  = -1 * sin($beta) * sin($e);
	$b +=      cos($beta) * sin($rambda) * cos($e);
	$c  =      sin($beta) * cos($e);
	$c +=      cos($beta) * sin($rambda) * sin($e);

	$alpha  = $b / $a;
	$alpha  = rad2deg(atan($alpha));
	if ($a < 0)		$alpha += 180;
	if ($alpha < 0)	$alpha += 360;

	$delta   = rad2deg(asin($c));

	return array($alpha, $delta);
}

/**
 * 黄道→赤道変換
 * @param	double $ramda, $beta 黄経，黄緯
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	double (赤経,赤緯)
*/
function eclip2equat($rambda, $beta, $year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__eclip2equat($rambda, $beta, $jy);
}

/**
 * 天体の時角の差分
 * @param	int $mode 0=出, 1=没, 2=南中
 * @param	double $alpha, $delta 天体の赤経，赤緯
 * @param	double $latitude 観測値の緯度（東経は正数）
 * @param	double $height 観測地点の出没高度（度）
 * @param	double $sidereal 恒星時
 * @return	double 時角の差
*/
function hour_ang_dif($mode, $alpha, $delta, $latitude, $height, $sidereal) {
	//南中の場合は天体の時角を返す
	if ($mode == 2) {
		$tk = 0;
	} else {
		$tk  = sin(deg2rad($height));
		$tk -= sin(deg2rad($delta)) * sin(deg2rad($latitude));
		$tk /= cos(deg2rad($delta)) * cos(deg2rad($latitude));
		//出没点の時角
		$tk  = rad2deg(acos($tk));
		//$tkは出のときマイナス、入のときプラス
		if ($mode == 0 && $tk > 0)	$tk = -$tk;
		if ($mode == 1 && $tk < 0)	$tk = -$tk;
	}
	//天体の時角
	$t = $sidereal - $alpha;
	$dt = $tk - $t;
	//$dtの絶対値を180°以下に調整
	if ($dt > 180) {
		while ($dt > 180) {
			$dt -= 360;
		}
	}
	if ($dt < -180) {
		while ($dt < -180) {
			$dt += 360;
		}
	}

	return $dt;
}

// 太陽の位置計算 =========================================================
/**
 * 太陽の黄経計算（視黄経）
 * @param	double $jy 2000.0からの経過年数
 * @return	double 太陽の黄経（視黄経）
*/
function __longitude_sun($jy) {
	$th  = 0.0003 * sin(deg2rad($this->__angle(329.7  +   44.43  * $jy)));
	$th += 0.0003 * sin(deg2rad($this->__angle(352.5  + 1079.97  * $jy)));
	$th += 0.0004 * sin(deg2rad($this->__angle( 21.1  +  720.02  * $jy)));
	$th += 0.0004 * sin(deg2rad($this->__angle(157.3  +  299.30  * $jy)));
	$th += 0.0004 * sin(deg2rad($this->__angle(234.9  +  315.56  * $jy)));
	$th += 0.0005 * sin(deg2rad($this->__angle(291.2  +   22.81  * $jy)));
	$th += 0.0005 * sin(deg2rad($this->__angle(207.4  +    1.50  * $jy)));
	$th += 0.0006 * sin(deg2rad($this->__angle( 29.8  +  337.18  * $jy)));
	$th += 0.0007 * sin(deg2rad($this->__angle(206.8  +   30.35  * $jy)));
	$th += 0.0007 * sin(deg2rad($this->__angle(153.3  +   90.38  * $jy)));
	$th += 0.0008 * sin(deg2rad($this->__angle(132.5  +  659.29  * $jy)));
	$th += 0.0013 * sin(deg2rad($this->__angle( 81.4  +  225.18  * $jy)));
	$th += 0.0015 * sin(deg2rad($this->__angle(343.2  +  450.37  * $jy)));
	$th += 0.0018 * sin(deg2rad($this->__angle(251.3  +    0.20  * $jy)));
	$th += 0.0018 * sin(deg2rad($this->__angle(297.8  + 4452.67  * $jy)));
	$th += 0.0020 * sin(deg2rad($this->__angle(247.1  +  329.64  * $jy)));
	$th += 0.0048 * sin(deg2rad($this->__angle(234.95 +   19.341 * $jy)));
	$th += 0.0200 * sin(deg2rad($this->__angle(355.05 +  719.981 * $jy)));
	$th += (1.9146 - 0.00005 * $jy) * sin(deg2rad($this->__angle(357.538 + 359.991 * $jy)));
	$th += $this->__angle(280.4603 + 360.00769 * $jy);

	return $this->__angle($th);
}

/**
 * 太陽の黄経計算（視黄経）
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 太陽の黄経（視黄経）
*/
function longitude_sun($year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__longitude_sun($jy);
}

/**
 * 太陽の距離計算
 * @param	double $jy 2000.0からの経過年数
 * @return	double 太陽の黄経（視黄経）
*/
function __distance_sun($jy) {
	$r_sun  = 0.000007 * sin(deg2rad($this->__angle(156.0 +  329.60 * $jy)));
	$r_sun += 0.000007 * sin(deg2rad($this->__angle(254.0 +  450.40 * $jy)));
	$r_sun += 0.000013 * sin(deg2rad($this->__angle( 27.8 + 4452.67 * $jy)));
	$r_sun += 0.000030 * sin(deg2rad($this->__angle( 90.0                )));
	$r_sun += 0.000091 * sin(deg2rad($this->__angle(265.1 +  719.98 * $jy)));
	$r_sun += (0.007256 - 0.0000002 * $jy) * sin(deg2rad($this->__angle(267.54 + 359.991 * $jy)));

	return pow(10, $r_sun);
}

/**
 * 太陽の距離計算
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 太陽の距離
*/
function distance_sun($year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__distance_sun($jy);
}

/**
 * 日出／日没／南中
 * @param	int $mode 0=出, 1=没, 2=南中
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $longitude 観測値の経度（東経は正数）
 * @param	double $latitude  観測値の緯度
 * @param	double $height 観測値の高さ
 * @return	double 太陽の距離
*/
function sun_time($mode, $longitude, $latitude, $height, $year, $month, $day) {
	//地球自転遅れ補正値(日)計算
	$rotate_rev = (57.0 + 0.8 * ($year - 1990)) / 86400.0;

	//2000年1月1日力学時正午からの経過日数(日)計算
	$day_progress = $this->J2000($year, $month, $day);

	//逐次計算時刻(日)初期設定
	$time_loop = 0.5;

	//補正値初期値
	$rev = 1.0;

	//地平線伏角
	$dip = 0.0353333 * sqrt($height);

	while (abs($rev) > $this->CONVERGE) {
		//経過ユリウス年(日)計算
		//( 2000.0(2000年1月1日力学時正午)からの経過年数 (年) )
		$jy = ($day_progress + $time_loop + $rotate_rev) / 365.25;
		//太陽の黄経
		$long_sun = $this->__longitude_sun($jy);
		//太陽の距離
		$dist_sun = $this->__distance_sun($jy);
		//黄道 → 赤道変換
		list($alpha, $delta) = $this->__eclip2equat($long_sun, 0, $jy);
		//太陽の視半径
		$r_sun = 0.266994 / $dist_sun;
		//太陽の視差
		$dif_sun = 0.0024428 / $dist_sun;
		//太陽の出入高度
		$height_sun = -1 * $r_sun - $this->ASTRO_REFRACT - $dip + $dif_sun;
		//恒星時
		$sidereal = $this->__sidereal($jy, $time_loop, $longitude);
		//時角差計算
		$hour_ang_dif = $this->hour_ang_dif($mode, $alpha, $delta, $latitude, $height_sun, $sidereal);

		//仮定時刻に対する補正値
		$rev = $hour_ang_dif / 360.0;
		$time_loop += $rev;
	}

	return $time_loop;
}

/**
 * その日が二十四節気かどうか
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	string 二十四節気／'' :二十四節気ではない
*/
function getSolarTerm($year, $month, $day) {
	static $old = -1.0;
	static $table = array(
  0 => '春分',
  1 => '清明',
  2 => '穀雨',
  3 => '立夏',
  4 => '小満',
  5 => '芒種',
  6 => '夏至',
  7 => '小暑',
  8 => '大暑',
  9 => '立秋',
 10 => '処暑',
 11 => '白露',
 12 => '秋分',
 13 => '寒露',
 14 => '霜降',
 15 => '立冬',
 16 => '小雪',
 17 => '大雪',
 18 => '冬至',
 19 => '小寒',
 20 => '大寒',
 21 => '立春',
 22 => '雨水',
 23 => '啓蟄'
);

	//太陽黄経
	$l1 = $this->longitude_sun($year, $month, $day, 0, 0, 0);
	$l2 = $this->longitude_sun($year, $month, $day, 24, 0, 0);

	$n1 = floor($l1 / 15.0);
	$n2 = floor($l2 / 15.0);

	return ($n1 != $n2) ? $table[$n2] : '';
}

/**
 * その日の節月を求める
 * @param	int $year, $month, $day グレゴリオ暦による年月日
 * @return	int 節月／0：計算失敗
*/
function getSetsugetsu($year, $month, $day) {
	static $table = array(
  1 => 315,
  2 => 345,
  3 =>  15,
  4 =>  45,
  5 =>  75,
  6 => 105,
  7 => 135,
  8 => 165,
  9 => 195,
 10 => 225,
 11 => 255,
 12 => 285,
 13 => 315
);

	//太陽黄経
	$ls = $this->longitude_sun($year, $month, $day, 24, 0, 0);

	//節月
	foreach ($table as $key=>$val) {
		if ($key == 2) {
			if (($ls >= $table[2]) || ($ls < $table[3]))	return $key;
		} else {
			if (($ls >= $table[$key]) && ($ls < $table[$key + 1]))	return $key;
		}
	}
	return 0;
}

/**
 * その日が七十二候かどうか
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	string 七十二候／'' :七十二候ではない
*/
function getSolarTerm72($year, $month, $day) {
	static $old = -1.0;
	static $table = array(
 0 => '雀始巣',
 1 => '桜始開',
 2 => '雷乃発声',
 3 => '玄鳥至',
 4 => '鴻雁北',
 5 => '虹始見',
 6 => '葭始生',
 7 => '霜止出苗',
 8 => '牡丹華',
 9 => '鼃始鳴',
10 => '蚯蚓出',
11 => '竹笋生',
12 => '蚕起食桑',
13 => '紅花栄',
14 => '麦秋至',
15 => '蟷螂生',
16 => '腐草為蛍',
17 => '梅子黄',
18 => '乃東枯',
19 => '菖蒲華',
20 => '半夏生',
21 => '温風至',
22 => '蓮始開',
23 => '鷹乃学習',
24 => '桐始結花',
25 => '土潤溽暑',
26 => '大雨時行',
27 => '涼風至',
28 => '寒蝉鳴',
29 => '蒙霧升降',
30 => '綿柎開',
31 => '天地始粛',
32 => '禾乃登',
33 => '草露白',
34 => '鶺鴒鳴',
35 => '玄鳥去',
36 => '雷乃収声',
37 => '蟄虫坏戸',
38 => '水始涸',
39 => '鴻雁来',
40 => '菊花開',
41 => '蟋蟀在戸',
42 => '霜始降',
43 => '霎時施',
44 => '楓蔦黄',
45 => '山茶始開',
46 => '地始凍',
47 => '金盞香',
48 => '虹蔵不見',
49 => '朔風払葉',
50 => '橘始黄',
51 => '閉塞成冬',
52 => '熊蟄穴',
53 => '鱖魚群',
54 => '乃東生',
55 => '麋角解',
56 => '雪下出麦',
57 => '芹乃栄',
58 => '水泉動',
59 => '雉始雊',
60 => '款冬華',
61 => '水沢腹堅',
62 => '雞始乳',
63 => '東風解凍',
64 => '黄鶯睍睆',
65 => '魚上氷',
66 => '土脉潤起',
67 => '霞始靆',
68 => '草木萠動',
69 => '蟄虫啓戸',
70 => '桃始笑',
71 => '菜虫化蝶'
);

	//太陽黄経
	$l1 = $this->longitude_sun($year, $month, $day, 0, 0, 0);
	$l2 = $this->longitude_sun($year, $month, $day, 24, 0, 0);

	$n1 = floor($l1 / 5.0);
	$n2 = floor($l2 / 5.0);

	return ($n1 != $n2) ? $table[$n2] : '';
}

/**
 * その日が節分（立春の前日）かどうか
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	bool TRUE：節分／FALSE：節分ではない
*/
function isSetsubun($year, $month, $day) {
	//太陽黄経
	$l1 = $this->longitude_sun($year, $month, $day, +24, 0, 0);
	$l2 = $this->longitude_sun($year, $month, $day, +48, 0, 0);

	$n1 = floor($l1 / 15.0);
	$n2 = floor($l2 / 15.0);

	//翌日が立春かどうか
	return (($n1 != $n2) && ($n2 == 21)) ? TRUE : FALSE;
}

/**
 * 恵方の方位角を求める
 * @param	int $year 西暦年
 * @return	float 恵方の方位角（北を0度として時計回り）
*/
function eho($year) {
	static $table = array(
		255,		//庚年（西南西微西）
		165,		//辛年（南南東微南）
		345,		//壬年（北北西微北）
		165,		//癸年（南南東微南）
		 75,		//甲年（東北東微東）
		255,		//乙年（西南西微西）
		165,		//丙年（南南東微南）
		345,		//丁年（北北西微北）
		165			//戊年（南南東微南）
	);
	$i = $year % 10;

	return $table[$i];
}

/**
 * その年の土用を求める
 * @param	int $year 西暦年
 * @return	array	[0]['in']['year','month','day']		冬の土用の入り
 *					   ['ushi']['year','month','day']	冬の土用の丑の日
 *					   ['out']['year','month','day']	冬の土用の明け
 * 					[1]									春の土用の入り／明け
 * 					[2]									夏の土用の入り／明け
 * 					[3]									秋の土用の入り／明け
*/
function getDoyo($year) {
	static $table1 = array(297, 360, 27, 117, 207, 297);
	static $table2 = array(315, 360, 45, 135, 225, 315);
	$key  = 0;
	$flag = 0;			//土用期間中フラグ
	$doyo = array();	//土用の入り／明けを格納

	for ($month = 1; $month <= 12; $month++) {
		$day_in_month = $this->getDaysInMonth($year, $month);
		for ($day = 1; $day <= $day_in_month; $day++) {
			//太陽黄経
			$l1 = $this->longitude_sun($year, $month, $day, 24, 0, 0);
			$l2 = $this->longitude_sun($year, $month, $day, 48, 0, 0);
			//入り判定
			if (($flag == 0) && ($l1 > $table1[$key]) && ($l1 <= $table1[$key + 1])) {
				$key2 = ($key > 0) ? $key - 1 : $key;
				$doyo[$key2]['in']['year']  = $year;
				$doyo[$key2]['in']['month'] = $month;
				$doyo[$key2]['in']['day']   = $day;
				$flag = 1;
			}
			if ($flag > 0) {
				//明け判定
				if (($l2 >= $table2[$key]) && ($l2 <= $table2[$key + 1])) {
					$key2 = ($key > 0) ? $key - 1 : $key;
					$doyo[$key2]['out']['year']  = $year;
					$doyo[$key2]['out']['month'] = $month;
					$doyo[$key2]['out']['day']   = $day;
					$key++;
					$flag = 0;
					if ($key == 1)	$key++;
					if ($key > 4)	return $doyo;
				//丑の日判定
				} else if (($flag == 1) && (preg_match('/丑/ui', $this->eto_day($year, $month, $day)) > 0)) {
					$key2 = ($key > 0) ? $key - 1 : $key;
					$doyo[$key2]['ushi']['year']  = $year;
					$doyo[$key2]['ushi']['month'] = $month;
					$doyo[$key2]['ushi']['day']   = $day;
					$flag++;
				}
			}
		}
	}
	return $doyo;
}

/**
 * その日が土用かどうか
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	array(季節, 種類)
*/
function isDoyo($year, $month, $day) {
	static $table1 = array('冬' ,'春', '夏', '秋');
	static $table2 = array('in'=>'入り', 'ushi'=>'丑', 'out'=>'明け');
	static $year0 = -1;			//西暦年保存用
	static $doyo = array();		//土用情報保存用

	//土用情報を取得
	if ($year0 != $year) {
		$year0 = $year;
		$doyo = $this->getDoyo($year0);
	}

	//土用判定
	foreach ($doyo as $key1=>$arr1) {
		foreach ($arr1 as $key2=>$arr2) {
			if (($arr2['year'] == $year0) && ($arr2['month'] == $month) && ($arr2['day'] == $day)) {
				return array($table1[$key1], $table2[$key2]);
			}
		}
	}
	return array('', '');
}

// 月の位置計算 ===========================================================
/**
 * 月の黄経計算（視黄経）
 * @param	double $jy 2000.0からの経過年数
 * @return	double 月の黄経（視黄経）
*/
function __longitude_moon($jy) {
	$am  = 0.0006 * sin(deg2rad($this->__angle( 54.0 + 19.3  * $jy)));
	$am += 0.0006 * sin(deg2rad($this->__angle( 71.0 +  0.2  * $jy)));
	$am += 0.0020 * sin(deg2rad($this->__angle( 55.0 + 19.34 * $jy)));
	$am += 0.0040 * sin(deg2rad($this->__angle(119.5 +  1.33 * $jy)));
	$rm_moon  = 0.0003 * sin(deg2rad($this->__angle(280.0   + 23221.3    * $jy)));
	$rm_moon += 0.0003 * sin(deg2rad($this->__angle(161.0   +    40.7    * $jy)));
	$rm_moon += 0.0003 * sin(deg2rad($this->__angle(311.0   +  5492.0    * $jy)));
	$rm_moon += 0.0003 * sin(deg2rad($this->__angle(147.0   + 18089.3    * $jy)));
	$rm_moon += 0.0003 * sin(deg2rad($this->__angle( 66.0   +  3494.7    * $jy)));
	$rm_moon += 0.0003 * sin(deg2rad($this->__angle( 83.0   +  3814.0    * $jy)));
	$rm_moon += 0.0004 * sin(deg2rad($this->__angle( 20.0   +   720.0    * $jy)));
	$rm_moon += 0.0004 * sin(deg2rad($this->__angle( 71.0   +  9584.7    * $jy)));
	$rm_moon += 0.0004 * sin(deg2rad($this->__angle(278.0   +   120.1    * $jy)));
	$rm_moon += 0.0004 * sin(deg2rad($this->__angle(313.0   +   398.7    * $jy)));
	$rm_moon += 0.0005 * sin(deg2rad($this->__angle(332.0   +  5091.3    * $jy)));
	$rm_moon += 0.0005 * sin(deg2rad($this->__angle(114.0   + 17450.7    * $jy)));
	$rm_moon += 0.0005 * sin(deg2rad($this->__angle(181.0   + 19088.0    * $jy)));
	$rm_moon += 0.0005 * sin(deg2rad($this->__angle(247.0   + 22582.7    * $jy)));
	$rm_moon += 0.0006 * sin(deg2rad($this->__angle(128.0   +  1118.7    * $jy)));
	$rm_moon += 0.0007 * sin(deg2rad($this->__angle(216.0   +   278.6    * $jy)));
	$rm_moon += 0.0007 * sin(deg2rad($this->__angle(275.0   +  4853.3    * $jy)));
	$rm_moon += 0.0007 * sin(deg2rad($this->__angle(140.0   +  4052.0    * $jy)));
	$rm_moon += 0.0008 * sin(deg2rad($this->__angle(204.0   +  7906.7    * $jy)));
	$rm_moon += 0.0008 * sin(deg2rad($this->__angle(188.0   + 14037.3    * $jy)));
	$rm_moon += 0.0009 * sin(deg2rad($this->__angle(218.0   +  8586.0    * $jy)));
	$rm_moon += 0.0011 * sin(deg2rad($this->__angle(276.5   + 19208.02   * $jy)));
	$rm_moon += 0.0012 * sin(deg2rad($this->__angle(339.0   + 12678.71   * $jy)));
	$rm_moon += 0.0016 * sin(deg2rad($this->__angle(242.2   + 18569.38   * $jy)));
	$rm_moon += 0.0018 * sin(deg2rad($this->__angle(  4.1   +  4013.29   * $jy)));
	$rm_moon += 0.0020 * sin(deg2rad($this->__angle( 55.0   +    19.34   * $jy)));
	$rm_moon += 0.0021 * sin(deg2rad($this->__angle(105.6   +  3413.37   * $jy)));
	$rm_moon += 0.0021 * sin(deg2rad($this->__angle(175.1   +   719.98   * $jy)));
	$rm_moon += 0.0021 * sin(deg2rad($this->__angle( 87.5   +  9903.97   * $jy)));
	$rm_moon += 0.0022 * sin(deg2rad($this->__angle(240.6   +  8185.36   * $jy)));
	$rm_moon += 0.0024 * sin(deg2rad($this->__angle(252.8   +  9224.66   * $jy)));
	$rm_moon += 0.0024 * sin(deg2rad($this->__angle(211.9   +   988.63   * $jy)));
	$rm_moon += 0.0026 * sin(deg2rad($this->__angle(107.2   + 13797.39   * $jy)));
	$rm_moon += 0.0027 * sin(deg2rad($this->__angle(272.5   +  9183.99   * $jy)));
	$rm_moon += 0.0037 * sin(deg2rad($this->__angle(349.1   +  5410.62   * $jy)));
	$rm_moon += 0.0039 * sin(deg2rad($this->__angle(111.3   + 17810.68   * $jy)));
	$rm_moon += 0.0040 * sin(deg2rad($this->__angle(119.5   +     1.33   * $jy)));
	$rm_moon += 0.0040 * sin(deg2rad($this->__angle(145.6   + 18449.32   * $jy)));
	$rm_moon += 0.0040 * sin(deg2rad($this->__angle( 13.2   + 13317.34   * $jy)));
	$rm_moon += 0.0048 * sin(deg2rad($this->__angle(235.0   +    19.34   * $jy)));
	$rm_moon += 0.0050 * sin(deg2rad($this->__angle(295.4   +  4812.66   * $jy)));
	$rm_moon += 0.0052 * sin(deg2rad($this->__angle(197.2   +   319.32   * $jy)));
	$rm_moon += 0.0068 * sin(deg2rad($this->__angle( 53.2   +  9265.33   * $jy)));
	$rm_moon += 0.0079 * sin(deg2rad($this->__angle(278.2   +  4493.34   * $jy)));
	$rm_moon += 0.0085 * sin(deg2rad($this->__angle(201.5   +  8266.71   * $jy)));
	$rm_moon += 0.0100 * sin(deg2rad($this->__angle( 44.89  + 14315.966  * $jy)));
	$rm_moon += 0.0107 * sin(deg2rad($this->__angle(336.44  + 13038.696  * $jy)));
	$rm_moon += 0.0110 * sin(deg2rad($this->__angle(231.59  +  4892.052  * $jy)));
	$rm_moon += 0.0125 * sin(deg2rad($this->__angle(141.51  + 14436.029  * $jy)));
	$rm_moon += 0.0153 * sin(deg2rad($this->__angle(130.84  +   758.698  * $jy)));
	$rm_moon += 0.0305 * sin(deg2rad($this->__angle(312.49  +  5131.979  * $jy)));
	$rm_moon += 0.0348 * sin(deg2rad($this->__angle(117.84  +  4452.671  * $jy)));
	$rm_moon += 0.0410 * sin(deg2rad($this->__angle(137.43  +  4411.998  * $jy)));
	$rm_moon += 0.0459 * sin(deg2rad($this->__angle(238.18  +  8545.352  * $jy)));
	$rm_moon += 0.0533 * sin(deg2rad($this->__angle( 10.66  + 13677.331  * $jy)));
	$rm_moon += 0.0572 * sin(deg2rad($this->__angle(103.21  +  3773.363  * $jy)));
	$rm_moon += 0.0588 * sin(deg2rad($this->__angle(214.22  +   638.635  * $jy)));
	$rm_moon += 0.1143 * sin(deg2rad($this->__angle(  6.546 +  9664.0404 * $jy)));
	$rm_moon += 0.1856 * sin(deg2rad($this->__angle(177.525 +   359.9905 * $jy)));
	$rm_moon += 0.2136 * sin(deg2rad($this->__angle(269.926 +  9543.9773 * $jy)));
	$rm_moon += 0.6583 * sin(deg2rad($this->__angle(235.700 +  8905.3422 * $jy)));
	$rm_moon += 1.2740 * sin(deg2rad($this->__angle(100.738 +  4133.3536 * $jy)));
	$rm_moon += 6.2887 * sin(deg2rad($this->__angle(134.961 +  4771.9886 * $jy + $am)));

	return $rm_moon + $this->__angle(218.3161 + 4812.67881 * $jy);
}

/**
 * 月の黄経計算（視黄経）
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月の黄経（視黄経）
*/
function longitude_moon($year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__longitude_moon($jy);
}

/**
 * 月の黄緯計算（視黄緯）
 * @param	double $jy 2000.0からの経過年数
 * @return	double 月の黄緯（視黄緯）
*/
function __latitude_moon($jy) {
	$bm  = 0.0005 * sin(deg2rad($this->__angle(307.0  + 19.4   * $jy)));
	$bm += 0.0026 * sin(deg2rad($this->__angle( 55.0  + 19.34  * $jy)));
	$bm += 0.0040 * sin(deg2rad($this->__angle(119.5  +  1.33  * $jy)));
	$bm += 0.0043 * sin(deg2rad($this->__angle(322.1  + 19.36  * $jy)));
	$bm += 0.0267 * sin(deg2rad($this->__angle(234.95 + 19.341 * $jy)));

	$bt_moon  =  0.0003 * sin(deg2rad($this->__angle(234.0   + 19268.0    * $jy)));
	$bt_moon +=  0.0003 * sin(deg2rad($this->__angle(146.0   +  3353.3    * $jy)));
	$bt_moon +=  0.0003 * sin(deg2rad($this->__angle(107.0   + 18149.4    * $jy)));
	$bt_moon +=  0.0003 * sin(deg2rad($this->__angle(205.0   + 22642.7    * $jy)));
	$bt_moon +=  0.0004 * sin(deg2rad($this->__angle(147.0   + 14097.4    * $jy)));
	$bt_moon +=  0.0004 * sin(deg2rad($this->__angle( 13.0   +  9325.4    * $jy)));
	$bt_moon +=  0.0004 * sin(deg2rad($this->__angle( 81.0   + 10242.6    * $jy)));
	$bt_moon +=  0.0004 * sin(deg2rad($this->__angle(238.0   + 23281.3    * $jy)));
	$bt_moon +=  0.0004 * sin(deg2rad($this->__angle(311.0   +  9483.9    * $jy)));
	$bt_moon +=  0.0005 * sin(deg2rad($this->__angle(239.0   +  4193.4    * $jy)));
	$bt_moon +=  0.0005 * sin(deg2rad($this->__angle(280.0   +  8485.3    * $jy)));
	$bt_moon +=  0.0006 * sin(deg2rad($this->__angle( 52.0   + 13617.3    * $jy)));
	$bt_moon +=  0.0006 * sin(deg2rad($this->__angle(224.0   +  5590.7    * $jy)));
	$bt_moon +=  0.0007 * sin(deg2rad($this->__angle(294.0   + 13098.7    * $jy)));
	$bt_moon +=  0.0008 * sin(deg2rad($this->__angle(326.0   +  9724.1    * $jy)));
	$bt_moon +=  0.0008 * sin(deg2rad($this->__angle( 70.0   + 17870.7    * $jy)));
	$bt_moon +=  0.0010 * sin(deg2rad($this->__angle( 18.0   + 12978.66   * $jy)));
	$bt_moon +=  0.0011 * sin(deg2rad($this->__angle(138.3   + 19147.99   * $jy)));
	$bt_moon +=  0.0012 * sin(deg2rad($this->__angle(148.2   +  4851.36   * $jy)));
	$bt_moon +=  0.0012 * sin(deg2rad($this->__angle( 38.4   +  4812.68   * $jy)));
	$bt_moon +=  0.0013 * sin(deg2rad($this->__angle(155.4   +   379.35   * $jy)));
	$bt_moon +=  0.0013 * sin(deg2rad($this->__angle( 95.8   +  4472.03   * $jy)));
	$bt_moon +=  0.0014 * sin(deg2rad($this->__angle(219.2   +   299.96   * $jy)));
	$bt_moon +=  0.0015 * sin(deg2rad($this->__angle( 45.8   +  9964.00   * $jy)));
	$bt_moon +=  0.0015 * sin(deg2rad($this->__angle(211.1   +  9284.69   * $jy)));
	$bt_moon +=  0.0016 * sin(deg2rad($this->__angle(135.7   +   420.02   * $jy)));
	$bt_moon +=  0.0017 * sin(deg2rad($this->__angle( 99.8   + 14496.06   * $jy)));
	$bt_moon +=  0.0018 * sin(deg2rad($this->__angle(270.8   +  5192.01   * $jy)));
	$bt_moon +=  0.0018 * sin(deg2rad($this->__angle(243.3   +  8206.68   * $jy)));
	$bt_moon +=  0.0019 * sin(deg2rad($this->__angle(230.7   +  9244.02   * $jy)));
	$bt_moon +=  0.0021 * sin(deg2rad($this->__angle(170.1   +  1058.66   * $jy)));
	$bt_moon +=  0.0022 * sin(deg2rad($this->__angle(331.4   + 13377.37   * $jy)));
	$bt_moon +=  0.0025 * sin(deg2rad($this->__angle(196.5   +  8605.38   * $jy)));
	$bt_moon +=  0.0034 * sin(deg2rad($this->__angle(319.9   +  4433.31   * $jy)));
	$bt_moon +=  0.0042 * sin(deg2rad($this->__angle(103.9   + 18509.35   * $jy)));
	$bt_moon +=  0.0043 * sin(deg2rad($this->__angle(307.6   +  5470.66   * $jy)));
	$bt_moon +=  0.0082 * sin(deg2rad($this->__angle(144.9   +  3713.33   * $jy)));
	$bt_moon +=  0.0088 * sin(deg2rad($this->__angle(176.7   +  4711.96   * $jy)));
	$bt_moon +=  0.0093 * sin(deg2rad($this->__angle(277.4   +  8845.31   * $jy)));
	$bt_moon +=  0.0172 * sin(deg2rad($this->__angle(  3.18  + 14375.997  * $jy)));
	$bt_moon +=  0.0326 * sin(deg2rad($this->__angle(328.96  + 13737.362  * $jy)));
	$bt_moon +=  0.0463 * sin(deg2rad($this->__angle(172.55  +   698.667  * $jy)));
	$bt_moon +=  0.0554 * sin(deg2rad($this->__angle(194.01  +  8965.374  * $jy)));
	$bt_moon +=  0.1732 * sin(deg2rad($this->__angle(142.427 +  4073.3220 * $jy)));
	$bt_moon +=  0.2777 * sin(deg2rad($this->__angle(138.311 +    60.0316 * $jy)));
	$bt_moon +=  0.2806 * sin(deg2rad($this->__angle(228.235 +  9604.0088 * $jy)));
	$bt_moon +=  5.1282 * sin(deg2rad($this->__angle( 93.273 +  4832.0202 * $jy + $bm)));

	return $bt_moon;
}

/**
 * 月の黄緯計算（視黄経）
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月の黄緯（視黄経）
*/
function latitude_moon($year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__latitude_moon($jy);
}

/**
 * 月の地心距離
 * @param	double $jd ユリウス日
 * @return	double 月の地心距離（平均距離1.0に対する比率）
*/
function __distance_moon($jd) {
static $tbl = array(
	array(0.051820, 477198.868,134.963), array( 0.009530, 413335.35, 100.74),
	array(0.007842, 890534.22, 235.7  ), array( 0.002824, 954397.74, 269.93),
	array(0.000858,1367733.1,   10.7  ), array( 0.000531, 854535.2,  238.2 ),
	array(0.000400, 377336.3,  103.2  ), array( 0.000319, 441199.8,  137.4 ),
	array(0.000271, 445267.0,  118.0  ), array( 0.000263, 513198.0,  312.0 ),
	array(0.000197, 489205.0,  232.0  ), array( 0.000173,1431597.0,   45.0 ),
	array(0.000167,1303870.0,  336.0  ), array( 0.000111,  35999.0,  178.0 ),
	array(0.000103, 826671.0,  201.0  ), array( 0.000084,  63864.0,  214.0 ),
	array(0.000083, 926533.0,   53.0  ), array( 0.000078,1844932.0,  146.0 ),
	array(0.000073,1781068.0,  111.0  ), array( 0.000064,1331734.0,   13.0 ),
	array(0.000063, 449334.0,  278.0  ), array( 0.000041, 481266.0,  295.0 ),
	array(0.000034, 918399.0,  272.0  ), array( 0.000033, 541062.0,  349.0 ),
	array(0.000031, 922466.0,  253.0  ), array( 0.000030,  75870.0,  131.0 ),
	array(0.000029, 990397.0,   87.0  ), array( 0.000026, 818536.0,  241.0 ),
	array(0.000023, 553069.0,  266.0  ), array( 0.000019,1267871.0,  339.0 ),
	array(0.000013,1403732.0,  188.0  ), array( 0.000013, 341337.0,  106.0 ),
	array(0.000013, 401329.0,    4.0  ), array( 0.000012,2258267.0,  246.0 ),
	array(0.000011,1908795.0,  180.0  ), array( 0.000011, 858602.0,  219.0 ),
	array(0.000010,1745069.0,  114.0  ), array( 0.000009, 790672.0,  204.0 ),
	array(0.000007,2322131.0,  281.0  ), array( 0.000007,1808933.0,  148.0 ),
	array(0.000006, 485333.0,  276.0  ), array( 0.0000006, 99863.0,  212.0 ),
	array(0.000005, 405201.0,  140.0  )
);
	$t = ($jd - 2451545.0) / 36525.0;
	$s = 0.950725;
	foreach ($tbl as $val) {
		$r = $val[1] * $t + $val[2];
		$s += $val[0] * cos($r * 0.017453292519943);
	}
	$d = 6378.14 / sin($s * 0.017453292519943);

	return $d / 384400.0;		//平均距離で割る
}

/**
 * 月の地心距離
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月の地心距離（平均距離1.0に対する比率）
*/
function distance_moon($year, $month, $day, $hour, $min, $sec) {
	$jd = $this->Gregorian2JD($year, $month, $day, $hour, $min, $sec);

	return $this->__distance_moon($jd);
}

/**
 * 月の視差
 * @param	double $jy 2000.0からの経過年数
 * @return	double 月の視差
*/
function __dif_moon($jy) {
	$p_moon  =  0.0003 * sin(deg2rad($this->__angle(227.0  +  4412.0   * $jy)));
	$p_moon +=  0.0004 * sin(deg2rad($this->__angle(194.0  +  3773.4   * $jy)));
	$p_moon +=  0.0005 * sin(deg2rad($this->__angle(329.0  +  8545.4   * $jy)));
	$p_moon +=  0.0009 * sin(deg2rad($this->__angle(100.0  + 13677.3   * $jy)));
	$p_moon +=  0.0028 * sin(deg2rad($this->__angle(  0.0  +  9543.98  * $jy)));
	$p_moon +=  0.0078 * sin(deg2rad($this->__angle(325.7  +  8905.34  * $jy)));
	$p_moon +=  0.0095 * sin(deg2rad($this->__angle(190.7  +  4133.35  * $jy)));
	$p_moon +=  0.0518 * sin(deg2rad($this->__angle(224.98 +  4771.989 * $jy)));
	$p_moon +=  0.9507 * sin(deg2rad($this->__angle(90.0)));

	return $p_moon;
}

/**
 * 月の視差
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月の視差
*/
function dif_moon($year, $month, $day, $hour, $min, $sec) {
	$jy = $this->Gregorian2JY($year, $month, $day, $hour, $min, $sec);

	return $this->__dif_moon($jy);
}

/**
 * 月の視半径（視差から算出）
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月の視半径（度）
*/
function rad_moon($year, $month, $day, $hour, $min, $sec) {
	$dif = $this->dif_moon($year, $month, $day, $hour, $min, $sec);
	$rad = asin(0.2725 * sin(deg2rad($dif)));

	return rad2deg($rad);
}

/**
 * 月の出／月の入り／南中
 * @param	int $mode 0=出, 1=没, 2=南中
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $longitude 観測値の経度（東経は正数）
 * @param	double $latitude  観測値の緯度
 * @param	double $height 観測値の高さ
 * @return	double 時刻（単位：日）／FALSE 月出／月没がない
*/
function moon_time($mode, $longitude, $latitude, $height, $year, $month, $day) {
	//地球自転遅れ補正値(日)計算
	$rotate_rev = (57.0 + 0.8 * ($year - 1990)) / 86400.0;

	//2000年1月1日力学時正午からの経過日数(日)計算
	$day_progress = $this->J2000($year, $month, $day);

	//逐次計算時刻(日)初期設定
	$time_loop = 0.5;

	//補正値初期値
	$rev = 1.0;

	//地平線伏角
	$dip = 0.0353333 * sqrt($height);

	$diff_moon = 0;
	$height_moon = 0;
	while (abs($rev) > $this->CONVERGE) {
		//経過ユリウス年(日)計算
		//( 2000.0(2000年1月1日力学時正午)からの経過年数 (年) )
		$jy = ($day_progress + $time_loop + $rotate_rev) / 365.25;
		//月の黄経
		$long_moon = $this->__longitude_moon($jy);
		//月の黄緯
		$lat_moon = $this->__latitude_moon($jy);
		//黄道 → 赤道変換
		list($alpha, $delta) = $this->__eclip2equat($long_moon, $lat_moon, $jy);
		if ($mode != 2) {
			//月の視差
			$dif_moon = $this->__dif_moon($jy);
			//月の出没高度
			$height_moon = -1 * $this->ASTRO_REFRACT - $dip + $dif_moon;
		}
		//恒星時
		$sidereal = $this->__sidereal($jy, $time_loop, $longitude);
		//時角差計算
		$hour_ang_dif = $this->hour_ang_dif($mode, $alpha, $delta, $latitude, $height_moon, $sidereal);

		//仮定時刻に対する補正値
		$rev = $hour_ang_dif / 347.8;
		$time_loop += $rev;
	}

	//月出／月没がない場合は 0 とする
	return ($time_loop < 0 || $time_loop >=1) ? FALSE : $time_loop;
}

/**
 * 月齢を求める（視黄経）
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	double 月齢（視黄経）
*/
function moon_age($year, $month, $day, $hour, $min, $sec) {
	$jd0 = $this->Gregorian2JD($year, $month, $day, $hour, $min, $sec) + ($this->TDIFF / 24);
	$tm1 = floor($jd0);
	$tm2 = $jd0 - $tm1;

	//繰り返し計算によって朔の時刻を計算
	//誤差が±1.0 sec以内になったら打ち切る
	$lc = 1;
	$delta_t1 = 0;
	$delta_t2 = 1;
	while (($delta_t1 + abs($delta_t2)) > (1.0 / 86400.0)) {
		$jd = $tm1 + $tm2;
		list($year, $month, $day, $hour, $min, $sec) = $this->JD2Gregorian($jd);
		$longitude_sun  = $this->longitude_sun($year, $month, $day, $hour, $min, $sec);
		$longitude_moon = $this->longitude_moon($year, $month, $day, $hour, $min, $sec);

		//Δλ＝λmoon－λsun
		$delta_rm = $longitude_moon - $longitude_sun;

		//ループ1回目 で $delta_rm < 0.0 の場合には引き込み範囲に入るよう補正
		if ($lc == 1 && $delta_rm < 0) {
			$delta_rm = $this->__angle($delta_rm);
		//春分の近くで朔がある場合 ( 0 ≦λsun≦ 20 ) で、月の黄経λmoon≧300 の
		//場合には、Δλ＝ 360.0 － Δλ と計算して補正
		} else if ($longitude_sun >= 0 && $longitude_sun <= 20 && $longitude_moon >= 300) {
			$delta_rm = $this->__angle($delta_rm);
			$delta_rm = 360 - $delta_rm;
		//Δλの引き込み範囲 ( ±40°) を逸脱した場合には補正
		} else if (abs($delta_rm) > 40.0) {
			$delta_rm = $this->__angle($delta_rm);
		}

		//時刻引数の補正値 Δt
		$delta_t1  = floor($delta_rm * 29.530589 / 360.0);
		$delta_t2  = $delta_rm * 29.530589 / 360.0;
		$delta_t2 -= $delta_t1;

		//時刻引数の補正
		$tm1 = $tm1 - $delta_t1;
		$tm2 = $tm2 - $delta_t2;
		if ($tm2 < 0) {
			$tm2++;
			$tm1--;
		}

		//ループ回数が15回になったら、初期値 tm を tm-26
		if ($lc == 15 && abs($delta_t1 + $delta_t2) > (1.0 / 86400.0)) {
			$tm1 = floor($jd0 - 26);
			$tm2 = 0;
			//初期値を補正したにも関わらず振動を続ける場合は、
			//初期値を答えとして返して強制的にループを抜け出して異常終了
		} else if ($lc > 30 && abs($delta_t1 + $delta_t2) > (1.0 / 86400.0)) {
			$tm1 = $jd0;
			$tm2 = 0;
			break;
		}
		$lc++;
	}

	//時刻引数を合成
	$ma = $jd0 - ($tm2 + $tm1);
	if ($ma > 30)	$ma -= 30;
	return $ma;
}

/**
 * 潮を求める
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	string 潮
*/
function tide($year, $month, $day, $hour, $min, $sec) {
	//黄経差=>潮（気象庁方式）
	static $table = array(
 36 => '大潮',
 72 => '中潮',
108 => '小潮',
120 => '長潮',
132 => '若潮',
168 => '中潮',
216 => '大潮',
252 => '中潮',
288 => '小潮',
300 => '長潮',
312 => '若潮',
348 => '中潮',
360 => '大潮'
);

	$longitude_sun  = $this->longitude_sun($year, $month, $day, $hour, $min, $sec);
	$longitude_moon = $this->longitude_moon($year, $month, $day, $hour, $min, $sec);
	//Δλ＝λmoon－λsun
	$delta_rm = $this->__angle($longitude_moon - $longitude_sun);

	foreach ($table as $key=>$val) {
		if ($delta_rm < $key) {
			$tide = $val;
			break;
		}
	}

	return $tide;
}

/**
 * 満月の呼び名を求める
 * @param	int $month  月
 * @return	string 満月の呼び名
*/
function getFullMoonNickname($month) {
	$table_jp = array(
'ウルフムーン',
'スノームーン',
'ワームムーン',
'ピンクムーン',
'フラワームーン',
'ストロベリームーン',
'パクムーン',
'スタージャンムーン',
'ハーベストムーン',
'ハンターズムーン',
'ビーバームーン',
'コールドムーン'
);

	$table_en = array(
'Wolf Moon',
'Snow Moon',
'Worm Moon',
'Pink Moon',
'Flower Moon',
'Strawberry Moon',
'Buck Moon',
'Sturgeon Moon',
'Harvest Moon',
'Hunter\#x27;s Moon',
'Beaver Moon',
'Cold Moon'
);

	$month = (int)$month;
	if (($month >= 1) && ($month << 12)) {
		if ($this->language == 'jp') {
			$name = $table_jp[$month - 1];
		} else {
			$name = $table_en[$month - 1];
		}
	} else {
		$name = '';
	}

	return $name;
}

// 休日 ===================================================================
/**
 * 固定祝日であれば、その名称を取得する
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 固定祝日の名称／FALSE=祝日ではない
*/
function getFixedHoliday($year, $month, $day) {
//固定祝日
static $fixed_holiday = array(
//    月  日  開始年 終了年  名称
array( 1,  1, 1949, 9999, '元日',         "New Year's Day"),
array( 1, 15, 1949, 1999, '成人の日',     'Coming of Age Day'),
array( 2, 11, 1967, 9999, '建国記念の日', 'National Foundation Day'),
array( 2, 23, 2020, 9999, '天皇誕生日',   "The Emperor's Birthday"),
array( 4, 29, 1949, 1989, '天皇誕生日',   "The Emperor's Birthday"),
array( 4, 29, 1990, 2006, 'みどりの日',   'Greenery Day'),
array( 4, 29, 2007, 9999, '昭和の日',     'Showa Day'),
array( 5,  3, 1949, 9999, '憲法記念日',   'Constitution Memorial Day'),
array( 5,  4, 1988, 2006, '国民の休日',   'Holiday for a Nation'),
array( 5,  4, 2007, 9999, 'みどりの日',   'Greenery Day'),
array( 5,  5, 1949, 9999, 'こどもの日',   "Children's Day"),
array( 7, 20, 1996, 2002, '海の日',       'Marine Day'),
array( 7, 22, 2021, 2021, '海の日',       'Marine Day'),
array( 7, 23, 2020, 2020, '海の日',       'Marine Day'),
array( 7, 23, 2021, 2021, 'スポーツの日', 'Health Sports Day'),
array( 7, 24, 2020, 2020, 'スポーツの日', 'Health Sports Day'),
array( 8,  8, 2021, 2021, '山の日',       'Mountain Day'),
array( 8, 11, 2016, 2019, '山の日',       'Mountain Day'),
array( 8, 10, 2020, 2020, '山の日',       'Mountain Day'),
array( 8, 11, 2022, 9999, '山の日',       'Mountain Day'),
array( 9, 15, 1966, 2002, '敬老の日',     'Respect for the Aged Day'),
array(10, 10, 1966, 1999, '体育の日',     'Health and Sports Day'),
array(11,  3, 1948, 9999, '文化の日',     'National Culture Day'),
array(11, 23, 1948, 9999, '勤労感謝の日', 'Labbor Thanksgiving Day'),
array(12, 23, 1989, 2018, '天皇誕生日',   "The Emperor's Birthday"),
//以下、1年だけの祝日
array( 4, 10, 1959, 1959, '皇太子明仁親王の結婚の儀', "The Rite of Wedding of HIH Crown Prince Akihito"),
array( 2, 24, 1989, 1989, '昭和天皇の大喪の礼', "The Funeral Ceremony of Emperor Showa."),
array(11, 12, 1990, 1990, '即位礼正殿の儀', "The Ceremony of the Enthronement
      of His Majesty the Emperor (at the Seiden)"),
array( 6,  9, 1993, 1993, '皇太子徳仁親王の結婚の儀 ', "The Rite of Wedding of HIH Crown Prince Naruhito"),
array( 5,  1, 2019, 2019, '即位の日', 'Day of cadence'),
array(10, 22, 2019, 2019, '即位礼正殿の儀', 'The Ceremony of the Enthronement of His Majesty the Emperor (at the Seiden)'),
);

	$name = FALSE;
	foreach ($fixed_holiday as $val) {
		if ($month == $val[0] && $day == $val[1]) {
			if ($year >= $val[2] && $year <= $val[3]) {
				$name = preg_match('/jp/i', $this->language) == 1 ? $val[4] : $val[5];
				break;
			}
		}
	}
	return $name;
}

/**
 * ある年の春分の日を求める
 * @param	int $year 西暦年
 * @return	int 日（3月の）
*/
function getVernalEquinox($year) {
	return floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
}

/**
 * ある年の秋分の日を求める
 * @param	int $year 西暦年
 * @return	int 日（9月の）
*/
function getAutumnalEquinox($year) {
	return floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
}

/**
 * 移動祝日（春分／秋分の日）であれば、その名称を取得する
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 移動祝日の名称／FALSE=祝日ではない
*/
function getMovableHoliday1($year, $month, $day) {
	$name = FALSE;

	//春分の日
	$dd = $this->getVernalEquinox($year);
	if ($year >=1949 && $day == $dd && $month == 3) {
		$name = preg_match('/jp/i', $this->language) == 1 ? '春分の日' : 'Vernal Equinox Day';
	}
	//秋分の日
	$dd = $this->getAutumnalEquinox($year);
	if ($year >=1948 && $day == $dd && $month == 9) {
		$name = preg_match('/jp/i', $this->language) == 1 ? '秋分の日' : 'Autumnal Equinox Day';
	}
	return $name;
}

/**
 * ある月の第N曜日を求める
 * @param	int $year 西暦年
 * @param	int $month 月
 * @param	int $week  曜日番号；0 (日曜)～ 6 (土曜)
 * @param	int $n     第N曜日
 * @return	int $day 日
*/
function getWeeksOfMonth($year, $month, $week, $n) {
	if ($n < 1)		return FALSE;

	$jd1 = $this->Gregorian2JD($year, $month, 1, 0, 0, 0);
	$wn1 = $this->getWeekNumber($year, $month, 1);
	$dd  = $week - $wn1 < 0 ? 7 + $week - $wn1 : $week - $wn1;
	$jd2 = $jd1 + $dd;
	$jdn = $jd2 + 7 * ($n - 1);
	list($yy, $mm, $dd) = $this->JD2Gregorian($jdn);

	if ($mm != $month)	return FALSE;	//月のオーバーフロー

	return $dd;
}

/**
 * 移動祝日（ハッピーマンデー）であれば、その名称を取得する
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 移動祝日の名称／FALSE=祝日ではない
*/
function getMovableHoliday2($year, $month, $day) {
//移動祝日（ハッピーマンデー法）
static $movable_holiday = array(
//    月  曜日番号 第N曜日 開始年  終了年  名称
array( 1, 1, 2, 2000, 9999, '成人の日', 'Coming of Age Day'),
array( 7, 1, 3, 2003, 2019, '海の日',   'Marine Day'),
array( 7, 1, 3, 2022, 9999, '海の日',   'Marine Day'),
array( 9, 1, 3, 2003, 9999, '敬老の日', 'Respect for the Aged Day'),
array(10, 1, 2, 2000, 2019, '体育の日', 'Health and Sports Day'),
array(10, 1, 2, 2022, 9999, 'スポーツの日', 'Health Sports Day')
);

	$name = FALSE;
	foreach ($movable_holiday as $val) {
		if ($month == $val[0] && $day == $this->getWeeksOfMonth($year, $month, $val[1], $val[2])) {
			if ($year >= $val[3] && $year <= $val[4]) {
				$name = preg_match('/jp/i', $this->language) == 1 ? $val[5] : $val[6];
				break;
			}
		}
	}
	return $name;
}

/**
 * 固定祝日または移動祝日かどうか調べる
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	bool TRUE/FALSE
*/
function isFixedMovableHoliday($year, $month, $day) {
	if ($this->getFixedHoliday($year, $month, $day, 'en') != FALSE)	return TRUE;
	if ($this->getMovableHoliday1($year, $month, $day, 'en') != FALSE)	return TRUE;
	if ($this->getMovableHoliday2($year, $month, $day, 'en') != FALSE)	return TRUE;
	return FALSE;
}

/**
 * 振替休日かどうか調べる
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	bool TRUE/FALSE
*/
function isTransferHoliday($year, $month, $day) {
	$jd = $this->Gregorian2JD($year, $month, $day, 0, 0, 0);
	$j0 = $this->Gregorian2JD(1973, 4, 12, 0, 0, 0);
	if ($jd < $j0)	return FALSE;		//有効なのは1973年4月12日以降

	//当日が祝日なら FALSE
	if ($this->isFixedMovableHoliday($year, $month, $day))		return FALSE;

	$n = ($year <= 2006) ? 1 : 7;	//改正法なら最大7日間遡る
	$jd--;							//1日前
	for ($i = 0; $i < $n; $i++) {		//無限ループに陥らないように
		list($yy, $mm, $dd) = $this->JD2Gregorian($jd);
		//祝日かつ日曜日なら振替休日
		if ($this->isFixedMovableHoliday($yy, $mm, $dd)
			&& ($this->getWeekNumber($yy, $mm, $dd) == 0))		return TRUE;
		//祝日でなければ打ち切り
		if (! $this->isFixedMovableHoliday($yy, $mm, $dd))		break;
		$jd--;	//1日前
	}
	return FALSE;
}

/**
 * 国民の休日かどうか調べる
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	bool TRUE/FALSE
*/
function isNationalHoliday($year, $month, $day) {
	if ($year < 2003)	return FALSE;	//有効なのは2003年以降
	$j0 = $this->Gregorian2JD($year, $month, $day, 0, 0, 0) - 1;	//前日
	list($yy0, $mm0, $dd0) = $this->JD2Gregorian($j0);
	$j1 = $this->Gregorian2JD($year, $month, $day, 0, 0, 0) + 1;	//翌日
	list($yy1, $mm1, $dd1) = $this->JD2Gregorian($j1);

	//前日と翌日が固定祝日または移動祝日なら国民の休日
	if ($this->isFixedMovableHoliday($yy0, $mm0, $dd0)
		&& $this->isFixedMovableHoliday($yy1, $mm1, $dd1))		return TRUE;
	return FALSE;
}

/**
 * 祝日であれば、その名称を取得する
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 祝日の名称／FALSE=祝日ではない
*/
function getHoliday($year, $month, $day) {
	//固定祝日
	$name = $this->getFixedHoliday($year, $month, $day, 'jp');
	if ($name != FALSE)		return $name;
	//移動祝日（春分／秋分の日）
	$name = $this->getMovableHoliday1($year, $month, $day, 'jp');
	if ($name != FALSE)		return $name;
	//移動祝日（ハッピーマンデー）
	$name = $this->getMovableHoliday2($year, $month, $day, 'jp');
	if ($name != FALSE)		return $name;
	//振替休日
	if ($this->isTransferHoliday($year, $month, $day)) {
		return preg_match('/jp/i', $this->language) == 1 ? '振替休日' : 'holiday in lieu';
	}
	//国民の祝日
	if ($this->isNationalHoliday($year, $month, $day)) {
		return preg_match('/jp/i', $this->language) == 1 ? '国民の休日' : "Citizen's Holiday";
	}
	//祝日ではない
	return FALSE;
}

/**
 * 祝日かどうかを調べる
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	bool TRUE/FALSE
*/
function isHoliday($year, $month, $day) {
	return getHoliday($year, $month, $day, 'jp') == FALSE ? FALSE : TRUE;
}

// 旧暦計算 ===============================================================
/**
 * グレゴオリオ暦＝旧暦テーブル 作成
 * @param	int $year 西暦年
 * @return	double 太陽の黄経（視黄経）
*/
function makeLunarCalendar($year) {
	//旧暦の2033年問題により、2033年以降はエラーフラグを立てる
	if ($year >= 2033) {
		$this->error = TRUE;
		$this->errmsg = '2033年以降は正しい旧暦計算ができません';
	}

	unset($this->tblmoon);
	$this->tblmoon = array();

	//前年の冬至を求める
	for ($day = 1; $day <= 31; $day++) {
		$lsun = $this->longitude_sun($year - 1, 12, $day, 0, 0, 0);
		if (floor($lsun / 15.0) > 17)	break;
	}
	$d1 = $day - 1;	//冬至

	//翌年の雨水を求める
	for ($day = 1; $day <= 31; $day++) {
		$lsun = $this->longitude_sun($year + 1, 2, $day, 0, 0, 0);
		if (floor($lsun / 15.0) > 22)	break;
	}
	$d2 = $day - 1;	//雨水

	//朔の日を求める
	$cnt = 0;
	$dd = $d1;
	$mm = 12;
	$yy = $year - 1;
	while ($yy <= $year + 1) {
		$dm = $this->getDaysInMonth($yy, $mm);
		while ($dd <= $dm) {
			$age1 = $this->moon_age($yy, $mm, $dd,  0 - $this->TDIFF, 0, 0);	//Ver.3.11 bug-fix
			$age2 = $this->moon_age($yy, $mm, $dd, 23 - $this->TDIFF, 59, 59);	//Ver.3.11 bug-fix
			if ($age2 <= $age1) {
				$this->tblmoon[$cnt]['year']  = $yy;
				$this->tblmoon[$cnt]['month'] = $mm;
				$this->tblmoon[$cnt]['day']   = $dd;
				$this->tblmoon[$cnt]['age']   = $age1;
				$this->tblmoon[$cnt]['jd']    = $this->Gregorian2JD($yy, $mm, $dd, 0, 0, 0);
				$cnt++;
			}
			$dd++;
		}
		$mm++;
		$dd = 1;
		if ($mm > 12) {
			$yy++;
			$mm = 1;
		}
	}

	//二十四節気（中）を求める
	$tblsun = array();
	$cnt = 0;
	$dd = $d1;
	$mm = 12;
	$yy = $year - 1;
	while ($yy <= $year + 1) {
		$dm = $this->getDaysInMonth($yy, $mm);
		while ($dd <= $dm) {
			$l1 = $this->longitude_sun($yy, $mm, $dd,  0, 0, 0);
			$l2 = $this->longitude_sun($yy, $mm, $dd, 24, 0, 0);
			$n1 = floor($l1 / 15.0);
			$n2 = floor($l2 / 15.0);
			if (($n2 != $n1) && ($n2 % 2 == 0)) {
				$tblsun[$cnt]['jd'] = $this->Gregorian2JD($yy, $mm, $dd, 0, 0, 0);
				$oldmonth = floor($n2 / 2) + 2;
				if ($oldmonth > 12)	$oldmonth -= 12;
				$tblsun[$cnt]['oldmonth']  = $oldmonth;
				$cnt++;
			}
			$dd++;
		}
		$mm++;
		$dd = 1;
		if ($mm > 12) {
			$yy++;
			$mm = 1;
		}
	}

	//月の名前を決める
	$n1 = count($this->tblmoon);
	$n2 = count($tblsun);
	for ($i = 0; $i < $n1 - 1; $i++) {
		for ($j = 0; $j < $n2; $j++) {
			if (($this->tblmoon[$i]['jd'] <= $tblsun[$j]['jd'])
				&& ($this->tblmoon[$i + 1]['jd'] > $tblsun[$j]['jd'])) {
				$this->tblmoon[$i]['oldmonth'] = $tblsun[$j]['oldmonth'];
				$this->tblmoon[$i]['oldleap']  = FALSE;
				$this->tblmoon[$i + 1]['oldmonth'] = $tblsun[$j]['oldmonth'];
				$this->tblmoon[$i + 1]['oldleap']  = TRUE;
				break;
			}
		}
	}
}

/**
 * 旧暦を求める
 * @param	int $year  西暦年
 * @param	int $month 月
 * @param	int $day   日
 * @return	array(旧暦月,日,閏月フラグ)／FALSE：旧暦計算不能
*/
function Gregorian2Lunar($year, $month, $day) {
	//2033年問題チェック
	if ($this->error)	return  FALSE;

	$jd = $this->Gregorian2JD($year, $month, $day, 0, 0, 0);
	$str = '';
	$n1 = count($this->tblmoon);
	for ($i = 0; $i < $n1 - 1; $i++) {
		if ($jd < $this->tblmoon[$i + 1]['jd']) {
			$day = floor($jd - $this->tblmoon[$i]['jd']) + 1;
			$items = array($this->tblmoon[$i]['oldmonth'], $day, $this->tblmoon[$i]['oldleap']);
			break;
		}
	}
	return $items;
}

/**
 * 六曜を求める
 * @param	int $month  旧暦月
 * @param	int $day    旧暦日
 * @return	string 六曜
*/
function rokuyou($month, $day) {
	static $table = array(
 0 => '大安',
 1 => '赤口',
 2 => '先勝',
 3 => '友引',
 4 => '先負',
 5 => '仏滅'
);

	return $table[($month + $day) % 6];
}

/**
 * 干支を求める（下請け関数）
 * @param	int $a1 十干の基準値
 * @param	int $a2 十二支の基準値
 * @param	int $n  計算したい値
 * @return	string 干支
*/
function __eto($a1, $a2, $n) {
//十干
static $table1 = array(
 0 =>'甲',
 1 =>'乙',
 2 =>'丙',
 3 =>'丁',
 4 =>'戊',
 5 =>'己',
 6 =>'庚',
 7 =>'辛',
 8 =>'壬',
 9 =>'癸'
);

//十二支
static $table2 = array(
 0 =>'子',
 1 =>'丑',
 2 =>'寅',
 3 =>'卯',
 4 =>'辰',
 5 =>'巳',
 6 =>'午',
 7 =>'未',
 8 =>'申',
 9 =>'酉',
10 =>'戌',
11 =>'亥'
);

	return $table1[abs($n - $a1) % 10] . $table2[abs($n - $a2) % 12];
}

/**
 * 年の干支を求める
 * @param	int $year  年
 * @return	string 干支
*/
function eto_year($year) {
	return $this->__eto(1904, 1900, $year);
}

/**
 * 月の干支を求める
 * @param	int $year  年
 * @param	int $month 月
 * @return	string 干支
*/
function eto_month($year, $month) {
	return $this->__eto(0, 0, ($year - 1903) * 12 + $month - 11);
}

/**
 * 日の干支を求める
 * @param	int $year  年
 * @param	int $month 月
 * @param	int $day   日
 * @return	string 干支
*/
function eto_day($year, $month, $day) {
	$j1 = $this->Gregorian2JD(1902, 4, 11, 0, 0, 0);
	$j2 = $this->Gregorian2JD($year, $month, $day, 0, 0, 0);

	return $this->__eto(0, 0, $j2 - $j1);
}

/**
 * 一粒万倍日かどうか
 * @param	int $year, $month, $day グレゴリオ暦による年月日
 * @param	int $method 計算方式（1または2）（省略可能：デフォルトは1）
 * @return	bool TRUE：一粒万倍日／FALSE：ではない
*/
function ichiryumanbai($year, $month, $day, $method=1) {
	//計算方式I
	$table[1] = array(
 1 => array('丑', '午'),
 2 => array('酉', '寅'),
 3 => array('子', '卯'),
 4 => array('卯', '辰'),
 5 => array('巳', '午'),
 6 => array('酉', '午'),
 7 => array('子', '未'),
 8 => array('卯', '申'),
 9 => array('酉', '午'),
10 => array('酉', '戌'),
11 => array('亥', '子'),
12 => array('卯', '子'),
);
	//計算方式II
	$table[2] = array(
 1 => array('酉'),
 2 => array('申'),
 3 => array('未'),
 4 => array('午'),
 5 => array('巳'),
 6 => array('辰'),
 7 => array('卯'),
 8 => array('寅'),
 9 => array('丑'),
10 => array('子'),
11 => array('亥'),
12 => array('戌'),
);

	//計算方式のチェック
	if (($method != 1) && ($method != 2))	return FALSE;

	//節月を求める
	$setsu = $this->getSetsugetsu($year, $month, $day);
	//干支の右1文字取得
	$eto = mb_substr($this->eto_day($year, $month, $day), 1, 1);

	return in_array($eto, $table[$method][$setsu]);
}

// End of Class ===========================================================
}

// 潮位計算 ===============================================================
// @参考URL https://www.pahoo.org/e-soul/webtech/php02/php02-51-01.shtm
class pahooTide {
	const FILE_ZIPNAME = 'tideData.zip';	//計算用パラメータ格納ZIPファイル
	const FILE_C1      = '_C1.txt';		//表-C.1
	const FILE_C2      = '_C2.txt';		//表-C.2
	const FILE_INDEX   = '_index.txt';		//地点一覧
	const FILE_EXT     = '.txt';			//拡張子
	const JST          = -9;				//日本時の時差
	const COMMENT = '#';			//コメント文字
	var $error, $errmsg;			//エラーフラグ，エラーメッセージ
	var $_s, $_h, $_p, $_N;		//天文引数

	var $c1, $c2;					//表-C.1, C.2
	var $index;					//地点一覧
	var $port;						//ある地点の分潮一覧表

//分潮記号
var $keys = array('Sa', 'Ssa', 'Mm', 'MSf', 'Mf', '2Q1', 'σ1', 'Q1', 'ρ1', 'O1', 'MP1', 'M1', 'χ1', 'π1', 'P1', 'S1', 'K1', 'ψ1', 'φ1', 'θ1', 'J1', 'SO1', 'OO1', 'OQ2', 'MNS2', '2N2', 'μ2', 'N2', 'ν2', 'OP2', 'M2', 'MKS2', 'λ2', 'L2', 'T2', 'S2', 'R2', 'K2', 'MSN2', 'KJ2', '2SM2', 'MO3', 'M3', 'SO3', 'MK3', 'SK3', 'MN4', 'M4', 'SN4', 'MS4', 'MK4', 'S4', 'SK4', '2MN6', 'M6', 'MSN6', '2MS6', '2MK6', '2SM6', 'MSK6');

/**
 * コンストラクタ
 * @param	なし
 * @return	なし
*/
function __construct() {
	$this->error  = FALSE;
	$this->errmsg = '';

	//表-C.1, C.2, 地点一覧を配列に格納
	$zipfname = __DIR__ . '/'. self::FILE_ZIPNAME;
	$zip = new ZipArchive;
	if ($zip->open($zipfname) == TRUE) {
		$this->str2array($zip->getFromName(self::FILE_C1), $this->c1);
		$this->str2array($zip->getFromName(self::FILE_C2), $this->c2);
		$this->str2array($zip->getFromName(self::FILE_INDEX), $this->index);
	    $zip->close();
	} else {
		$this->error = TRUE;
		$this->errmsg = "cannot read \"{$zipfname}\"";
	}
	$zip = NULL;
}

/**
 * デストラクタ
 * @return	なし
*/
function __destruct() {
	unset($this->items);
}

/**
 * エラー状況
 * @return	bool TRUE:異常／FALSE:正常
*/
function iserror() {
	return $this->error;
}

/**
 * エラーメッセージ取得
 * @param	なし
 * @return	string 現在発生しているエラーメッセージ
*/
function geterror() {
	return $this->errmsg;
}

/**
 * 表から配列へパラメータを格納する
 * @param	string $str パラメータ表
 * @param	array  $arr パラメータを格納する配列
 * @return	なし
*/
function str2array($str, &$arr) {
	$tok = strtok($str, "\n");
	while ($tok != FALSE) {
		$ss = trim($tok);
		if ($ss == '')	continue;
		$cols = preg_split("/\t/iu", $ss);
		$key = trim($cols[0]);
		if (mb_substr($key, 0, 1) != self::COMMENT) {
			for ($i = 1; $i < count($cols); $i++) {
				if (mb_substr($cols[$i], 0, 1) == self::COMMENT)	break;		//コメントから行末まで無視
				$arr[$key][$i - 1] = trim($cols[$i]);
			}
		}
		$tok = strtok("\n");
	}
}

/**
 * 角度を0以上360未満に正規化する
 * @param	double $d 角度
 * @return	double 正規化した角度
*/
function _stdegree($d) {
	while ($d < 0)			$d += (double)360.0;
	while ($d >= 360.0)	$d -= (double)360.0;
	return (double)$d;
}

/**
 * 天文引数を求める
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	なし
*/
function sun_moon($year, $month, $day) {
	$y = (double)$year - 2000;
	$L = (double)floor(($year + 3) / 4) - 500;
	$d = (double)date('z', mktime(0, 0, 0, $month, $day, $year)) + $L;

	$this->_s = (double)211.728 + 129.38471 * $y + 13.176396 * $d;
	$this->_h = (double)279.974 -   0.23871 * $y +  0.985647 * $d;
	$this->_p = (double) 83.298 +  40.66229 * $y +  0.111404 * $d;
	$this->_N = (double)125.071 -  19.32812 * $y -  0.052954 * $d;

	$this->_s = $this->_stdegree($this->_s);
	$this->_h = $this->_stdegree($this->_h);
	$this->_p = $this->_stdegree($this->_p);
	$this->_N = $this->_stdegree($this->_N);
}

/**
 * L2, M1分潮のfi, uiの計算
 * @param	string $i  分潮記号 'L2' または 'M1'
 * @param	string $fn 'fi' または 'ui'
 * @return	double 計算結果
*/
function fiui_L2M1($i, $fn) {
	//L2
	if ($i == 'L2') {
		$fcosu = (double)1 - 0.2505 * cos(deg2rad(2 * $this->_p))
				- 0.1102 * cos(deg2rad(2 * $this->_p - $this->_N))
				- 0.0156 * cos(deg2rad(2 * $this->_p - 2 * $this->_N))
				- 0.0370 * cos(deg2rad($this->_N));
		$fsinu = (double)-0.2505 * sin(deg2rad(2 * $this->_p))
				- 0.1102 * sin(deg2rad(2 * $this->_p - $this->_N))
				- 0.0156 * sin(deg2rad(2 * $this->_p - 2 * $this->_N))
				- 0.0370 * sin(deg2rad($this->_N));
	//M1
	} else {
		$fcosu = (double)2 * cos(deg2rad($this->_p))
				+ 0.4 * cos(deg2rad($this->_p - $this->_N));
		$fsinu = (double)sin(deg2rad($this->_p))
				 + 0.2 * sin(deg2rad($this->_p - $this->_N));
	}

	//fi
	if ($fn == 'fi') {
		$res = (double)sqrt($fcosu * $fcosu + $fsinu * $fsinu);
	//ui
	} else {
		$res = (double)atan($fcosu / $fsinu);
	}

	return $res;
}

/**
 * fi, uiの計算
 * @param	string $i  分潮記号
 * @param	string $fn 'fi' または 'ui'
 * @return	double 計算結果
*/
function fiui($i, $fn) {
	//L2 or M1
	if (($i == 'L2') || ($i == 'M1')) {
		$res = (double)$this->fiui_L2M1($i, $fn);

	//fi
	} else if ($fn == 'fi') {
		//表-C.1による計算
		if (isset($this->c1[$i])) {
			$res = (double)$this->c1[$i][0]
			+ (double)$this->c1[$i][1] * cos(deg2rad($this->_N))
			+ (double)$this->c1[$i][2] * cos(2 * deg2rad($this->_N))
			+ (double)$this->c1[$i][3] * cos(3 * deg2rad($this->_N));
		//表-C.2の参照
		} else {
			$res = $this->c2[$i][5];
			if (! is_numeric($res))	$res = (double)$this->fiui($res, $fn);
		}
	//ui
	} else {
		//表-C.1による計算
		if (isset($this->c1[$i])) {
			$res = (double)$this->c1[$i][4] * sin(deg2rad($this->_N))
			+ (double)$this->c1[$i][5] * sin(2 * deg2rad($this->_N))
			+ (double)$this->c1[$i][6] * sin(3 * deg2rad($this->_N));
		//表-C.2の参照
		} else {
			$res = (double)$this->c2[$i][6];
			if (! is_numeric($res))	$res = (double)$this->fiui($res, $fn);
		}
	}
	return $res;
}

/**
 * fi, uiの式の評価
 * @param	string $str 式
 * @param	string $fn 'fi' または 'ui'
 * @return	double 計算結果
*/
function evalFiUi($str, $fn) {
	//定数
	if (is_numeric($str))	return (double)$str;

	//式の分解
	$stack = array();
	$ptr = 0;
	$cc = '';
	$i = 0;
	while ($i < mb_strlen($str)) {
		$c = mb_substr($str, $i, 1);
		//演算子
		if (preg_match("/[\+\-\*\/\^]/ui", $c) > 0) {
		//負の符号
			if (($ptr == 0) && ($c == '-')) {
				$stack[$ptr] = $c;
			//定数
			} else if (preg_match("/^[0-9\.]+$/ui", $cc) > 0) {
				$stack[$ptr] = $cc;
				$ptr++;
			//変数
			} else {
				$stack[$ptr] = "\$this->fiui('{$cc}', '{$fn}')";
				$ptr++;
			}
			$stack[$ptr] = $c;
			$ptr++;
			$cc = '';
		//定数・変数
		} else if (preg_match("/[A-Z|0-9\.]+/ui", $c) > 0) {
			$cc .= $c;
		}
		$i++;
	}
	//定数・変数
	if (preg_match("/^[0-9\.]+$/ui", $cc) > 0) {
		$stack[$ptr] = $cc;
	//変数
	} else {
		$stack[$ptr] = "\$this->fiui('{$cc}', '{$fn}')";
	}

	//式の再構築
	$ee = '';
	$ptr = 0;
	while ($ptr < count($stack)) {
		//負の符号
		if (($ptr == 0) && ($stack[$ptr] == '-')) {
			$stack[$ptr + 1] = $stack[$ptr] . $stack[$ptr + 1];
			$stack[$ptr] = '';
			$ptr++;
		//べき乗演算子
		} else if ($stack[$ptr] == '^') {
			$stack[$ptr + 1] = 'pow(' . $stack[$ptr - 1] . ',' . $stack[$ptr + 1] . ')';
			$ptr++;
		//四則演算子
		} else if (preg_match("/^[\+\-\*\/]$/ui", $stack[$ptr]) > 0) {
			$stack[$ptr + 1] = $stack[$ptr - 1] . $stack[$ptr] . $stack[$ptr + 1];
			$ptr++;
		}
		$ptr++;
	}
	$res = (double)eval('return ' . $stack[$ptr - 1] . ';');

	return $res;
}

/**
 * 潮位を求める地点を設定する
 * @param	string $code 地点記号
 * @return	なし
*/
function setLocation($code) {
	//潮位一覧を読み込む
	$zipfname =  __DIR__ . '/'. self::FILE_ZIPNAME;
	$fname = $code . self::FILE_EXT;
	$zip = new ZipArchive;
	if ($zip->open($zipfname) == TRUE) {
		$this->str2array($zip->getFromName($fname), $this->port);
	} else {
		$this->error = TRUE;
		$this->errmsg = "cannot read \"zip://{$zipfname}#{$fname}\"";
	}
}

/**
 * 現在設定されている地点の潮位を求める
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min 時分（世界時）
 * @return	double 潮位
*/
function tide_level($year, $month, $day, $hour, $min) {
	$tt = (double)$hour + $min / 60;
	$td = (double)0;
	foreach ($this->keys as $i) {
		$V0 = (double)$this->c2[$i][0] * $this->_s
				+ (double)$this->c2[$i][1] * $this->_h
				+ (double)$this->c2[$i][2] * $this->_p
				+ (double)$this->c2[$i][3];
		$V0 = (double)$this->_stdegree($V0);
		$fi = (double)$this->evalFiUi($this->c2[$i][5], 'fi');
		$ui = (double)$this->evalFiUi($this->c2[$i][6], 'ui');
		$Viui = $V0 + $ui + (double)$this->c2[$i][4] * (double)$this->port['longitude'][0] + (double)$this->c2[$i][7] * $tt - (double)$this->port[$i][1];
		$Viui = $this->_stdegree($Viui);
		$d = $fi * (double)$this->port[$i][0] * cos(deg2rad($Viui));
		$td += $d;
	}

	return (double)$this->port['base'][0] + $td;
}

/**
 * 指定地点のある日の干潮、満潮時刻と潮位を求める
 * @param	string $code 地点記号
 * @param	int $year, $month, $day グレゴリオ暦による年月日
 * @param	array $items 結果を格納する配列
 * 				array('high'=>array('hh:mm',潮位), 'low'=>array('hh:mm',潮位))
 * @return	TRUE/FALSE
*/
function tide_day($code, $year, $month, $day, &$items) {
	$this->setLocation($code);
	if ($this->error)	return FALSE;

	$this->sun_moon($year, $month, $day);

	$interval = 15;				//第1段階刻み（分）
	$td0 = $this->tide_level($year, $month, $day, self::JST, -$interval);
	$flag = 0;
	$cnt_high = 0;
	$cnt_low = 0;
	for ($i = 0; $i <= 24 * 60; $i += $interval) {
		//第1段階刻み
		$hh = floor($i / 60);
		$mm = $i - $hh * 60;;
		$td1 = $this->tide_level($year, $month, $day, $hh + self::JST, $mm);
		if ($flag == 0) {
			if ($td1 > $td0)	$flag = +1;		//上昇
			else				$flag = -1;		//下降
		} else if ($flag < 0) {
			if ($td1 > $td0) {						//上昇へ転じた
				//第2段階刻み
				$td0 = $this->tide_level($year, $month, $day, $hh + self::JST, $mm - $interval - 1);
				for ($j = -$interval; $j < $interval; $j++) {
					$hh = floor(($i + $j) / 60);
					$mm = ($i + $j) - $hh * 60;;
					$td1 = $this->tide_level($year, $month, $day, $hh + self::JST, $mm);
					if ($td1 > $td0) {				//上昇へ転じた
						$items['low'][$cnt_low]['hhmm'] = sprintf("%02d:%02d", $hh, $mm);
						$items['low'][$cnt_low]['lev'] = (int)$td0;
						$cnt_low++;
						$flag = +1;
						break;
					}
					$td0 = $td1;
				}
			}
		} else {
			if ($td1 < $td0) {						//下降へ転じた
				//第2段階刻み
				$td0 = $this->tide_level($year, $month, $day, self::JST, $mm - $interval - 1);
				for ($j = -$interval; $j < $interval; $j++) {
					$hh = floor(($i + $j) / 60);
					$mm = ($i + $j) - $hh * 60;;
					$td1 = $this->tide_level($year, $month, $day, $hh + self::JST, $mm);
					if ($td1 < $td0) {				//下降へ転じた
						$items['high'][$cnt_high]['hhmm'] = sprintf("%02d:%02d", $hh, $mm);
						$items['high'][$cnt_high]['lev'] = (int)$td0;
						$cnt_high++;
						$flag = -1;
						break;
					}
					$td0 = $td1;
				}
			}
		}
		$td0 = $td1;
	}
	return TRUE;
}

/**
 * 指定地点の名称、都道府県名、住所を取得する
 * @param	string $code 地点記号
 * @param	array $items 名称,都道府県名,住所,緯度,経度を格納する配列
 * @return	TRUE/FALSE
*/
function getLocation($code, &$items) {
	if (isset($this->index[$code])) {
		$items['title']		= $this->index[$code][0];
		$items['prefecture']	= $this->index[$code][4];
		$items['address']		= $this->index[$code][5];
		$items['latitude']		= $this->index[$code][1];
		$items['longitude']	= $this->index[$code][2];
		$res = TRUE;
	} else {
		$this->error = TRUE;
		$this->errmsg = "cannnot find location code\"{$code}\"";
		$res = FALSE;
	}
	return $res;
}

/**
 * 指定した緯度・経度に近い順に観測地点コード一覧を返す
 * @param	double $long 経度（世界測地系）
 * @param	double $lat  緯度（世界測地系）
 * @return	array 観測地点コード一覧
*/
function neighbor($long, $lat) {
	//住所・緯度・経度に関わるクラス
	require_once('pahooGeoCode.php');
	$pgc = new pahooGeoCode();

	$dist = array();
	foreach ($this->index as $key=>$arr) {
		$dist[$key] = $pgc->distance($long, $lat, $arr[2], $arr[1]);
	}
	asort($dist);
	$pgc = NULL;

	return $dist;
}

// End of Class ===========================================================
}

/*
** バージョンアップ履歴 ===================================================
 *
 * @version  3.62  2022/04/09  getSetsugetsu() - 境界条件を修正
 * @version  3.61  2022/04/01  getSetsugetsu() - 太陽黄経の小数点以下を切り上げ
 * @version  3.6   2022/03/28  getSetsugetsu(), ichiryumanbai() 追加
 * @version  3.5   2021/08/01  getDoyo(), isDoyo() 追加
 * @version  3.41  2021/02/11  eho() 追加
 * @version  3.4   2021/01/23  isSetsubun() 追加
 * @version  3.3   2021/01/04  getFullMoonNickname() 追加
 * @version  3.23  2020/11/30  2021年の祝日変更
 * @version  3.22  2020/01/25  __getWeekString()の言語判定を修正
 * @version  3.21  2020/01/02  2020年の祝日変更
 * @version  3.2   2019/12/20  makeLunarCalendar(), Gregorian2Lunar()に
 *								2033年問題判定を追加
 * @version  3.15  2019/11/29  スポーツの日（2020年10月第2月曜日～）
 * @version  3.14  2019/05/11  天皇誕生日（2020/02/23～）を追加
 * @version  3.13  2019/03/29  Julian2JD(), AD2JD() 追加
 * @version  3.12  2019/02/09  getSolarTerm72() 漢字の変更など
 * @version  3.11  2019/02/08  bug-fix: makeLunarCalendar()
 * @version  3.1   2019/01/30  bug-fix: getWeekNumber()
 * @version  3.01  2018/12/13  即位にともなう祝日（2019/05/01,10/22）を追加
 * @version  3.0   2018/08/03  クラス pahooTide 追加
 * @version  2.82  2018/01/25  bug-fix: day2hhmm() 60分が発生してしまった
 * @version  2.81  2018/01/03  bug-fix: moon_age()
 * @version  2.8   2018/01/02  rad_moon 追加
 * @version  2.7   2018/01/01  distance_moon 追加
 * @version  2.62  2016/04/17  getSolarTerm72 追加
 * @version  2.61  2016/03/12  ereg系関数廃止
 * @version  2.6   2016/03/09  WinBinder対応
 * @version  2.5   2016/01/09  tide 追加
 * @version  2.4   2016/01/01  eto_year, eto_month, eto_day 追加
 * @version  2.3   2015/12/23  getWeekString 追加
 * @version  2.2   2015/12/20  旧暦計算を追加
 * @version  2.1   2015/12/13  休日処理を追加
 * @version  2.0   2015/12/12  classとして分離
 * @version  1.0   2015/11/28
*/
