<?php
/** pahooAstronomy.php
 * 天文計算クラス
 *
 * @copyright	(c)studio pahoo
 * @author		パパぱふぅ
 * @動作環境	PHP 5/7
 * @参考URL		http://www.pahoo.org/e-soul/webtech/phpgd/phpgd-27-01.shtm
*/
require_once('pahooCalendar.php');

// pahooAstronomyクラス ======================================================
class pahooAstronomy extends pahooCalendar {

// 惑星の位置計算 =========================================================

// 平均軌道要素
var $PlanetOrbitalElements = array(
'Mercury' => array(
252.2509,	+4.0932377062,	+0.000303,		//Ｌ 平均黄経
77.4561,	+1.556401,		+0.000295,		//ω 近日点黄経
48.3309,	+1.186112,		+0.000175,		//Ω 昇交点黄経
7.0050,		+0.001821,						//ｉ 軌道傾角
0.205632,	+0.00002040,					//ｅ 軌道離心率
+0.387098,	0.0								//ａ 軌道長半径
),
'Venus' => array(
181.9798,	+1.602168732,	+0.000310,		//Ｌ 平均黄経
131.5637,	+1.402152,		-0.001076,		//ω 近日点黄経
76.6799,	+0.901044,		+0.000406,		//Ω 昇交点黄経
3.3947,		+0.001004,						//ｉ 軌道傾角
0.006772,	-0.00004778,					//ｅ 軌道離心率
0.723330,	0.0								//ａ 軌道長半径
),
'Mars' => array(
355.4330,	+0.524071085,	+0.000311,		//Ｌ 平均黄経
336.0602,	+1.840968,		+0.000135,		//ω 近日点黄経
49.5581,	+0.772019,		+0.0,			//Ω 昇交点黄経
1.8497,		-0.000601,						//ｉ 軌道傾角
0.093401,	+0.00009048,					//ｅ 軌道離心率
+1.523679,	0.0								//ａ 軌道長半径
),
'Jupiter' => array(
34.3515,	+0.083129439,	+0.000223,		//Ｌ 平均黄経
14.3312,	+1.612635,		+0.001030,		//ω 近日点黄経
100.4644,	+1.020977,		+0.000403,		//Ω 昇交点黄経
1.3033,		-0.005496,						//ｉ 軌道傾角
0.048498,	+0.00016323,					//ｅ 軌道離心率
5.202603,	0.0								//ａ 軌道長半径
),
'Saturn' => array(
50.0774,	+0.033497907,	+0.000519,		//Ｌ 平均黄経
93.0572,	+1.963761,		+0.000838,		//ω 近日点黄経
113.665,	+0.877088,		-0.000121,		//Ω 昇交点黄経
2.4889,		-0.003736,						//ｉ 軌道傾角
0.055548,	-0.00034664,					//ｅ 軌道離心率
9.554909,	-0.0000021						//ａ 軌道長半径
),
'Uranus' => array(
314.0550,	+0.011769036,	+0.000304,		//Ｌ 平均黄経
173.0053,	+1.486378,		+0.000214,		//ω 近日点黄経
74.0060,	+0.521127,		+0.001339,		//Ω 昇交点黄経
0.7732,		+0.000774,						//ｉ 軌道傾角
0.046381,	-0.00002729,					//ｅ 軌道離心率
19.218446,	-0.000003						//ａ 軌道長半径
),
'Neptune' => array(
304.3487,	+0.006020077,	+0.000309,		//Ｌ 平均黄経
48.1203,	+1.426296,		+0.000384,		//ω 近日点黄経
131.784,	+1.102204,		+0.000260,		//Ω 昇交点黄経
1.7700,		-0.009308,						//ｉ 軌道傾角
0.009456,	+0.00000603,					//ｅ 軌道離心率
30.110387,	0.0								//ａ 軌道長半径
),
'Pluto' => array(
238.4670,	+0.00401596,	-0.0091,		//Ｌ 平均黄経
224.1416,	+1.3901,		+0.0003,		//ω 近日点黄経
110.3182,	+1.3507,		+0.0004,		//Ω 昇交点黄経
17.1451,	-0.0055,						//ｉ 軌道傾角
0.249005,	+0.000039,						//ｅ 軌道離心率
39.540343,	+0.003131						//ａ 軌道長半径
)
);

/**
 * ケプラー運動方程式の解法（漸化法）
 * @param	double $l 平均近点離角
 * @param	double $e 軌道離心率
 * @return	double 離心近点離角
*/
function __kepler($l, $e) {
	$u0 = $l;
	do {
		$du = ($l - $u0 + rad2deg($e * sin(deg2rad($u0)))) / (1 - $e * cos(deg2rad($u0)));
		$u1 = $u0 + $du;
		$u0 = $u1;
	} while(abs($du) < 1e-15);

	return $u1;
}

/**
 * 惑星の日心黄道座標を計算
 * @param	string $planet 惑星名
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	array(黄経,黄緯,動径)／FALSE：惑星名の間違い
*/
function zodiacSun($planet, $year, $month, $day, $hour, $min, $sec) {
	//平均軌道要素
	if (! isset($this->PlanetOrbitalElements[$planet]))		return FALSE;
	$tbl = $this->PlanetOrbitalElements[$planet];

	//計算開始
	$d = $this->Gregorian2JD($year, $month, $day, $hour, $min, $sec) - 2451544.5;
	$T = $d / 36525.0;

	//平均軌道要素
	$L     = $this->__angle($tbl[0] + $tbl[1] * $d + $tbl[2] * pow($T, 2));
	$omega = $this->__angle($tbl[3] + $tbl[4] * $T + $tbl[5] * pow($T, 2));
	$OMEGA = $this->__angle($tbl[6] + $tbl[7] * $T + $tbl[8] * pow($T, 2));
	$i     = $tbl[9] + $tbl[10] * $T;
	$e     = $this->__angle($tbl[11] + $tbl[12] * $T);
	$a     = $tbl[13] + $tbl[14] * $T;
	if ($planet == 'Uranus') {
		$n = 255.65443 / pow($tbl[13], 1.5);
		$a = $tbl[13] + $tbl[14] * $n * $T;
	}

	$M = $this->__angle($L - $omega);		//平均近点離角
	$E = $this->__kepler($M, $e);			//離心近点離角

	$V = $this->__angle(2 * rad2deg(atan(pow((1 + $e) / (1 - $e), 0.5) * tan(deg2rad($E / 2)))));	

	$U = $omega + $V - $OMEGA;		//黄緯引数
	$r = $a * (1 - pow($e, 2)) / (1 + $e * cos(deg2rad($V)));	//動径
	$b = rad2deg(asin(sin(deg2rad($i)) * sin(deg2rad($U))));	//黄緯
	$l = $OMEGA + rad2deg(atan(cos(deg2rad($i)) * sin(deg2rad($U)) / cos(deg2rad($U))));
	if (cos(deg2rad($U)) < 0)	$l += 180;						//黄経

	return array($l, $b, $r);
}

/**
 * 惑星の地心黄道座標を計算
 * @param	string $planet 惑星名
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @param	double $hour, $min, $sec 時分秒（世界時）
 * @return	array(黄経,黄緯,動径)
*/
function zodiacEarth($planet, $year, $month, $day, $hour, $min, $sec) {
	//日心黄道座標
	$items = $this->zodiacSun($planet, $year, $month, $day, $hour, $min, $sec);
	if (($items == FALSE) || (count($items) != 3))	return FALSE;

	$l = $items[0];		//日心黄経
	$b = $items[1];		//日心黄緯
	$r = $items[2];		//日心動径

	//太陽の黄経・黄緯
	$ls = $this->longitude_sun($year, $month, $day, $hour + $this->TDIFF, $min, $sec);
	$rs = $this->distance_sun($year, $month, $day, $hour + $this->TDIFF, $min, $sec);

	//直交座標へ変換
	$X = $r * cos(deg2rad($b)) * cos(deg2rad($l)) + $rs * cos(deg2rad($ls));
	$Y = $r * cos(deg2rad($b)) * sin(deg2rad($l)) + $rs * sin(deg2rad($ls));
	$Z = $r * sin(deg2rad($b));

	//地心黄道座標変換
	$r1 = sqrt(pow($X, 2) + pow($Y, 2) + pow($Z, 2));	//動径
	$b1 = rad2deg(asin($Z / $r1));						//黄緯
	$l1 = rad2deg(atan($Y / $X));
	if ($X < 0)		$l1 += 180;
	$l1 = $this->__angle($l1);							//黄経

	return array($l1, $b1, $r1);
}

// End of Class ===========================================================
}

/*
** バージョンアップ履歴 ===================================================
 *
 * @version  1.0   2019/03/23
*/
