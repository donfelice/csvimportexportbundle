var step = $('.card-body').data('step');
var file_name = $('.card-body').data('file-name');

var card1 = document.getElementById( 'card-step-1' );
var card2 = document.getElementById( 'card-step-2' );
var card3 = document.getElementById( 'card-step-3' );
var notification_csv_import_finished = document.getElementById( 'csv-import-finished' );

//card2.className += " invisible";
//card3.className += " invisible";

if ( step == "1" ) {
    card2.classList.remove("invisible");
}

if ( step == "2" ) {
    card2.classList.remove("invisible");
    card3.classList.remove("invisible");
}

if ( step == "3" ) {
    card1.classList.add("invisible");
    card2.classList.add("invisible");
    card3.classList.add("invisible");
    notification_csv_import_finished.classList.remove("invisible");

}

function enableBtn( btn ) {
    var this_btn = document.getElementById( btn );
    this_btn.classList.remove('disabled');
}

function gotoURL( file_name ) {

    var content_type = document.getElementById( 'select_content_type' ).value;
    var url = "/admin/csv/import/2/" + file_name + "/" + content_type;
    console.log(url);

    location.href = url;

}
