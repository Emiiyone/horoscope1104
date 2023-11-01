/** jsdate.html
 * 年月日セレクタ
 *
 * @copyright	(c)studio pahoo
 * @author		パパぱふぅ
 * @参考URL		http://www.pahoo.org/e-soul/webtech/js01/js01-06-01.shtm
*/

/**
 * 初期設定
 * @param	int y 西暦年
 * @param	int m 月
 * @param	int d 日
 * @return	string yyyy/mm/dd
*/
function initJsdate(y, m, d) {
	setSelector(y, m, d);
	//年セレクタ変更時の動作
	$('#year').change(function() {
		var y = $('#year  option:selected').val();
		var m = $('#month option:selected').val();
		var d = $('#date  option:selected').val();
		setSelector(y, m, d);
	});
	//月セレクタ変更時の動作
	$('#month').change(function() {
		var y = $('#year  option:selected').val();
		var m = $('#month option:selected').val();
		var d = $('#date  option:selected').val();
		setSelector(y, m, d);
	});
};

/**
 * 今日の日付を返す
 * @param	なし
 * @return	string yyyy/mm/dd
*/
function yyyymmdd() {
	var now = new Date();
	return now.getFullYear() + '/' + (now.getMonth() + 1) + '/' + now.getDate();
}
/**
 * 閏年かどうか判定する
 * @param	int year 西暦年
 * @return	bool true:閏年である／false:平年である
*/
function isleap(year) {
	var res = false;
	if (year % 4 == 0)		res = true;
	if (year % 100 == 0)	res = false;
	if (year % 400 == 0)	res = true;
	return res;
}

/**
 * 指定した月の日数を返す
 * @param	int year  西暦年
 * @param	int month 月
 * @return	int 日数／FALSE:引数の異常
*/
function getDaysInMonth(year, month) {
	var days = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	if (month < 1 || month > 12)	return FALSE;
	days[2] = isleap(year) ? 29 : 28;		//閏年の判定

	return days[month];
}

/**
 * 年月日セレクタをつくる
 * @param	int year  西暦年
 * @param	int month 月
 * @param	int date  日
 * @return	int 日数／FALSE:引数の異常
*/
function setSelector(year, month, date) {
	//年セレクタ
	var html = '';
	for (i = -2; i <= +2; i++) {
		if (i == 0)		selected = ' selected';
		else			selected = '';
		yy = parseInt(year) + parseInt(i);
		html += '<option value="'+ yy + '"' + selected + '>'+ yy + '</option>';
	}
	$('#year').html(html);

	//月セレクタ
	var html = '';
	for (mm = 1; mm <= 12; mm++) {
		if (mm == month)	selected = ' selected';
		else				selected = '';
		html += '<option value="'+ mm + '"' + selected + '>'+ mm + '</option>';
	}
	$('#month').html(html);

	//日セレクタ
	var html = '';
	dm = getDaysInMonth(year, month);
	for (dd = 1; dd <= dm; dd++) {
		if (dd == date)	selected = ' selected';
		else				selected = '';
		html += '<option value="'+ dd + '"' + selected + '>'+ dd + '</option>';
	}
	$('#date').html(html);
}


/*
** バージョンアップ履歴 ===================================================
 *
 * @version 2.0  2019/03/23  外部ファイルとして分離
 * @version 1.0  2018/08/09
*/
