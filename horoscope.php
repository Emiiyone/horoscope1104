<?php
/** horoscope.php
 * カラー・ホロスコープ
 *
 * @copyright	(c)studio pahoo
 * @author		パパぱふぅ
 * @動作環境	PHP 5/7/8
 * @参考URL		https://www.pahoo.org/e-soul/webtech/phpgd/phpgd-27-01.shtm
 *
 * ■URLパラメータ
 * width   = 描画領域（正方形）の1辺の長さ（ピクセル） 100～1000
 * （例）CrystalSnow.php?width=600&recur=5
*/
// 初期化処理 ================================================================
define('INTERNAL_ENCODING', 'UTF-8');
mb_internal_encoding(INTERNAL_ENCODING);
mb_regex_encoding(INTERNAL_ENCODING);
define('MYSELF', basename($_SERVER['SCRIPT_NAME']));
define('REFERENCE', 'https://www.pahoo.org/e-soul/webtech/phpgd/phpgd-27-01.shtm');

//プログラム・タイトル
define('TITLE', 'カラー・ホロスコープ');

//リファラチェック＋リリースフラグの設定
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'localhost')) {
	define('FLAG_RELEASE', FALSE);
	define('REFER_ON', '');
	ini_set('display_errors', 1);
	ini_set('error_reporting', E_ALL);
} else {
	//リリース・フラグ（公開時にはTRUEにすること）
	define('FLAG_RELEASE', TRUE);
	//リファラ・チェック（直リン防止用；空文字ならチェックしない）
	define('REFER_ON', 'www.pahoo.org');
}

//ホロスコープID
define('HOROSCOPE', 'horoscope');

//ホロスコープを計算する時刻（日本標準時）
define('HOUR', 21);

//ホロスコープの表示サイズ（単位：ピクセル）
define('RADIUS', 300);				//外側の円の半径
define('WIDTH1', 50);				//星座を描く部分の幅

//ホロスコープの表示色
define('COLOR1', '#FFDD88');		//星座配置部分の色
define('COLOR2', '#FFFFCC');		//惑星配置部分の色
define('COLOR3', '#FFBB00');		//境界線の色
define('COLOR4', '#0000FF');		//星座アイコンの色
define('COLOR5', '#0000CC');		//惑星アイコンの色

//ホロスコープの表示色
define('SVGPATH', './svg/');		//SVGアイコン・ファイルのパス名

//誕生日の星座（初期値）
define('DEF_ZODIAC', 'Aries');

//天文計算クラス：include_pathが通ったディレクトリに配置
require_once('pahooAstronomy.php');

/**
 * 共通HTMLヘッダ
 * @global string $HtmlHeader
*/
$encode = INTERNAL_ENCODING;
$title  = TITLE;
$width  = RADIUS * 2;
$HtmlHeader =<<< EOT
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="{$encode}">
<title>{$title}</title>
<meta name="author" content="studio pahoo" />
<meta name="copyright" content="studio pahoo" />
<meta name="ROBOTS" content="NOINDEX,NOFOLLOW" />
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width={$width},user-scalable=yes" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1/i18n/jquery.ui.datepicker-ja.min.js"></script>
<script src="./jsdate.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1/themes/ui-lightness/jquery-ui.css" rel="stylesheet" />
<style>
.date-sunday	.ui-state-default {
	background-image: none;
	background-color: #FF0000;
	color: #FFFFFF;
}
.date-saturday	.ui-state-default {
	background-image: none;
	background-color: #0000FF;
	color: #FFFFFF;
}
.date-holiday0	.ui-state-default {
	background-image: none;
	background-color: #FF6666;
	color: #FFFFFF;
}
.date-holiday1	.ui-state-default {
	background-image: none;
	background-color: #FFCCCC;
	color: #000088;
}
</style>

EOT;

/**
 * 共通HTMLフッタ
 * @global string $HtmlFooter
*/
$HtmlFooter =<<< EOT
</html>

EOT;

// サブルーチン ==============================================================
/**
 * エラー処理ハンドラ
*/
function myErrorHandler ($errno, $errmsg, $filename, $linenum) {
	echo 'Sory, system error occured !';
	exit(1);
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (FLAG_RELEASE)	$old_error_handler = set_error_handler('myErrorHandler');

//PHP5判定
if (! isphp5over()) {
	echo 'Error > Please let me run on PHP5 or more...';
	exit(1);
}

//リファラ・チェック
if (REFER_ON != '') {
	if (isset($_SERVER['HTTP_REFERER'])) {
		$url = parse_url($_SERVER['HTTP_REFERER']);
		$res = ($url['host'] == REFER_ON) ? TRUE : FALSE;
	} else {
		$res = FALSE;
	}
} else {
	$res = TRUE;
}
if (! $res) {
	echo 'Please refer to ' . REFER_ON . ' !';
	exit(1);
}

/**
 * PHP5以上かどうか検査する
 * @return	bool TRUE：PHP5以上／FALSE:PHP5未満
*/
function isphp5over() {
	$version = explode('.', phpversion());

	return $version[0] >= 5 ? TRUE : FALSE;
}

/**
 * 指定したボタンが押されてきたかどうか
 * @param	string $btn  ボタン名
 * @return	bool TRUE＝押された／FALSE＝押されていない
*/
function isButton($btn) {
	if (isset($_GET[$btn]) && $_GET[$btn] != '')	return TRUE;
	if (isset($_POST[$btn]) && $_POST[$btn] != '')	return TRUE;
	return FALSE;
}

/**
 * $_GET または $_POST から値を取り出す
 * @param	string $key キー
 * @return	mixed 取得値／NULL:エラー
*/
function getParam($key) {
	$res = NULL;
	if (isset($_GET[$key]))			$res = $_GET[$key];
	else if (isset($_POST[$key]))	$res = $_POST[$key];
	return $res;
}

/**
 * $_GET または $_POST から正規化した数値を取り出す
 * @param	string $key キー
 * @param	string $type int:整数 float:浮動小数
 * @param	mixed  $min, $max 最小値、最大値
 * @param	mixed  $def デフォルト値
 * @return	mixed  取得値
*/
function getValidNumber($key, $type, $min, $max, $def) {
	//整数かどうか
	if (preg_match("/int/i", $type) != 0) {
		$flag_int = TRUE;
		$format = "%d";
	} else {
		$flag_int = FALSE;
		$format = "%f";
	}

	$res = NULL;
	//引数チェック
	if ($min > $max)	list($min, $max) = [$max, $min];

	//値の取り出し
	$res = getParam($key);
	if ($res == '' || $res == NULL) {
		$res = $def;
	} else if (! is_numeric($res)) {
		$res = $def;
	} else {
		if ($flag_int)	$res = round($res);		//整数に丸める
		//最大値・最小値チェック
		if ($res > $max) {
			$res = $def;
		} else if ($res < $min) {
			$res = $def;
		}
	}

	return $res;
}

/**
 * jQuery:Datepicker 用の祝日スクリプトを作成する
 * @param	pahooCalendar $pc  暦計算クラス
 * @param	int $start   開始年
 * @param	int $finish  終了年
 * @param	string $ymd  初期値；省略可能
 * @return	string スクリプト
*/
function makeJSDatepicker($pc, $start, $finish, $ymd='') {
	$js =<<< EOD
$(function() {
  //カレンダーの範囲
  var minD = new Date({$start}, 0, 1);
  var maxD = new Date({$finish}, 11, 31);

var holidays = {

EOD;
	$cnt = 0;
	for ($year = $start; $year <= $finish; $year++) {
		for ($month = 1; $month <= 12; $month++) {
			$day_of_month = $pc->getDaysInMonth($year, $month);
			for ($day = 1; $day <= $day_of_month; $day++) {
				$name = $pc->getHoliday($year, $month, $day);
				if ($name != FALSE) {
					$yyyymmdd = sprintf('%04d%02d%02d', $year, $month, $day);
					if ($cnt > 0)	$js .= ",\n";
					$js .= <<< EOD
"{$yyyymmdd}":{type:0, title:"{$name}"}
EOD;
				$cnt++;
				}
			}
		}
	}

	$js .=<<< EOD

};

	//Datepickerの設定
	$(".datepicker").datepicker({
		dateFormat: "yy/mm/dd",
		minDate: minD,
		maxDate: maxD,
		beforeShowDay: function(day) {
			var result;
			var holiday = holidays[$.datepicker.formatDate('yymmdd', day)]
			// 祝日・非営業日定義に存在するか？
			if (holiday) {
				result =  [true, "date-holiday" + holiday.type, holiday.title];
			} else {
				switch (day.getDay()) {
					case 0: // 日曜日か？
						result = [true, "date-sunday"];
						break;
					case 6: // 土曜日か？
						result = [true, "date-saturday"];
						break;
					default:
						result = [true, ""];
						break;
				}
			}
			return result;
		},
		onSelect: function() {
			var ymd = $("#yyyymmdd").val();
			var arr = ymd.split(/\//);
			$("#year").val(Number(arr[0]).toString());
			$("#month").val(Number(arr[1]).toString());
			$("#date").val(Number(arr[2]).toString());
		}
	});
	$(".datepicker").datepicker("setDate", "{$ymd}");
});

EOD;

	return $js;
}

// ホロスコープ：サブルーチン ===============================================
//黄道十二宮
$Zodiac = array(
//    英名				十二宮名	和名		記号	開始月日	開始黄経
array('Aries',			'白羊宮', 'おひつじ座',	'U+2648', '0321',	0),
array('Taurus',			'金牛宮', 'おうし座',	'U+2649', '0420',	30),
array('Gemini',			'双児宮', 'ふたご座',	'U+264A', '0521',	60),
array('Cancer',			'巨蟹宮', 'かに座',		'U+264B', '0622',	90),
array('Leo',			'獅子宮', 'しし座',		'U+264C', '0723',	120),
array('Virgo',			'処女宮', 'おとめ座',	'U+264D', '0823',	150),
array('Libra',			'天秤宮', 'てんびん座',	'U+264E', '0923',	180),
array('Scorpio',		'天蝎宮', 'さそり座',	'U+264F', '1024',	210),
array('Sagittarius',	'人馬宮', 'いて座',		'U+2650', '1123',	240),
array('Capricorn',		'磨羯宮', 'やぎ座',		'U+2651', '1222',	270),
array('Aquarius',		'宝瓶宮', 'みずがめ座',	'U+2652', '0120',	300),
array('Pisces',			'双魚宮', 'うお座',		'U+2653', '0219',	300)
);

//惑星・太陽・月
$Planets = array(
//    英名			和名		記号		カラー
array('Sun',		'太陽',		'U+2609',	'D68E31'),
array('Moon',		'月',		'U+263D',	'DCDCDC'),
array('Mercury',	'水星',		'U+263F',	'FFFF00'),
array('Venus',		'金星',		'U+2640',	'00FF00'),
array('Mars',		'火星',		'U+2642',	'FF0000'),
array('Jupiter',	'木星',		'U+2643',	'F312D8'),
array('Saturn',		'土星',		'U+2644',	'8B0000'),
array('Uranus',		'天王星',	'U+2645',	'00FFFF'),
array('Neptune',	'海王星',	'U+2646',	'00139F'),
array('Pluto',		'冥王星',	'U+2647',	'000000'),
array('Earth',		'地球',		'U+1F728',	'000000')
);

//惑星カラー
$Pcolor = array(0, 0, 0, 0);

/**
 * ラッキーカラー計算
 * @param	int    $i  惑星番号
 * @param	float  $th 黄経（度）
 * @param	string $zd 星座名（英名）
 * @return	string 描画スクリプト
*/
function calcColor($i, $th, $zd) {
	global $Zodiac, $Planets, $Pcolor;

	foreach ($Zodiac as $val) {
		if ($val[0] == $zd) {
			$dd = ($th - $val[5] - 15);
			if ($dd < (-180))	$dd += 360;
			$dd = (180 - abs($dd)) / 180;
			$Pcolor[0] += $dd;
			for ($j = 0; $j < 3; $j++) {
				$cc = hexdec(substr($Planets[$i][3], $j * 2, 2));
				$cc *= $dd;
				$Pcolor[$j + 1] += $cc;
			}
			break;
		}
	}
}

/**
 * SVGアイコンを1つ描く
 * @param	string $th    黄経（度）
 * @param	string $rd    中心からの距離（ピクセル）
 * @param	string $name  アイコン名（主ファイル名）
 * @param	string $size  アイコンのサイズ（ピクセル）
 * @param	string $color アイコンのカラー（RGB指定）
 * @param	string $id    CANVASのID
 * @return	string 描画スクリプト
*/
function drawIcon($th, $rd, $name, $size, $color, $id) {
	//描画座標
	$th = 180 - $th;
	$th = deg2rad($th);
	$x = RADIUS + $rd * cos($th) - $size / 2;
	$y = RADIUS + $rd * sin($th) - $size / 2;

	//SVGファイルの読み込み
	$fname = SVGPATH . $name . '.svg';
	$svg = file_get_contents($fname);
	$svg = preg_replace('/\#000000/ums', $color, $svg);		//色指定
	$svg = 'data:image/svg+xml;base64,' . base64_encode($svg);

	//スクリプト生成
	$js = <<< EOD
	var img_{$name} = new Image();
	img_{$name}.src = '{$svg}';
	img_{$name}.onload = function() {
		var ctx = document.getElementById('{$id}').getContext('2d');
		ctx.drawImage(img_{$name}, {$x}, {$y}, {$size}, {$size});
	}

EOD;
	return $js;
}

/**
 * 惑星アイコンを配置＋ラッキーカラー計算
 * @param	int    $year, $month, $day  グレゴリオ暦による年月日
 * @param	string $zd 星座名（英名）
 * @return	string 描画スクリプト
*/
function drawPlanets($year, $month, $day, $zd) {
	global $Planets;

	//オブジェクト生成
	$pas = new pahooAstronomy();

	$id = HOROSCOPE;				//CANVASのID
	$size = WIDTH1 * 0.7;			//アイコンのサイズ
	$color = COLOR5;				//アイコンのカラー
	$js = '';
	for ($i = 0; $i < 10; $i++) {
		$name = $Planets[$i][0];
		$rd = (RADIUS - WIDTH1 * 2) / 10 * $i + WIDTH1;
		//太陽
		if ($i == 0) {
			$l = $pas->longitude_sun($year, $month, $day, HOUR, 0, 0);
		//月
		} else if ($i == 1) {
			$l = $pas->longitude_moon($year, $month, $day, HOUR, 0, 0);
		//惑星
		} else {
			$items = $pas->zodiacEarth($name, $year, $month, $day, HOUR - $pas->TDIFF, 0, 0);
			$l = $items[0];
		}
		$js .= drawIcon($l, $rd, $name, $size, $color, $id);
		calcColor($i, $l, $zd);
	}

	//オブジェクト解放
	$pas = NULL;

	return $js;
}

/**
 * 黄道十二宮アイコンを配置
 * @param	int $year, $month, $day  グレゴリオ暦による年月日
 * @return	string 描画スクリプト
*/
function drawZodiac() {
	global $Zodiac;

	$id = HOROSCOPE;				//CANVASのID
	$rd = RADIUS - (WIDTH1 * 0.5);	//中心からの距離
	$size = WIDTH1 * 0.7;			//アイコンのサイズ
	$color = COLOR4;				//アイコンのカラー
	$js = '';
	for ($i = 0; $i < 12; $i++) {
		$name = $Zodiac[$i][0];
		$js .= drawIcon($i * 30 + 15, $rd, $name, $size, $color, $id);
	}

	return $js;
}

/**
 * ホロスコープを描く
 * @param	int    $year, $month, $day  グレゴリオ暦による年月日
 * @param	string $zd 星座名（英名）
 * @return	string HTML BODY
*/
function drawHoroscope($year, $month, $day, $zd) {
	$id = HOROSCOPE;
	$radius = RADIUS;
	$width1 = WIDTH1;
	$color1 = COLOR1;
	$color2 = COLOR2;
	$color3 = COLOR3;
	$js1 = drawZodiac();
	$js2 = drawPlanets($year, $month, $day, $zd);
	$js =<<< EOT
<script>
onload = function() {
	initJsdate({$year}, {$month}, {$day});		//年月日セレクタ

	var canvas = document.getElementById('{$id}');
	if ( ! canvas || ! canvas.getContext ) { return false; }
	var ctx = canvas.getContext('2d');
	var r = {$radius};
	var cx = r;
	var cy = r;
	var x, y;

	ctx.strokeStyle ='{$color3}';

	ctx.fillStyle = '{$color1}';
	ctx.beginPath();
	ctx.arc(cx, cx, r, 0, Math.PI * 2, false);
	ctx.stroke();
	ctx.fill();

	ctx.fillStyle = '{$color2}';
	ctx.beginPath();
	ctx.arc(cx, cy, r - {$width1}, 0, Math.PI * 2, false);
	ctx.stroke();
	ctx.fill();

	//12分割
	for (var i = 0; i < 12; i++) {
		var th = Math.PI - Math.PI * 2 / 12 * i;
		x = r + r * Math.cos(th);
		y = r + r * Math.sin(th);
		ctx.moveTo(cx, cy);
		ctx.lineTo(x, y);
		ctx.stroke();
	}
	//黄道十二宮
	{$js1}
	//惑星・太陽・月
	{$js2}
}
</script>

EOT;

	return $js;
}

/**
 * 凡例を作成する
 * @return	string HTML文
*/
function makeLegend() {
	global $Zodiac, $Planets;

	$html = '';
	//黄道十二宮
	foreach ($Zodiac as $val) {
		$code = preg_replace('/U\+/', '', $val[3]);
		$name = $val[2];
		$html .=<<< EOD
&#x{$code};&nbsp;{$name}　

EOD;
	}
	//惑星
	foreach ($Planets as $val) {
		$code = preg_replace('/U\+/', '', $val[2]);
		$name = $val[1];
		$html .=<<< EOD
&#x{$code};&nbsp;{$name}　

EOD;
	}
	return $html;
}

/**
 * 星座セレクタ作成
 * @param	string $zd 星座名の初期値（英名）
 * @return	string HTML文
*/
function makeSelectZodiac($zd) {
	global $Zodiac, $Pcolor;

	//今日のラッキーカラー計算
	$hexcolor = '#';
	for ($j = 0; $j < 3; $j++) {
		$cc = (int)($Pcolor[$j + 1] / $Pcolor[0]);
		while ($cc < 0)		$cc += 255;
		while ($cc > 255)	$cc -= 255;
		$hexcolor .= strtoupper(dechex($cc));
	}

	$html =<<< EOD
誕生日の星座　<select name="zodiac" id="zodiac">

EOD;
	foreach ($Zodiac as $val) {
		$selected = ($val[0] == $zd) ? 'selected' : '';
		$html .=<<< EOD
<option value="{$val[0]}" {$selected}>{$val[2]}</option>

EOD;
	}
	$html .=<<< EOD
</select>
<p>ラッキーカラー　<span style="border-style: solid; border-color:black; background-color:{$hexcolor};">　　</span>
&nbsp;{$hexcolor}
</p>

EOD;
	return $html;
}

/**
 * HTML BODYを作成する
 * @param	string $yyyymmdd 年月日（yyyy/mm/dd）
 * @param	string $jsdt     datapickerのスクリプト
 * @param	string $jsdt     選択星座（英名）
 * @return	string HTML BODY
*/
function makeCommonBody($yyyymmdd, $jsdt, $zd) {
	$myself = MYSELF;
	$refere = REFERENCE;
	$title  = TITLE;
	$version = '<span style="font-size:small;">' . date('Y/m/d版', filemtime(__FILE__)) . '</span>';
	$width  = RADIUS * 2;
	$debug  = '';

	//デバッグ情報
	if (FLAG_RELEASE) {
		$msg = '';
	} else {
		$phpver = phpversion();
		$msg =<<< EOT
<p>
<span style="font-weight:bold;">★デバックモードで動作中...</span><br />
PHPver : {$phpver}
</p>

EOT;
	}

	//年月日
	if (preg_match('/([0-9]+)\/([0-9]+)\/([0-9]+)/', $yyyymmdd, $arr) > 0) {
		$y = $arr[1];
		$m = $arr[2];
		$d = $arr[3];
	} else {
		$y = date('Y') + 0;
		$m = date('m') + 0;
		$d = date('d') + 0;
	}

	//ホロスコープ作成
	$id = HOROSCOPE;
	$height = $width = RADIUS * 2;
	$js = drawHoroscope($y, $m, $d, $zd);

	$legend = makeLegend();				//凡例
	$zodiac = makeSelectZodiac($zd);	//星座セレクタ

	$html =<<< EOD
<script>
{$jsdt}
</script>
</head>

<body>
{$js}
<h2>{$title} {$version}</h2>
<form name="myform" method="get" action="{$myself}">
年月日　
<select name="year"  id="year"></select>年
<select name="month" id="month"></select>月
<select name="date"  id="date"></select>日&nbsp;
&#x1f4c5;&nbsp;
<input class="datepicker" type="text" id="yyyymmdd" name="yyyymmdd" size="10" value="{$yyyymmdd}" />
<input type="submit" id="exec" name="exec" value="作成" />
<input type="submit" id="clear" name="clear" value="リセット" />
<br />
{$zodiac}
</form>

<canvas id="{$id}" width="{$width}" height="{$height}"></canvas>

<div style="border-style:solid; border-width:1px; margin:20px 0px 0px 0px; padding:5px; width:{$width}px; font-size:small; overflow-wrap:break-word; word-break:break-all;">
<h3>使い方</h3>
<ol>
<li>［<span style="font-weight:bold;">年月日</span>］を選択してください．</li>
<li>&#x1f4c5; の右側のテキストボックスをクリックすると，カレンダーから年月日を入力できます．</li>
<li>［<span style="font-weight:bold;">誕生日の星座</span>］を選択してください．</li>
<li>［<span style="font-weight:bold;">作成</span>］ボタンをクリックしてください．</li>
<li>ホロスコープと今日のラッキーカラーを表示します．</li>
<li>［<span style="font-weight:bold;">リセット</span>］ボタンを押すと，初期化します．</li>
</ol>
<h3>凡例</h3>
<p style="margin-left:20px;">{$legend}</p>
※参考サイト：<a href="{$refere}">{$refere}</a>
{$msg}
</div>
</body>

EOD;

	return $html;
}

// メイン・プログラム =======================================================
//オブジェクト生成
$pc = new pahooCalendar();	//暦計算クラス

//初期値
$year  = getValidNumber('year',  'int', 1900, 2099, date('Y'));		//年
$month = getValidNumber('month', 'int',    1,   12, date('n'));		//月
$day   = getValidNumber('date',  'int',    1,   31, date('j'));		//日
$zd    = getParam('zodiac');										//星座
if ($zd == NULL)	$zd = DEF_ZODIAC;

//リセット
if (isButton('clear')) {
	$year  = date('Y');
	$month = date('n');
	$day   = date('j');
}

$yyyymmdd = sprintf('%04d/%02d/%02d', $year, $month, $day);

//JSDatepicker
$start  = date('Y') - 2;	//カレンダーの範囲‥‥いまから2年前から
$finish = date('Y') + 3;	//3年後まで
$jsdt = makeJSDatepicker($pc, $start, $finish, $yyyymmdd);

$HtmlBody = makeCommonBody($yyyymmdd, $jsdt, $zd);

//オブジェクト解放
$pgc = NULL;

// 表示処理
echo $HtmlHeader;
echo $HtmlBody;
echo $HtmlFooter;

/*
** バージョンアップ履歴 ===================================================
 *
 * @version  2.1  2022/04/24  カラーコード計算修正，PHP8対応，リファラ・チェック改良
 * @version  2.0  2019/06/02  ラッキーカラーを追加
 * @version  1.0  2019/03/23
*/
?>

