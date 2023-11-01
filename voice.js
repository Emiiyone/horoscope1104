
//$(function() {
	//$( "*[name=voice]" ).click(function( event ) {
		//$("#sound01").get(0).play();
	//});
//});

// function ring(){
//     document.getElementById("sound01").load();
//     document.getElementById("sound01").play();
//   }

  $(function(){
 
	//音を鳴らす
	$('#koe01').mouseover(function(){
 
		document.getElementById("sound01").currentTime = 0;
		document.getElementById("sound01").play();
 
	});

    $('#koe02').mouseover(function(){
 
		document.getElementById("sound02").currentTime = 0;
		document.getElementById("sound02").play();
 
	});
 
 
});